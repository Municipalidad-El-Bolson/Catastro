<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Centenario\ExcelLugaresService;

class LugarController extends Controller
{
    public function geojson(ExcelLugaresService $service)
    {
        $lugares = $service->lugares();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => collect($lugares)->map(fn ($l) => [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $l['lng'], (float) $l['lat']],
                ],
                'properties' => [
                    'id'        => $l['id'],
                    'titulo'    => $l['titulo'] ?? '',
                    'categoria' => $l['categoria'] ?? '',
                    'color'     => '#2563eb',
                ],
            ])->values()->all(),
        ]);
    }

    public function show($id, ExcelLugaresService $service)
    {
        $lugar = $service->findById((int) $id);
        abort_if(!$lugar, 404);

        return response()->json($lugar);
    }
}

