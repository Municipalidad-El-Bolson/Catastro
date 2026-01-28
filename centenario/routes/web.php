<?php

use App\Http\Controllers\LugarController;
use App\Services\DriveService;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    $meta = $drive->files->get($fileId, [
        'fields' => 'id,name,mimeType,modifiedTime,md5Checksum,size',
        'supportsAllDrives' => true,
    ]);

    $resp = $drive->files->get($fileId, [
        'alt' => 'media',
        'supportsAllDrives' => true,
    ]);

    $mime = $meta->getMimeType() ?: 'application/octet-stream';

    // ETag estable: md5Checksum si existe, si no modifiedTime+size
    $etagBase = $meta->getMd5Checksum()
        ?: (($meta->getModifiedTime() ?: '') . '|' . ($meta->getSize() ?: ''));

    $etag = $etagBase ? '"' . sha1($etagBase) . '"' : null;

    // Guardar en cache (bin + metadata)
    $disk->makeDirectory($cacheDir);
    $disk->put($binPath, $resp->getBody()->getContents());
    $disk->put($metaPath, json_encode([
        'mime'      => $mime,
        'etag'      => $etag,
        'cached_at' => time(),
        'name'      => $meta->getName(),
        'modified'  => $meta->getModifiedTime(),
        'size'      => $meta->getSize(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    // Si el navegador ya tenía esta versión
    if ($etag && request()->header('If-None-Match') === $etag) {
        return response('', 304)->header('ETag', $etag);
    }

    return response($response->getBody())
    ->header('Content-Type', $file->getMimeType())
    ->header('Cache-Control', 'public, max-age=3600');

});

Route::get('/', fn () => redirect()->route('centenario.index'));

Route::prefix('centenario')->name('centenario.')->group(function () {
    Route::view('/', 'centenario.index')->name('index');
    Route::get('/geojson', [LugarController::class, 'geojson'])->name('geojson');
    Route::get('/{lugar}', [LugarController::class, 'show'])->name('show');
});