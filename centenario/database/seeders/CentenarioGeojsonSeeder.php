<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Lugar;
use App\Models\LugarImagen;
use App\Models\Categoria;

class CentenarioGeojsonSeeder extends Seeder
{
    public function run(): void
    {
        $file = database_path('data/ARCHIVO_HISTORICO_DIGITAL.json');
        if (!file_exists($file)) {
            throw new \RuntimeException("No existe el archivo: {$file}");
        }

        $geo = json_decode(file_get_contents($file), true);
        $features = $geo['features'] ?? [];
        if (!is_array($features) || count($features) === 0) {
            throw new \RuntimeException("GeoJSON sin features o formato inválido.");
        }

        // Si querés re-seedear limpio:
        DB::transaction(function () use ($features) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table('lugar_imagenes')->delete();
            DB::table('lugares')->delete();
            DB::table('categorias')->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            // Categorías base
            $cats = [
                'imagenes'   => Categoria::create(['nombre'=>'Imágenes',   'slug'=>'imagenes',   'icono'=>'fa-image',     'color'=>'#2563eb']),
                'documentos' => Categoria::create(['nombre'=>'Documentos', 'slug'=>'documentos', 'icono'=>'fa-file-pdf',  'color'=>'#dc2626']),
                'audio'      => Categoria::create(['nombre'=>'Audio',      'slug'=>'audio',      'icono'=>'fa-volume-up', 'color'=>'#16a34a']),
                'otros'      => Categoria::create(['nombre'=>'Otros',      'slug'=>'otros',      'icono'=>'fa-folder',    'color'=>'#6b7280']),
            ];

            $imgExt  = ['.jpg','.jpeg','.png','.webp','.gif','.tif','.tiff','.jfif','.jpe'];
            $docExt  = ['.pdf','.doc','.docx','.ppt','.pptx','.txt','.zip'];
            $audExt  = ['.ogg','.mp3','.wav','.m4a'];
            $getExt = fn($s) => is_string($s) ? strtolower(trim($s)) : '';

            $rowsLugares = [];
            $rowsImgs = [];

            $now = now();

            foreach ($features as $i => $f) {
                $geom = $f['geometry'] ?? null;
                if (($geom['type'] ?? null) !== 'Point') continue;

                $coords = $geom['coordinates'] ?? null;
                if (!is_array($coords) || count($coords) < 2) continue;

                $lng = (float) $coords[0];
                $lat = (float) $coords[1];

                $p = $f['properties'] ?? [];

                $titulo = trim((string)($p['TITULO_1'] ?? '')) ?: trim((string)($p['TIT_COL'] ?? '')) ?: 'Sin título';
                $desc   = trim((string)($p['DESCRIP'] ?? ''));
                $aut    = trim((string)($p['AUTORES'] ?? ''));
                $lugar  = trim((string)($p['LUGAR'] ?? ''));

                $url    = trim((string)($p['URL'] ?? ''));
                $formato = $getExt($p['FORMATO'] ?? '');

                // Elegir categoría por extensión
                $cat = $cats['otros'];
                if (in_array($formato, $imgExt, true)) $cat = $cats['imagenes'];
                elseif (in_array($formato, $docExt, true)) $cat = $cats['documentos'];
                elseif (in_array($formato, $audExt, true)) $cat = $cats['audio'];

                // armamos descripción combinada
                $descripcion = $desc;
                if ($aut)   $descripcion .= ($descripcion ? "\n\n" : '') . "Autores: {$aut}";
                if ($lugar) $descripcion .= ($descripcion ? "\n" : '') . "Lugar: {$lugar}";

                // Insert batch de lugares (sin Eloquent para velocidad)
                $rowsLugares[] = [
                    'categoria_id' => $cat->id,
                    'titulo'       => $titulo,
                    'descripcion'  => $descripcion ?: null,
                    'lng'          => $lng,
                    'lat'          => $lat,
                    'direccion'    => null,
                    'localidad'    => $lugar ?: null,
                    'orden'        => (int)($p['FID'] ?? ($i + 1)),
                    'activo'       => true,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }

            // Insert lugares y recuperar IDs (hacemos de a chunks y luego consultamos)
            foreach (array_chunk($rowsLugares, 500) as $chunk) {
                DB::table('lugares')->insert($chunk);
            }

            // Ahora levantamos lugares en el mismo orden para asociar URL (simple)
            // (Si querés 100% robusto con IDs, agregamos una columna 'codigo' y seed por código)
            $lugares = Lugar::query()->orderBy('id')->get(['id']);

            // Recorrer nuevamente para armar lugar_imagenes
            $idx = 0;
            foreach ($features as $i => $f) {
                $geom = $f['geometry'] ?? null;
                if (($geom['type'] ?? null) !== 'Point') continue;
                $coords = $geom['coordinates'] ?? null;
                if (!is_array($coords) || count($coords) < 2) continue;

                $p = $f['properties'] ?? [];
                $url = trim((string)($p['URL'] ?? ''));
                if (!$url) { $idx++; continue; }

                $lugarId = $lugares[$idx]->id ?? null;
                $idx++;

                if (!$lugarId) continue;

                $rowsImgs[] = [
                    'lugar_id'    => $lugarId,
                    'path'        => $url, // guardamos URL externa tal cual
                    'titulo'      => null,
                    'orden'       => 0,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }

            foreach (array_chunk($rowsImgs, 500) as $chunk) {
                DB::table('lugar_imagenes')->insert($chunk);
            }
        });
    }
}
