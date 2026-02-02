<?php

namespace App\Http\Controllers;

use App\Services\DriveService;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Facades\Log;

class DriveMediaController extends Controller
{
    public function show(string $id)
    {
        $id = trim($id);
        abort_if($id === '', 404);

        try {
            $drive = DriveService::client();

            // Metadata para mime/name
            $file = $drive->files->get($id, ['fields' => 'id,name,mimeType']);
            $mime = (string)($file->mimeType ?? 'application/octet-stream');

            // Descargar binario
            $resp = $drive->files->get($id, ['alt' => 'media']);
            $content = $resp->getBody()->getContents();

            return response($content, 200, [
                'Content-Type' => $mime,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        } catch (GoogleServiceException $e) {
            // Si hay permisos/404/403, esto lo deja claro en el log
            Log::error('Drive API error', [
                'id' => $id,
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'errors' => $e->getErrors(),
            ]);

            $code = (int)$e->getCode();
            if ($code < 400 || $code > 599) $code = 500;

            return response("Drive error ({$code}): ".$e->getMessage(), $code);
        } catch (\Throwable $e) {
            Log::error('DriveMedia fatal', ['id' => $id, 'e' => $e->getMessage()]);
            return response("Media fatal: ".$e->getMessage(), 500);
        }
    }
}
