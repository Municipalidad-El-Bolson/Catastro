<?php

namespace App\Services\Centenario;

use Maatwebsite\Excel\Facades\Excel;

class ExcelLugaresService
{
    private function path(): string
    {
        return storage_path('app/centenario.xlsx');
    }

    /** Devuelve lugares (1 por fila del Excel) con id autogenerado si no existe */
    public function lugares(): array
    {
        $path = $this->path();

        if (!file_exists($path)) {
            throw new \RuntimeException("No existe el Excel en: {$path}");
        }

        $rows = Excel::toArray([], $path)[0] ?? [];
        if (!$rows) return [];

        // header
        $header = array_map(
            fn($h) => strtolower(trim((string)$h)),
            array_shift($rows)
        );

        return collect($rows)
            // eliminar filas vacías
            ->filter(fn($row) => count(array_filter($row, fn($v) => $v !== null && $v !== '')) > 0)
            ->values()
            // mapear filas
            ->map(function ($row, $idx) use ($header) {
                $data = array_combine($header, array_pad($row, count($header), null));

                // si el Excel no trae id, generamos uno estable por orden de fila
                $id = (isset($data['id']) && $data['id'] !== null && $data['id'] !== '')
                    ? (int) $data['id']
                    : ($idx + 1);

                $titulo = (string)($data['titulo'] ?? '');

                return [
                    'id'          => $id,
                    'titulo'      => $titulo,
                    'descripcion' => $data['descripcion'] ?? '',
                    'anio'        => $data['anio'] ?? null,
                    'lat'         => (float)($data['lat'] ?? 0),
                    'lng'         => (float)($data['lng'] ?? 0),
                    'categoria'   => $data['categoria'] ?? '',
                    'direccion'   => $data['direccion'] ?? '',
                    'localidad'   => $data['localidad'] ?? '',

                    // tu excel: 1 archivo por fila
                    'imagenes' => array_values(array_filter([[
                        'kind'   => (string)($data['kind'] ?? 'image'),      // image
                        'fileId' => (string)($data['drive_file_id'] ?? ''), // requerido
                        'titulo' => $titulo ?: 'Archivo',
                        'categoria' => (string)($data['categoria'] ?? ''),
                        'anio'      => isset($data['anio']) ? (int)$data['anio'] : null,
                    ]], fn($x) => !empty($x['fileId']))),
                ];
            })
            // opcional: filtrar los que no tienen coords válidas
            ->filter(fn($l) => is_finite($l['lat']) && is_finite($l['lng']) && $l['lat'] != 0 && $l['lng'] != 0)
            ->values()
            ->all();
    }

    /** Buscar por id (el que viaja en el GeoJSON y en /centenario/{id}) */
    public function findById(int $id): ?array
    {
        return collect($this->lugares())->firstWhere('id', $id);
    }
}
