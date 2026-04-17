<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // 의뢰자 통계
        $clientTotal = Client::count();
        $clientThisMonth = Client::where('created_at', '>=', now()->startOfMonth())->count();
        $clientByGrade = Client::select('grade', DB::raw('count(*) as cnt'))->groupBy('grade')->pluck('cnt', 'grade');

        // 프로젝트 통계
        $projectTotal = Project::count();
        $projectActive = Project::whereNotIn('stage', ['done', 'cancelled'])->count();
        $projectByStage = Project::select('stage', DB::raw('count(*) as cnt'))->groupBy('stage')->pluck('cnt', 'stage');
        $projectByType = Project::select('project_type', DB::raw('count(*) as cnt'))->groupBy('project_type')->pluck('cnt', 'project_type');

        // 견적서 통계
        $estimateTotal = Estimate::count();
        $estimateByStatus = Estimate::select('status', DB::raw('count(*) as cnt'))->groupBy('status')->pluck('cnt', 'status');
        $estimateTotalAmount = Estimate::whereIn('status', ['completed', 'paid'])->sum('total_amount');
        $estimatePaidAmount = Estimate::where('status', 'paid')->sum('total_amount');

        // 상담 이력 통계
        $consultTotal = Consultation::count();
        $consultThisMonth = Consultation::where('consulted_at', '>=', now()->startOfMonth())->count();
        $consultByType = Consultation::select('consult_type', DB::raw('count(*) as cnt'))->groupBy('consult_type')->pluck('cnt', 'consult_type');

        // 월별 추이 (최근 6개월)
        $monthlyClients = [];
        $monthlyProjects = [];
        $monthlyConsults = [];
        $monthlyEstimates = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $label = $m->format('Y.m');
            $start = $m->copy()->startOfMonth();
            $end = $m->copy()->endOfMonth();
            $monthlyClients[] = ['label' => $label, 'value' => Client::whereBetween('created_at', [$start, $end])->count()];
            $monthlyProjects[] = ['label' => $label, 'value' => Project::whereBetween('created_at', [$start, $end])->count()];
            $monthlyConsults[] = ['label' => $label, 'value' => Consultation::whereBetween('consulted_at', [$start, $end])->count()];
            $monthlyEstimates[] = ['label' => $label, 'value' => (int) Estimate::whereBetween('created_at', [$start, $end])->sum('total_amount')];
        }

        // 일정 통계
        $scheduleThisMonth = Schedule::where('start_date', '>=', now()->startOfMonth())->where('start_date', '<=', now()->endOfMonth())->count();
        $scheduleByColor = Schedule::select('color', DB::raw('count(*) as cnt'))->groupBy('color')->pluck('cnt', 'color');

        return view('dashboard', compact(
            'clientTotal', 'clientThisMonth', 'clientByGrade',
            'projectTotal', 'projectActive', 'projectByStage', 'projectByType',
            'estimateTotal', 'estimateByStatus', 'estimateTotalAmount', 'estimatePaidAmount',
            'consultTotal', 'consultThisMonth', 'consultByType',
            'monthlyClients', 'monthlyProjects', 'monthlyConsults', 'monthlyEstimates',
            'scheduleThisMonth', 'scheduleByColor'
        ));
    }
}
