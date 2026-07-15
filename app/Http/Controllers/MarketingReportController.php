<?php

namespace App\Http\Controllers;

use App\Models\BroadcastRoomContract;
use App\Models\BroadcastRoomUsage;
use App\Models\Client;
use App\Models\Consultation;
use App\Models\ConsultationType;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectPayment;
use App\Models\RentalContract;
use App\Models\WorkType;
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

        // 기본 기간 = 이번 달 1일 ~ 말일 (1개월 단위)
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', now()->endOfMonth()->format('Y-m-d'));
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
        // 플랫폼 % 산출 기준(기간 내 의뢰자 수)
        $platformTotal = $allClients->count();

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
        $legacyPaidEstimates = Estimate::with('project')
            ->where('status', 'paid')
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

        // 3) 프로젝트 유형·작업 유형별 매출 세분화 — 결제를 프로젝트에 조인해 (유형 × 작업 유형) 매트릭스로 집계
        $revenueMatrix = [];
        if (Schema::hasTable('project_payments')) {
            $rows = ProjectPayment::whereBetween('project_payments.created_at', [$fromDt, $toDt])
                ->join('projects', 'projects.id', '=', 'project_payments.project_id')
                ->selectRaw("coalesce(projects.project_type, '') as t, coalesce(projects.work_type, '') as w, sum(project_payments.amount) as total")
                ->groupBy('t', 'w')->get();
            foreach ($rows as $r) {
                $revenueMatrix[$r->t][$r->w] = ($revenueMatrix[$r->t][$r->w] ?? 0) + (int) $r->total;
            }
        }
        // 레거시 견적 매출도 연결된 프로젝트 기준으로 합산 (미연결 견적은 빈 키 = 미지정)
        foreach ($legacyPaidEstimates as $e) {
            $tKey = $e->project?->project_type ?? '';
            $wKey = $e->project?->work_type ?? '';
            $revenueMatrix[$tKey][$wKey] = ($revenueMatrix[$tKey][$wKey] ?? 0) + (int) $e->total_amount;
        }

        // 라벨 매핑 (비활성 유형 포함) — 유형별 합계 + 유형 내 작업 유형 하위 분해, 작업 유형 단독 집계
        $typeLabels = ConsultationType::map(false);
        $workLabels = WorkType::map(false);
        $revenueByProjectType = [];
        $revenueByWorkType = [];
        foreach ($revenueMatrix as $t => $works) {
            $tLabel = $t === '' ? '미지정' : ($typeLabels[$t] ?? $t);
            foreach ($works as $w => $amount) {
                if ((int) $amount === 0) {
                    continue;
                }
                $wLabel = $w === '' ? '미지정' : ($workLabels[$w] ?? $w);
                $revenueByProjectType[$tLabel]['total'] = ($revenueByProjectType[$tLabel]['total'] ?? 0) + (int) $amount;
                $revenueByProjectType[$tLabel]['works'][$wLabel] = ($revenueByProjectType[$tLabel]['works'][$wLabel] ?? 0) + (int) $amount;
                $revenueByWorkType[$wLabel] = ($revenueByWorkType[$wLabel] ?? 0) + (int) $amount;
            }
        }
        $revenueByProjectType = collect($revenueByProjectType)
            ->filter(fn ($d) => ($d['total'] ?? 0) !== 0)
            ->sortByDesc('total')
            ->map(function ($d) {
                arsort($d['works']);

                return $d;
            })->all();
        $revenueByWorkType = array_filter($revenueByWorkType, fn ($v) => $v !== 0);
        arsort($revenueByWorkType);

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
            'newClients', 'clientsByInflow', 'clientsByType', 'clientsByGrade', 'platformCounts', 'platformTotal', 'contentCounts',
            'totalConsults', 'reConsultCount',
            'projectsByScale', 'projectsByWorkType', 'scaleWorkMatrix',
            'newProjects', 'settingDone', 'cancelled', 'cancelReasons',
            'revenueService', 'revenueProduct', 'revenueTotal', 'revenueBreakdown', 'revenueByProjectType', 'revenueByWorkType',
            'rentalActive', 'rentalMonthlyRevenue', 'rentalNewInPeriod',
            'broadcastActive', 'broadcastMonthlyRevenue', 'broadcastUsagesInPeriod', 'broadcastUsageRevenue',
            'monthlyTrend',
            'funnelInquiry', 'funnelEstimateIssued', 'funnelPaid', 'funnelSettingDone', 'funnelConversion',
            'avgInquiryToComplete', 'avgPaidToComplete', 'pipeline'
        ));
    }

    /** 총 매출 드릴다운 — 기간 내 결제가 발생한 프로젝트별 순매출 목록 (JSON) */
    public function revenueProjects(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', now()->endOfMonth()->format('Y-m-d'));
        $fromDt = $from.' 00:00:00';
        $toDt = $to.' 23:59:59';

        $typeLabels = ConsultationType::map(false);
        $items = collect();

        if (Schema::hasTable('project_payments')) {
            $items = ProjectPayment::whereBetween('project_payments.created_at', [$fromDt, $toDt])
                ->join('projects', 'projects.id', '=', 'project_payments.project_id')
                ->leftJoin('clients', 'clients.id', '=', 'projects.client_id')
                ->selectRaw('projects.id, projects.name, projects.project_type, projects.manual_client_name,
                    clients.nickname as client_nickname, clients.name as client_name,
                    sum(project_payments.amount) as total, count(*) as pay_count, max(project_payments.paid_at) as last_paid_at')
                ->groupBy('projects.id', 'projects.name', 'projects.project_type', 'projects.manual_client_name', 'clients.nickname', 'clients.name')
                ->get()
                ->map(fn ($r) => [
                    'project_id' => $r->id,
                    'name' => $r->name,
                    'client' => $r->client_nickname ?: $r->client_name ?: $r->manual_client_name ?: '(의뢰자 없음)',
                    'type' => $typeLabels[$r->project_type] ?? $r->project_type,
                    'amount' => (int) $r->total,
                    'pay_count' => (int) $r->pay_count,
                    'last_paid_at' => $r->last_paid_at,
                    'source' => 'payment',
                ]);
        }

        // 레거시: 결제 내역 미연결 결제완료 견적 (총 매출 정의와 동일)
        $linkedEstimateIds = Schema::hasTable('project_payments')
            ? ProjectPayment::whereBetween('created_at', [$fromDt, $toDt])->whereNotNull('estimate_id')->pluck('estimate_id')->unique()->all()
            : [];
        $legacy = Estimate::with('project.client')
            ->where('status', 'paid')
            ->whereBetween('created_at', [$fromDt, $toDt])
            ->when(! empty($linkedEstimateIds), fn ($q) => $q->whereNotIn('id', $linkedEstimateIds))
            ->get()
            ->map(fn ($e) => [
                'project_id' => $e->project_id,
                'name' => $e->project?->name ?? '견적 #'.$e->id.' (프로젝트 미연결)',
                'client' => $e->client_nickname ?: $e->client_name ?: ($e->project?->client?->nickname ?? '(의뢰자 없음)'),
                'type' => $e->project ? ($typeLabels[$e->project->project_type] ?? $e->project->project_type) : '견적',
                'amount' => (int) $e->total_amount,
                'pay_count' => 1,
                'last_paid_at' => $e->created_at->format('Y-m-d'),
                'source' => 'estimate',
            ]);

        $rows = $items->concat($legacy)
            ->filter(fn ($r) => $r['amount'] !== 0)
            ->sortByDesc('amount')->values();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'total' => $rows->sum('amount'),
            'count' => $rows->count(),
            'projects' => $rows,
        ]);
    }
}
