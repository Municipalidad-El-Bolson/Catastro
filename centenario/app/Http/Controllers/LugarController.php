<?php

namespace App\Http\Controllers;
use App\Models\Lugar;


class LugarController extends Controller
{
    public function index()
    {
        return view('centenario.index');
    }

    public function geojson()
    {
        $lugares = Lugar::query()
            ->with('categoria')
            ->where('activo', true)
            ->orderBy('orden')
            ->get();

        $features = $lugares->map(fn($l) => [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float)$l->lng, (float)$l->lat],
            ],
            'properties' => [
                'id' => $l->id,
                'titulo' => $l->titulo,
                'categoria' => $l->categoria?->nombre,
                'icono' => $l->categoria?->icono,
                'color' => $l->categoria?->color,
            ],
        ]);

        return response()->json(['type'=>'FeatureCollection','features'=>$features]);
    }

    private function tituloLindo(?string $texto): ?string
    {
        if (!$texto) return null;

        // quitar extensión
        $t = preg_replace('/\.(jpg|jpeg|png|gif|webp|jfif|bmp|tiff|pdf|mp3|wav)$/i', '', $texto);

        // quitar código inicial tipo 02110401020122-
        $t = preg_replace('/^\d{6,}-/','', $t);

        // limpiar guiones/underscores
        $t = str_replace(['_', '-'], ' ', $t);

        // normalizar espacios
        $t = preg_replace('/\s+/', ' ', $t);

        return trim($t);
    }

    public function show(Lugar $lugar)
    {
        $lugar->load(['categoria','imagenes']);

        // 👉 título humano
        $titulo = $this->tituloLindo($lugar->descripcion)
            ?? $this->tituloLindo($lugar->titulo)
            ?? 'Sin título';

        // 👉 descripción humana (si no hay, usar el título)
        $descripcion = $this->tituloLindo($lugar->descripcion)
                    ?? $this->tituloLindo($lugar->titulo);

        return response()->json([
            'id' => $lugar->id,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'direccion' => $lugar->direccion,
            'localidad' => $lugar->localidad,
            'lat' => (float)$lugar->lat,
            'lng' => (float)$lugar->lng,
            'categoria' => $lugar->categoria?->nombre,

            'imagenes' => $lugar->imagenes->map(fn($img)=>[
                'fileId' => $img->drive_file_id,
                'kind'   => $img->kind,
                'titulo' => $this->tituloLindo($img->titulo ?? $lugar->descripcion ?? $lugar->titulo),
            ]),
        ]);
    }


}
