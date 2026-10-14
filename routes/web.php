<?php

use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GameController::class, 'home'])->name('home');
Route::post('/games', [GameController::class, 'store'])->name('games.store');
Route::post('/join', [GameController::class, 'join'])->name('games.join');

Route::prefix('/g/{game:code}')->group(function () {
    Route::get('/', [GameController::class, 'player'])->name('games.player');
    Route::get('/state', [GameController::class, 'playerState'])->name('games.player.state');

    Route::prefix('/spymaster/{token}')->group(function () {
        Route::get('/', [GameController::class, 'spymaster'])->name('games.spymaster');
        Route::get('/state', [GameController::class, 'spymasterState'])->name('games.spymaster.state');
        Route::post('/reveal/{index}', [GameController::class, 'reveal'])->whereNumber('index')->name('games.reveal');
        Route::post('/end-turn', [GameController::class, 'endTurn'])->name('games.end-turn');
        Route::post('/finish', [GameController::class, 'finish'])->name('games.finish');
        Route::post('/new-board', [GameController::class, 'newBoard'])->name('games.new-board');
    });
});
