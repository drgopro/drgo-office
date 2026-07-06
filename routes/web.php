<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AssigneeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BroadcastRoomController;
use App\Http\Controllers\CalendarCategoryController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientDocumentController;
use App\Http\Controllers\ClientFieldDefinitionController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\ConsultationTypeController;
use App\Http\Controllers\CrmDemoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\ExcelImportController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MarketingReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDocumentController;
use App\Http\Controllers\ProjectFieldDefinitionController;
use App\Http\Controllers\ProjectTagController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\RentalContractController;
use App\Http\Controllers\RentalEquipmentController;
use App\Http\Controllers\ScheduleAttachmentController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\VisitReportTemplateController;
use App\Http\Controllers\WikiCategoryController;
use App\Http\Controllers\WikiController;
use App\Http\Controllers\WorkTypeController;
use App\Models\ClientFieldDefinition;
use App\Models\ConsultationType;
use App\Models\ProjectFieldDefinition;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/qr-scan', fn () => view('qr-scan'))->name('qr-scan');
    // CRM 개편 데모 (운영 무영향, 검증용)
    Route::get('/crm-demo', [CrmDemoController::class, 'index'])->name('crm-demo');
    Route::get('/api/crm-demo/projects', [CrmDemoController::class, 'projects']);
    Route::post('/api/crm-demo/projects', [CrmDemoController::class, 'store']);
    Route::patch('/api/crm-demo/projects/{project}', [CrmDemoController::class, 'update']);
    Route::patch('/api/crm-demo/projects/{project}/stage', [CrmDemoController::class, 'updateStage']);
    Route::post('/api/crm-demo/projects/{project}/cancel', [CrmDemoController::class, 'cancel']);
    Route::delete('/api/crm-demo/projects/{project}', [CrmDemoController::class, 'destroy']);
    // 결제 내역 (운영 동일)
    Route::get('/api/crm-demo/projects/{project}/payments', [CrmDemoController::class, 'payments']);
    Route::post('/api/crm-demo/projects/{project}/payment', [CrmDemoController::class, 'savePayment']);
    Route::patch('/api/crm-demo/projects/{project}/payments/{payment}', [CrmDemoController::class, 'updatePayment']);
    Route::delete('/api/crm-demo/projects/{project}/payments/{payment}', [CrmDemoController::class, 'destroyPayment']);
    Route::post('/api/crm-demo/projects/{project}/payments/refund', [CrmDemoController::class, 'refundPayment']);
    Route::get('/crm-demo/{project}', [CrmDemoController::class, 'show'])->name('crm-demo.show');
    Route::patch('/api/crm-demo/projects/{project}/overview', [CrmDemoController::class, 'saveOverview']);
    Route::post('/api/crm-demo/projects/{project}/consultations', [CrmDemoController::class, 'addConsultation']);
    Route::delete('/api/crm-demo/projects/{project}/consultations/{cid}', [CrmDemoController::class, 'deleteConsultation']);
    Route::post('/api/crm-demo/projects/{project}/feedbacks', [CrmDemoController::class, 'addFeedback']);
    Route::delete('/api/crm-demo/projects/{project}/feedbacks/{fid}', [CrmDemoController::class, 'deleteFeedback']);
    Route::get('/api/crm-demo/tags', [CrmDemoController::class, 'tags']);
    Route::post('/api/crm-demo/tags', [CrmDemoController::class, 'storeTag']);
    Route::delete('/api/crm-demo/tags/{id}', [CrmDemoController::class, 'destroyTag']);
    Route::get('/api/dashboard/{type}', [DashboardController::class, 'detail']);
    Route::get('/api/dashboard-export/excel', [DashboardController::class, 'exportExcel']);

    // 마케팅 통계 (master/admin/member 접근 가능, guest 차단)
    Route::middleware('role:master,admin,member')->group(function () {
        Route::get('/marketing-report', [MarketingReportController::class, 'index'])->name('marketing-report');
    });

    // 마이페이지 (전체 사용자)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // 캘린더 (조회: 전체, 수정: 권한 필요)
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::get('/calendar/history', [CalendarController::class, 'historyIndex'])->name('calendar.history');
    Route::get('/api/events', [CalendarController::class, 'events'])->name('api.events');
    Route::get('/api/events/search', [CalendarController::class, 'search']);
    Route::get('/api/events/history', [CalendarController::class, 'historyEvents']);
    Route::get('/api/events/trashed', [CalendarController::class, 'trashed'])->middleware('permission:calendar.edit');
    Route::get('/api/events/{schedule}/detail', [CalendarController::class, 'detail']);
    Route::get('/api/events/{schedule}/history', [CalendarController::class, 'history']);
    Route::middleware('permission:calendar.edit')->group(function () {
        Route::post('/api/events', [CalendarController::class, 'store'])->name('api.events.store');
        Route::post('/api/events/{schedule}/complete', [CalendarController::class, 'complete']);
        Route::post('/api/events/{schedule}/uncomplete', [CalendarController::class, 'uncomplete']);
        Route::post('/api/events/{id}/restore', [CalendarController::class, 'restore'])->withTrashed();
        Route::delete('/api/events/{id}/force', [CalendarController::class, 'forceDestroy'])->withTrashed();
        Route::post('/api/events/trash/empty', [CalendarController::class, 'emptyTrash']);
        Route::match(['PUT', 'PATCH', 'POST'], '/api/events/{schedule}', [CalendarController::class, 'update'])->name('api.events.update');
        Route::delete('/api/events/{schedule}', [CalendarController::class, 'destroy'])->name('api.events.destroy');
        Route::middleware('permission:calendar.backup')->group(function () {
            Route::get('/api/events/export/json', [CalendarController::class, 'exportJson']);
            Route::post('/api/events/import/json', [CalendarController::class, 'importJson']);
            Route::get('/api/events/export/ical', [CalendarController::class, 'exportIcal']);
            Route::post('/api/events/import/ical', [CalendarController::class, 'importIcal']);
        });
        Route::get('/api/schedules/{schedule}/attachments', [ScheduleAttachmentController::class, 'index']);
        Route::post('/api/schedules/{schedule}/attachments', [ScheduleAttachmentController::class, 'store']);
        Route::delete('/api/schedule-attachments/{attachment}', [ScheduleAttachmentController::class, 'destroy']);
        // 배송 송장 (delivery-tracker 추적)
        Route::get('/api/schedules/{schedule}/shipments', [ShipmentController::class, 'index']);
        Route::post('/api/schedules/{schedule}/shipments', [ShipmentController::class, 'store']);
        Route::post('/api/schedules/{schedule}/shipments/refresh', [ShipmentController::class, 'refresh']);
        Route::delete('/api/schedule-shipments/{shipment}', [ShipmentController::class, 'destroy']);
    });
    Route::get('/schedule-attachments/{attachment}/view', [ScheduleAttachmentController::class, 'serve'])->name('schedule-attachments.serve');

    // 담당자 API
    Route::get('/api/assignees', [AssigneeController::class, 'index'])->name('api.assignees');

    // 담당자 관리 (master/admin 전용 — 외부 담당자 추가/수정/삭제)
    Route::middleware('role:master,admin')->group(function () {
        Route::get('/api/assignees/manage', [AssigneeController::class, 'manage']);
        Route::post('/api/assignees', [AssigneeController::class, 'store']);
        Route::patch('/api/assignees/{assignee}', [AssigneeController::class, 'update']);
        Route::delete('/api/assignees/{assignee}', [AssigneeController::class, 'destroy']);
    });
    Route::get('/api/activity-logs', [ActivityLogController::class, 'index']);

    // 의뢰자 JSON API
    Route::middleware('permission:clients.view')->group(function () {
        Route::get('/api/clients/list', [ClientController::class, 'listJson']);
        Route::get('/api/clients/{client}/detail', [ClientController::class, 'detail']);
    });
    Route::middleware('permission:clients.edit')->group(function () {
        Route::post('/api/clients', [ClientController::class, 'storeJson']);
        Route::patch('/api/clients/{client}', [ClientController::class, 'updateJson']);
        Route::post('/api/clients/{client}/memos', [ClientController::class, 'storeMemo']);
        Route::delete('/api/client-memos/{memo}', [ClientController::class, 'destroyMemo']);
    });

    // 의뢰자 (create가 {client} 와일드카드보다 먼저)
    Route::middleware('permission:clients.edit')->group(function () {
        Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');
        Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
        Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
        Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
    });
    Route::middleware('permission:clients.view')->group(function () {
        Route::get('/api/clients/search', [ClientController::class, 'search']);
        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    });

    // 프로젝트
    Route::middleware('permission:projects.edit')->group(function () {
        Route::post('/clients/{client}/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::patch('/projects/{project}/stage', [ProjectController::class, 'updateStage'])->name('projects.stage');
        Route::patch('/api/projects/{project}', [ProjectController::class, 'updateJson']);
        Route::delete('/api/projects/{project}', [ProjectController::class, 'destroy']);
        Route::post('/api/projects/{project}/memos', [ProjectController::class, 'storeMemo']);
        Route::delete('/api/project-memos/{memo}', [ProjectController::class, 'destroyMemo']);
        Route::post('/api/projects/{project}/payment', [ProjectController::class, 'savePayment'])->name('projects.payment');
        Route::patch('/api/projects/{project}/payments/{payment}', [ProjectController::class, 'updatePayment']);
        Route::delete('/api/projects/{project}/payments/{payment}', [ProjectController::class, 'destroyPayment']);
        Route::post('/api/projects/{project}/payments/refund', [ProjectController::class, 'refundPayment'])->name('projects.payments.refund');
        Route::post('/api/projects/{project}/stage-data', [ProjectController::class, 'saveStageData'])->name('projects.stageData');
        // 소분류 태그 관리 (추가/삭제) — 컨트롤러에서 tags.manage 권한 재확인
        Route::post('/api/project-subtags', [ProjectTagController::class, 'storeSubtag']);
        Route::delete('/api/project-subtags/{subtag}', [ProjectTagController::class, 'destroySubtag']);
    });
    Route::middleware('permission:projects.view')->group(function () {
        Route::get('/api/project-tags', [ProjectTagController::class, 'index']);
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::get('/api/projects/{project}/payment-estimates', [ProjectController::class, 'paymentEstimates']);
        Route::get('/api/projects/{project}/payments', [ProjectController::class, 'payments']);
        Route::get('/api/projects/{project}/schedules', [ProjectController::class, 'projectSchedules']);
    });

    // 의뢰자 문서
    Route::middleware('permission:documents.edit')->group(function () {
        Route::post('/clients/{client}/documents', [ClientDocumentController::class, 'store'])->name('documents.store');
        Route::delete('/documents/{document}', [ClientDocumentController::class, 'destroy'])->name('documents.destroy');
    });
    Route::middleware('permission:clients.view')->group(function () {
        Route::get('/documents/{document}/download', [ClientDocumentController::class, 'download'])->name('documents.download');
        Route::get('/documents/{document}/view', [ClientDocumentController::class, 'serve'])->name('documents.serve');
    });

    // 프로젝트 문서
    Route::middleware('permission:documents.edit')->group(function () {
        Route::post('/projects/{project}/documents', [ProjectDocumentController::class, 'store'])->name('project-documents.store');
        Route::post('/api/projects/{project}/documents/inline', [ProjectDocumentController::class, 'inlineUpload'])->name('project-documents.inline');
        Route::delete('/project-documents/{document}', [ProjectDocumentController::class, 'destroy'])->name('project-documents.destroy');
    });
    Route::middleware('permission:projects.view')->group(function () {
        Route::get('/project-documents/{document}/download', [ProjectDocumentController::class, 'download'])->name('project-documents.download');
        Route::get('/project-documents/{document}/view', [ProjectDocumentController::class, 'serve'])->name('project-documents.serve');
    });

    // 상담 이력
    Route::middleware('permission:projects.edit')->group(function () {
        Route::post('/projects/{project}/consultations', [ConsultationController::class, 'store'])->name('consultations.store');
        Route::patch('/consultations/{consultation}', [ConsultationController::class, 'update'])->name('consultations.update');
        Route::delete('/consultations/{consultation}', [ConsultationController::class, 'destroy'])->name('consultations.destroy');
    });

    // 재고 관리
    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
        Route::get('/api/inventory/categories', [InventoryController::class, 'categories']);
        Route::get('/api/inventory/products', [InventoryController::class, 'products']);
        Route::get('/api/inventory/stock', [InventoryController::class, 'stock']);
        Route::get('/api/inventory/estimate-products', [InventoryController::class, 'estimateProducts']);
        Route::get('/api/inventory/movements', [InventoryController::class, 'movements']);
        Route::get('/api/inventory/projects', [InventoryController::class, 'projectsForMovement']);
        Route::get('/api/inventory/orders', [PurchaseOrderController::class, 'index']);
        Route::get('/rental-equipment', [RentalEquipmentController::class, 'index'])->name('rental-equipment');
        Route::get('/api/rental/board', [RentalEquipmentController::class, 'board']);
        Route::get('/api/rental/lookup', [RentalEquipmentController::class, 'lookup']);
    });
    Route::middleware('permission:inventory.edit')->group(function () {
        Route::post('/api/inventory/categories', [InventoryController::class, 'storeCategory']);
        Route::patch('/api/inventory/categories/{category}', [InventoryController::class, 'updateCategory']);
        Route::post('/api/inventory/categories/{category}/move', [InventoryController::class, 'moveCategory']);
        Route::post('/api/inventory/categories/reorder', [InventoryController::class, 'reorderCategories']);
        Route::delete('/api/inventory/categories/{category}', [InventoryController::class, 'destroyCategory']);
        Route::post('/api/inventory/products', [InventoryController::class, 'storeProduct']);
        Route::patch('/api/inventory/products/{product}', [InventoryController::class, 'updateProduct']);
        Route::post('/api/inventory/products/bulk-estimate', [InventoryController::class, 'bulkSetEstimate']);
        Route::post('/api/inventory/products/bulk-delete', [InventoryController::class, 'bulkDeleteProducts']);
        Route::delete('/api/inventory/products/{product}', [InventoryController::class, 'destroyProduct']);
        Route::post('/api/inventory/movements', [InventoryController::class, 'storeMovement']);
        Route::post('/api/inventory/orders', [PurchaseOrderController::class, 'store']);
        Route::patch('/api/inventory/orders/{order}', [PurchaseOrderController::class, 'update']);
        Route::post('/api/inventory/orders/{order}/receive', [PurchaseOrderController::class, 'receive']);

        Route::post('/api/rental/items', [RentalEquipmentController::class, 'storeItem']);
        Route::patch('/api/rental/items/{item}', [RentalEquipmentController::class, 'updateItem']);
        Route::delete('/api/rental/items/{item}', [RentalEquipmentController::class, 'destroyItem']);
        Route::post('/api/rental/targets', [RentalEquipmentController::class, 'storeTarget']);
        Route::patch('/api/rental/targets/{target}', [RentalEquipmentController::class, 'updateTarget']);
        Route::delete('/api/rental/targets/{target}', [RentalEquipmentController::class, 'destroyTarget']);
        Route::post('/api/rental/groups', [RentalEquipmentController::class, 'storeGroup']);
        Route::patch('/api/rental/groups/{group}', [RentalEquipmentController::class, 'updateGroup']);
        Route::delete('/api/rental/groups/{group}', [RentalEquipmentController::class, 'destroyGroup']);
        Route::post('/api/rental/categories', [RentalEquipmentController::class, 'storeCategory']);
        Route::patch('/api/rental/categories/{category}', [RentalEquipmentController::class, 'updateCategory']);
        Route::delete('/api/rental/categories/{category}', [RentalEquipmentController::class, 'destroyCategory']);
        Route::post('/api/rental/assign', [RentalEquipmentController::class, 'assign']);
        Route::post('/api/rental/assign-group', [RentalEquipmentController::class, 'assignGroup']);
    });

    // 렌탈 계약 (월 단위)
    Route::middleware('permission:clients.view')->group(function () {
        Route::get('/rental-contracts', [RentalContractController::class, 'index'])->name('rental-contracts');
        Route::get('/api/rental-contracts', [RentalContractController::class, 'list']);
        Route::get('/api/rental-contracts/search-clients', [RentalContractController::class, 'searchClients']);
    });
    Route::middleware('permission:clients.edit')->group(function () {
        Route::post('/api/rental-contracts', [RentalContractController::class, 'store']);
        Route::patch('/api/rental-contracts/{contract}', [RentalContractController::class, 'update']);
        Route::delete('/api/rental-contracts/{contract}', [RentalContractController::class, 'destroy']);
    });

    // 방송룸
    Route::middleware('permission:clients.view')->group(function () {
        Route::get('/broadcast-room', [BroadcastRoomController::class, 'index'])->name('broadcast-room');
        Route::get('/api/broadcast-room/contracts', [BroadcastRoomController::class, 'contracts']);
        Route::get('/api/broadcast-room/usages', [BroadcastRoomController::class, 'usages']);
    });
    Route::middleware('permission:clients.edit')->group(function () {
        Route::post('/api/broadcast-room/contracts', [BroadcastRoomController::class, 'storeContract']);
        Route::patch('/api/broadcast-room/contracts/{contract}', [BroadcastRoomController::class, 'updateContract']);
        Route::delete('/api/broadcast-room/contracts/{contract}', [BroadcastRoomController::class, 'destroyContract']);
        Route::post('/api/broadcast-room/usages', [BroadcastRoomController::class, 'storeUsage']);
        Route::patch('/api/broadcast-room/usages/{usage}', [BroadcastRoomController::class, 'updateUsage']);
        Route::delete('/api/broadcast-room/usages/{usage}', [BroadcastRoomController::class, 'destroyUsage']);
    });

    // 견적서 (edit가 {estimate} 와일드카드보다 먼저)
    Route::middleware('permission:estimates.edit')->group(function () {
        Route::post('/api/estimates', [EstimateController::class, 'store']);
        Route::get('/estimates/{estimate}/edit', [EstimateController::class, 'edit'])->name('estimates.edit');
        Route::patch('/api/estimates/{estimate}', [EstimateController::class, 'update']);
        Route::post('/api/estimates/{estimate}/issue', [EstimateController::class, 'issue']);
        Route::delete('/api/estimates/{estimate}', [EstimateController::class, 'destroy']);
    });
    Route::middleware('permission:estimates.view')->group(function () {
        Route::get('/estimates', [EstimateController::class, 'index'])->name('estimates');
        Route::get('/api/estimates', [EstimateController::class, 'estimates']);
        Route::get('/estimates/{estimate}/print', [EstimateController::class, 'print'])->name('estimates.print');
    });

    // 위키
    Route::get('/wiki', [WikiController::class, 'index'])->name('wiki.index');
    Route::get('/wiki/create', [WikiController::class, 'create'])->name('wiki.create');
    Route::get('/wiki/{wiki}', [WikiController::class, 'show'])->name('wiki.show');
    Route::post('/wiki', [WikiController::class, 'store'])->name('wiki.store');
    Route::patch('/wiki/{wiki}', [WikiController::class, 'update'])->name('wiki.update');
    Route::delete('/wiki/{wiki}', [WikiController::class, 'destroy'])->name('wiki.destroy');
    Route::get('/api/wiki/{wiki}/diagram', [WikiController::class, 'getDiagram']);
    Route::post('/api/wiki/{wiki}/diagram', [WikiController::class, 'saveDiagram']);
    Route::post('/api/wiki/upload', [WikiController::class, 'uploadFile'])->name('wiki.upload');
    Route::post('/api/wiki/bulk-category', [WikiController::class, 'bulkCategory'])->name('wiki.bulk-category');
    Route::get('/wiki-files/{attachment}', [WikiController::class, 'serveFile'])->name('wiki.file');
    Route::get('/wiki-tools/broadcast-editor', fn () => view('wiki.tools.broadcast-editor'))->name('wiki.broadcast-editor');
    // 위키 카테고리 (계층) — 조회는 위키 사용자, 편집은 master/admin
    Route::get('/api/wiki-categories', [WikiCategoryController::class, 'index']);
    Route::middleware('role:master,admin')->group(function () {
        Route::post('/api/wiki-categories', [WikiCategoryController::class, 'store']);
        Route::post('/api/wiki-categories/reorder', [WikiCategoryController::class, 'reorder']);
        Route::patch('/api/wiki-categories/{category}', [WikiCategoryController::class, 'update']);
        Route::delete('/api/wiki-categories/{category}', [WikiCategoryController::class, 'destroy']);
    });

    // 관리자 (master, admin만)
    // 엑셀 가져오기
    Route::get('/api/import/template/{type}', [ExcelImportController::class, 'template']);
    Route::post('/api/import/{type}', [ExcelImportController::class, 'import']);

    Route::middleware('role:master,admin')->group(function () {
        Route::get('/admin', [AdminController::class, 'index'])->name('admin');
        Route::get('/api/settings', [AdminController::class, 'settings']);
        Route::post('/api/settings', [AdminController::class, 'updateSettings']);
        Route::get('/api/admin/users', [AdminController::class, 'users']);
        Route::post('/api/admin/users', [AdminController::class, 'storeUser']);
        Route::patch('/api/admin/users/{user}', [AdminController::class, 'updateUser']);
        Route::middleware('role:master')->patch('/api/admin/users/{user}/account', [AdminController::class, 'updateUserAccount']);
        Route::get('/api/admin/teams', [AdminController::class, 'teams']);
        Route::post('/api/admin/teams', [AdminController::class, 'storeTeam']);
        Route::patch('/api/admin/teams/{team}', [AdminController::class, 'updateTeam']);
        Route::delete('/api/admin/teams/{team}', [AdminController::class, 'destroyTeam']);

        // 의뢰자 동적 필드 정의 (master/admin 전용)
        Route::get('/api/admin/client-fields', [ClientFieldDefinitionController::class, 'index']);
        Route::post('/api/admin/client-fields', [ClientFieldDefinitionController::class, 'store']);
        Route::patch('/api/admin/client-fields/{field}', [ClientFieldDefinitionController::class, 'update']);
        Route::delete('/api/admin/client-fields/{field}', [ClientFieldDefinitionController::class, 'destroy']);
        Route::post('/api/admin/client-fields/reorder', [ClientFieldDefinitionController::class, 'reorder']);

        // 프로젝트 동적 필드 정의 (master/admin 전용)
        Route::get('/api/admin/project-fields', [ProjectFieldDefinitionController::class, 'index']);
        Route::post('/api/admin/project-fields', [ProjectFieldDefinitionController::class, 'store']);
        Route::patch('/api/admin/project-fields/{field}', [ProjectFieldDefinitionController::class, 'update']);
        Route::delete('/api/admin/project-fields/{field}', [ProjectFieldDefinitionController::class, 'destroy']);
        Route::post('/api/admin/project-fields/reorder', [ProjectFieldDefinitionController::class, 'reorder']);

        // 캘린더 카테고리 (master/admin 전용)
        Route::get('/api/admin/calendar-categories', [CalendarCategoryController::class, 'index']);
        Route::post('/api/admin/calendar-categories', [CalendarCategoryController::class, 'store']);
        Route::post('/api/admin/calendar-categories/reorder', [CalendarCategoryController::class, 'reorder']);
        Route::patch('/api/admin/calendar-categories/{category}', [CalendarCategoryController::class, 'update']);
        Route::delete('/api/admin/calendar-categories/{category}', [CalendarCategoryController::class, 'destroy']);
        Route::post('/api/admin/calendar-categories/{category}/reset', [CalendarCategoryController::class, 'reset']);

        // 상담 유형 (master/admin 전용)
        Route::get('/api/admin/consultation-types', [ConsultationTypeController::class, 'index']);
        Route::post('/api/admin/consultation-types', [ConsultationTypeController::class, 'store']);
        Route::patch('/api/admin/consultation-types/{type}', [ConsultationTypeController::class, 'update']);
        Route::delete('/api/admin/consultation-types/{type}', [ConsultationTypeController::class, 'destroy']);
        Route::post('/api/admin/consultation-types/reorder', [ConsultationTypeController::class, 'reorder']);

        // 보고서 템플릿 (master/admin 전용)
        Route::get('/api/admin/visit-report-templates', [VisitReportTemplateController::class, 'index']);
        Route::post('/api/admin/visit-report-templates', [VisitReportTemplateController::class, 'store']);
        Route::patch('/api/admin/visit-report-templates/{template}', [VisitReportTemplateController::class, 'update']);
        Route::delete('/api/admin/visit-report-templates/{template}', [VisitReportTemplateController::class, 'destroy']);

        // 작업 유형 (master/admin 전용)
        Route::get('/api/admin/work-types', [WorkTypeController::class, 'index']);
        Route::post('/api/admin/work-types', [WorkTypeController::class, 'store']);
        Route::patch('/api/admin/work-types/{type}', [WorkTypeController::class, 'update']);
        Route::delete('/api/admin/work-types/{type}', [WorkTypeController::class, 'destroy']);
    });

    // 보고서 템플릿 활성 목록 (조회 — 보고서 에디터 드롭다운용)
    Route::middleware('permission:projects.view')->group(function () {
        Route::get('/api/visit-report-templates/active', [VisitReportTemplateController::class, 'active']);
        Route::get('/api/work-types/active', [WorkTypeController::class, 'active']);
    });

    // 의뢰자 페이지에서 활성 필드 정의 조회 (clients.view 권한)
    Route::middleware('permission:clients.view')->group(function () {
        Route::get('/api/client-fields/active', function () {
            return ClientFieldDefinition::active()->ordered()->get();
        });
    });

    // 프로젝트 페이지에서 활성 필드 정의 조회 (projects.view 권한)
    Route::middleware('permission:projects.view')->group(function () {
        Route::get('/api/project-fields/active', function () {
            return ProjectFieldDefinition::active()->ordered()->get();
        });
    });

    // 상담 유형 활성 목록 (조회 전용, 의뢰자/프로젝트 페이지의 드롭다운용)
    Route::get('/api/consultation-types/active', function () {
        return ConsultationType::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'key', 'label']);
    });

});
