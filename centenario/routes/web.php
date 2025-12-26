<?php

use App\Http\Controllers\LugarController;
use App\Services\DriveService;
use Illuminate\Support\Facades\Route;

Route::get('/media/drive/{fileId}', function ($fileId) {
    $drive = \App\Services\DriveService::client();

    $file = $drive->files->get($fileId, ['fields' => 'mimeType']);
    $response = $drive->files->get($fileId, ['alt' => 'media']);

    return response($response->getBody())
        ->header('Content-Type', $file->getMimeType())
        ->header('Cache-Control', 'public, max-age=86400');
});


Route::get('/', fn() => redirect()->route('centenario.index'));

Route::get('/centenario', [LugarController::class, 'index'])->name('centenario.index');

// datos
Route::get('/centenario/geojson', [LugarController::class, 'geojson'])->name('centenario.geojson');
Route::get('/centenario/lugares/{lugar}', [LugarController::class, 'show'])->name('centenario.show');
