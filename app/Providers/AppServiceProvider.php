<?php

namespace App\Providers;

use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectPayment;
use App\Models\RevenueEntry;
use App\Models\Schedule;
use App\Services\LeaveLedger;
use App\Services\RevenueLedger;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // 전역 페이지네이션 뷰 (커스텀 컴팩트 디자인)
        Paginator::defaultView('vendor.pagination.drgo');
        Paginator::defaultSimpleView('vendor.pagination.drgo');

        // ── 매출 인식 원장(revenue_entries) 자동 동기화 — 여기 한 곳에만 등록 (롤백 시 이 블록 제거) ──
        // 원장은 파생 테이블이라 어떤 경로로 결제/환불/상태가 바뀌어도 견적 단위 재계산으로 따라간다.
        Estimate::saved(fn (Estimate $e) => RevenueLedger::syncEstimate($e));
        Estimate::deleted(function (Estimate $e) {
            if (Schema::hasTable('revenue_entries')) {
                RevenueEntry::where('estimate_id', $e->id)->delete();
            }
        });
        ProjectPayment::saved(fn (ProjectPayment $p) => RevenueLedger::onPaymentChanged($p));
        ProjectPayment::deleted(fn (ProjectPayment $p) => RevenueLedger::onPaymentDeleted($p));
        Project::saved(function (Project $p) {
            if ($p->wasChanged('stage') || $p->wasChanged('completed_at')) {
                RevenueLedger::onProjectStageChanged($p); // 완료 전환/해제 시 연동 견적 인식일 이동
            }
        });

        // ── 연차 사용 원장(leave_usages) 자동 동기화 — 휴가 일정의 '연차 차감' 체크 반영 ──
        Schedule::saved(function (Schedule $s) {
            if (Schema::hasTable('leave_usages')) {
                LeaveLedger::syncSchedule($s);
            }
        });
        Schedule::deleted(function (Schedule $s) {
            if (Schema::hasTable('leave_usages')) {
                LeaveLedger::syncSchedule($s); // 소프트 삭제 → 자동 기록 제거
            }
        });
    }
}
