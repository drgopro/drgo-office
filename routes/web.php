<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClientController;
use Illuminate\Support\Facades\Route;

// 로그인
Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// 인증 필요 구간
Route::middleware('auth')->group(function () {

    // 대시보드
    Route::get('/', function () {
        return view('dashboard');
    })->name('dashboard');

    // 캘린더
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::get('/api/events', [CalendarController::class, 'events'])->name('api.events');
    Route::post('/api/events', [CalendarController::class, 'store'])->name('api.events.store');
    Route::put('/api/events/{schedule}', [CalendarController::class, 'update'])->name('api.events.update');
    Route::delete('/api/events/{schedule}', [CalendarController::class, 'destroy'])->name('api.events.destroy');

    // 의뢰자
    Route::resource('clients', ClientController::class);

});
