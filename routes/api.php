<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProspectoController;
use App\Http\Controllers\Api\ProgramaController;
use App\Http\Controllers\Api\UbicacionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/ping', function () {
    return response()->json(['message' => 'pong!']);
});
//Rutas y metodos para los prospectos
Route::get('/prospectos', [ProspectoController::class, 'index']);
Route::post('/prospectos', [ProspectoController::class, 'store']);


//Rutas y metodos para los programas
//obtener programas
Route::get('/programas', [ProgramaController::class, 'ObtenerProgramas']);

//Rutas y metodos para las ubicacioneroi
Route::get('/ubicacion/{paisId}', [UbicacionController::class, 'getUbicacionByPais']);
//rutas para ontener el pais por id
