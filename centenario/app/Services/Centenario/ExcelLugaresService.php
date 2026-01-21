<?php

namespace App\Services\Centenario;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExcelLugaresService
{
    private function xlsxPath(): string
    {
        return storage_path('app/centenario.xlsx');
    }

    public function lugares(): array
    {
        $lugares  = $this->importRows();    // Import
        $imagenes = $this->imagenesRows();  // Imagenes

        // indexar imagenes por codigo (normalizado)
        $imgsByCodigo = [];
        foreach ($imagenes as $img) {
            $cod = $this->code($img['codigo'] ?? null);
            if ($cod === '') continue;
            $imgsByCodigo[$cod][] = $img;
        }

        foreach ($lugares as &$l) {
            $cod = $this->code($l['codigo'] ?? null);

            $importAssets = [];
            $importFileId = $this->code($l['drive_file_id'] ?? '');
            if ($importFileId !== '') {
                $importAssets[] = [
                    'codigo'        => $cod,
                    'drive_file_id' => $importFileId,
                    'kind'          => (string)($l['import_kind'] ?? 'image'),
                    'anio'          => $l['anio'] ?? null,
                    'asset_categoria'=> '', // opcional
                    'titulo'        => (string)($l['titulo'] ?? 'Archivo'),
                    'orden'         => -100, // para que aparezca primero
                    'nota'          => 'Import',
                ];
            }

            $sheetAssets = $imgsByCodigo[$cod] ?? [];

            $l['imagenes'] = array_merge($importAssets, $sheetAssets);

            usort($l['imagenes'], function ($a, $b) {
                $oa = (int)($a['orden'] ?? 0);
                $ob = (int)($b['orden'] ?? 0);
                if ($oa !== $ob) return $oa <=> $ob;

                $aa = (int)($a['anio'] ?? 0);
                $ab = (int)($b['anio'] ?? 0);
                return $aa <=> $ab;
            });
        }

        return $lugares;
    }


    public function findByCodigo(string $codigo): ?array
    {
        $codigo = $this->code($codigo);
        foreach ($this->lugares() as $l) {
            if (($l['codigo'] ?? '') === $codigo) return $l;
        }
        return null;
    }

    /**
     * Lee una hoja por nombre y devuelve filas como arrays asociativos (headers => value),
     * usando valores FORMATEADOS (strings) para evitar notación científica.
     */
    private function readSheetAssoc(string $sheetName): array
    {
        $path = $this->xlsxPath();
        if (!file_exists($path)) return [];

        $spreadsheet = IOFactory::load($path);

        $sheet = $this->getSheetByNameInsensitive($spreadsheet->getAllSheets(), $sheetName);
        if (!$sheet) return [];

        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();
        $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

        // headers (fila 1)
        $headers = [];
        for ($c = 1; $c <= $highestColIndex; $c++) {
            $cell = $sheet->getCellByColumnAndRow($c, 1);
            $h = trim((string)$cell->getFormattedValue());
            $headers[$c] = $h;
        }

        $out = [];
        for ($r = 2; $r <= $highestRow; $r++) {
            $row = [];
            $allEmpty = true;

            for ($c = 1; $c <= $highestColIndex; $c++) {
                $key = $headers[$c] ?? '';
                if ($key === '') continue;

                $cell = $sheet->getCellByColumnAndRow($c, $r);
                $val = trim((string)$cell->getFormattedValue());

                if ($val !== '') $allEmpty = false;
                $row[$key] = $val;
            }

            if ($allEmpty) continue;
            $out[] = $row;
        }

        return $out;
    }

    private function getSheetByNameInsensitive(array $sheets, string $name): ?Worksheet
    {
        $needle = mb_strtolower(trim($name));
        foreach ($sheets as $sh) {
            if (mb_strtolower(trim($sh->getTitle())) === $needle) return $sh;
        }
        return null;
    }

    private function trimStr($v): string
    {
        return trim((string)$v);
    }

    private function normKey($v): string
    {
        $s = trim((string)$v);
        $s = mb_strtolower($s);
        // normalizar espacios
        $s = preg_replace('/\s+/', ' ', $s);
        return $s;
    }

    /**
     * Normaliza codigo para que Import.codigo == Imagenes.codigo
     * - fuerza a string
     * - convierte coma decimal a punto
     * - recorta
     */
    private function code($v): string
    {
        $s = trim((string)$v);
        if ($s === '') return '';

        // si viene "2,1103E+16" lo dejamos estable
        $s = str_replace(',', '.', $s);
        $s = preg_replace('/\s+/', '', $s);

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

    private function importRows(): array
    {
        $rows = $this->readSheetAssoc('Import');
        if (!$rows) return [];

        $out = [];

        foreach ($rows as $row) {
            $codigo = $this->code($row['codigo'] ?? '');
            if ($codigo === '') continue;

            $lat = $this->fnum($row['lat'] ?? null);
            $lng = $this->fnum($row['lng'] ?? null);
            if (!is_finite($lat) || !is_finite($lng)) continue;

            $out[] = [
                'codigo'       => $codigo,
                'titulo'       => $this->trimStr($row['titulo'] ?? ''),
                'descripcion'  => $this->trimStr($row['descripcion'] ?? ''),
                'anio'         => $this->trimStr($row['anio'] ?? ''),
                'lat'          => $lat,
                'lng'          => $lng,
                'categoria'    => $this->trimStr($row['categoria'] ?? ''),

                // ✅ PASO 3 (IMPORTANTE)
                'drive_file_id'=> $this->code($row['drive_file_id'] ?? ''),
                'import_kind'  => $this->trimStr($row['kind'] ?? 'image'),

                // estilo default (después lo vas a pisar con Categorias)
                'color'        => '#2563eb',
                'icono'        => '',
            ];
        }

        return $out;
    }

    private function imagenesRows(): array
    {
        $rows = $this->readSheetAssoc('Imagenes');
        if (!$rows) return [];

        $out = [];

        foreach ($rows as $row) {
            $codigo = $this->code($row['codigo'] ?? '');
            $fileId = $this->code($row['drive_file_id'] ?? '');

            if ($codigo === '' || $fileId === '') continue;

            $out[] = [
                'codigo'          => $codigo,
                'drive_file_id'   => $fileId,
                'kind'            => $this->trimStr($row['kind'] ?? 'image'),
                'anio'            => $this->trimStr($row['anio'] ?? ''),
                'asset_categoria' => $this->trimStr($row['asset_categoria'] ?? ''),
                'titulo'          => $this->trimStr($row['titulo'] ?? ''),
                'orden'           => (int)($row['orden'] ?? 0),
                'nota'            => $this->trimStr($row['nota'] ?? ''),
            ];
        }

        usort($out, fn($a,$b) => ((int)$a['orden'] <=> (int)$b['orden']));
        return $out;
    }


}
