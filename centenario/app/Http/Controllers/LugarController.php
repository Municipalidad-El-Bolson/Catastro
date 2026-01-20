<?php

namespace App\Http\Controllers;

use App\Services\Centenario\ExcelLugaresService;

class LugarController extends Controller
{
    public function geojson(ExcelLugaresService $service)
    {
        $lugares = $service->lugares();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => collect($lugares)->map(fn($l) => [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float)$l['lng'], (float)$l['lat']],
                ],
                'properties' => [
                    'id'        => (string)$l['codigo'],     // 👈 clave estable
                    'codigo'    => (string)$l['codigo'],
                    'titulo'    => $l['titulo'] ?? '',
                    'categoria' => $l['categoria'] ?? '',
                    'color'     => $l['color'] ?? '#2563eb',
                ],
            ])->values()->all(),
        ]);
    }

    public function show(string $codigo, ExcelLugaresService $service)
    {
        $lugar = $service->findByCodigo($codigo);
        abort_if(!$lugar, 404);

        return response()->json($lugar);
    }
}
