<?php

use App\Http\Controllers\TurntableConfigController;
use Illuminate\Support\Facades\Route;

Route::get('turntable/{project}/{tab}.xml', TurntableConfigController::class)
    ->whereNumber(['project', 'tab'])
    ->name('turntable.config');
