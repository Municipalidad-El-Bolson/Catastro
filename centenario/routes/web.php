<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LugarController;

Route::get('/', fn() => redirect()->route('centenario.index'));

Route::get('/centenario', [LugarController::class, 'index'])->name('centenario.index');

// datos
Route::get('/centenario/geojson', [LugarController::class, 'geojson'])->name('centenario.geojson');
Route::get('/centenario/lugares/{lugar}', [LugarController::class, 'show'])->name('centenario.show');
