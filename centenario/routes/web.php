<?php

use App\Http\Controllers\LugarController;
use App\Services\DriveService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/media/drive/{fileId}', function (string $fileId) {
    $disk = Storage::disk('local'); // storage/app
    $cacheDir = 'drive-cache';
    $binPath  = "{$cacheDir}/{$fileId}.bin";
    $metaPath = "{$cacheDir}/{$fileId}.json";

    // TTL del cache local (ej: 7 días)
    $ttlSeconds = 60 * 60 * 24 * 7;

    // Si existe en cache y no expiró -> servir desde disco
    if ($disk->exists($binPath) && $disk->exists($metaPath)) {
        $meta = json_decode($disk->get($metaPath), true) ?: [];
        $cachedAt = (int)($meta['cached_at'] ?? 0);

        if ($cachedAt && (time() - $cachedAt) < $ttlSeconds) {
            $etag = $meta['etag'] ?? null;

            // Conditional request: si el navegador ya tiene lo mismo
            if ($etag && request()->header('If-None-Match') === $etag) {
                return response('', 304)->header('ETag', $etag);
            }

            $mime = $meta['mime'] ?? 'application/octet-stream';

            return response()->file(
                $disk->path($binPath),
                [
                    'Content-Type'  => $mime,
                    'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
                    'ETag'          => $etag ?? '',
                ]
            );
        }
    }

    // Si no está cacheado (o expiró), baja de Drive
    $drive = DriveService::client();

    // IMPORTANTE para Shared Drives:
    $file = $drive->files->get($fileId, [
        'fields' => 'id,name,mimeType,modifiedTime,md5Checksum,size',
        'supportsAllDrives' => true,
    ]);

    $resp = $drive->files->get($fileId, [
        'alt' => 'media',
        'supportsAllDrives' => true,
    ]);

    $mime = $file->getMimeType() ?: 'application/octet-stream';

    // ETag estable: md5Checksum si existe, si no modifiedTime+size
    $etagBase = $file->getMd5Checksum()
        ?: (($file->getModifiedTime() ?: '') . '|' . ($file->getSize() ?: ''));

    $etag = $etagBase ? '"' . sha1($etagBase) . '"' : null;

    // Guardar en cache (bin + metadata)
    $disk->makeDirectory($cacheDir);

    $content = $resp->getBody()->getContents();

    // Guardar bin original en cache (opcional, pero útil)
    $disk->put($binPath, $content);

    // Detectar TIFF por nombre o mime
    $name = strtolower((string) $file->getName());
    $isTiff = str_ends_with($name, '.tif') || str_ends_with($name, '.tiff') || $mime === 'image/tiff';

    // Si es TIFF -> convertir a WEBP para que el browser lo muestre
    if ($isTiff) {
        if (!extension_loaded('imagick')) {
            // sin imagick no se puede convertir
            // devolvemos 415 en vez de "silencio"
            return response('TIFF no soportado (falta Imagick).', 415);
        }

        $webpPath = "{$cacheDir}/{$fileId}.webp";

        // si ya lo convertimos antes, devolver cache
        if ($disk->exists($webpPath)) {
            return response()->file(
                $disk->path($webpPath),
                [
                    'Content-Type'  => 'image/webp',
                    'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
                    'ETag'          => $etag ?? '',
                ]
            );
        }

        $im = new \Imagick();
        $im->readImageBlob($content);

        // si es multipágina, agarramos la primera
        if ($im->getNumberImages() > 1) {
            $im->setIteratorIndex(0);
        }

        // Normalizar para web
        $im->setImageColorspace(\Imagick::COLORSPACE_SRGB);

        // Aplanar alpha / layers (evita transparencias raras)
        $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
        $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

        // Convertir a webp
        $im->setImageFormat('webp');
        $im->setImageCompressionQuality(82);

        $webp = $im->getImageBlob();

        $im->clear();
        $im->destroy();

        // Guardar webp cacheado
        $disk->put($webpPath, $webp);

        // Guardar meta (mantenemos etag/mime original, pero respondemos webp)
        $disk->put($metaPath, json_encode([
            'mime'      => 'image/webp',
            'etag'      => $etag,
            'cached_at' => time(),
            'name'      => $file->getName(),
            'modified'  => $file->getModifiedTime(),
            'size'      => $file->getSize(),
            'converted_from' => $mime,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return response($webp, 200, [
            'Content-Type'  => 'image/webp',
            'Cache-Control' => 'public, max-age=3600',
            'ETag'          => $etag ?? '',
        ]);
    }

    // NO TIFF: seguir normal
    $disk->put($metaPath, json_encode([
        'mime'      => $mime,
        'etag'      => $etag,
        'cached_at' => time(),
        'name'      => $file->getName(),
        'modified'  => $file->getModifiedTime(),
        'size'      => $file->getSize(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    return response($content, 200, [
        'Content-Type'  => $mime,
        'Cache-Control' => 'public, max-age=3600',
        'ETag'          => $etag ?? '',
    ]);

});

// Home
Route::get('/', fn () => redirect()->route('centenario.index'));

// Centenario
Route::prefix('centenario')->name('centenario.')->group(function () {
    Route::view('/', 'centenario.index')->name('index');
    Route::get('/geojson', [LugarController::class, 'geojson'])->name('geojson');
    Route::get('/{lugar}', [LugarController::class, 'show'])->name('show');
});
