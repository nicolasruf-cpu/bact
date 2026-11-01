<?php

use App\Modules\Identity\Http\Controllers\ActiveSiteController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::put('active-site', [ActiveSiteController::class, 'update'])->name('active-site.update');
});

require __DIR__.'/settings.php';
