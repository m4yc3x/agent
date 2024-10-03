<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Room;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', Room::class)->name('dashboard');
});
