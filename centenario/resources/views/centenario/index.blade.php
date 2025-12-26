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
{{-- FULL HEIGHT real (sin depender del footer del layout) --}}
<div x-data="centenarioMap" x-init="init()" class="h-screen flex bg-slate-950">

  {{-- MAP WRAPPER --}}
  <div class="relative flex-1">
    {{-- MAPBOX (CONTENEDOR VACÍO) --}}
    <div id="map" class="absolute inset-0"></div>

    {{-- overlay oscuro pro --}}
    <div class="pointer-events-none absolute inset-0
                bg-gradient-to-r from-black/25 via-transparent to-black/35
                mix-blend-multiply"></div>

    {{-- hint --}}
    <div class="absolute top-3 left-3 z-10 pointer-events-auto">
      <div class="px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 text-xs shadow-lg">
        Click en un punto para ver el detalle
      </div>
    </div>
  </div>

{{-- SIDEBAR --}}
<aside
  class="w-[420px] max-w-full border-l border-slate-800 bg-slate-950/95 text-slate-100"
  :class="open ? 'block' : 'hidden md:block'"
>
  {{-- Header --}}
  <div class="p-4 border-b border-slate-800 flex items-center gap-2 justify-between">
    <div class="min-w-0">
      <div class="text-xs text-slate-400" x-text="detalle?.categoria ?? ''"></div>
      <h2 class="text-lg font-bold truncate" x-text="detalle?.titulo ?? 'Detalle'"></h2>
    </div>

    <div class="flex items-center gap-2">
      <button
        class="hidden md:inline-flex px-3 py-1 rounded-lg bg-slate-900 border border-slate-800 hover:bg-slate-800 text-xs"
        @click="collapsed = !collapsed"
        x-text="collapsed ? 'Expandir' : 'Minimizar'"
      ></button>

      <button class="md:hidden px-3 py-1 rounded bg-slate-800" @click="open=false">Cerrar</button>
    </div>
  </div>

  {{-- Body --}}
  <div class="p-4 overflow-auto h-[calc(100vh-57px)]">
    <template x-if="loading">
      <div class="text-sm text-slate-400">Cargando…</div>
    </template>

    <template x-if="!loading && detalle">
      <div class="space-y-4">

        {{-- Tarjeta “patrimonial” --}}
        <div class="rounded-2xl overflow-hidden border border-[#e6e0cf] bg-[#faf7f0] text-[#2b2b2b] shadow-xl">
          {{-- Preview grande --}}
          <div class="relative" x-show="!collapsed">

            {{-- IMAGE --}}
            <template x-if="primaryAsset() && primaryAsset().kind === 'image'">
              <img
                class="w-full h-64 object-cover"
                :src="mediaUrl(primaryAsset().fileId)"
                :alt="primaryAsset().titulo ?? detalle.titulo"
              >
            </template>

            {{-- PDF --}}
            <template x-if="primaryAsset() && primaryAsset().kind === 'pdf'">
              <iframe
                class="w-full h-64 bg-white"
                :src="mediaUrl(primaryAsset().fileId)"
              ></iframe>
            </template>

            {{-- AUDIO --}}
            <template x-if="primaryAsset() && primaryAsset().kind === 'audio'">
              <div class="p-4">
                <div class="text-sm font-semibold text-[#6f6a3a] mb-2">
                  Audio
                </div>
                <audio controls class="w-full">
                  <source :src="mediaUrl(primaryAsset().fileId)">
                </audio>
              </div>
            </template>

            {{-- Si no hay asset --}}
            <template x-if="!primaryAsset()">
              <div class="p-6 text-center text-sm text-slate-600">
                Sin multimedia asociada
              </div>
            </template>

            {{-- Botón agrandar --}}
            <button
              class="absolute top-3 right-3 px-3 py-1 rounded-xl bg-black/55 text-white text-xs backdrop-blur border border-white/20 hover:bg-black/65"
              x-show="primaryAsset() && (primaryAsset().kind === 'image' || primaryAsset().kind === 'pdf')"
              @click="openPreview(primaryAsset())"
            >
              Ampliar
            </button>
          </div>

          {{-- Contenido --}}
          <div class="p-5 text-center space-y-3">
            <div class="text-sm text-[#8a865f]" x-text="detalle.localidad ?? ''"></div>

            <div class="text-[22px] leading-snug font-semibold text-[#6f6a3a]"
                 x-text="detalle.titulo"></div>

            <div class="text-sm text-[#4b4b4b] whitespace-pre-line"
                 x-text="detalle.descripcion ?? ''"></div>

            <div class="text-sm text-[#6b6b6b]" x-text="detalle.direccion ?? ''"></div>
            <a
              :href="googleMapsUrl()"
              target="_blank"
              rel="noopener"
              class="mt-2 inline-flex w-full items-center justify-center gap-2
                    rounded-xl bg-[#6f6a3a] px-4 py-2
                    text-white font-semibold
                    hover:bg-[#5e5a30]"
            >
              <span>Cómo llegar</span>
              <span>📍</span>
            </a>
          </div>
        </div>

        {{-- Adjuntos --}}
        <div class="space-y-2" x-show="(detalle.imagenes||[]).length">
          <div class="text-xs text-slate-400 uppercase tracking-wide">Archivos</div>

          <template x-for="item in detalle.imagenes" :key="item.fileId">
            <button
              type="button"
              class="w-full text-left rounded-xl border border-slate-800 bg-slate-900/50 hover:bg-slate-900 px-3 py-2 flex items-center gap-3"
              @click="setPrimary(item)"
            >
              <div class="w-9 h-9 rounded-lg flex items-center justify-center border border-slate-700 bg-slate-950 text-slate-200 text-sm">
                <span x-show="item.kind==='image'">🖼️</span>
                <span x-show="item.kind==='pdf'">📄</span>
                <span x-show="item.kind==='audio'">🔊</span>
                <span x-show="!item.kind">🔗</span>
              </div>

              <div class="min-w-0">
                <div class="text-sm text-slate-100 truncate" x-text="item.titulo ?? 'Archivo'"></div>
                <div class="text-xs text-slate-400" x-text="item.kind ?? ''"></div>
              </div>

              <div class="ml-auto text-xs text-slate-400">Ver</div>
            </button>
          </template>
        </div>

      </div>
    </template>
  </div>

  {{-- Modal preview (imagen/pdf) --}}
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
    collapsed: false,

    previewOpen: false,
    previewUrl: '',
    previewTitle: '',
    previewKind: '',

    _mediaPrimary: null,

    mediaUrl(fileId){
      return `/media/drive/${encodeURIComponent(fileId)}`;
    },

    googleMapsUrl(){
      if (!this.detalle) return '#';

      const lat = Number(this.detalle.lat);
      const lng = Number(this.detalle.lng);

      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return '#';

      return `https://www.google.com/maps/dir/?api=1&origin=My+Location&destination=${lat},${lng}&travelmode=walking`;
    },

    primaryAsset(){
      const items = this.detalle?.imagenes || [];
      if (!items.length) return null;

      // si el usuario seleccionó uno, usar ese
      if (this._mediaPrimary) return this._mediaPrimary;

      // elegir por prioridad
      return items.find(i => i.kind === 'image')
          || items.find(i => i.kind === 'pdf')
          || items.find(i => i.kind === 'audio')
          || items[0];
    },

    setPrimary(item){
      this._mediaPrimary = item;
    },

    openPreview(item){
      if (!item?.fileId) return;
      this.previewKind = item.kind;
      this.previewTitle = item.titulo || 'Vista previa';
      this.previewUrl = this.mediaUrl(item.fileId);
      this.previewOpen = true;
    },

    flyToDetalle(){
      if (!this.map || !this.detalle) return;
      const lng = Number(this.detalle.lng);
      const lat = Number(this.detalle.lat);
      if (!Number.isFinite(lng) || !Number.isFinite(lat)) return;

      this.map.easeTo({
        center: [lng, lat],
        zoom: Math.max(this.map.getZoom(), 16),
        pitch: 55,
        bearing: -15,
        duration: 650
      });
    },

    map: null,
    open: true,
    loading: false,
    detalle: null,


    async init() {
    if (this.map) return;

    const el = document.getElementById('map');
    if (!el) return;
    el.innerHTML = '';
    el.style.minHeight = '100vh';

    mapboxgl.accessToken = @json(config('services.mapbox.token'));
    if (!mapboxgl?.accessToken) return;

    this.map = new mapboxgl.Map({
      container: 'map',
      style: 'mapbox://styles/mapbox/satellite-streets-v12',
      center: [-71.53, -41.9645],
      zoom: 15.2,
      maxZoom: 20,
      pitch: 55,   // 👈 “realidad” (inclinación)
      bearing: -15 // 👈 leve giro para efecto pro
    });

    this.map.on('error', (e)=>console.error('[mapbox error]', e?.error || e));

    this.map.addControl(new mapboxgl.NavigationControl({ visualizePitch: true }), 'top-right');
    this.map.addControl(new mapboxgl.FullscreenControl(), 'top-right');

    this.map.on('load', async () => {
      // ✅ Terreno 3D (DEM)
      try {
        if (!this.map.getSource('mapbox-dem')) {
          this.map.addSource('mapbox-dem', {
            type: 'raster-dem',
            url: 'mapbox://mapbox.mapbox-terrain-dem-v1',
            tileSize: 512,
            maxzoom: 14
          });
        }
        this.map.setTerrain({ source: 'mapbox-dem', exaggeration: 1.35 });

        // Cielo (se nota mucho cuando hay pitch)
        if (!this.map.getLayer('sky')) {
          this.map.addLayer({
            id: 'sky',
            type: 'sky',
            paint: {
              'sky-type': 'atmosphere',
              'sky-atmosphere-sun': [0.0, 0.0],
              'sky-atmosphere-sun-intensity': 10
            }
          });
        }
      } catch (e) {
        console.warn('No se pudo habilitar terreno/sky:', e);
      }

      // ✅ Buildings 3D (si existen en el estilo)
      try {
        if (!this.map.getLayer('3d-buildings')) {
          this.map.addLayer(
            {
              id: '3d-buildings',
              source: 'composite',
              'source-layer': 'building',
              filter: ['==', 'extrude', 'true'],
              type: 'fill-extrusion',
              minzoom: 15,
              paint: {
                'fill-extrusion-color': '#a1a1aa',
                'fill-extrusion-height': ['get', 'height'],
                'fill-extrusion-base': ['get', 'min_height'],
                'fill-extrusion-opacity': 0.6
              }
            }
          );
        }
      } catch (e) {
        console.warn('No se pudo agregar 3D buildings:', e);
      }

      // ✅ Cargar puntos
      const geo = await fetch(@json(route('centenario.geojson'))).then(r=>r.json());

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

      if (!this.map.getLayer('lugares-selected')) {
        this.map.addLayer({
          id:'lugares-selected',
          type:'circle',
          source:'lugares',
          filter: ['==', ['get','id'], -1],
          paint:{
            'circle-radius': 12,
            'circle-color': '#fff',
            'circle-opacity': 0.25
          }
        });
      }


      // ✅ CLICK EN PUNTO → cargar detalle + centrar
      this.map.on('click', 'lugares-points', (e) => {
        const f = e.features?.[0];
        if (!f) return;

        const id = f.properties?.id;
        this.cargarDetalle(id);
        this.open = true;

        const coords = f.geometry.coordinates;
        this.map.easeTo({
          center: coords,
          zoom: Math.max(this.map.getZoom(), 16),
          duration: 650
        });
      });

      this.map.on('mouseenter','lugares-points', ()=>this.map.getCanvas().style.cursor='pointer');
      this.map.on('mouseleave','lugares-points', ()=>this.map.getCanvas().style.cursor='');
      this.map.setFilter('lugares-selected', ['==', ['get','id'], Number(id)]);

      // evita mapa “blanco” por layout
      setTimeout(()=>{ try{ this.map.resize(); }catch(e){} }, 200);
    });

    window.addEventListener('resize', ()=>{ try{ this.map.resize(); }catch(e){} });
  },


    async cargarDetalle(id){
      this.loading = true;
      this._mediaPrimary = null;
      try{
        const url = @json(route('centenario.show', ['lugar'=>'__ID__'])).replace('__ID__', id);
        this.detalle = await fetch(url).then(r=>r.json());
      } finally {
        this.loading = false;
      }
    },
  }));
});
</script>
@endpush
