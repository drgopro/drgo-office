<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AssigneeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BankDepositController;
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
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LinkPreviewController;
use App\Http\Controllers\MarketingReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDocumentController;
use App\Http\Controllers\ProjectFieldDefinitionController;
use App\Http\Controllers\ProjectTagController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RentalContractController;
use App\Http\Controllers\RentalEquipmentController;
use App\Http\Controllers\RequestItemPresetController;
use App\Http\Controllers\ScheduleAttachmentController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\TodoController;
use App\Http\Controllers\UpdateNoteController;
use App\Http\Controllers\VisitReportTemplateController;
use App\Http\Controllers\WikiCategoryController;
use App\Http\Controllers\WikiController;
use App\Http\Controllers\WikiTemplateController;
use App\Http\Controllers\WorkTypeController;
use App\Models\ClientFieldDefinition;
use App\Models\ConsultationType;
use App\Models\ProjectFieldDefinition;
use App\Models\User;
use App\Services\MarketPriceCrawler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

// 무통장입금 SMS 포워딩 웹훅 — 폰 앱에서 호출 (세션 인증 대신 시크릿 토큰, CSRF 제외)
Route::post('/api/bank-deposits/ingest', [BankDepositController::class, 'ingest']);

// 의뢰자용 공개 견적서 — 난수 토큰으로만 접근 (로그인 불필요)
Route::get('/estimate-view/{token}', [EstimateController::class, 'publicView'])->name('estimates.public');
// 페이앱 결제 결과 통지 (feedbackurl — 연동 KEY/VALUE + 견적서 토큰으로 검증, CSRF 제외)
Route::post('/api/payapp/feedback', [EstimateController::class, 'payappFeedback'])->name('payapp.feedback');

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
        Route::get('/marketing-report/revenue', [MarketingReportController::class, 'revenuePage'])->name('marketing-report.revenue');
        Route::get('/marketing-report/schedules-export', [MarketingReportController::class, 'schedulesExport'])->name('marketing-report.schedules-export');
        Route::get('/marketing-report/schedules-export-raw', [MarketingReportController::class, 'schedulesExportRaw'])->name('marketing-report.schedules-export-raw');
        Route::get('/api/marketing-report/revenue-projects', [MarketingReportController::class, 'revenueProjects']);
    });

    // 피드백 보드 (버그 제보/기능 요청, guest 차단)
    Route::middleware('role:master,admin,member')->group(function () {
        // @멘션 자동완성용 멤버 목록 (피드백·위키 댓글 공용)
        Route::get('/api/mention-users', function () {
            return User::where('is_active', true)
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'username'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->display_name ?? $u->username]);
        });
        Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback');
        Route::get('/api/feedback', [FeedbackController::class, 'list']);
        Route::post('/api/feedback', [FeedbackController::class, 'store']);
        Route::patch('/api/feedback/{post}', [FeedbackController::class, 'update']);
        Route::delete('/api/feedback/{post}', [FeedbackController::class, 'destroy']);
        Route::post('/api/feedback/{post}/status', [FeedbackController::class, 'updateStatus']);
        Route::post('/api/feedback/{post}/comments', [FeedbackController::class, 'storeComment']);
        Route::delete('/api/feedback-comments/{comment}', [FeedbackController::class, 'destroyComment']);
        Route::post('/api/feedback/{post}/attachments', [FeedbackController::class, 'storeAttachments']);
        Route::delete('/api/feedback-attachments/{attachment}', [FeedbackController::class, 'destroyAttachment']);
        Route::get('/feedback-attachments/{attachment}/view', [FeedbackController::class, 'serveAttachment'])->name('feedback-attachments.serve');
        Route::get('/feedback-attachments/{attachment}/thumb', [FeedbackController::class, 'thumbAttachment'])->name('feedback-attachments.thumb');
    });

    // 마이페이지 (전체 사용자)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // 캘린더 (조회: 전체, 수정: 권한 필요)
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::view('/calendar/widget', 'calendar.widget')->name('calendar.widget'); // 데스크탑 위젯용 컴팩트 뷰
    Route::get('/calendar/history', [CalendarController::class, 'historyIndex'])->name('calendar.history');
    Route::get('/api/events', [CalendarController::class, 'events'])->name('api.events');
    Route::get('/api/request-item-presets', [RequestItemPresetController::class, 'index']); // 의뢰 세부 항목 선택지 (3뎁스 프리셋)
    Route::get('/api/projects/{project}/request-items', [ProjectController::class, 'requestItems']); // 캘린더에서 프로젝트 의뢰 내용 불러오기
    Route::get('/api/events/search', [CalendarController::class, 'search']);
    Route::get('/api/events/history', [CalendarController::class, 'historyEvents']);
    Route::get('/api/events/change-log', [CalendarController::class, 'changeLog']); // 사이드바 삭제/변경 이력 (문장 로그)
    Route::get('/api/events/trashed', [CalendarController::class, 'trashed'])->middleware('permission:calendar.edit');
    Route::get('/api/events/{schedule}/detail', [CalendarController::class, 'detail']);
    Route::get('/api/events/{schedule}/children', [CalendarController::class, 'childrenIndex']); // 장기 일정 하위 목록
    Route::get('/api/events/{schedule}/history', [CalendarController::class, 'history']);
    Route::middleware('permission:calendar.edit')->group(function () {
        Route::post('/api/events', [CalendarController::class, 'store'])->name('api.events.store');
        // 장기 일정 하위 일정 (일자별 시간·담당자)
        Route::post('/api/events/{schedule}/children', [CalendarController::class, 'childrenStore']);
        Route::patch('/api/events/children/{child}', [CalendarController::class, 'childrenUpdate']);
        Route::delete('/api/events/children/{child}', [CalendarController::class, 'childrenDestroy']);
        Route::post('/api/events/{schedule}/complete', [CalendarController::class, 'complete']);
        Route::post('/api/events/{schedule}/uncomplete', [CalendarController::class, 'uncomplete']);
        Route::post('/api/events/{id}/restore', [CalendarController::class, 'restore'])->withTrashed();
        Route::delete('/api/events/{id}/force', [CalendarController::class, 'forceDestroy'])->withTrashed();
        Route::post('/api/events/trash/empty', [CalendarController::class, 'emptyTrash']);
        Route::match(['PUT', 'PATCH', 'POST'], '/api/events/{schedule}', [CalendarController::class, 'update'])->name('api.events.update');
        Route::delete('/api/events/{schedule}', [CalendarController::class, 'destroy'])->name('api.events.destroy');
        // 캘린더 백업/가져오기 — 전체 일정 유출·대량 변경 가능성이 있어 관리자(master/admin) 전용
        Route::middleware('role:master,admin')->group(function () {
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
    Route::get('/schedule-attachments/{attachment}/thumb', [ScheduleAttachmentController::class, 'thumb'])->name('schedule-attachments.thumb');

    // 웹푸시 구독 (일정 알림)
    Route::post('/api/push/subscribe', [PushSubscriptionController::class, 'store']);
    Route::delete('/api/push/subscribe', [PushSubscriptionController::class, 'destroy']);

    // 오피스 전체 통합 검색 (사이드바 Ctrl+K)
    Route::get('/api/global-search', [GlobalSearchController::class, 'search']);

    // 상단 알림 리스트
    Route::get('/api/notifications', [NotificationController::class, 'index']);
    Route::post('/api/notifications/read', [NotificationController::class, 'markRead']);

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
        Route::post('/api/projects', [ProjectController::class, 'storeStandalone']); // 의뢰자명 확인 불가 — 미연동 프로젝트
        Route::patch('/projects/{project}/stage', [ProjectController::class, 'updateStage'])->name('projects.stage');
        Route::patch('/api/projects/{project}', [ProjectController::class, 'updateJson']);
        Route::delete('/api/projects/{project}', [ProjectController::class, 'destroy']);
        Route::post('/api/projects/{project}/memos', [ProjectController::class, 'storeMemo']);
        Route::delete('/api/project-memos/{memo}', [ProjectController::class, 'destroyMemo']);
        Route::post('/api/projects/{project}/payment', [ProjectController::class, 'savePayment'])->name('projects.payment');
        Route::patch('/api/projects/{project}/payments/{payment}', [ProjectController::class, 'updatePayment']);
        Route::delete('/api/projects/{project}/payments/{payment}', [ProjectController::class, 'destroyPayment']);
        Route::post('/api/projects/{project}/payments/refund', [ProjectController::class, 'refundPayment'])->name('projects.payments.refund');
        // 청구·잔금 관리
        Route::post('/api/projects/{project}/billings', [ProjectController::class, 'storeBilling']);
        Route::patch('/api/project-billings/{billing}', [ProjectController::class, 'updateBilling']);
        Route::delete('/api/project-billings/{billing}', [ProjectController::class, 'destroyBilling']);
        Route::post('/api/projects/{project}/stage-data', [ProjectController::class, 'saveStageData'])->name('projects.stageData');
        // 소분류 태그 관리 (추가/삭제) — 컨트롤러에서 tags.manage 권한 재확인
        Route::post('/api/project-subtags', [ProjectTagController::class, 'storeSubtag']);
        Route::delete('/api/project-subtags/{subtag}', [ProjectTagController::class, 'destroySubtag']);
    });
    Route::middleware('permission:projects.view')->group(function () {
        Route::get('/api/project-tags', [ProjectTagController::class, 'index']);
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects-billing', [ProjectController::class, 'billingIndex'])->name('projects.billing');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::get('/api/projects/{project}/payment-estimates', [ProjectController::class, 'paymentEstimates']);
        Route::get('/api/projects/{project}/payments', [ProjectController::class, 'payments']);
        Route::get('/api/projects/{project}/summary', [ProjectController::class, 'summary']);
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
        Route::get('/documents/{document}/thumb', [ClientDocumentController::class, 'thumb'])->name('documents.thumb');
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
        Route::get('/project-documents/{document}/thumb', [ProjectDocumentController::class, 'thumb'])->name('project-documents.thumb');
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
        Route::post('/api/inventory/margin-threshold', [InventoryController::class, 'updateMarginThreshold']); // 마진률 경고 기준(%) 저장
        Route::post('/api/inventory/products/{product}/refresh-market-price', [InventoryController::class, 'refreshMarketPrice']); // 컴퓨존 시세 수동 갱신
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
        Route::post('/api/estimates/{estimate}/payapp-request', [EstimateController::class, 'payappRequest']);
        Route::post('/api/estimates/{estimate}/payapp-cancel', [EstimateController::class, 'payappCancel']);
        Route::delete('/api/estimates/{estimate}', [EstimateController::class, 'destroy']);
    });
    Route::middleware('permission:estimates.view')->group(function () {
        Route::get('/estimates', [EstimateController::class, 'index'])->name('estimates');
        Route::get('/api/estimates', [EstimateController::class, 'estimates']);
        Route::get('/estimates/{estimate}/print', [EstimateController::class, 'print'])->name('estimates.print');
    });

    // 할 일 — 담당자별 칸반 보드
    Route::get('/todos', [TodoController::class, 'index'])->name('todos.index');
    Route::get('/api/todos', [TodoController::class, 'board']);
    Route::post('/api/todos', [TodoController::class, 'store']);
    Route::patch('/api/todos/reorder', [TodoController::class, 'reorder']); // {todo}보다 먼저 매칭돼야 함
    Route::patch('/api/todos/{todo}', [TodoController::class, 'update']);
    Route::patch('/api/todos/{todo}/assign', [TodoController::class, 'assign']);
    Route::patch('/api/todos/{todo}/complete', [TodoController::class, 'complete']);
    Route::patch('/api/todos/{todo}/hold-due', [TodoController::class, 'holdDue']);
    Route::delete('/api/todos/{todo}', [TodoController::class, 'destroy']);
    Route::post('/api/todos/{todo}/attachments', [TodoController::class, 'storeAttachments']);
    Route::delete('/api/todo-attachments/{attachment}', [TodoController::class, 'destroyAttachment']);
    Route::get('/todo-attachments/{attachment}', [TodoController::class, 'serveAttachment'])->name('todo-attachments.serve');
    Route::get('/api/link-preview', [LinkPreviewController::class, 'show']); // 본문 링크 OG 미리보기

    // 사용 가이드 — 정적 HTML 문서 (resources/guides)
    Route::view('/guide', 'guide.index')->name('guide.index');
    Route::get('/guide/{slug}', function (string $slug) {
        abort_unless(in_array($slug, ['calendar', 'projects', 'clients', 'rental-broadcast'], true), 404);

        return response()->file(resource_path("guides/{$slug}.html"));
    })->name('guide.show');

    // 위키
    Route::get('/wiki', [WikiController::class, 'index'])->name('wiki.index');
    Route::get('/wiki/create', [WikiController::class, 'create'])->name('wiki.create');
    Route::get('/wiki/{wiki}', [WikiController::class, 'show'])->name('wiki.show');
    Route::post('/wiki', [WikiController::class, 'store'])->name('wiki.store');
    Route::patch('/wiki/{wiki}', [WikiController::class, 'update'])->name('wiki.update');
    Route::delete('/wiki/{wiki}', [WikiController::class, 'destroy'])->name('wiki.destroy');
    Route::post('/api/wiki/reorder', [WikiController::class, 'reorder'])->name('wiki.reorder'); // 게시물 수동 정렬 (관리자)
    Route::get('/api/wiki/order', [WikiController::class, 'orderState']); // 정렬 상태 폴링 (실시간 반영용)
    Route::get('/api/wiki/drafts', [WikiController::class, 'drafts'])->name('wiki.drafts'); // 내 임시저장 목록
    Route::get('/api/wiki/drafts/{wiki}', [WikiController::class, 'draftShow']);
    Route::get('/api/wiki/{wiki}/diagram', [WikiController::class, 'getDiagram']);
    Route::post('/api/wiki/{wiki}/diagram', [WikiController::class, 'saveDiagram']);
    Route::post('/api/wiki/upload', [WikiController::class, 'uploadFile'])->name('wiki.upload');
    Route::post('/api/wiki/bulk-category', [WikiController::class, 'bulkCategory'])->name('wiki.bulk-category');
    // 위키 댓글 — 회의록 게시물 전용
    Route::post('/wiki/{wiki}/comments', [WikiController::class, 'storeComment'])->name('wiki.comments.store');
    Route::patch('/wiki-comments/{comment}', [WikiController::class, 'updateComment'])->name('wiki.comments.update');
    Route::delete('/wiki-comments/{comment}', [WikiController::class, 'destroyComment'])->name('wiki.comments.destroy');
    Route::get('/wiki-files/{attachment}', [WikiController::class, 'serveFile'])->name('wiki.file');
    Route::get('/wiki-files/{attachment}/thumb', [WikiController::class, 'thumbFile'])->name('wiki.file.thumb');
    Route::get('/wiki-tools/broadcast-editor', fn () => view('wiki.tools.broadcast-editor'))->name('wiki.broadcast-editor');
    // 위키 템플릿 — 글 작성 시 불러오는 미리 만든 글 서식
    Route::get('/api/wiki-templates', [WikiTemplateController::class, 'index']);
    Route::get('/api/wiki-templates/{template}', [WikiTemplateController::class, 'show']);
    Route::post('/api/wiki-templates', [WikiTemplateController::class, 'store']);
    Route::patch('/api/wiki-templates/{template}', [WikiTemplateController::class, 'update']);
    Route::delete('/api/wiki-templates/{template}', [WikiTemplateController::class, 'destroy']);
    // 위키 카테고리 (계층) — 조회는 위키 사용자, 편집은 master/admin
    Route::get('/api/wiki-categories', [WikiCategoryController::class, 'index']);
    Route::middleware('role:master,admin')->group(function () {
        Route::post('/api/wiki-categories', [WikiCategoryController::class, 'store']);
        Route::post('/api/wiki-categories/reorder', [WikiCategoryController::class, 'reorder']);
        Route::patch('/api/wiki-categories/{category}', [WikiCategoryController::class, 'update']);
        Route::delete('/api/wiki-categories/{category}', [WikiCategoryController::class, 'destroy']);
    });

    // 입금 내역 (팀 관리에서 deposits.view 권한 부여, admin 이상 항상 허용)
    Route::middleware('permission:deposits.view')->group(function () {
        Route::get('/deposits', [BankDepositController::class, 'index'])->name('deposits');
        Route::get('/api/bank-deposits', [BankDepositController::class, 'list']);
    });

    // 관리자 (master, admin만)
    Route::middleware('role:master,admin')->group(function () {
        // 엑셀 가져오기
        Route::get('/api/import/template/{type}', [ExcelImportController::class, 'template']);
        Route::post('/api/import/{type}', [ExcelImportController::class, 'import']);

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

        // 운영 커맨드 최근 실행 로그 — SSH 없이 브라우저로 결과 확인
        Route::get('/admin/smoke-log', function () {
            $path = storage_path('logs/smoke.log');

            return response(
                file_exists($path) ? file_get_contents($path) : '아직 smoke:run 실행 기록이 없습니다.',
                200,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        });
        // 스모크 데이터 정리 실행 — Forge 커맨드 입력 없이 브라우저 접속만으로 실행 (멱등이라 GET 허용)
        Route::get('/admin/smoke-cleanup', function () {
            Artisan::call('smoke:cleanup');

            return response(Artisan::output(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        });
        // 시세 크롤링 진단 (컴퓨존·피씨팩토리 공용) — 서버에서 실제 fetch를 실행해 결과 확인 (스니펫은 compuzone-log에 기록)
        Route::get('/admin/compuzone-probe', function (Request $request) {
            $url = (string) $request->query('url');
            if ($url === '') {
                return response("url 파라미터가 필요합니다.\n예: /admin/compuzone-probe?url=https://www.compuzone.co.kr/product/product_detail.htm?ProductNo=...", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
            }
            // &diag=1 — DNS/TCP 연결 진단만 수행 (해외 IP 차단 여부 판별)
            if ($request->boolean('diag')) {
                $diag = app(MarketPriceCrawler::class)->diagnoseConnectivity($url);

                return response(
                    "=== 연결 진단 ===\n".json_encode($diag, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                    ."\n\n해석: tcp_443이 '실패 ... Timeout'이면 방화벽이 이 서버 IP를 차단 중일 가능성이 높습니다 (해외/데이터센터 IP 차단).",
                    200,
                    ['Content-Type' => 'text/plain; charset=UTF-8']
                );
            }

            $result = app(MarketPriceCrawler::class)->fetch($url, logProbe: true);
            $html = $result['html'] ?? null;
            unset($result['html']);

            $out = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            // &find=문자열 — 페이지 HTML에서 해당 문자열 주변(±300자)을 최대 10곳 출력 (셀렉터 보정용)
            $find = (string) $request->query('find');
            if ($find !== '') {
                $out .= "\n\n=== '{$find}' 검색 결과 ===";
                if ($html === null) {
                    $out .= "\n(HTML을 받지 못했습니다)";
                } else {
                    $pos = 0;
                    $n = 0;
                    while ($n < 10 && ($pos = mb_strpos($html, $find, $pos)) !== false) {
                        $n++;
                        $out .= "\n--- #{$n} ---\n".mb_substr($html, max(0, $pos - 300), mb_strlen($find) + 600)."\n";
                        $pos += mb_strlen($find);
                    }
                    if ($n === 0) {
                        $out .= "\n(페이지 HTML에 없음 — 해당 값이 JS/AJAX로 나중에 로드되는 것일 수 있습니다)";
                    }
                }
            }

            return response(
                $out."\n\n원본 HTML 스니펫은 /admin/compuzone-log 에서 확인하세요. &find=판매가 처럼 붙이면 해당 문자열 주변 HTML을 볼 수 있습니다.",
                200,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        });
        Route::get('/admin/compuzone-log', function () {
            $path = storage_path('logs/compuzone.log');

            return response(
                file_exists($path) ? file_get_contents($path) : '아직 컴퓨존 시세 조회 기록이 없습니다.',
                200,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        });
        // 배포 커밋 → 위키 '업데이트' 게시물 초안 자동 생성 (임시저장으로 만들어 검토 후 발행)
        Route::get('/admin/update-note-draft', [UpdateNoteController::class, 'generateDraft'])->name('admin.update-note-draft');
        Route::get('/admin/normalize-log', function () {
            $path = storage_path('logs/normalize.log');

            return response(
                file_exists($path) ? file_get_contents($path) : '아직 data:normalize 실행 기록이 없습니다.',
                200,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        });

        // 캘린더 의뢰 세부 항목 프리셋 (master/admin 전용)
        Route::post('/api/admin/request-item-presets', [RequestItemPresetController::class, 'store']);
        Route::patch('/api/admin/request-item-presets/{preset}', [RequestItemPresetController::class, 'update']);
        Route::delete('/api/admin/request-item-presets/{preset}', [RequestItemPresetController::class, 'destroy']);

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
