<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AssigneeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientDocumentController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDocumentController;
use App\Http\Controllers\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {

    Route::get('/', fn () => view('dashboard'))->name('dashboard');

    // 캘린더
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::get('/api/events', [CalendarController::class, 'events'])->name('api.events');
    Route::post('/api/events', [CalendarController::class, 'store'])->name('api.events.store');
    Route::match(['PUT', 'PATCH', 'POST'], '/api/events/{schedule}', [CalendarController::class, 'update'])->name('api.events.update');
    Route::delete('/api/events/{schedule}', [CalendarController::class, 'destroy'])->name('api.events.destroy');

    // 담당자 API
    Route::get('/api/assignees', [AssigneeController::class, 'index'])->name('api.assignees');

    // 의뢰자
    Route::get('/api/clients/search', [ClientController::class, 'search']);
    Route::resource('clients', ClientController::class);

    // 프로젝트
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/clients/{client}/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::patch('/projects/{project}/stage', [ProjectController::class, 'updateStage'])->name('projects.stage');

    // 의뢰자 문서
    Route::post('/clients/{client}/documents', [ClientDocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}/download', [ClientDocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/view', [ClientDocumentController::class, 'serve'])->name('documents.serve');
    Route::delete('/documents/{document}', [ClientDocumentController::class, 'destroy'])->name('documents.destroy');

    // 프로젝트 문서
    Route::post('/projects/{project}/documents', [ProjectDocumentController::class, 'store'])->name('project-documents.store');
    Route::get('/project-documents/{document}/download', [ProjectDocumentController::class, 'download'])->name('project-documents.download');
    Route::get('/project-documents/{document}/view', [ProjectDocumentController::class, 'serve'])->name('project-documents.serve');
    Route::delete('/project-documents/{document}', [ProjectDocumentController::class, 'destroy'])->name('project-documents.destroy');

    // 재고 관리
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
    Route::get('/api/inventory/categories', [InventoryController::class, 'categories']);
    Route::post('/api/inventory/categories', [InventoryController::class, 'storeCategory']);
    Route::patch('/api/inventory/categories/{category}', [InventoryController::class, 'updateCategory']);
    Route::delete('/api/inventory/categories/{category}', [InventoryController::class, 'destroyCategory']);
    Route::get('/api/inventory/products', [InventoryController::class, 'products']);
    Route::post('/api/inventory/products', [InventoryController::class, 'storeProduct']);
    Route::patch('/api/inventory/products/{product}', [InventoryController::class, 'updateProduct']);
    Route::delete('/api/inventory/products/{product}', [InventoryController::class, 'destroyProduct']);
    Route::get('/api/inventory/stock', [InventoryController::class, 'stock']);
    Route::get('/api/inventory/estimate-products', [InventoryController::class, 'estimateProducts']);
    Route::get('/api/inventory/movements', [InventoryController::class, 'movements']);
    Route::post('/api/inventory/movements', [InventoryController::class, 'storeMovement']);
    Route::get('/api/inventory/orders', [PurchaseOrderController::class, 'index']);
    Route::post('/api/inventory/orders', [PurchaseOrderController::class, 'store']);
    Route::patch('/api/inventory/orders/{order}', [PurchaseOrderController::class, 'update']);
    Route::post('/api/inventory/orders/{order}/receive', [PurchaseOrderController::class, 'receive']);

    // 상담 이력
    Route::post('/projects/{project}/consultations', [ConsultationController::class, 'store'])->name('consultations.store');
    Route::patch('/consultations/{consultation}', [ConsultationController::class, 'update'])->name('consultations.update');
    Route::delete('/consultations/{consultation}', [ConsultationController::class, 'destroy'])->name('consultations.destroy');

    // 견적서
    Route::get('/estimates', [EstimateController::class, 'index'])->name('estimates');
    Route::get('/api/estimates', [EstimateController::class, 'estimates']);
    Route::post('/api/estimates', [EstimateController::class, 'store']);
    Route::get('/estimates/{estimate}/edit', [EstimateController::class, 'edit'])->name('estimates.edit');
    Route::patch('/api/estimates/{estimate}', [EstimateController::class, 'update']);
    Route::post('/api/estimates/{estimate}/issue', [EstimateController::class, 'issue']);
    Route::delete('/api/estimates/{estimate}', [EstimateController::class, 'destroy']);
    Route::get('/estimates/{estimate}/print', [EstimateController::class, 'print'])->name('estimates.print');

    // 마이페이지
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // 관리자
    Route::get('/admin', [AdminController::class, 'index'])->name('admin');
    Route::get('/api/settings', [AdminController::class, 'settings']);
    Route::post('/api/settings', [AdminController::class, 'updateSettings']);

});
