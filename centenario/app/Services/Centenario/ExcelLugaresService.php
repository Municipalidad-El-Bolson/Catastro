<?php

namespace App\Services\Centenario;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\Cache;



class ExcelLugaresService
{
    private ?Spreadsheet $spreadsheet = null;
    private ?array $lugaresCached = null;
    private ?array $lugaresIndexCached = null;

    private function xlsxPath(): string
    {
        return storage_path('app/centenario.xlsx');
    }

    private function spreadsheet(): ?Spreadsheet
    {
        if ($this->spreadsheet) return $this->spreadsheet;

        $path = $this->xlsxPath();
        if (!file_exists($path)) return null;

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true); // ✅ acelera
        $this->spreadsheet = $reader->load($path);

        return $this->spreadsheet;
    }


    public function lugares(): array
    {
        // cache en memoria (misma request)
        if ($this->lugaresCached !== null) return $this->lugaresCached;

        $path = $this->xlsxPath();
        if (!file_exists($path)) return $this->lugaresCached = [];

        $v = filemtime($path) ?: 0;                 // cambia cuando reemplazás el excel
        $key = "centenario.lugares.v{$v}";

        $this->lugaresCached = Cache::remember($key, 3600, function () {
            return $this->lugaresUncached();
        });

        return $this->lugaresCached;
    }


    public function lugaresUncached(): array
    {
        $lugares  = $this->importRows();     // Import
        $imagenes = $this->imagenesRows();   // Imagenes

        // NUEVO: index de Categorias por Subtipo -> (icono, color)
        $catIndex = $this->categoriasIndex();

        // indexar imagenes por codigo (normalizado)
        $imgsByCodigo = [];
        foreach ($imagenes as $img) {
            $cod = $this->code($img['codigo'] ?? null);
            if ($cod === '') continue;
            $imgsByCodigo[$cod][] = $img;
        }

        foreach ($lugares as &$l) {
            $cod = $this->code($l['codigo'] ?? null);

            // ✅ NUEVO: aplicar icono/color sugeridos según "categoria" (subtipo)
            $subtipoKey = $this->normKey($l['categoria'] ?? '');
            $conf = $subtipoKey !== '' ? ($catIndex[$subtipoKey] ?? null) : null;

            if ($conf) {
                // icono: si no hay icono definido en import, usar sugerido
                if (empty($l['icono'])) {
                    $l['icono'] = (string)($conf['icono'] ?? '');
                }

                // color: si está default (#2563eb) o vacío, usar sugerido
                $cur = trim((string)($l['color'] ?? ''));
                if ($cur === '' || mb_strtolower($cur) === '#2563eb') {
                    $sug = trim((string)($conf['color'] ?? ''));
                    if ($sug !== '') $l['color'] = $sug;
                }
            }

            // assets desde Import (principal)
            $importAssets = [];
            $importFileId = $this->code($l['drive_file_id'] ?? '');
            if ($importFileId !== '') {
                $importAssets[] = [
                    'codigo'         => $cod,
                    'drive_file_id'  => $importFileId,
                    'kind'           => (string)($l['import_kind'] ?? 'image'),
                    'anio'           => $l['anio'] ?? null,
                    'asset_categoria'=> '', // opcional
                    'titulo'         => (string)($l['titulo'] ?? 'Archivo'),
                    'orden'          => -100, // para que aparezca primero
                    'nota'           => 'Import',
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

        // index en memoria (misma request)
        if ($this->lugaresIndexCached === null) {
            $this->lugaresIndexCached = [];
            foreach ($this->lugares() as $l) {
                $c = (string)($l['codigo'] ?? '');
                if ($c !== '') $this->lugaresIndexCached[$c] = $l;
            }
        }

        return $this->lugaresIndexCached[$codigo] ?? null;
    }


    /**
     * Lee una hoja por nombre y devuelve filas como arrays asociativos (headers => value),
     * usando valores FORMATEADOS (strings) para evitar notación científica.
     */
    private function readSheetAssoc(string $sheetName): array
    {
        $path = $this->xlsxPath();
        if (!file_exists($path)) return [];

        $spreadsheet = $this->spreadsheet();
        if (!$spreadsheet) return [];


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

    /**
     * Normaliza claves para lookup (Subtipo/categoria):
     * - minúsculas
     * - trim
     * - espacios múltiples a uno
     */
    private function normKey($v): string
    {
        $s = trim((string)$v);
        $s = mb_strtolower($s);
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

    // ✅ NUEVO: construir lookup Subtipo -> icono/color (desde hoja Categorias)
    private function categoriasIndex(): array
    {
        $rows = $this->readSheetAssoc('Categorias');
        if (!$rows) return [];

        $idx = [];

        foreach ($rows as $r) {
            // soportar variantes de header por si cambian
            $subtipo = $r['Subtipo'] ?? $r['subtipo'] ?? $r['SUBTIPO'] ?? '';
            $key = $this->normKey($subtipo);
            if ($key === '') continue;

            $icono = $r['Icono_sugerido'] ?? $r['icono_sugerido'] ?? $r['ICONO_SUGERIDO'] ?? '';
            $color = $r['Color_sugerido'] ?? $r['color_sugerido'] ?? $r['COLOR_SUGERIDO'] ?? '';

            $idx[$key] = [
                'icono' => trim((string)$icono),
                'color' => trim((string)$color),
            ];
        }

        return $idx;
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

                // ⚠️ Import.categoria = Subtipo
                'categoria'    => $this->trimStr($row['categoria'] ?? ''),

                // principal asset (Import)
                'drive_file_id'=> $this->code($row['drive_file_id'] ?? ''),
                'import_kind'  => $this->trimStr($row['kind'] ?? 'image'),

                // default (se pisa con CategoriasIndex si corresponde)
                'color'        => $this->trimStr($row['color'] ?? '#2563eb') ?: '#2563eb',
                'icono'        => $this->trimStr($row['icono'] ?? ''),
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
