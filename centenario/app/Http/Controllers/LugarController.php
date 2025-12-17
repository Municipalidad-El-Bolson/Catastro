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

    public function show(Lugar $lugar)
    {
        $lugar->load(['categoria','imagenes']);
        return response()->json([
            'id' => $lugar->id,
            'titulo' => $lugar->titulo,
            'descripcion' => $lugar->descripcion,
            'direccion' => $lugar->direccion,
            'localidad' => $lugar->localidad,
            'lat' => (float)$lugar->lat,
            'lng' => (float)$lugar->lng,
            'categoria' => $lugar->categoria?->nombre,
            'imagenes' => $lugar->imagenes->map(fn($img)=>[
                'kind'  => $img->kind, // file | folder
                'url'   => $img->kind === 'file' && $img->drive_file_id
                    ? route('drive.file', $img->drive_file_id)
                    : ($img->path ?? null),
                'titulo' => $img->titulo ?: $lugar->titulo,
            ]),

        ]);
    }
}
