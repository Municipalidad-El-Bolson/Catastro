@extends('layouts.app')

@push('styles')
  <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet" />
  <style>
    html, body { height: 100%; }
    main { padding:0!important; margin:0!important; }
    footer { display:none!important; }
    #map { width: 100%; height: 100%; }

  </style>
@endpush

@section('content')
<div x-data="centenarioMap" x-init="$nextTick(() => init())" class="h-screen flex bg-slate-950">

  <div class="relative flex-1 min-w-0 h-full">
    <div id="map" class="absolute inset-0"></div>
  </div>

  {{-- SIDEBAR --}}
  <aside
    class="shrink-0 relative border-l border-slate-800 bg-slate-950/95 text-slate-100 transition-all duration-300"
    :class="sidebarMin ? 'w-[72px]' : 'w-[420px]'"
  >
    <button
      class="absolute -left-4 top-4 z-40 w-8 h-8 rounded-2xl border border-slate-800 bg-slate-950/90 backdrop-blur shadow
            flex items-center justify-center hover:bg-slate-900"
      @click="sidebarMin = !sidebarMin"
      :title="sidebarMin ? 'Expandir panel' : 'Minimizar panel'"
    >
      <span x-text="sidebarMin ? '›' : '‹'"></span>
    </button>

    <div class="p-4 border-b border-slate-800 flex items-center gap-2 justify-between" x-show="!sidebarMin">
      <div class="min-w-0">
        <div class="text-xs text-slate-400" x-text="detalle?.categoria ?? ''"></div>
        <h2 class="text-lg font-bold truncate" x-text="detalle?.titulo ?? 'Detalle'"></h2>
      </div>
    </div>

    <div class="p-4 overflow-auto" :class="sidebarMin ? 'pt-14 h-screen' : 'h-[calc(100vh-57px)]'">
      <template x-if="loading && !sidebarMin">
        <div class="text-sm text-slate-400">Cargando…</div>
      </template>

      <template x-if="!loading && detalle && !sidebarMin">
        <div class="space-y-4">
          <div class="rounded-2xl overflow-hidden border border-[#e6e0cf] bg-[#faf7f0] text-[#2b2b2b] shadow-xl">
            <div class="relative">
              <template x-if="primaryAsset() && primaryAsset().kind === 'image'">
                <img class="w-full h-40 object-cover"
                     :src="mediaUrlFromItem(primaryAsset())"
                     :alt="(primaryAsset().titulo ?? detalle.titulo)">
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

            <div class="p-5 text-center space-y-3">
              <div class="text-sm text-[#8a865f]" x-text="detalle.localidad ?? ''"></div>
              <div class="text-[22px] leading-snug font-semibold text-[#6f6a3a]" x-text="detalle.titulo"></div>
              <div class="text-sm text-[#4b4b4b] whitespace-pre-line" x-text="detalle.descripcion ?? ''"></div>
              <div class="text-sm text-[#6b6b6b]" x-text="detalle.direccion ?? ''"></div>

              <a :href="googleMapsUrl()" target="_blank" rel="noopener"
                 class="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-white font-semibold hover:bg-emerald-700">
                <span>Cómo llegar</span><span>📍</span>
              </a>
            </div>
          </div>

          <div class="space-y-2" x-show="imagesOnly().length">
            <div class="text-xs text-slate-400 uppercase tracking-wide">Imágenes</div>
            <div class="grid grid-cols-2 gap-2">
              <template x-for="img in imagesOnly()" :key="img._fileId">
                <button type="button"
                        class="rounded-xl overflow-hidden border border-slate-800 bg-slate-900/40 hover:bg-slate-900 text-left"
                        @click="openPreview(img)">
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

    {{-- modal preview igual que antes si querés --}}
  </aside>

</div>
@endsection

@push('scripts')
<script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>

<script>
console.log('SCRIPT CENTENARIO CARGADO ✅');

document.addEventListener('alpine:init', () => {
  Alpine.data('centenarioMap', () => ({
    sidebarMin: false,
    loading: false,
    detalle: null,
    map: null,

    previewOpen: false,
    previewUrl: '',
    previewTitle: '',
    previewKind: '',
    _mediaPrimary: null,
    _clickBound: false,


    initWatchers() {
      // cuando cambia sidebarMin
      this.$watch('sidebarMin', () => {
        this.$nextTick(() => {
          setTimeout(() => { try { this.map?.resize(); } catch(e) {} }, 50);
        });
      });

      // cuando termina la animación del aside (transition-all duration-300)
      const aside = document.querySelector('aside');
      if (aside && !aside._resizeBound) {
        aside._resizeBound = true;
        aside.addEventListener('transitionend', (ev) => {
          if (ev.propertyName === 'width') {
            try { this.map?.resize(); } catch(e) {}
          }
        });
      }
    },


    mediaUrl(fileId) { return `/media/drive/${encodeURIComponent(fileId)}`; },
    fileIdOf(item) { return item?._fileId || item?.file_id || item?.drive_file_id || item?.fileId || null; },

    mediaUrlFromItem(item) {
      const id = this.fileIdOf(item);
      return id ? this.mediaUrl(id) : '';
    },

    imagesOnly() {
      const items = this.detalle?.imagenes || [];
      return items
        .filter(i => String(i?.kind || '').toLowerCase() === 'image')
        .map(i => ({ ...i, _fileId: this.fileIdOf(i) }))
        .filter(i => !!i._fileId);
    },

    primaryAsset() {
      const items = this.detalle?.imagenes || [];
      if (!items.length) return null;
      return this._mediaPrimary || items[0];
    },

    openPreview(item) {
      const id = this.fileIdOf(item);
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

    async init() {
      if (this.map) {
        try { this.map.resize(); } catch(e) {}
        return;
      }

      this.initWatchers();

      const el = document.getElementById('map');
      if (!el) return;

      el.replaceChildren();

      mapboxgl.accessToken = @json(config('services.mapbox.token'));
      if (!mapboxgl?.accessToken) return;

      console.log('MAP EL SIZE:', el.clientWidth, el.clientHeight, 'children:', el.children.length);

      this.map = new mapboxgl.Map({
        container: el,
        style: 'mapbox://styles/mapbox/satellite-streets-v12',
        center: [-71.53, -41.9645],
        zoom: 15.2,
        maxZoom: 20,
        pitch: 55,
        bearing: -15
      });

      setTimeout(() => {
        console.log('AFTER CREATE: canvas?', !!el.querySelector('canvas'), 'SIZE:', el.clientWidth, el.clientHeight);
      }, 300);


      this.map.addControl(new mapboxgl.NavigationControl({ visualizePitch: true }), 'top-right');
      this.map.addControl(new mapboxgl.FullscreenControl(), 'top-right');

      this.map.on('error', (e) => console.error('MAPBOX ERROR', e?.error || e));

      this.map.on('load', async () => {
        const geo = await fetch(@json(route('centenario.geojson'))).then(r => r.json());

        if (!this.map.getSource('lugares')) {
          this.map.addSource('lugares', { type:'geojson', data: geo });
        } else {
          this.map.getSource('lugares').setData(geo);
        }

        if (!this.map.getLayer('lugares-points')) {
          this.map.addLayer({
            id:'lugares-points',
            type:'circle',
            source:'lugares',
            paint:{
              'circle-radius': 7,
              'circle-stroke-width': 2,
              'circle-stroke-color': '#fff',
              'circle-color': ['coalesce', ['get','color'], '#2563eb']
            }
          });
        }

        if (!this._clickBound) {
          this._clickBound = true;

          this.map.on('click', 'lugares-points', (e) => {
            const f = e.features?.[0];
            if (!f) return;

            const id = f.properties?.id; // ojo: puede ser string
            if (id === undefined || id === null) return;

            this.cargarDetalle(id);

            const coords = f.geometry.coordinates;
            this.map.easeTo({
              center: coords,
              zoom: Math.max(this.map.getZoom(), 16),
              duration: 650
            });
          });

          this.map.on('mouseenter','lugares-points', ()=>this.map.getCanvas().style.cursor='pointer');
          this.map.on('mouseleave','lugares-points', ()=>this.map.getCanvas().style.cursor='');
        }

          setTimeout(() => { try { this.map.resize(); } catch(e) {} }, 50);
          setTimeout(() => { try { this.map.resize(); } catch(e) {} }, 350);
      });

      window.addEventListener('resize', ()=>{ try{ this.map.resize(); }catch(e){} });
    },

    async cargarDetalle(id) {
      this.loading = true;
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
