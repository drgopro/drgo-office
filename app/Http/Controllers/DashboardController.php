<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index()
    {
        // 의뢰자 통계
        $clientTotal = Client::count();
        $clientThisMonth = Client::where('created_at', '>=', now()->startOfMonth())->count();
        $clientByGrade = Client::select('grade', DB::raw('count(*) as cnt'))->groupBy('grade')->pluck('cnt', 'grade');

        // 일별 집계 (이번 달)
        $dailyData = [];
        $daysInMonth = now()->daysInMonth;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = now()->startOfMonth()->addDays($d - 1);
            $ds = $date->format('Y-m-d');
            $de = $date->copy()->endOfDay();
            $dailyData[] = [
                'label' => $d.'일',
                'clients' => Client::whereBetween('created_at', [$ds, $de])->count(),
                'projects' => Project::whereBetween('created_at', [$ds, $de])->count(),
                'consults' => Consultation::where('consulted_at', '>=', $ds)->where('consulted_at', '<=', $de)->count(),
            ];
        }

        // 년별 집계 (최근 3년)
        $yearlyData = [];
        for ($y = 2; $y >= 0; $y--) {
            $yr = now()->subYears($y)->year;
            $ys = "{$yr}-01-01";
            $ye = "{$yr}-12-31 23:59:59";
            $yearlyData[] = [
                'label' => $yr.'년',
                'clients' => Client::whereBetween('created_at', [$ys, $ye])->count(),
                'projects' => Project::whereBetween('created_at', [$ys, $ye])->count(),
                'consults' => Consultation::whereBetween('consulted_at', [$ys, $ye])->count(),
                'schedules' => Schedule::where('start_date', '>=', $ys)->where('start_date', '<=', substr($ye, 0, 10))->count(),
            ];
        }

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

        // 월별 추이 (최근 6개월) — 유형별 분리
        $monthlyData = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $label = $m->format('Y.m');
            $start = $m->copy()->startOfMonth();
            $end = $m->copy()->endOfMonth();
            $sd = $start->format('Y-m-d');
            $ed = $end->format('Y-m-d');

            $monthlyData[] = [
                'label' => $label,
                'clients' => Client::whereBetween('created_at', [$start, $end])->count(),
                'clients_cumul' => Client::where('created_at', '<=', $end)->count(),
                'projects' => Project::whereBetween('created_at', [$start, $end])->count(),
                'projects_visit' => Project::whereBetween('created_at', [$start, $end])->where('project_type', 'visit')->count(),
                'projects_remote' => Project::whereBetween('created_at', [$start, $end])->where('project_type', 'remote')->count(),
                'consults' => Consultation::whereBetween('consulted_at', [$start, $end])->count(),
                'schedules' => Schedule::where('start_date', '>=', $sd)->where('start_date', '<=', $ed)->count(),
                'schedules_visit' => Schedule::where('start_date', '>=', $sd)->where('start_date', '<=', $ed)->where('color', 'gold')->count(),
                'schedules_remote' => Schedule::where('start_date', '>=', $sd)->where('start_date', '<=', $ed)->where('color', 'teal')->count(),
                'est_amount' => (int) Estimate::whereBetween('created_at', [$start, $end])->sum('total_amount'),
                'est_paid' => (int) Estimate::where('status', 'paid')->whereBetween('created_at', [$start, $end])->sum('total_amount'),
                'est_count' => Estimate::whereBetween('created_at', [$start, $end])->count(),
            ];
        }

        // 하위호환용 배열 (기존 차트 코드 대응)
        $monthlyClients = array_map(fn ($d) => ['label' => $d['label'], 'value' => $d['clients'], 'cumul' => $d['clients_cumul']], $monthlyData);
        $monthlyProjects = array_map(fn ($d) => ['label' => $d['label'], 'value' => $d['projects']], $monthlyData);
        $monthlyConsults = array_map(fn ($d) => ['label' => $d['label'], 'value' => $d['consults']], $monthlyData);
        $monthlyEstimates = array_map(fn ($d) => ['label' => $d['label'], 'value' => $d['est_amount'], 'paid' => $d['est_paid'], 'count' => $d['est_count']], $monthlyData);
        $monthlySchedules = array_map(fn ($d) => ['label' => $d['label'], 'value' => $d['schedules']], $monthlyData);

        // 최근 3개월 요약 카드용
        $recent3 = array_slice($monthlyData, -3);
        $r3ProjectTotal = array_sum(array_column($recent3, 'projects'));
        $r3Visit = array_sum(array_column($recent3, 'projects_visit')) + array_sum(array_column($recent3, 'schedules_visit'));
        $r3Remote = array_sum(array_column($recent3, 'projects_remote')) + array_sum(array_column($recent3, 'schedules_remote'));
        $r3Consults = array_sum(array_column($recent3, 'consults'));
        $r3Clients = array_sum(array_column($recent3, 'clients'));
        $r3Revenue = array_sum(array_column($recent3, 'est_paid'));

        // 일정 통계
        $scheduleThisMonth = Schedule::where('start_date', '>=', now()->startOfMonth())->where('start_date', '<=', now()->endOfMonth())->count();
        $scheduleByColor = Schedule::select('color', DB::raw('count(*) as cnt'))->groupBy('color')->pluck('cnt', 'color');

        return view('dashboard', compact(
            'clientTotal', 'clientThisMonth', 'clientByGrade', 'dailyData', 'yearlyData',
            'projectTotal', 'projectActive', 'projectByStage', 'projectByType',
            'estimateTotal', 'estimateByStatus', 'estimateTotalAmount', 'estimatePaidAmount',
            'consultTotal', 'consultThisMonth', 'consultByType',
            'monthlyClients', 'monthlyProjects', 'monthlyConsults', 'monthlyEstimates', 'monthlySchedules',
            'monthlyData', 'r3ProjectTotal', 'r3Visit', 'r3Remote', 'r3Consults', 'r3Clients', 'r3Revenue',
            'scheduleThisMonth', 'scheduleByColor'
        ));
    }

    public function detail(Request $request, string $type)
    {
        $gradeL = ['normal' => '일반', 'vip' => 'VIP', 'rental' => '렌탈'];
        $stageL = ['consulting' => '상담', 'equipment' => '장비파악', 'proposal' => '일정제안', 'estimate' => '견적/계약', 'payment' => '결제/예약', 'visit' => '세팅', 'as' => 'AS', 'done' => '완료', 'cancelled' => '취소'];
        $typeL = ['visit' => '방문세팅', 'remote' => '원격세팅', 'design' => '디자인', 'inquiry' => '단순문의', 'as' => 'A/S', 'troubleshoot' => '문제 해결'];
        $consultL = ['kakao' => '카카오톡', 'phone' => '전화', 'visit' => '내방상담', 'field' => '현장답사'];
        $statusL = ['created' => '작성중', 'editing' => '수정중', 'completed' => '완료', 'paid' => '결제완료', 'hold' => '보류'];
        $colorL = ['gold' => '방문의뢰', 'teal' => '원격/방송룸', 'blue' => '사내업무', 'red' => '휴가/개인', 'green' => '촬영/스튜디오', 'purple' => '미팅/내방'];
        $resultL = ['in_progress' => '진행중', 'waiting' => '대기', 'valid' => '유효', 'invalid' => '무효', 'done' => '완료'];

        // 기간 필터
        $from = $request->query('from');
        $to = $request->query('to');

        $dateFilter = function ($query, string $dateCol = 'created_at') use ($from, $to) {
            if ($from) {
                $query->where($dateCol, '>=', $from);
            }
            if ($to) {
                $query->where($dateCol, '<=', $to.' 23:59:59');
            }

            return $query;
        };

        return match ($type) {
            'clients' => response()->json(
                $dateFilter(Client::orderByDesc('created_at'))->limit(300)->get()->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name, 'nickname' => $c->nickname, 'phone' => $c->phone,
                    'grade' => $gradeL[$c->grade] ?? $c->grade, 'created_at' => $c->created_at->format('Y.m.d'),
                    'url' => '/clients?open='.$c->id,
                ])
            ),
            'projects' => response()->json(
                $dateFilter(Project::with('client')->orderByDesc('created_at'))->limit(300)->get()->map(fn ($p) => [
                    'id' => $p->id, 'name' => $p->name, 'client' => $p->client?->name,
                    'type' => $typeL[$p->project_type] ?? $p->project_type,
                    'stage' => $stageL[$p->stage] ?? $p->stage,
                    'created_at' => $p->created_at->format('Y.m.d'),
                    'url' => '/projects/'.$p->id,
                ])
            ),
            'consultations' => response()->json(
                $dateFilter(Consultation::with('client', 'consultant')->orderByDesc('consulted_at'), 'consulted_at')->limit(300)->get()->map(fn ($c) => [
                    'id' => $c->id, 'client' => $c->client?->name, 'type' => $consultL[$c->consult_type] ?? $c->consult_type,
                    'result' => $resultL[$c->result] ?? $c->result, 'content' => \Str::limit($c->content, 60),
                    'consultant' => $c->consultant?->display_name, 'date' => $c->consulted_at->format('Y.m.d'),
                ])
            ),
            'estimates' => response()->json(
                $dateFilter(Estimate::with('creator')->orderByDesc('created_at'))->limit(300)->get()->map(fn ($e) => [
                    'id' => $e->id, 'client' => $e->client_nickname ?: $e->client_name,
                    'status' => $statusL[$e->status] ?? $e->status, 'total' => number_format($e->total_amount ?? 0),
                    'creator' => $e->creator?->display_name, 'created_at' => $e->created_at->format('Y.m.d'),
                    'url' => '/estimates/'.$e->id.'/edit',
                ])
            ),
            'schedules' => response()->json(
                $dateFilter(Schedule::orderBy('start_date'), 'start_date')->limit(300)->get()->map(fn ($s) => [
                    'id' => $s->id, 'title' => $s->title, 'color' => $colorL[$s->color] ?? $s->color,
                    'client' => $s->client_name,
                    'date' => $s->start_date?->format('Y-m-d'),
                    'time' => $s->start_time ? substr($s->start_time, 0, 5) : '종일',
                ])
            ),
            default => response()->json([]),
        };
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', now()->format('Y-m-d'));
        $fromDt = $from.' 00:00:00';
        $toDt = $to.' 23:59:59';

        $gradeL = ['normal' => '일반', 'vip' => 'VIP', 'rental' => '렌탈'];
        $typeL = ['visit' => '방문세팅', 'remote' => '원격세팅', 'design' => '디자인', 'inquiry' => '단순문의', 'as' => 'A/S', 'troubleshoot' => '문제 해결'];
        $stageL = ['consulting' => '상담', 'equipment' => '장비파악', 'proposal' => '일정제안', 'estimate' => '견적/계약', 'payment' => '결제/예약', 'visit' => '세팅', 'as' => 'AS', 'done' => '완료', 'cancelled' => '취소'];
        $consultL = ['kakao' => '카카오톡', 'phone' => '전화', 'visit' => '내방상담', 'field' => '현장답사'];
        $statusL = ['created' => '작성중', 'editing' => '수정중', 'completed' => '완료', 'paid' => '결제완료', 'hold' => '보류'];
        $colorL = ['gold' => '방문의뢰', 'teal' => '원격/방송룸', 'blue' => '사내업무', 'red' => '휴가/개인', 'green' => '촬영/스튜디오', 'purple' => '미팅/내방'];
        $resultL = ['in_progress' => '진행중', 'waiting' => '대기', 'valid' => '유효', 'invalid' => '무효', 'done' => '완료'];

        $spreadsheet = new Spreadsheet;
        $bold = fn ($sheet, $range) => $sheet->getStyle($range)->getFont()->setBold(true);
        $auto = fn ($sheet, $cols) => collect($cols)->each(fn ($c) => $sheet->getColumnDimension($c)->setAutoSize(true));

        // ── 핵심 지표 계산 ──

        // 1. 재상담 수 (기간 이전에 등록된 의뢰자가 기간 내에 상담한 건수 중 2회차 이상)
        $consultationsInPeriod = Consultation::whereBetween('consulted_at', [$fromDt, $toDt])->get();
        $clientConsultCounts = $consultationsInPeriod->groupBy(fn ($c) => $c->client_id ?? 0)->map->count();
        $reConsultCount = $clientConsultCounts->filter(fn ($cnt) => $cnt >= 2)->sum(fn ($cnt) => $cnt - 1);
        $existingClientConsults = $consultationsInPeriod->filter(fn ($c) => $c->client_id && Client::where('id', $c->client_id)->where('created_at', '<', $fromDt)->exists())->count();

        // 2. 신규 의뢰자
        $newClients = Client::whereBetween('created_at', [$fromDt, $toDt])->count();

        // 3. 상담 진행 중
        $consultInProgress = Consultation::whereBetween('consulted_at', [$fromDt, $toDt])->where('result', 'in_progress')->count();
        $totalConsults = $consultationsInPeriod->count();

        // 4. 세팅까지 진행된 건수 (stage가 visit, as, done)
        $settingDone = Project::whereBetween('created_at', [$fromDt, $toDt])->whereIn('stage', ['visit', 'as', 'done'])->count();

        // 5. 취소된 프로젝트
        $cancelledProjects = Project::whereBetween('created_at', [$fromDt, $toDt])->where('stage', 'cancelled')->count();

        // 6. 세팅 후 매출 등록된 건수 (결제완료 견적서)
        $paidEstimates = Estimate::where('status', 'paid')->whereBetween('created_at', [$fromDt, $toDt]);
        $paidCount = $paidEstimates->count();

        // 7. 매출 분류 (세팅비 = service_total, 장비판매 = product_total)
        $allPaidEstimates = Estimate::where('status', 'paid')->whereBetween('created_at', [$fromDt, $toDt])->get();
        $revenueService = $allPaidEstimates->sum('service_total');
        $revenueProduct = $allPaidEstimates->sum('product_total');
        $revenueTotal = $allPaidEstimates->sum('total_amount');

        // 기타 보조 지표
        $newProjects = Project::whereBetween('created_at', [$fromDt, $toDt])->count();
        $visitProjects = Project::whereBetween('created_at', [$fromDt, $toDt])->where('project_type', 'visit')->count();
        $remoteProjects = Project::whereBetween('created_at', [$fromDt, $toDt])->where('project_type', 'remote')->count();

        // Sheet 1: 핵심 KPI 요약
        $s1 = $spreadsheet->getActiveSheet();
        $s1->setTitle('KPI 요약');
        $s1->fromArray(['닥터고블린 오피스 — 기간 보고서'], null, 'A1');
        $s1->mergeCells('A1:D1');
        $s1->fromArray(["조회 기간: {$from} ~ {$to}"], null, 'A2');
        $s1->mergeCells('A2:D2');
        $bold($s1, 'A1');

        // 마케팅 → 매출 파이프라인
        $s1->fromArray(['구분', '지표', '건수/금액', '비고'], null, 'A4');
        $bold($s1, 'A4:D4');

        $kpiRows = [
            ['마케팅', '신규 등록 의뢰자', $newClients, '기간 내 새로 등록된 의뢰자'],
            ['마케팅', '기존 의뢰자 재상담 수', $reConsultCount, '같은 의뢰자의 2번째 이상 상담'],
            ['마케팅', '기존 의뢰자 상담 건수', $existingClientConsults, '기간 전에 등록된 의뢰자의 기간 내 상담'],
            ['', '', '', ''],
            ['상담', '총 상담 건수', $totalConsults, ''],
            ['상담', '상담 진행 중', $consultInProgress, '아직 완료되지 않은 상담'],
            ['상담', '상담 완료', $consultationsInPeriod->where('result', 'done')->count(), ''],
            ['', '', '', ''],
            ['프로젝트', '신규 프로젝트', $newProjects, "방문 {$visitProjects} / 원격 {$remoteProjects}"],
            ['프로젝트', '세팅 완료 (visit 이상)', $settingDone, '세팅·AS·완료 단계에 도달한 건'],
            ['프로젝트', '취소된 프로젝트', $cancelledProjects, ''],
            ['프로젝트', '전환율 (세팅/신규)', $newProjects > 0 ? round($settingDone / $newProjects * 100, 1).'%' : '-', '프로젝트 → 세팅 전환율'],
            ['', '', '', ''],
            ['매출', '결제 완료 건수', $paidCount, ''],
            ['매출', '총 매출', number_format($revenueTotal).'원', ''],
            ['매출', '└ 세팅비', number_format($revenueService).'원', 'service_items 합계'],
            ['매출', '└ 장비판매', number_format($revenueProduct).'원', 'product_items 합계'],
            ['매출', '건당 평균 매출', $paidCount > 0 ? number_format((int) ($revenueTotal / $paidCount)).'원' : '-', ''],
            ['', '', '', ''],
            ['누적', '총 의뢰자 (기간 종료 시점)', Client::where('created_at', '<=', $toDt)->count(), ''],
            ['누적', '총 프로젝트', Project::where('created_at', '<=', $toDt)->count(), ''],
        ];
        foreach ($kpiRows as $i => $r) {
            $s1->fromArray($r, null, 'A'.($i + 5));
        }
        // 구분 열 색상 강조
        foreach ([5, 6, 7, 9, 10, 11, 13, 14, 15, 18, 19, 20, 24, 25] as $r) {
            if ($r <= count($kpiRows) + 4) {
                $s1->getStyle("A{$r}")->getFont()->setBold(true);
            }
        }
        $auto($s1, ['A', 'B', 'C', 'D']);

        // Sheet 2: 일별 추이
        $s2 = $spreadsheet->createSheet();
        $s2->setTitle('일별 추이');
        $s2->fromArray(['날짜', '신규 의뢰자', '신규 프로젝트', '상담', '일정', '방문 일정', '원격 일정'], null, 'A1');
        $bold($s2, 'A1:G1');
        $row = 2;
        $cur = Carbon::parse($from);
        $endDate = Carbon::parse($to);
        while ($cur->lte($endDate)) {
            $ds = $cur->format('Y-m-d');
            $de = $ds.' 23:59:59';
            $s2->fromArray([
                $ds,
                Client::whereBetween('created_at', [$ds, $de])->count(),
                Project::whereBetween('created_at', [$ds, $de])->count(),
                Consultation::where('consulted_at', '>=', $ds)->where('consulted_at', '<=', $de)->count(),
                Schedule::where('start_date', $ds)->count(),
                Schedule::where('start_date', $ds)->where('color', 'gold')->count(),
                Schedule::where('start_date', $ds)->where('color', 'teal')->count(),
            ], null, "A{$row}");
            $row++;
            $cur->addDay();
        }
        $auto($s2, ['A', 'B', 'C', 'D', 'E', 'F', 'G']);
        $dailyRowCount = $row - 1;

        // 일별 추이 차트 (라인)
        if ($dailyRowCount > 1) {
            $dailyLabels = [new DataSeriesValues('String', "'일별 추이'!\$A\$2:\$A\${$dailyRowCount}", null, $dailyRowCount - 1)];
            $dailySeries = [
                new DataSeriesValues('Number', "'일별 추이'!\$B\$2:\$B\${$dailyRowCount}", "'일별 추이'!\$B\$1", $dailyRowCount - 1),
                new DataSeriesValues('Number', "'일별 추이'!\$C\$2:\$C\${$dailyRowCount}", "'일별 추이'!\$C\$1", $dailyRowCount - 1),
                new DataSeriesValues('Number', "'일별 추이'!\$D\$2:\$D\${$dailyRowCount}", "'일별 추이'!\$D\$1", $dailyRowCount - 1),
            ];
            $dailyDS = new DataSeries(DataSeries::TYPE_LINECHART, null, range(0, count($dailySeries) - 1), $dailyLabels, $dailyLabels, $dailySeries);
            $dailyChart = new Chart('DailyTrend', new Title('일별 신규 등록 추이'), new Legend(Legend::POSITION_BOTTOM), new PlotArea(null, [$dailyDS]));
            $dailyChart->setTopLeftPosition('I1');
            $dailyChart->setBottomRightPosition('R20');
            $s2->addChart($dailyChart);
        }

        // KPI 요약에 매출 분류 파이차트
        $chartDataRow = count($kpiRows) + 6;
        $s1->fromArray(['매출 구성', '금액'], null, 'F4');
        $s1->fromArray(['세팅비', $revenueService], null, 'F5');
        $s1->fromArray(['장비판매', $revenueProduct], null, 'F6');
        $bold($s1, 'F4:G4');

        $pieLabels = [new DataSeriesValues('String', "'KPI 요약'!\$F\$5:\$F\$6", null, 2)];
        $pieValues = [new DataSeriesValues('Number', "'KPI 요약'!\$G\$5:\$G\$6", "'KPI 요약'!\$G\$4", 2)];
        $pieDS = new DataSeries(DataSeries::TYPE_PIECHART, null, [0], $pieLabels, $pieLabels, $pieValues);
        $pieChart = new Chart('RevenueBreakdown', new Title('매출 구성 (세팅비 vs 장비판매)'), new Legend(Legend::POSITION_BOTTOM), new PlotArea(null, [$pieDS]));
        $pieChart->setTopLeftPosition('F8');
        $pieChart->setBottomRightPosition('K22');
        $s1->addChart($pieChart);

        // KPI 요약에 파이프라인 바차트
        $s1->fromArray(['파이프라인', '건수'], null, 'F24');
        $s1->fromArray(['신규 의뢰자', $newClients], null, 'F25');
        $s1->fromArray(['총 상담', $totalConsults], null, 'F26');
        $s1->fromArray(['신규 프로젝트', $newProjects], null, 'F27');
        $s1->fromArray(['세팅 완료', $settingDone], null, 'F28');
        $s1->fromArray(['결제 완료', $paidCount], null, 'F29');
        $bold($s1, 'F24:G24');

        $barLabels = [new DataSeriesValues('String', "'KPI 요약'!\$F\$25:\$F\$29", null, 5)];
        $barValues = [new DataSeriesValues('Number', "'KPI 요약'!\$G\$25:\$G\$29", "'KPI 요약'!\$G\$24", 5)];
        $barDS = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_STANDARD, [0], $barLabels, $barLabels, $barValues);
        $barDS->setPlotDirection(DataSeries::DIRECTION_HORIZONTAL);
        $barChart = new Chart('Pipeline', new Title('마케팅 → 매출 파이프라인'), new Legend(Legend::POSITION_BOTTOM), new PlotArea(null, [$barDS]));
        $barChart->setTopLeftPosition('F31');
        $barChart->setBottomRightPosition('K45');
        $s1->addChart($barChart);

        // Sheet 3: 의뢰자 목록
        $s3 = $spreadsheet->createSheet();
        $s3->setTitle('의뢰자');
        $s3->fromArray(['이름', '닉네임', '전화번호', '등급', '소속', '주소', '등록일'], null, 'A1');
        $bold($s3, 'A1:G1');
        $row = 2;
        Client::whereBetween('created_at', [$fromDt, $toDt])->orderByDesc('created_at')->chunk(200, function ($items) use ($s3, &$row, $gradeL) {
            foreach ($items as $c) {
                $s3->fromArray([$c->name, $c->nickname, $c->phone, $gradeL[$c->grade] ?? $c->grade, $c->affiliation, $c->address, $c->created_at->format('Y.m.d')], null, "A{$row}");
                $row++;
            }
        });

        // Sheet 4: 프로젝트 목록
        $s4 = $spreadsheet->createSheet();
        $s4->setTitle('프로젝트');
        $s4->fromArray(['프로젝트명', '의뢰자', '유형', '단계', '상태', '등록일'], null, 'A1');
        $bold($s4, 'A1:F1');
        $row = 2;
        Project::with('client')->whereBetween('created_at', [$fromDt, $toDt])->orderByDesc('created_at')->chunk(200, function ($items) use ($s4, &$row, $typeL, $stageL) {
            foreach ($items as $p) {
                $s4->fromArray([$p->name, $p->client?->name, $typeL[$p->project_type] ?? $p->project_type, $stageL[$p->stage] ?? $p->stage, $p->status, $p->created_at->format('Y.m.d')], null, "A{$row}");
                $row++;
            }
        });

        // Sheet 5: 상담 이력
        $s5 = $spreadsheet->createSheet();
        $s5->setTitle('상담 이력');
        $s5->fromArray(['의뢰자', '유형', '진행상황', '내용', '담당자', '날짜'], null, 'A1');
        $bold($s5, 'A1:F1');
        $row = 2;
        Consultation::with('client', 'consultant')->whereBetween('consulted_at', [$fromDt, $toDt])->orderByDesc('consulted_at')->chunk(200, function ($items) use ($s5, &$row, $consultL, $resultL) {
            foreach ($items as $c) {
                $s5->fromArray([$c->client?->name, $consultL[$c->consult_type] ?? $c->consult_type, $resultL[$c->result] ?? $c->result, $c->content, $c->consultant?->display_name, $c->consulted_at->format('Y.m.d')], null, "A{$row}");
                $row++;
            }
        });

        // Sheet 6: 견적서
        $s6 = $spreadsheet->createSheet();
        $s6->setTitle('견적서');
        $s6->fromArray(['#', '의뢰자', '상태', '금액', '작성자', '등록일'], null, 'A1');
        $bold($s6, 'A1:F1');
        $row = 2;
        Estimate::with('creator')->whereBetween('created_at', [$fromDt, $toDt])->orderByDesc('created_at')->chunk(200, function ($items) use ($s6, &$row, $statusL) {
            foreach ($items as $e) {
                $s6->fromArray([$e->id, $e->client_nickname ?: $e->client_name, $statusL[$e->status] ?? $e->status, $e->total_amount ?? 0, $e->creator?->display_name, $e->created_at->format('Y.m.d')], null, "A{$row}");
                $row++;
            }
        });

        // Sheet 7: 일정
        $s7 = $spreadsheet->createSheet();
        $s7->setTitle('일정');
        $s7->fromArray(['제목', '유형', '의뢰자', '날짜', '시간', '장소'], null, 'A1');
        $bold($s7, 'A1:F1');
        $row = 2;
        Schedule::where('start_date', '>=', $from)->where('start_date', '<=', $to)->orderBy('start_date')->chunk(200, function ($items) use ($s7, &$row, $colorL) {
            foreach ($items as $s) {
                $s7->fromArray([$s->title, $colorL[$s->color] ?? $s->color, $s->client_name, $s->start_date?->format('Y-m-d'), $s->start_time ? substr($s->start_time, 0, 5) : '종일', $s->location], null, "A{$row}");
                $row++;
            }
        });

        $filename = "drgo-report-{$from}-{$to}.xlsx";

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setIncludeCharts(true);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
