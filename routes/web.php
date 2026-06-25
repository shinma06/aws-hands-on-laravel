<?php

use App\Http\Controllers\BedrockController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('/bedrock', [BedrockController::class, 'index'])->name('bedrock.index');
Route::post('/bedrock/invoke', [BedrockController::class, 'invoke'])->name('bedrock.invoke');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
