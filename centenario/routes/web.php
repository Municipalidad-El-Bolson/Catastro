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

Route::get('/', fn () => redirect()->route('centenario.index'));

Route::prefix('centenario')->name('centenario.')->group(function () {
    Route::view('/', 'centenario.index')->name('index');
    Route::get('/geojson', [LugarController::class, 'geojson'])->name('geojson');
    Route::get('/{lugar}', [LugarController::class, 'show'])->name('show');
});

