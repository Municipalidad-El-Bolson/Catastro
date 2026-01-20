<?php

namespace App\Services\Centenario;

use Maatwebsite\Excel\Facades\Excel;

class ExcelLugaresService
{
    // Ajustá esta ruta a donde guardás el xlsx
    private function xlsxPath(): string
    {
        return storage_path('app/centenario.xlsx');
    }

    public function lugares(): array
    {
        $lugares  = $this->importRows();    // lugares base (Import)
        $imagenes = $this->imagenesRows();  // assets (Imagenes)

        // indexar imagenes por codigo
        $imgsByCodigo = [];
        foreach ($imagenes as $img) {
            $cod = $this->code($img['codigo'] ?? null);
            if ($cod === '') continue;
            $imgsByCodigo[$cod][] = $img;
        }

        // attach assets a cada lugar
        foreach ($lugares as &$l) {
            $cod = $this->code($l['codigo'] ?? null);
            $l['imagenes'] = $imgsByCodigo[$cod] ?? [];
        }

        return $lugares;
    }

    public function findByCodigo(string $codigo): ?array
    {
        $codigo = $this->code($codigo);
        foreach ($this->lugares() as $l) {
            if ($this->code($l['codigo'] ?? '') === $codigo) return $l;
        }
        return null;
    }

    private function code($v): string
    {
        $s = trim((string)$v);
        return $s;
    }

    private function fnum($v): ?float
    {
        if ($v === null) return null;
        $s = trim((string)$v);
        if ($s === '') return null;
        $s = str_replace(',', '.', $s);
        $n = (float)$s;
        return is_finite($n) ? $n : null;
    }

    private function sheetByName(array $sheets, string $name): ?array
    {
        // Si estás usando toArray() sin nombres de hoja,
        // esto NO sirve. Lo mejor es usar WithMultipleSheets.
        // Acá te lo dejo por si tu lectura ya te trae keyed arrays.
        return $sheets[$name] ?? null;
    }

    private function importRows(): array
    {
        $path = $this->xlsxPath();

        // 👇 simple (por índice). Ideal: leer por nombre “Import”.
        $sheets = Excel::toArray([], $path);
        $rows = $sheets[0] ?? []; // AJUSTÁ si Import no es la primera sheet

        if (!$rows) return [];

        $header = array_map(fn($h) => trim((string)$h), $rows[0] ?? []);
        $out = [];

        foreach (array_slice($rows, 1) as $r) {
            $row = [];
            foreach ($header as $i => $k) $row[$k] = $r[$i] ?? null;

            $codigo = $this->code($row['codigo'] ?? '');
            if ($codigo === '') continue;

            $lat = $this->fnum($row['lat'] ?? null);
            $lng = $this->fnum($row['lng'] ?? null);

            // sin coords no hay punto
            if (!is_finite($lat) || !is_finite($lng)) continue;

            $out[] = [
                'codigo'      => $codigo,
                'titulo'      => (string)($row['titulo'] ?? ''),
                'descripcion' => (string)($row['descripcion'] ?? ''),
                'anio'        => $row['anio'] ?? null,
                'lat'         => $lat,
                'lng'         => $lng,
                'categoria'   => (string)($row['categoria'] ?? ''),
                'kind'        => (string)($row['kind'] ?? 'point'),
                'color'       => '#2563eb',
            ];
        }

        return $out;
    }

    private function imagenesRows(): array
    {
        $path = $this->xlsxPath();
        $sheets = Excel::toArray([], $path);

        $rows = $sheets[2] ?? []; // AJUSTÁ: donde esté tu hoja “Imagenes”
        if (!$rows) return [];

        $header = array_map(fn($h) => trim((string)$h), $rows[0] ?? []);
        $out = [];

        foreach (array_slice($rows, 1) as $r) {
            $row = [];
            foreach ($header as $i => $k) $row[$k] = $r[$i] ?? null;

            $codigo = $this->code($row['codigo'] ?? '');
            $fileId = $this->code($row['drive_file_id'] ?? '');

            if ($codigo === '' || $fileId === '') continue;

            $out[] = [
                'codigo'         => $codigo,
                'drive_file_id'  => $fileId,
                'kind'           => (string)($row['kind'] ?? 'image'),
                'anio'           => $row['anio'] ?? null,
                'asset_categoria'=> (string)($row['asset_categoria'] ?? ''),
                'titulo'         => (string)($row['titulo'] ?? ''),
                'orden'          => (int)($row['orden'] ?? 0),
                'nota'           => (string)($row['nota'] ?? ''),
            ];
        }

        usort($out, fn($a,$b) => ($a['orden'] <=> $b['orden']));
        return $out;
    }
}
