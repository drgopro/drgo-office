<?php

namespace App\Http\Controllers;

use App\Models\BroadcastRoomContract;
use App\Models\BroadcastRoomUsage;
use App\Models\Client;
use App\Models\Consultation;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\RentalContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketingReportController extends Controller
{
    public function index(Request $request)
    {
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
        $cancelReasons = Project::whereBetween('cancelled_at', [$fromDt, $toDt])
            ->whereNotNull('cancel_reason')
            ->select('cancel_reason', DB::raw('count(*) as cnt'))
            ->groupBy('cancel_reason')->pluck('cnt', 'cancel_reason');

        // ── 매출 지표 ──
        $paidEstimates = Estimate::where('status', 'paid')->whereBetween('created_at', [$fromDt, $toDt])->get();
        $revenueService = $paidEstimates->sum('service_total');
        $revenueProduct = $paidEstimates->sum('product_total');
        $revenueTotal = $paidEstimates->sum('total_amount');

        // category_breakdown 합산
        $revenueBreakdown = ['setup' => 0, 'product' => 0, 'labor' => 0, 'dispatch' => 0, 'rush' => 0, 'other' => 0];
        foreach ($paidEstimates as $e) {
            foreach ($e->category_breakdown ?? [] as $key => $val) {
                if (isset($revenueBreakdown[$key])) {
                    $revenueBreakdown[$key] += (int) $val;
                }
            }
        }

        // ── 렌탈/방송룸 현황 ──
        $rentalActive = RentalContract::where('status', 'active')->count();
        $rentalMonthlyRevenue = RentalContract::where('status', 'active')->sum('monthly_fee');
        $rentalNewInPeriod = RentalContract::whereBetween('start_date', [$from, $to])->count();

        $broadcastActive = BroadcastRoomContract::where('status', 'active')->count();
        $broadcastMonthlyRevenue = BroadcastRoomContract::where('status', 'active')->sum('monthly_fee');
        $broadcastUsagesInPeriod = BroadcastRoomUsage::whereBetween('used_date', [$from, $to])->count();
        $broadcastUsageRevenue = BroadcastRoomUsage::whereBetween('used_date', [$from, $to])->sum('fee');

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
                'revenue' => (int) Estimate::where('status', 'paid')->whereBetween('created_at', [$ms, $me])->sum('total_amount'),
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
            'monthlyTrend'
        ));
    }
}
