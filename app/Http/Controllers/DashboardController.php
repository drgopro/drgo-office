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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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
                'est_amount' => (int) Estimate::whereBetween('created_at', [$ds, $de])->sum('total_amount'),
                'est_paid' => (int) Estimate::where('status', 'paid')->whereBetween('created_at', [$ds, $de])->sum('total_amount'),
                'est_count' => Estimate::whereBetween('created_at', [$ds, $de])->count(),
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
                'est_amount' => (int) Estimate::whereBetween('created_at', [$ys, $ye])->sum('total_amount'),
                'est_paid' => (int) Estimate::where('status', 'paid')->whereBetween('created_at', [$ys, $ye])->sum('total_amount'),
                'est_count' => Estimate::whereBetween('created_at', [$ys, $ye])->count(),
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

        // ── 스타일 헬퍼 ──
        $consultDone = $consultationsInPeriod->where('result', 'done')->count();
        $conversionRate = $newProjects > 0 ? round($settingDone / $newProjects * 100, 1).'%' : '—';
        $avgRevenue = $paidCount > 0 ? number_format((int) ($revenueTotal / $paidCount)).'원' : '—';
        $cumulClients = Client::where('created_at', '<=', $toDt)->count();
        $cumulProjects = Project::where('created_at', '<=', $toDt)->count();

        $darkBlue = '1E3A5F';
        $accentBlue = '2563EB';
        $headerBg = 'F0F4FF';
        $stripeBg = 'F8FAFC';
        $white = 'FFFFFF';

        $applyStyle = function ($sheet, $range, $bgColor, $fontColor = '333333', $isBold = false, $fontSize = 10) {
            $style = $sheet->getStyle($range);
            $style->getFont()->setSize($fontSize)->setColor(new Color($fontColor));
            if ($isBold) {
                $style->getFont()->setBold(true);
            }
            if ($bgColor) {
                $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bgColor);
            }
            $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        };

        $sectionHeader = function ($sheet, $row, $title, $cols = 'A:D') use ($accentBlue, $applyStyle) {
            $colEnd = explode(':', $cols)[1] ?? 'D';
            $sheet->mergeCells("A{$row}:{$colEnd}{$row}");
            $sheet->setCellValue("A{$row}", $title);
            $applyStyle($sheet, "A{$row}:{$colEnd}{$row}", $accentBlue, 'FFFFFF', true, 11);
        };

        $subHeader = function ($sheet, $row, $headers) use ($headerBg, $applyStyle) {
            foreach ($headers as $col => $val) {
                $c = chr(65 + $col);
                $sheet->setCellValue("{$c}{$row}", $val);
            }
            $endCol = chr(65 + count($headers) - 1);
            $applyStyle($sheet, "A{$row}:{$endCol}{$row}", $headerBg, '333333', true);
        };

        $dataRow = function ($sheet, $row, $values, $isStripe = false) use ($stripeBg, $white) {
            $bg = $isStripe ? $stripeBg : $white;
            foreach ($values as $col => $val) {
                $c = chr(65 + $col);
                $sheet->setCellValue("{$c}{$row}", $val);
            }
            $endCol = chr(65 + count($values) - 1);
            $sheet->getStyle("A{$row}:{$endCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
        };

        // Sheet 1: 기간 보고서
        $s1 = $spreadsheet->getActiveSheet();
        $s1->setTitle('기간 보고서');
        $s1->getDefaultColumnDimension()->setWidth(16);
        $s1->getColumnDimension('A')->setWidth(14);
        $s1->getColumnDimension('B')->setWidth(28);
        $s1->getColumnDimension('C')->setWidth(16);
        $s1->getColumnDimension('D')->setWidth(32);
        $s1->getColumnDimension('F')->setWidth(22);
        $s1->getColumnDimension('G')->setWidth(4);
        $s1->getColumnDimension('I')->setWidth(16);
        $s1->getColumnDimension('J')->setWidth(12);

        // 타이틀
        $s1->mergeCells('A1:J1');
        $s1->setCellValue('A1', '닥터고블린 오피스  —  기간 보고서');
        $applyStyle($s1, 'A1:J1', $darkBlue, 'FFFFFF', true, 14);
        $s1->getRowDimension(1)->setRowHeight(36);
        $s1->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);

        $s1->mergeCells('A2:J2');
        $s1->setCellValue('A2', "조회 기간: {$from} ~ {$to}");
        $applyStyle($s1, 'A2:J2', $darkBlue, 'BBCCE0', false, 10);
        $s1->getRowDimension(2)->setRowHeight(22);

        $r = 4;

        // ── 마케팅 섹션 ──
        $sectionHeader($s1, $r, '📢  마케팅');
        // 우측: 핵심 지표 카드
        $s1->setCellValue("F{$r}", '🎯  핵심 지표');
        $applyStyle($s1, "F{$r}", $accentBlue, 'FFFFFF', true, 11);
        // 차트 데이터
        $s1->setCellValue("I{$r}", '차트 데이터');
        $applyStyle($s1, "I{$r}:J{$r}", $accentBlue, 'FFFFFF', true, 10);
        $r++;

        $subHeader($s1, $r, ['구분', '지표', '건수/금액', '비고']);
        $s1->setCellValue("F{$r}", '신규 의뢰자');
        $applyStyle($s1, "F{$r}", $white, '666666', false);
        $subHeader($s1, $r, array_merge(array_fill(0, 4, ''), ['', '', '', '', '단계', '건수']));
        $applyStyle($s1, "I{$r}:J{$r}", $stripeBg, '666666', true);
        $r++;

        // 마케팅 데이터
        $dataRow($s1, $r, ['마케팅', '신규 등록 의뢰자', number_format($newClients), '기간 내 새로 등록된 의뢰자'], true);
        $s1->setCellValue("F{$r}", number_format($newClients).'건');
        $applyStyle($s1, "F{$r}", $white, '1E3A5F', true, 18);
        $s1->setCellValue("I{$r}", '신규 의뢰자');
        $s1->setCellValue("J{$r}", $newClients);
        $applyStyle($s1, "I{$r}:J{$r}", $stripeBg, '333333', false);
        $r++;

        $dataRow($s1, $r, ['마케팅', '기존 의뢰자 재상담 수', number_format($reConsultCount), '같은 의뢰자의 2번째 이상 상담']);
        $s1->setCellValue("F{$r}", '이번 기간 신규 등록');
        $applyStyle($s1, "F{$r}", $white, '999999', false, 9);
        $s1->setCellValue("I{$r}", '총 상담');
        $s1->setCellValue("J{$r}", $totalConsults);
        $applyStyle($s1, "I{$r}:J{$r}", $stripeBg, '333333', false);
        $r++;

        $dataRow($s1, $r, ['마케팅', '기존 의뢰자 상담 건수', $existingClientConsults > 0 ? number_format($existingClientConsults) : '—', '기간 전 등록된 의뢰자의 기간 내 상담'], true);
        $s1->setCellValue("I{$r}", '신규 프로젝트');
        $s1->setCellValue("J{$r}", $newProjects);
        $applyStyle($s1, "I{$r}:J{$r}", $stripeBg, '333333', false);
        $r++;

        // 총 상담 카드
        $s1->setCellValue("F{$r}", '총 상담 건수');
        $applyStyle($s1, "F{$r}", $white, '666666', false);
        $s1->setCellValue("I{$r}", '세팅 완료');
        $s1->setCellValue("J{$r}", $settingDone);
        $applyStyle($s1, "I{$r}:J{$r}", $stripeBg, '333333', false);
        $r++;

        // ── 상담 섹션 ──
        $sectionHeader($s1, $r, '💬  상담');
        $s1->setCellValue("F{$r}", number_format($totalConsults).'건');
        $applyStyle($s1, "F{$r}", $white, '1E3A5F', true, 18);
        $s1->setCellValue("I{$r}", '결제 완료');
        $s1->setCellValue("J{$r}", $paidCount);
        $applyStyle($s1, "I{$r}:J{$r}", $stripeBg, '333333', false);
        $r++;

        $subHeader($s1, $r, ['구분', '지표', '건수/금액', '비고']);
        $s1->setCellValue("F{$r}", "진행중 {$consultInProgress}  |  완료 {$consultDone}");
        $applyStyle($s1, "F{$r}", $white, '666666', false, 9);
        $r++;

        $dataRow($s1, $r, ['상담', '총 상담 건수', number_format($totalConsults), ''], true);
        $s1->setCellValue("I{$r}", '구분');
        $s1->setCellValue("J{$r}", '금액');
        $applyStyle($s1, "I{$r}:J{$r}", $stripeBg, '666666', true);
        $r++;

        $dataRow($s1, $r, ['상담', '상담 진행중', number_format($consultInProgress), '아직 완료되지 않은 상담']);
        $s1->setCellValue("F{$r}", '신규 프로젝트');
        $applyStyle($s1, "F{$r}", $white, '666666', false);
        $s1->setCellValue("I{$r}", '세팅비');
        $s1->setCellValue("J{$r}", $revenueService);
        $applyStyle($s1, "I{$r}:J{$r}", $stripeBg, '333333', false);
        $r++;

        $dataRow($s1, $r, ['상담', '상담 완료', number_format($consultDone), ''], true);
        $s1->setCellValue("F{$r}", number_format($newProjects).'건');
        $applyStyle($s1, "F{$r}", $white, '1E3A5F', true, 18);
        $s1->setCellValue("I{$r}", '장비판매');
        $s1->setCellValue("J{$r}", $revenueProduct);
        $applyStyle($s1, "I{$r}:J{$r}", $stripeBg, '333333', false);
        $r++;

        $s1->setCellValue("F{$r}", "방문 {$visitProjects}  |  원격 {$remoteProjects}");
        $applyStyle($s1, "F{$r}", $white, '666666', false, 9);
        $r++;

        // ── 프로젝트 섹션 ──
        $sectionHeader($s1, $r, '🏗  프로젝트');
        $r++;

        $subHeader($s1, $r, ['구분', '지표', '건수/금액', '비고']);
        $s1->setCellValue("F{$r}", '프로젝트 취소');
        $applyStyle($s1, "F{$r}", $white, '666666', false);
        $r++;

        $dataRow($s1, $r, ['프로젝트', '신규 프로젝트', number_format($newProjects), "방문 {$visitProjects} / 원격 {$remoteProjects}"], true);
        $s1->setCellValue("F{$r}", number_format($cancelledProjects).'건');
        $applyStyle($s1, "F{$r}", $white, '1E3A5F', true, 18);
        $r++;

        $dataRow($s1, $r, ['프로젝트', '세팅 완료 (visit 이상)', $settingDone > 0 ? number_format($settingDone) : '—', '세팅·AS·완료 단계에 도달한 건']);
        $s1->setCellValue("F{$r}", "전환율 {$conversionRate}");
        $applyStyle($s1, "F{$r}", $white, '666666', false, 9);
        $r++;

        $dataRow($s1, $r, ['프로젝트', '취소된 프로젝트', number_format($cancelledProjects), ''], true);
        $r++;

        $dataRow($s1, $r, ['프로젝트', '전환율 (세팅/신규)', $conversionRate, '프로젝트 → 세팅 전환율']);
        $r++;

        // 매출 카드
        $s1->setCellValue("F{$r}", '총 매출');
        $applyStyle($s1, "F{$r}", $white, '666666', false);
        $r++;

        // ── 매출 섹션 ──
        $sectionHeader($s1, $r, '💰  매출');
        $s1->setCellValue("F{$r}", number_format($revenueTotal).'원');
        $applyStyle($s1, "F{$r}", $white, '1E3A5F', true, 18);
        $r++;

        $subHeader($s1, $r, ['구분', '지표', '금액', '비고']);
        $s1->setCellValue("F{$r}", '세팅비 '.number_format($revenueService).'  |  장비판매 '.number_format($revenueProduct));
        $applyStyle($s1, "F{$r}", $white, '666666', false, 9);
        $r++;

        $dataRow($s1, $r, ['매출', '결제 완료 건수', $paidCount > 0 ? number_format($paidCount) : '—', '']);
        $r++;
        $dataRow($s1, $r, ['매출', '총 매출', number_format($revenueTotal).'원', ''], true);
        $r++;
        $dataRow($s1, $r, ['매출', 'ㄴ 세팅비', number_format($revenueService).'원', 'service_items 합계']);
        $r++;
        $dataRow($s1, $r, ['매출', 'ㄴ 장비판매', number_format($revenueProduct).'원', 'product_items 합계'], true);
        $r++;
        $dataRow($s1, $r, ['매출', '건당 평균 매출', $avgRevenue, '']);
        $r += 2;

        // ── 누적 섹션 ──
        $sectionHeader($s1, $r, '📊  누적');
        $r++;
        $subHeader($s1, $r, ['구분', '지표', '건수', '비고']);
        $r++;
        $dataRow($s1, $r, ['누적', '총 의뢰자 (기간 종료 시점)', number_format($cumulClients), '']);
        $r++;
        $dataRow($s1, $r, ['누적', '총 프로젝트', number_format($cumulProjects), ''], true);

        // 얇은 테두리 적용 (전체 데이터 영역)
        $s1->getStyle("A4:D{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
        $s1->getStyle("F4:F{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');

        $kpiRows = []; // 하위호환

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
            $dailyChart->setTopLeftPosition('I2');
            $dailyChart->setBottomRightPosition('P18');
            $s2->addChart($dailyChart);
        }

        // 차트 — I/J열의 차트 데이터 참조
        $sheetName = '기간 보고서';

        // 파이프라인 바차트 (I6:J10)
        $barLabels = [new DataSeriesValues('String', "'{$sheetName}'!\$I\$6:\$I\$10", null, 5)];
        $barValues = [new DataSeriesValues('Number', "'{$sheetName}'!\$J\$6:\$J\$10", "'{$sheetName}'!\$J\$5", 5)];
        $barDS = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_STANDARD, [0], $barLabels, $barLabels, $barValues);
        $barDS->setPlotDirection(DataSeries::DIRECTION_HORIZONTAL);
        $barChart = new Chart('Pipeline', new Title('마케팅 → 매출 파이프라인'), new Legend(Legend::POSITION_BOTTOM), new PlotArea(null, [$barDS]));
        $barChart->setTopLeftPosition('F36');
        $barChart->setBottomRightPosition('J52');
        $s1->addChart($barChart);

        // 매출 구성 파이차트 (I13:J14)
        $pieLabels = [new DataSeriesValues('String', "'{$sheetName}'!\$I\$13:\$I\$14", null, 2)];
        $pieValues = [new DataSeriesValues('Number', "'{$sheetName}'!\$J\$13:\$J\$14", "'{$sheetName}'!\$J\$12", 2)];
        $pieDS = new DataSeries(DataSeries::TYPE_PIECHART, null, [0], $pieLabels, $pieLabels, $pieValues);
        $pieChart = new Chart('RevenueBreakdown', new Title('매출 구성'), new Legend(Legend::POSITION_BOTTOM), new PlotArea(null, [$pieDS]));
        $pieChart->setTopLeftPosition('F54');
        $pieChart->setBottomRightPosition('J68');
        $s1->addChart($pieChart);

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
