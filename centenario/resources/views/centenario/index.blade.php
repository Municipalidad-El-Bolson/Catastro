@extends('layouts.app')
@push('styles')
  <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet" />
  <style>
    html, body { height: 100%; }
    main { padding:0!important; margin:0!important; }
    footer { display:none!important; }

    /* el wrapper del mapa es el stacking-context */
    .map-shell { position: relative; height: 100%; }
    .mapboxgl-control-container { pointer-events: none; }
    .mapboxgl-control-container .mapboxgl-ctrl { pointer-events: auto; }
    .map-shell {
      position: relative;
      height: 100%;
      min-width: 0;   /* importantísimo para flex */
    }

    /* el mapa siempre ocupa todo y queda "abajo" */
    #map { position:absolute; inset:0; width:100%; height:100%; z-index: 1; }

    /* forzamos a mapbox para que respete z-index */
    .mapboxgl-canvas-container,
    .mapboxgl-control-container {
      position: absolute;
      inset: 0;
      z-index: 2;
    }

    /* Scrollbar gris oscuro – estilo timeline */
    .sidebar-scroll {
      direction: rtl;              /* mueve la scrollbar a la IZQUIERDA */
    }

    .sidebar-scroll > * {
      direction: ltr;              /* el contenido sigue normal */
    }

    .sidebar-scroll::-webkit-scrollbar {
      width: 8px;
    }

    .sidebar-scroll::-webkit-scrollbar-track {
      background: rgba(15, 23, 42, 0.6);   /* slate-900 */
      border-radius: 9999px;
    }

    .sidebar-scroll::-webkit-scrollbar-thumb {
      background: rgba(51, 65, 85, 0.9);   /* slate-600 */
      border-radius: 9999px;
    }

    .sidebar-scroll::-webkit-scrollbar-thumb:hover {
      background: rgba(71, 85, 105, 1);    /* slate-500 */
    }

    /* overlays arriba SIEMPRE */
    .map-overlay { position:absolute; z-index: 50; }

    /* Timeline: SIEMPRE centrada abajo dentro del mapa */
    .timeline-overlay{
      position: absolute;
      left: 1.5rem;
      right: 1.5rem;
      bottom: 1rem;
      z-index: 70;
      pointer-events: auto;
    }

    .timeline-card{
      width: 100%;
      max-width: min(1100px, 100%);
      margin-inline: auto;
    }

    /* 6 items MISMO ancho siempre */
    .timeline-row{
      display:flex;
      align-items:flex-start;
      gap:12px;
    }

    .timeline-item{
      flex: 1 1 0;     /* <- todos iguales */
      min-width: 0;    /* <- permite truncate */
      text-align:center;
    }

    /* placeholder para completar 6 sin deformar */
    .timeline-item.placeholder{
      opacity:0;
      pointer-events:none;
    }

    /* Botones redondos (flechas) con estética slate, tipo "pill" */
    .nav-round-btn{
      width: 44px;
      height: 44px;
      border-radius: 9999px;

      display: inline-flex;
      align-items: center;
      justify-content: center;

      background: rgba(2, 6, 23, 0.70);           /* slate-950 */
      color: rgba(226, 232, 240, 1);              /* slate-200 */
      border: 1px solid rgba(71, 85, 105, 0.70);  /* slate-600 */

      backdrop-filter: blur(10px);
      box-shadow: 0 12px 30px rgba(0,0,0,.35);

      transition: transform .12s ease, background .2s ease, border-color .2s ease;
    }

    .nav-round-btn:hover{
      background: rgba(15, 23, 42, 0.85);         /* slate-900 */
      border-color: rgba(148, 163, 184, 0.45);    /* slate-400 */
    }

    .nav-round-btn:active{
      transform: scale(0.96);
    }

    /* Card “gris” (misma estética del cuadrado/gris oscuro con blur) */
    .ui-card{
      border-radius: 16px;                         /* parecido a rounded-2xl */
      border: 1px solid rgba(30, 41, 59, 0.75);    /* slate-800 */
      background: rgba(2, 6, 23, 0.60);            /* slate-950 */
      backdrop-filter: blur(10px);
      box-shadow: 0 18px 40px rgba(0,0,0,.35);
    }

    /* Thumbnail de galería consistente */
    .ui-thumb{
      border-radius: 14px;
      overflow: hidden;
      border: 1px solid rgba(51, 65, 85, 0.80);    /* slate-700/800 */
      background: rgba(2, 6, 23, 0.45);
      transition: transform .12s ease, background .2s ease, border-color .2s ease;
    }

    .ui-thumb:hover{
      background: rgba(15, 23, 42, 0.60);
      border-color: rgba(148, 163, 184, 0.35);
      transform: translateY(-1px);
    }

    /* Imágenes cuadradas (queda muy “galería pro”) */
    .ui-thumb img{
      aspect-ratio: 1 / 1;
      width: 100%;
      height: auto;
      object-fit: cover;
      display: block;
    }

  </style>
@endpush


@section('content')
<div x-data="centenarioMap"
     x-init="$nextTick(() => init())"
     class="h-screen flex bg-slate-950 overflow-hidden">

  {{-- MAP WRAPPER --}}
  <div class="map-shell flex-1 min-w-0 h-full">

    <div id="map" class="absolute inset-0"></div>

    {{-- Overlay suave --}}
    <div class="absolute inset-0 bg-gradient-to-t
            from-slate-950/95 via-slate-950/35 to-transparent
            z-10 pointer-events-none"></div>

    <div class="timeline-overlay"
      x-show="timelineLugares.length"
      x-transition.opacity
      style="display:none;">

      <div
        class="w-full max-w-full
              rounded-2xl border border-slate-800
              bg-slate-950/85 backdrop-blur shadow-xl
              px-4 py-3
              transition-all duration-300"
        :style="sidebarMin
          ? 'width: 85%; margin-left: auto; margin-right: auto;'
          : 'width: 65%; margin-left: auto;'"
      >

      <div class="flex items-center gap-3">

      <button class="nav-round-btn text-[28px] leading-none font-black" @click="timelinePrevItem()">‹</button>

      <!-- Timeline -->
      <div class="relative flex-1 min-w-0">

        <!-- línea -->
        <div class="absolute left-2 right-2 top-[18px] h-[2px] bg-slate-700/80"></div>

        <!-- 6 items iguales -->
        <div class="flex gap-3">
          <template x-for="slot in timelineSlots()" :key="slot?.codigo ?? Math.random()">
            <div class="flex-1 min-w-[80px] text-center">
              <template x-if="slot">
                <button @click="selectByCodigo(slot.codigo, true)" class="w-full">
                  <div class="mx-auto w-10 h-10 rounded-full border border-slate-700 bg-slate-950
                              text-slate-100 flex items-center justify-center text-xs font-semibold">
                    <span x-text="slot.codigo"></span>
                  </div>
                  <div class="mt-2 text-xs text-slate-100 line-clamp-2">
                    <span x-text="slot.titulo"></span>
                  </div>
                </button>
              </template>
            </div>
          </template>
        </div>

      </div>

      <!-- Flecha derecha -->
      <button class="nav-round-btn text-[28px] leading-none font-black" @click="timelineNextItem()">›</button>
    </div>
  </div>
</div>

  {{-- SIDEBAR (misma estética que timeline) --}}
<aside
  class="shrink-0 relative z-50 text-slate-100 transition-all duration-300
         border border-slate-800 rounded-2xl
         bg-slate-950/85 backdrop-blur shadow-xl
         m-3 overflow-visible"
  :class="sidebarMin ? 'w-[72px]' : 'w-[420px]'"
>
  {{-- Toggle (sobresale un poquito a la derecha, centrado) --}}
  <button
    class="absolute top-1/2 right-0 translate-x-1/2 -translate-y-1/2 z-[999]
          nav-round-btn text-xl font-bold"
          @click="sidebarMin = !sidebarMin"
  >
    <span x-text="sidebarMin ? '›' : '‹'"></span>
  </button>


  <div class="h-full overflow-hidden rounded-2xl">

    {{-- Header --}}
    <div class="p-4 border-b border-slate-800/80" x-show="!sidebarMin">
      <div class="text-xs text-slate-300 uppercase tracking-wide" x-text="detalle?.categoria ?? ''"></div>
      <h2 class="text-lg font-bold text-slate-100 truncate" x-text="detalle?.titulo ?? 'Detalle'"></h2>

      <div class="text-[11px] text-slate-400 mt-1" x-show="detalle?.codigo">
        Código: <span class="text-slate-200" x-text="detalle?.codigo ?? ''"></span>
      </div>
    </div>

    {{-- Body --}}
    <div class="p-4 overflow-auto sidebar-scroll" :class="sidebarMin ? 'pt-14 h-screen' : 'h-[calc(100vh-57px)]'">
      <template x-if="loading && !sidebarMin">
        <div class="text-sm text-slate-300">Cargando…</div>
      </template>

      <template x-if="!loading && detalle && !sidebarMin">
        <div class="space-y-4">

          {{-- Tarjeta principal --}}
          <div class="rounded-2xl overflow-hidden
            border border-slate-800/80
            bg-slate-950/60 backdrop-blur
            text-slate-100 shadow-xl shadow-black/50">

            {{-- HERO (imagen + botón ampliar) --}}
            <div class="relative">
              {{-- Overlay suave (no bloquea clicks) --}}
              <div class="absolute inset-0
                bg-gradient-to-t
                from-slate-950/70
                via-slate-950/10
                to-transparent
                z-10 pointer-events-none"></div>

              <template x-if="primaryAsset()">
                <img class="w-full h-44 object-cover"
                    :src="mediaUrlFromItem(primaryAsset())"
                    :alt="(primaryAsset()?.titulo ?? detalle?.titulo ?? 'Imagen')">
              </template>

              <template x-if="!primaryAsset()">
                <div class="p-6 text-center text-sm text-slate-300">Sin multimedia asociada</div>
              </template>

              <button
                type="button"
                class="absolute z-20 top-3 right-3 px-3 py-1 rounded-xl
                      bg-slate-950/70 text-white text-xs backdrop-blur
                      border border-slate-700 hover:bg-slate-900/80"
                x-show="primaryAsset()"
                @click="openPreview(primaryAsset())"
              >Ampliar</button>
            </div>

            {{-- Texto --}}
            <div class="p-5 text-center space-y-3 bg-slate-950/70">
              <div class="text-sm text-slate-200" x-text="detalle?.localidad ?? ''"></div>
              <div class="text-[20px] leading-snug font-semibold text-slate-100" x-text="detalle?.titulo ?? ''"></div>
              <div class="text-sm text-slate-100 whitespace-pre-line" x-text="detalle?.descripcion ?? ''"></div>
              <div class="text-sm text-slate-200" x-text="detalle?.direccion ?? ''"></div>

              <a :href="googleMapsUrl()" target="_blank" rel="noopener"
                class="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-xl
                        bg-emerald-600/90 px-4 py-2 text-white font-semibold
                        hover:bg-emerald-500 transition">
                <span>Cómo llegar</span><span>📍</span>
              </a>
            </div>
          </div>


          {{-- Galería por año --}}
          <div class="space-y-2" x-show="Object.keys(galleryByYear()).length">
            <div class="text-xs text-slate-300 uppercase tracking-wide">Galería (por año)</div>

            <template x-for="(imgs, year) in galleryByYear()" :key="'year_' + year">
              <div class="relative ui-card overflow-hidden text-slate-100">


                <!-- overlay suave (opcional, no tapa el contenido) -->
                <div class="absolute inset-0
                            bg-gradient-to-t
                            from-slate-950/40
                            via-transparent
                            to-transparent
                            pointer-events-none"></div>

                <!-- contenido arriba del overlay -->
                <div class="relative">
                  <div class="px-3 py-2 border-b border-slate-800/80 flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-100" x-text="year"></div>
                    <div class="text-[11px] text-slate-400" x-text="imgs.length + ' img'"></div>
                  </div>

                  <div class="p-3 grid grid-cols-2 gap-2">
                    <template x-for="img in imgs" :key="img._fileId">
                      <button type="button"
                        class="ui-thumb text-left"
                        @click="openPreview(img)">
                        <img :src="mediaUrlFromItem(img)" />
                        <div class="p-2 space-y-1">
                          <div class="text-xs text-slate-100 truncate" x-text="img.titulo || 'Imagen'"></div>
                          <div class="text-[11px] text-slate-300 truncate" x-text="img.nota || ''"></div>
                        </div>
                      </button>
                    </template>
                  </div>
                </div>

              </div>
            </template>

          </div>

        </div>
      </template>

      <template x-if="!loading && !detalle && !sidebarMin">
        <div class="text-sm text-slate-300">Elegí un punto o un ítem del timeline.</div>
      </template>
    </div>

    {{-- Modal preview (lo dejo igual, ya coincide) --}}
    <template x-teleport="body">
      <div class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/70 p-4"
          x-show="previewOpen"
          x-transition.opacity
          @keydown.escape.window="previewOpen=false"
          style="display:none;">
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
    </template>
  </div>
</aside>

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
    _clickBound: false,

    // timeline
    timelineLugares: [],
    _lugaresByCodigo: {},

    // paginado timeline
    timelinePage: 0,
    timelinePageSize: 6,

    timelineSlots() {
      const items = this.timelinePageItems();
      const slots = items.slice(0, this.timelinePageSize);
      while (slots.length < this.timelinePageSize) slots.push(null);
      return slots;
    },

    timelineTotalPages() {
      const n = this.timelineLugares?.length || 0;
      return Math.max(1, Math.ceil(n / this.timelinePageSize));
    },
    timelinePageItems() {
      const start = this.timelinePage * this.timelinePageSize;
      return (this.timelineLugares || []).slice(start, start + this.timelinePageSize);
    },
    timelinePrev() {
      this.timelinePage = Math.max(0, this.timelinePage - 1);
    },
    timelineNext() {
      this.timelinePage = Math.min(this.timelineTotalPages() - 1, this.timelinePage + 1);
    },
    timelineGoToIndex(i) {
      const page = Math.floor(i / this.timelinePageSize);
      this.timelinePage = Math.max(0, Math.min(this.timelineTotalPages() - 1, page));
    },

    selectedCodigo() {
      return this.detalle?.codigo ? String(this.detalle.codigo) : null;
    },
    selectedIndex() {
      const cod = this.selectedCodigo();
      if (!cod) return -1;
      return (this.timelineLugares || []).findIndex(x => String(x.codigo) === cod);
    },
    ensureVisibleIndex(idx) {
      if (idx < 0) return;
      this.timelineGoToIndex(idx); // usa tu paginado de 6 para mostrar el grupo donde cae
    },
    timelineSelectAt(idx) {
      if (!this.timelineLugares?.length) return;
      idx = Math.max(0, Math.min(this.timelineLugares.length - 1, idx));
      const p = this.timelineLugares[idx];
      if (!p) return;
      this.ensureVisibleIndex(idx);
      this.selectByCodigo(p.codigo, true);
    },
    timelinePrevItem() {
      const idx = this.selectedIndex();
      // si no hay seleccionado, ir al primero visible
      if (idx < 0) return this.timelineSelectAt(this.timelinePage * this.timelinePageSize);
      this.timelineSelectAt(idx - 1);
    },
    timelineNextItem() {
      const idx = this.selectedIndex();
      if (idx < 0) return this.timelineSelectAt(this.timelinePage * this.timelinePageSize);
      this.timelineSelectAt(idx + 1);
    },


    initialsFromTitle(t) {
      const s = String(t || '').trim();
      if (!s) return '•';
      const words = s.split(/\s+/).filter(Boolean);
      // 1 palabra -> 2 letras, 2+ palabras -> iniciales
      if (words.length === 1) return words[0].slice(0, 2).toUpperCase();
      return (words[0][0] + (words[1]?.[0] || '')).toUpperCase();
    },
    shortTitle(t, max = 18) {
      const s = String(t || '').trim();
      if (!s) return '';
      return s.length > max ? s.slice(0, max - 1) + '…' : s;
    },

    // preview
    previewOpen: false,
    previewUrl: '',
    previewTitle: '',
    previewKind: '',
    _mediaPrimary: null,

    // helpers
    mediaUrl(fileId) { return `/media/drive/${encodeURIComponent(fileId)}`; },
    fileIdOf(item) { return item?._fileId || item?.file_id || item?.drive_file_id || item?.fileId || null; },
    mediaUrlFromItem(item) {
      const id = this.fileIdOf(item);
      return id ? this.mediaUrl(id) : '';
    },

    sortByCodigo(a, b) {
      const sa = String(a?.codigo ?? '');
      const sb = String(b?.codigo ?? '');
      const na = Number(sa), nb = Number(sb);
      const aNum = Number.isFinite(na) && sa.trim() !== '';
      const bNum = Number.isFinite(nb) && sb.trim() !== '';
      if (aNum && bNum) return na - nb;
      return sa.localeCompare(sb, 'es', { numeric: true, sensitivity: 'base' });
    },

    imagesOnly() {
      const items = this.detalle?.imagenes || [];
      return items
        .filter(i => {
          const k = String(i?.kind || '').toLowerCase().trim();
          // si no hay kind, asumimos que es imagen
          return k === '' || k === 'image' || k === 'imagen' || k === 'img';
        })
        .map(i => ({ ...i, _fileId: this.fileIdOf(i) }))
        .filter(i => !!i._fileId);
    },


    primaryAsset() {
      const imgs = this.imagesOnly();
      if (!imgs.length) return null;
      return this._mediaPrimary || imgs[0];
    },

    openPreview(item) {
      const id = this.fileIdOf(item);
      if (!id) return;
      this.previewKind  = (item?.kind || 'image').toLowerCase();
      this.previewTitle = item?.titulo || 'Vista previa';
      this.previewUrl   = this.mediaUrl(id);
      this.previewOpen  = true;
    },

    galleryByYear() {
      const imgs = this.imagesOnly()
        .map(i => ({
          ...i,
          _year: (i?.anio && String(i.anio).trim() !== '') ? String(i.anio).trim() : 'Sin año',
          _orden: Number(i?.orden ?? 0),
        }))
        .sort((a,b) => {
          const ay = a._year === 'Sin año' ? 999999 : Number(a._year) || 999999;
          const by = b._year === 'Sin año' ? 999999 : Number(b._year) || 999999;
          if (ay !== by) return ay - by;
          if (a._orden !== b._orden) return a._orden - b._orden;
          return String(a.titulo || '').localeCompare(String(b.titulo || ''), 'es', { sensitivity:'base' });
        });

      const grouped = {};
      for (const img of imgs) {
        grouped[img._year] ??= [];
        grouped[img._year].push(img);
      }
      return grouped;
    },

    googleMapsUrl() {
      if (!this.detalle) return '#';
      const lat = Number(this.detalle.lat);
      const lng = Number(this.detalle.lng);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return '#';
      return `https://www.google.com/maps/dir/?api=1&origin=My+Location&destination=${lat},${lng}&travelmode=walking`;
    },

    initWatchers() {
      this.$watch('sidebarMin', () => {
        this.$nextTick(() => setTimeout(() => { try { this.map?.resize(); } catch(e) {} }, 80));
      });

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

    async selectByCodigo(codigo, fromTimeline = false) {
      const cod = String(codigo);
      const p = this._lugaresByCodigo[cod];

      const idx = (this.timelineLugares || []).findIndex(x => String(x.codigo) === String(cod));
      if (idx >= 0) this.timelineGoToIndex(idx);

      
      await this.cargarDetalle(cod);

      if (p && this.map) {
        try { this.map.setFilter('lugares-selected', ['==', ['get', 'id'], cod]); } catch(e) {}
        try {
          this.map.easeTo({
            center: [Number(p.lng), Number(p.lat)],
            zoom: Math.max(this.map.getZoom(), 16),
            duration: fromTimeline ? 650 : 450
          });
        } catch(e) {}
      }
    },

    async init() {
      if (this.map) { try { this.map.resize(); } catch(e) {} return; }

      this.initWatchers();

      const el = document.getElementById('map');
      if (!el) return;

      el.replaceChildren();

      mapboxgl.accessToken = @json(config('services.mapbox.token'));
      if (!mapboxgl?.accessToken) {
        console.error('Falta Mapbox token');
        return;
      }

      this.map = new mapboxgl.Map({
        container: el,
        style: 'mapbox://styles/mapbox/satellite-streets-v12',
        center: [-71.53, -41.9645],
        zoom: 15.2,
        maxZoom: 20,
        pitch: 55,
        bearing: -15
      });

      this.map.addControl(new mapboxgl.NavigationControl({ visualizePitch: true }), 'top-right');
      this.map.addControl(new mapboxgl.FullscreenControl(), 'top-right');

      this.map.on('error', (e) => console.error('MAPBOX ERROR', e?.error || e));

      this.map.on('load', async () => {
        const geo = await fetch(@json(route('centenario.geojson'))).then(r => r.json());

        const feats = (geo?.features || []).map(f => ({
          codigo: String(f?.properties?.codigo ?? f?.properties?.id ?? ''),
          titulo: String(f?.properties?.titulo ?? ''),
          categoria: String(f?.properties?.categoria ?? ''),
          color: String(f?.properties?.color ?? '#2563eb'),
          lng: Number(f?.geometry?.coordinates?.[0]),
          lat: Number(f?.geometry?.coordinates?.[1]),
        })).filter(x => x.codigo && Number.isFinite(x.lat) && Number.isFinite(x.lng));

        feats.sort((a,b) => this.sortByCodigo(a,b));
        this.timelineLugares = feats;

        if (this.timelineLugares.length && !this.detalle) {
          this.selectByCodigo(this.timelineLugares[0].codigo, false);
        }

        this._lugaresByCodigo = {};
        for (const p of feats) this._lugaresByCodigo[String(p.codigo)] = p;

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
            id: 'lugares-selected',
            type: 'circle',
            source: 'lugares',
            filter: ['==', ['get', 'id'], '__none__'],
            paint: {
              'circle-radius': 13,
              'circle-color': '#fff',
              'circle-opacity': 0.25
            }
          });
        }

        if (!this._clickBound) {
          this._clickBound = true;

          this.map.on('click', 'lugares-points', (e) => {
            const f = e.features?.[0];
            if (!f) return;

            const id = String(f.properties?.id ?? f.properties?.codigo ?? '');
            if (!id) return;

            try { this.map.setFilter('lugares-selected', ['==', ['get', 'id'], id]); } catch(e){}
            this.selectByCodigo(id, false);
          });

          this.map.on('mouseenter','lugares-points', ()=>this.map.getCanvas().style.cursor='pointer');
          this.map.on('mouseleave','lugares-points', ()=>this.map.getCanvas().style.cursor='');
        }

        setTimeout(() => { try { this.map.resize(); } catch(e) {} }, 120);
      });

      window.addEventListener('resize', ()=>{ try{ this.map.resize(); }catch(e){} });
    },

    async cargarDetalle(codigo) {
      this.loading = true;
      this._mediaPrimary = null;
      try {
        const url = @json(route('centenario.show', ['lugar' => '__ID__']))
          .replace('__ID__', encodeURIComponent(String(codigo)));
        const data = await fetch(url).then(r => r.json());
        data.codigo = String(data.codigo ?? codigo);
        this.detalle = data;
      } finally {
        this.loading = false;
      }
    },
  }));
});
</script>
@endpush
