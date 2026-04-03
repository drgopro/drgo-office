<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\ProjectController;
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

    // 프로젝트
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/clients/{client}/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::patch('/projects/{project}/stage', [ProjectController::class, 'updateStage'])->name('projects.stage');

    // 상담 이력
    Route::post('/projects/{project}/consultations', [ConsultationController::class, 'store'])->name('consultations.store');
    Route::patch('/consultations/{consultation}', [ConsultationController::class, 'update'])->name('consultations.update');
    Route::delete('/consultations/{consultation}', [ConsultationController::class, 'destroy'])->name('consultations.destroy');

});
