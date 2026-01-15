@extends('layouts.app')

@push('styles')
  <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet" />
  <style>
    html, body { height: 100%; }
    main { padding:0!important; margin:0!important; }
    footer { display:none!important; }
  </style>
@endpush

@section('content')
<div x-data="centenarioMap" x-init="init()" class="h-screen min-h-screen flex bg-slate-950">

  {{-- MAP WRAPPER --}}
<div class="relative flex-1 h-full min-h-screen">
  <div id="map" class="absolute inset-0"></div>

    <div class="pointer-events-none absolute inset-0
                bg-gradient-to-r from-black/25 via-transparent to-black/35
                mix-blend-multiply"></div>

    <div class="absolute top-3 left-3 z-10 pointer-events-auto">
      <div class="px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 text-xs shadow-lg">
        Click en un punto para ver el detalle
      </div>
    </div>
  </div>

{{-- SIDEBAR --}}
<aside
  class="relative border-l border-slate-800 bg-slate-950/95 text-slate-100 transition-all duration-300"
  :class="sidebarMin ? 'w-[72px]' : 'w-[420px]'"
>
  {{-- Toggle flotante --}}
  <button
    class="absolute -left-4 top-4 z-40 w-8 h-8 rounded-2xl border border-slate-800 bg-slate-950/90 backdrop-blur shadow
           flex items-center justify-center hover:bg-slate-900"
    @click="sidebarMin = !sidebarMin"
    :title="sidebarMin ? 'Expandir panel' : 'Minimizar panel'"
  >
    <span x-text="sidebarMin ? '›' : '‹'"></span>
  </button>

  {{-- Header --}}
  <div class="p-4 border-b border-slate-800 flex items-center gap-2 justify-between" x-show="!sidebarMin">
    <div class="min-w-0">
      <div class="text-xs text-slate-400" x-text="detalle?.categoria ?? ''"></div>
      <h2 class="text-lg font-bold truncate" x-text="detalle?.titulo ?? 'Detalle'"></h2>
    </div>
  </div>

  {{-- Body --}}
  <div class="p-4 overflow-auto" :class="sidebarMin ? 'pt-14 h-screen' : 'h-[calc(100vh-57px)]'">

    <template x-if="loading && !sidebarMin">
      <div class="text-sm text-slate-400">Cargando…</div>
    </template>

    <template x-if="!loading && detalle && !sidebarMin">
      <div class="space-y-4">

        {{-- Tarjeta --}}
        <div class="rounded-2xl overflow-hidden border border-[#e6e0cf] bg-[#faf7f0] text-[#2b2b2b] shadow-xl">

          {{-- Preview grande --}}
          <div class="relative">
            <template x-if="primaryAsset() && primaryAsset().kind === 'image'">
              <img class="w-full h-40 object-cover"
                   :src="mediaUrl(primaryAsset().fileId)"
                   :alt="primaryAsset().titulo ?? detalle.titulo">
            </template>

            <template x-if="!primaryAsset()">
              <div class="p-6 text-center text-sm text-slate-600">Sin multimedia asociada</div>
            </template>

            <button
              class="absolute top-3 right-3 px-3 py-1 rounded-xl bg-black/55 text-white text-xs backdrop-blur border border-white/20 hover:bg-black/65"
              x-show="primaryAsset()"
              @click="openPreview(primaryAsset())"
            >Ampliar</button>
          </div>

          {{-- Contenido --}}
          <div class="p-5 text-center space-y-3">
            <div class="text-sm text-[#8a865f]" x-text="detalle.localidad ?? ''"></div>

            <div class="text-[22px] leading-snug font-semibold text-[#6f6a3a]" x-text="detalle.titulo"></div>

            <div class="text-sm text-[#4b4b4b] whitespace-pre-line" x-text="detalle.descripcion ?? ''"></div>

            <div class="text-sm text-[#6b6b6b]" x-text="detalle.direccion ?? ''"></div>

            <a
              :href="googleMapsUrl()"
              target="_blank"
              rel="noopener"
              class="mt-2 inline-flex w-full items-center justify-center gap-2
                    rounded-xl bg-emerald-600 px-4 py-2
                    text-white font-semibold hover:bg-emerald-700"
            >
              <span>Cómo llegar</span>
              <span>📍</span>
            </a>
          </div>
        </div>

        {{-- Imágenes (con categoría para timeline) --}}
        <div class="space-y-2" x-show="imagesOnly().length">
          <div class="text-xs text-slate-400 uppercase tracking-wide">Imágenes</div>

          <div class="grid grid-cols-2 gap-2">
            <template x-for="img in imagesOnly()" :key="img._fileId">
              <button
                type="button"
                class="rounded-xl overflow-hidden border border-slate-800 bg-slate-900/40 hover:bg-slate-900 text-left"
                @click="openPreview(img)"
              >
                <img :src="mediaUrlFromItem(img)" class="w-full h-28 object-cover" />
                <div class="p-2 space-y-1">
                  <div class="text-xs text-slate-200 truncate" x-text="img.titulo || 'Imagen'"></div>
                  <div class="text-[11px] text-slate-400 truncate" x-text="img.categoria || ''"></div>
                </div>
              </button>
            </template>
          </div>
        </div>

      </div>
    </template>

  </div>

  {{-- Modal preview --}}
  <div
    class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4"
    x-show="previewOpen"
    x-transition.opacity
    @keydown.escape.window="previewOpen=false"
    style="display:none;"
  >
    <div class="w-full max-w-5xl rounded-2xl overflow-hidden bg-slate-950 border border-slate-800 shadow-2xl">
      <div class="p-3 flex items-center justify-between border-b border-slate-800">
        <div class="text-sm text-slate-100 truncate" x-text="previewTitle"></div>
        <button class="px-3 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-sm" @click="previewOpen=false">
          Cerrar
        </button>
      </div>

      <div class="bg-black">
        <template x-if="previewKind === 'image'">
          <img class="w-full max-h-[80vh] object-contain" :src="previewUrl" />
        </template>

        <template x-if="previewKind === 'pdf'">
          <iframe class="w-full h-[80vh] bg-white" :src="previewUrl"></iframe>
        </template>
      </div>
    </div>
  </div>
</aside>


</div>
@endsection

@push('scripts')
<script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('centenarioMap', () => ({
    // UI
    sidebarMin: false,
    loading: false,
    detalle: null,

    // map
    map: null,

    // preview modal
    previewOpen: false,
    previewUrl: '',
    previewTitle: '',
    previewKind: '',

    // media selection
    _mediaPrimary: null,

    // ===== WATCHERS =====
    initWatchers() {
      this.$watch('sidebarMin', () => {
        this.$nextTick(() => {
          if (this.map) {
            try { this.map.resize(); } catch (e) {}
          }
        });
      });
    },

    // ===== MEDIA (helpers) =====
    mediaUrl(fileId) {
      return `/media/drive/${encodeURIComponent(fileId)}`;
    },

    fileIdOf(item) {
      return item?.file_id || item?.drive_file_id || item?.fileId || null;
    },

    imagesOnly() {
      const items = this.detalle?.imagenes || [];
      return items
        .filter(i => (String(i?.kind || '').toLowerCase() === 'image'))
        .map(i => ({ ...i, _fileId: this.fileIdOf(i) }))
        .filter(i => !!i._fileId);
    },

    mediaUrlFromItem(item) {
      const id = item?._fileId || this.fileIdOf(item);
      return id ? this.mediaUrl(id) : '';
    },

    primaryAsset() {
      const items = this.detalle?.imagenes || [];
      if (!items.length) return null;

      if (this._mediaPrimary) return this._mediaPrimary;

      return items.find(i => i.kind === 'image')
        || items.find(i => i.kind === 'pdf')
        || items.find(i => i.kind === 'audio')
        || items[0];
    },

    setPrimary(item) {
      this._mediaPrimary = item;
    },

    openPreview(item) {
      const id = item?._fileId || this.fileIdOf(item) || item?.fileId;
      if (!id) return;

      this.previewKind  = item?.kind || 'image';
      this.previewTitle = item?.titulo || 'Vista previa';
      this.previewUrl   = this.mediaUrl(id);
      this.previewOpen  = true;
    },

    googleMapsUrl() {
      if (!this.detalle) return '#';

      const lat = Number(this.detalle.lat);
      const lng = Number(this.detalle.lng);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return '#';

      return `https://www.google.com/maps/dir/?api=1&origin=My+Location&destination=${lat},${lng}&travelmode=walking`;
    },

    // ===== INIT MAP =====
    async init() {
      if (this.map) return;

      this.initWatchers();

      const el = document.getElementById('map');
      if (!el) return;

      el.style.background = '#0b1220';
      el.style.minHeight = '100vh';

      mapboxgl.accessToken = @json(config('services.mapbox.token'));
      if (!mapboxgl?.accessToken) {
        console.error('Falta Mapbox token');
        return;
      }

      this.map = new mapboxgl.Map({
        container: 'map',
        style: 'mapbox://styles/mapbox/satellite-streets-v12',
        center: [-71.53, -41.9645],
        zoom: 15.2,
        maxZoom: 20,
        pitch: 55,
        bearing: -15
      });

      this.map.addControl(new mapboxgl.NavigationControl({ visualizePitch: true }), 'top-right');
      this.map.addControl(new mapboxgl.FullscreenControl(), 'top-right');

      this.map.on('load', async () => {
        const geo = await fetch(@json(route('centenario.geojson'))).then(r => r.json());

        if (!this.map.getSource('lugares')) {
          this.map.addSource('lugares', { type: 'geojson', data: geo });
        } else {
          this.map.getSource('lugares').setData(geo);
        }

        if (!this.map.getLayer('lugares-points')) {
          this.map.addLayer({
            id: 'lugares-points',
            type: 'circle',
            source: 'lugares',
            paint: {
              'circle-radius': 7,
              'circle-stroke-width': 2,
              'circle-stroke-color': '#fff',
              'circle-color': ['coalesce', ['get', 'color'], '#2563eb']
            }
          });
        }

        this.map.on('error', (e) => console.error('[mapbox error]', e?.error || e));

        if (!this.map.getLayer('lugares-selected')) {
          this.map.addLayer({
            id: 'lugares-selected',
            type: 'circle',
            source: 'lugares',
            filter: ['==', ['get', 'id'], -1],
            paint: {
              'circle-radius': 12,
              'circle-color': '#fff',
              'circle-opacity': 0.25
            }
          });
        }

        // ✅ click: cargar detalle + seleccionar + centrar
        this.map.on('click', 'lugares-points', (e) => {
          const f = e.features?.[0];
          if (!f) return;

          const id = Number(f.properties?.id);
          if (!Number.isFinite(id)) return;

          this.map.setFilter('lugares-selected', ['==', ['get', 'id'], id]);
          this.cargarDetalle(id);

          const coords = f.geometry.coordinates;
          this.map.easeTo({
            center: coords,
            zoom: Math.max(this.map.getZoom(), 16),
            duration: 650
          });
        });

        this.map.on('mouseenter', 'lugares-points', () => this.map.getCanvas().style.cursor = 'pointer');
        this.map.on('mouseleave', 'lugares-points', () => this.map.getCanvas().style.cursor = '');

        // primer resize
        setTimeout(() => { try { this.map.resize(); } catch (e) {} }, 200);
      });

      window.addEventListener('resize', () => { try { this.map.resize(); } catch (e) {} });
    },

    async cargarDetalle(id) {
      this.loading = true;
      this._mediaPrimary = null;
      try {
        const url = @json(route('centenario.show', ['lugar' => '__ID__'])).replace('__ID__', id);
        this.detalle = await fetch(url).then(r => r.json());
      } finally {
        this.loading = false;
      }
    },
  }));
});
</script>

@endpush
