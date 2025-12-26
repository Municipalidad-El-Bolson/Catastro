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
  <aside class="w-[420px] max-w-full border-l border-slate-800 bg-slate-950/95 text-slate-100"
         :class="open ? 'block' : 'hidden md:block'">
    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
      <h2 class="text-lg font-bold truncate" x-text="detalle?.titulo ?? 'Detalle'"></h2>
      <button class="md:hidden px-3 py-1 rounded bg-slate-800" @click="open=false">Cerrar</button>
    </div>

    <div class="p-4 space-y-3 overflow-auto h-[calc(100vh-57px)]">
      <template x-if="loading">
        <div class="text-sm text-slate-400">Cargando…</div>
      </template>

      <template x-if="!loading && detalle">
        <div class="space-y-3">
          <div class="text-sm text-slate-400" x-text="detalle.categoria ?? ''"></div>
          <div class="text-sm whitespace-pre-line" x-text="detalle.descripcion ?? ''"></div>
          <div class="text-sm text-slate-300" x-text="detalle.direccion ?? ''"></div>

          <div class="grid grid-cols-2 gap-2" x-show="(detalle.imagenes||[]).length">
            <template x-for="item in detalle.imagenes" :key="item.url">
              <a :href="item.url" target="_blank"
                 class="rounded border border-slate-800 p-3 hover:bg-slate-900 text-sm">
                <div class="truncate" x-text="item.titulo ?? 'Abrir recurso'"></div>
              </a>
            </template>
          </div>
        </div>
      </template>
    </div>
  </aside>

</div>
@endsection

@push('scripts')
<script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('centenarioMap', () => ({
    map: null,
    open: true,
    loading: false,
    detalle: null,

    async init() {
      // anti doble init
      if (this.map) return;

      const el = document.getElementById('map');
      if (!el) { console.error('[centenario] no existe #map'); return; }

      // importante: contenedor vacío
      el.innerHTML = '';
      el.style.minHeight = '100vh';

      mapboxgl.accessToken = @json(config('services.mapbox.token'));
      if (!mapboxgl?.accessToken) {
        console.error('[centenario] Falta MAPBOX_TOKEN');
        return;
      }

      this.map = new mapboxgl.Map({
        container: 'map',
        style: 'mapbox://styles/mapbox/streets-v12',
        center: [-71.53, -41.9645],
        zoom: 15,
        maxZoom: 20,
      });

      this.map.on('error', (e)=>console.error('[mapbox error]', e?.error || e));

      this.map.addControl(new mapboxgl.NavigationControl({ visualizePitch: true }), 'top-right');
      this.map.addControl(new mapboxgl.FullscreenControl(), 'top-right');

      this.map.on('load', async () => {
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

        // por si el layout aún no acomodó tamaños
        setTimeout(()=>{ try{ this.map.resize(); }catch(e){} }, 200);
      });

      window.addEventListener('resize', ()=>{ try{ this.map.resize(); }catch(e){} });
    },

    async cargarDetalle(id){
      this.loading = true;
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
