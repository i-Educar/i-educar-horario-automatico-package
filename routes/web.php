<?php

use iEducar\Packages\BuritiHorarioAutomatico\Http\Controllers\HorarioController;
use Illuminate\Support\Facades\Route;

Route::prefix('horario-automatico')->group(function (): void {
    Route::get('/', [HorarioController::class, 'index']);
    Route::get('/matriz', [HorarioController::class, 'matriz']);
    Route::post('/matriz', [HorarioController::class, 'salvarMatriz']);
    Route::get('/validar', [HorarioController::class, 'validar']);
    Route::get('/diretivas', [HorarioController::class, 'diretivas']);
    Route::post('/diretivas', [HorarioController::class, 'salvarDiretivas']);
    Route::get('/gerar', [HorarioController::class, 'gerar']);
    Route::post('/gerar/iniciar', [HorarioController::class, 'iniciarGeracao']);
    Route::post('/gerar/continuar', [HorarioController::class, 'continuarGeracao']);
    Route::post('/gerar/pausar', [HorarioController::class, 'pausarGeracao']);
    Route::get('/gerar/status', [HorarioController::class, 'statusGeracao']);
    Route::get('/grade', [HorarioController::class, 'grade']);
    Route::post('/grade', [HorarioController::class, 'salvarAula']);
    Route::post('/grade/alocar', [HorarioController::class, 'alocar']);
    Route::post('/grade/{id}/mover', [HorarioController::class, 'mover'])->whereNumber('id');
    Route::delete('/grade/{id}', [HorarioController::class, 'apagar'])->whereNumber('id');
    Route::post('/grade/{id}/apagar', [HorarioController::class, 'apagar'])->whereNumber('id');
    Route::get('/choques', [HorarioController::class, 'choques']);
    Route::get('/cargos', [HorarioController::class, 'cargos']);
    Route::get('/relatorio', [HorarioController::class, 'relatorio']);
    Route::post('/relatorio/exportar', [HorarioController::class, 'exportarRelatorio']);
    Route::post('/fazer-valer', [HorarioController::class, 'fazerValer']);
    Route::get('/fazer-valer', [HorarioController::class, 'index']);
    Route::post('/nova-versao', [HorarioController::class, 'novaVersao']);
});
