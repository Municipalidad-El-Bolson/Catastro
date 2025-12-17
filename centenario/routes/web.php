<?php

use App\Http\Controllers\LugarController;
use App\Services\DriveService;
use Illuminate\Support\Facades\Route;

Route::get('/media/drive/file/{fileId}', function ($fileId) {
    $drive = DriveService::client();

    // MIME real del archivo (pdf, jpeg, etc.)
    $file = $drive->files->get($fileId, ['fields' => 'mimeType,name']);
    $stream = $drive->files->get($fileId, ['alt' => 'media']);

    return response($stream->getBody())
        ->header('Content-Type', $file->mimeType)
        ->header('Content-Disposition', 'inline; filename="'.$file->name.'"');
})->name('drive.file');


Route::get('/', fn() => redirect()->route('centenario.index'));

Route::get('/centenario', [LugarController::class, 'index'])->name('centenario.index');

// datos
Route::get('/centenario/geojson', [LugarController::class, 'geojson'])->name('centenario.geojson');
Route::get('/centenario/lugares/{lugar}', [LugarController::class, 'show'])->name('centenario.show');
