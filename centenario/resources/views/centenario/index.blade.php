@extends('layouts.app')

@push('styles')
  <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet" />
@endpush

@section('content')
<div x-data="centenarioMap()" class="h-[calc(100vh-64px)] flex">

  {{-- MAPA --}}
  <div id="map" class="flex-1"></div>

  {{-- SIDEBAR --}}
  <aside class="w-[420px] max-w-full border-l bg-white"
         :class="open ? 'block' : 'hidden md:block'">
    <div class="p-4 border-b flex items-center justify-between">
      <h2 class="text-lg font-bold" x-text="detalle?.titulo ?? 'Detalle'"></h2>
      <button class="md:hidden px-3 py-1 rounded bg-gray-100" @click="open=false">Cerrar</button>
    </div>

    <div class="p-4 space-y-3 overflow-auto h-[calc(100vh-64px-57px)]">
      <template x-if="loading">
        <div class="text-sm text-gray-500">Cargando…</div>
      </template>

      <template x-if="!loading && detalle">
        <div class="space-y-3">
          <div class="text-sm text-gray-500" x-text="detalle.categoria ?? ''"></div>
          <div class="text-sm" x-text="detalle.descripcion ?? ''"></div>

          <div class="text-sm text-gray-600" x-text="detalle.direccion ?? ''"></div>

          <div class="grid grid-cols-2 gap-2" x-show="(detalle.imagenes||[]).length">
            <template x-for="img in detalle.imagenes" :key="img.url">
              <figure class="rounded overflow-hidden border">
                <img :src="img.url" class="w-full h-28 object-cover" />
                <figcaption class="p-2 text-xs text-gray-600" x-text="img.titulo ?? ''"></figcaption>
              </figure>
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
function centenarioMap(){
  return {
    map: null,
    open: true,
    loading: false,
    detalle: null,

    async init(){
      mapboxgl.accessToken = @json(config('services.mapbox.token'));

      this.map = new mapboxgl.Map({
        container: 'map',
        style: 'mapbox://styles/mapbox/satellite-streets-v12',
        center: [-71.53, -41.9645],
        zoom: 15,
        maxZoom: 20,
      });

      this.map.on('load', async () => {
        // ✅ 3D Buildings (solo después del load)
        // Nota: en algunos estilos satelitales puede no verse tan bien.
        try {
          this.map.addLayer({
            id: '3d-buildings',
            source: 'composite',
            'source-layer': 'building',
            filter: ['==', 'extrude', 'true'],
            type: 'fill-extrusion',
            minzoom: 15,
            paint: {
              'fill-extrusion-color': '#aaa',
              'fill-extrusion-height': ['get', 'height'],
              'fill-extrusion-base': ['get', 'min_height'],
              'fill-extrusion-opacity': 0.55
            }
          });
        } catch (e) {
          console.warn('No se pudo agregar 3D buildings:', e);
        }

        // ✅ Cargar puntos
        const geo = await fetch(@json(route('centenario.geojson'))).then(r=>r.json());

        this.map.addSource('lugares', { type:'geojson', data: geo });

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

        this.map.on('click','lugares-points', (e) => {
          const f = e.features[0];
          const id = f.properties.id;
          this.cargarDetalle(id);

          this.map.easeTo({
            center: f.geometry.coordinates,
            zoom: Math.max(this.map.getZoom(), 14)
          });
        });

        this.map.on('mouseenter','lugares-points', ()=>this.map.getCanvas().style.cursor='pointer');
        this.map.on('mouseleave','lugares-points', ()=>this.map.getCanvas().style.cursor='');
      });
    },

    async cargarDetalle(id){
      this.loading = true;
      this.open = true;
      try{
        const url = @json(route('centenario.show', ['lugar'=>'__ID__'])).replace('__ID__', id);
        this.detalle = await fetch(url).then(r=>r.json());
      } finally {
        this.loading = false;
      }
    }
  }
}

document.addEventListener('alpine:init', () => {});
document.addEventListener('DOMContentLoaded', () => {
  // Alpine ejecuta init() automáticamente si lo ponés como x-init, o podés llamarlo:
});
</script>
@endpush
