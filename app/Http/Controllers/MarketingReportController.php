<?php

namespace App\Http\Controllers;

use App\Models\BroadcastRoomContract;
use App\Models\BroadcastRoomUsage;
use App\Models\Client;
use App\Models\Consultation;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectPayment;
use App\Models\RentalContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MarketingReportController extends Controller
{
    public function index(Request $request)
    {
        // 핵심 컬럼 중 하나라도 없으면 안내
        if (! Schema::hasColumn('clients', 'inflow_source') || ! Schema::hasColumn('projects', 'client_scale')) {
            return response()->view('marketing-report.needs-migration', [], 503);
        }

        $from = $request->query('from', now()->subMonths(2)->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', now()->format('Y-m-d'));
        $fromDt = $from.' 00:00:00';
        $toDt = $to.' 23:59:59';

        // ── 마케팅 지표 ──
        $newClients = Client::whereBetween('created_at', [$fromDt, $toDt])->count();

        $clientsByInflow = Client::whereBetween('created_at', [$fromDt, $toDt])
            ->select('inflow_source', DB::raw('count(*) as cnt'))
            ->groupBy('inflow_source')->pluck('cnt', 'inflow_source');

        $clientsByType = Client::whereBetween('created_at', [$fromDt, $toDt])
            ->select('client_type', DB::raw('count(*) as cnt'))
            ->groupBy('client_type')->pluck('cnt', 'client_type');

        $clientsByGrade = Client::whereBetween('created_at', [$fromDt, $toDt])
            ->select('grade', DB::raw('count(*) as cnt'))
            ->groupBy('grade')->pluck('cnt', 'grade');

        // 플랫폼별 집계 (JSON 필드)
        $allClients = Client::whereBetween('created_at', [$fromDt, $toDt])->get(['platforms', 'content_types']);
        $platformCounts = [];
        $contentCounts = [];
        foreach ($allClients as $c) {
            foreach ($c->platforms ?? [] as $p) {
                $platformCounts[$p] = ($platformCounts[$p] ?? 0) + 1;
            }
            foreach ($c->content_types ?? [] as $t) {
                $contentCounts[$t] = ($contentCounts[$t] ?? 0) + 1;
            }
        }
        arsort($platformCounts);
        arsort($contentCounts);

        // ── 상담 지표 ──
        $totalConsults = Consultation::whereBetween('consulted_at', [$fromDt, $toDt])->count();
        $reConsultCount = Consultation::whereBetween('consulted_at', [$fromDt, $toDt])
            ->select('client_id', DB::raw('count(*) as cnt'))
            ->groupBy('client_id')->having('cnt', '>=', 2)
            ->get()->sum(fn ($r) => $r->cnt - 1);

        // ── 프로젝트 지표 (규모별 분리) ──
        $projectsByScale = Project::whereBetween('created_at', [$fromDt, $toDt])
            ->select('client_scale', DB::raw('count(*) as cnt'))
            ->groupBy('client_scale')->pluck('cnt', 'client_scale');

        $projectsByWorkType = Project::whereBetween('created_at', [$fromDt, $toDt])
            ->select('work_type', DB::raw('count(*) as cnt'))
            ->groupBy('work_type')->pluck('cnt', 'work_type');

        // 규모별 + 작업유형별 매트릭스
        $scaleWorkMatrix = Project::whereBetween('created_at', [$fromDt, $toDt])
            ->select('client_scale', 'work_type', DB::raw('count(*) as cnt'))
            ->groupBy('client_scale', 'work_type')->get()
            ->groupBy('client_scale')->map(fn ($group) => $group->pluck('cnt', 'work_type'))->toArray();

        $newProjects = Project::whereBetween('created_at', [$fromDt, $toDt])->count();
        $settingDone = Project::whereBetween('created_at', [$fromDt, $toDt])->whereIn('stage', ['visit', 'as', 'done'])->count();
        $cancelled = Project::whereBetween('created_at', [$fromDt, $toDt])->where('stage', 'cancelled')->count();

        // ── 퍼널 분석 (기간 내 유입된 문의의 코호트 전환율) ──
        $funnelInquiry = $newProjects;
        $funnelEstimateIssued = 0;
        $funnelPaid = 0;
        $funnelSettingDone = 0;
        $avgInquiryToComplete = 0;
        $avgPaidToComplete = 0;

        try {
            $inquiryClientIds = Project::whereBetween('created_at', [$fromDt, $toDt])->pluck('client_id')->unique()->filter()->values()->toArray();
            if (count($inquiryClientIds) > 0) {
                $funnelEstimateIssued = Estimate::whereIn('client_id', $inquiryClientIds)->distinct('client_id')->count('client_id');
                $funnelPaid = Estimate::whereIn('client_id', $inquiryClientIds)->where('status', 'paid')->distinct('client_id')->count('client_id');
            }
            $funnelSettingDone = Project::whereBetween('created_at', [$fromDt, $toDt])
                ->where('stage', 'done')
                ->count();

            // 리드타임 분석
            $completedProjects = Project::whereNotNull('completed_at')
                ->whereBetween('completed_at', [$fromDt, $toDt])
                ->get(['id', 'created_at', 'completed_at']);

            $inquiryDiffs = [];
            foreach ($completedProjects as $p) {
                if ($p->created_at && $p->completed_at) {
                    $inquiryDiffs[] = (int) abs($p->created_at->diffInDays($p->completed_at));
                }
            }
            if (count($inquiryDiffs) > 0) {
                $avgInquiryToComplete = round(array_sum($inquiryDiffs) / count($inquiryDiffs), 1);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $funnelConversion = [
            'inquiry_to_estimate' => $funnelInquiry > 0 ? round(($funnelEstimateIssued / $funnelInquiry) * 100, 1) : 0,
            'estimate_to_paid' => $funnelEstimateIssued > 0 ? round(($funnelPaid / $funnelEstimateIssued) * 100, 1) : 0,
            'paid_to_setting' => $funnelPaid > 0 ? round(($funnelSettingDone / $funnelPaid) * 100, 1) : 0,
            'overall' => $funnelInquiry > 0 ? round(($funnelSettingDone / $funnelInquiry) * 100, 1) : 0,
        ];

        // ── 파이프라인 (현재 진행 중인 건 스냅샷, 날짜 무관) ──
        $pipeline = [
            'consulting' => Project::where('stage', 'consulting')->count(),
            'estimate' => Project::whereIn('stage', ['equipment', 'proposal', 'estimate'])->count(),
            'payment' => Project::where('stage', 'payment')->count(),
            'visit' => Project::whereIn('stage', ['visit', 'as'])->count(),
        ];
        $cancelReasons = collect();
        if (Schema::hasColumn('projects', 'cancel_reason') && Schema::hasColumn('projects', 'cancelled_at')) {
            $cancelReasons = Project::whereBetween('cancelled_at', [$fromDt, $toDt])
                ->whereNotNull('cancel_reason')
                ->select('cancel_reason', DB::raw('count(*) as cnt'))
                ->groupBy('cancel_reason')->pluck('cnt', 'cancel_reason');
        }

        // ── 매출 지표 ──
        // 1) 프로젝트 결제 내역 (project_payments) — 환불/취소는 음수로 저장되어 sum이 곧 순매출
        $projectPaymentRevenue = 0;
        $linkedEstimateIds = [];
        if (Schema::hasTable('project_payments')) {
            $projectPaymentRevenue = (int) ProjectPayment::whereBetween('created_at', [$fromDt, $toDt])->sum('amount');
            $linkedEstimateIds = ProjectPayment::whereBetween('created_at', [$fromDt, $toDt])
                ->whereNotNull('estimate_id')->pluck('estimate_id')->unique()->all();
        }

        // 2) Legacy: 견적서 status=paid 중 project_payments에 미연결인 것만 (중복 카운트 방지)
        $legacyPaidEstimates = Estimate::where('status', 'paid')
            ->whereBetween('created_at', [$fromDt, $toDt])
            ->when(! empty($linkedEstimateIds), fn ($q) => $q->whereNotIn('id', $linkedEstimateIds))
            ->get();

        $revenueService = (int) $legacyPaidEstimates->sum('service_total');
        $revenueProduct = (int) $legacyPaidEstimates->sum('product_total');
        $revenueLegacy = (int) $legacyPaidEstimates->sum('total_amount');
        $revenueTotal = $projectPaymentRevenue + $revenueLegacy;

        // category_breakdown 합산 (견적서 기반은 legacy 그대로 사용)
        // 'payment_only' = 견적서 없이 직접 결제된 ProjectPayment(단순 결제) — 환불/취소 차감 포함
        $revenueBreakdown = ['setup' => 0, 'product' => 0, 'labor' => 0, 'dispatch' => 0, 'rush' => 0, 'payment_only' => 0, 'other' => 0];
        foreach ($legacyPaidEstimates as $e) {
            foreach ($e->category_breakdown ?? [] as $key => $val) {
                if (isset($revenueBreakdown[$key])) {
                    $revenueBreakdown[$key] += (int) $val;
                }
            }
        }
        if (Schema::hasTable('project_payments')) {
            $revenueBreakdown['payment_only'] = (int) ProjectPayment::whereBetween('created_at', [$fromDt, $toDt])
                ->whereNull('estimate_id')
                ->sum('amount');
        }

        // ── 렌탈/방송룸 현황 (테이블 있을 때만) ──
        $rentalActive = 0;
        $rentalMonthlyRevenue = 0;
        $rentalNewInPeriod = 0;
        $broadcastActive = 0;
        $broadcastMonthlyRevenue = 0;
        $broadcastUsagesInPeriod = 0;
        $broadcastUsageRevenue = 0;

        if (Schema::hasTable('rental_contracts')) {
            $rentalActive = RentalContract::where('status', 'active')->count();
            $rentalMonthlyRevenue = (int) RentalContract::where('status', 'active')->sum('monthly_fee');
            $rentalNewInPeriod = RentalContract::whereBetween('start_date', [$from, $to])->count();
        }
        if (Schema::hasTable('broadcast_room_contracts')) {
            $broadcastActive = BroadcastRoomContract::where('status', 'active')->count();
            $broadcastMonthlyRevenue = (int) BroadcastRoomContract::where('status', 'active')->sum('monthly_fee');
        }
        if (Schema::hasTable('broadcast_room_usages')) {
            $broadcastUsagesInPeriod = BroadcastRoomUsage::whereBetween('used_date', [$from, $to])->count();
            $broadcastUsageRevenue = (int) BroadcastRoomUsage::whereBetween('used_date', [$from, $to])->sum('fee');
        }

        // ── 월별 추이 (최근 6개월) ──
        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $ms = $m->copy()->startOfMonth();
            $me = $m->copy()->endOfMonth();
            $monthlyTrend[] = [
                'label' => $m->format('Y.m'),
                'clients' => Client::whereBetween('created_at', [$ms, $me])->count(),
                'projects' => Project::whereBetween('created_at', [$ms, $me])->count(),
                'consults' => Consultation::whereBetween('consulted_at', [$ms, $me])->count(),
                'revenue' => (int) (
                    (Schema::hasTable('project_payments')
                        ? ProjectPayment::whereBetween('created_at', [$ms, $me])->sum('amount')
                        : 0)
                    + Estimate::where('status', 'paid')
                        ->whereBetween('created_at', [$ms, $me])
                        ->when(Schema::hasTable('project_payments'),
                            fn ($q) => $q->whereNotIn('id', ProjectPayment::whereNotNull('estimate_id')->whereBetween('created_at', [$ms, $me])->pluck('estimate_id')))
                        ->sum('total_amount')
                ),
            ];
        }

        return view('marketing-report.index', compact(
            'from', 'to',
            'newClients', 'clientsByInflow', 'clientsByType', 'clientsByGrade', 'platformCounts', 'contentCounts',
            'totalConsults', 'reConsultCount',
            'projectsByScale', 'projectsByWorkType', 'scaleWorkMatrix',
            'newProjects', 'settingDone', 'cancelled', 'cancelReasons',
            'revenueService', 'revenueProduct', 'revenueTotal', 'revenueBreakdown',
            'rentalActive', 'rentalMonthlyRevenue', 'rentalNewInPeriod',
            'broadcastActive', 'broadcastMonthlyRevenue', 'broadcastUsagesInPeriod', 'broadcastUsageRevenue',
            'monthlyTrend',
            'funnelInquiry', 'funnelEstimateIssued', 'funnelPaid', 'funnelSettingDone', 'funnelConversion',
            'avgInquiryToComplete', 'avgPaidToComplete', 'pipeline'
        ));
    }
}
