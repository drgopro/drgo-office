<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'clientTotal', 'clientThisMonth', 'clientByGrade',
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

    public function exportExcel(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;

        // Sheet 1: 월별 추이
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('월별 추이');
        $headers = ['월', '신규 의뢰자', '누적 의뢰자', '신규 프로젝트', '방문 프로젝트', '원격 프로젝트', '상담 건수', '방문 일정', '원격 일정', '견적 건수', '견적 금액', '결제 금액(매출)', '총 일정 수'];
        $sheet->fromArray($headers, null, 'A1');
        $row = 2;
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $start = $m->copy()->startOfMonth();
            $end = $m->copy()->endOfMonth();
            $sd = $start->format('Y-m-d');
            $ed = $end->format('Y-m-d');
            $sheet->fromArray([
                $m->format('Y.m'),
                Client::whereBetween('created_at', [$start, $end])->count(),
                Client::where('created_at', '<=', $end)->count(),
                Project::whereBetween('created_at', [$start, $end])->count(),
                Project::whereBetween('created_at', [$start, $end])->where('project_type', 'visit')->count(),
                Project::whereBetween('created_at', [$start, $end])->where('project_type', 'remote')->count(),
                Consultation::whereBetween('consulted_at', [$start, $end])->count(),
                Schedule::where('start_date', '>=', $sd)->where('start_date', '<=', $ed)->where('color', 'gold')->count(),
                Schedule::where('start_date', '>=', $sd)->where('start_date', '<=', $ed)->where('color', 'teal')->count(),
                Estimate::whereBetween('created_at', [$start, $end])->count(),
                (int) Estimate::whereBetween('created_at', [$start, $end])->sum('total_amount'),
                (int) Estimate::where('status', 'paid')->whereBetween('created_at', [$start, $end])->sum('total_amount'),
                Schedule::where('start_date', '>=', $sd)->where('start_date', '<=', $ed)->count(),
            ], null, "A{$row}");
            $row++;
        }
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Sheet 2: 의뢰자 목록
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('의뢰자');
        $gradeL = ['normal' => '일반', 'vip' => 'VIP', 'rental' => '렌탈'];
        $sheet2->fromArray(['이름', '닉네임', '전화번호', '등급', '소속', '주소', '등록일'], null, 'A1');
        $row = 2;
        Client::orderByDesc('created_at')->chunk(200, function ($clients) use ($sheet2, &$row, $gradeL) {
            foreach ($clients as $c) {
                $sheet2->fromArray([$c->name, $c->nickname, $c->phone, $gradeL[$c->grade] ?? $c->grade, $c->affiliation, $c->address, $c->created_at->format('Y.m.d')], null, "A{$row}");
                $row++;
            }
        });
        $sheet2->getStyle('A1:G1')->getFont()->setBold(true);

        // Sheet 3: 프로젝트 목록
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('프로젝트');
        $typeL = ['visit' => '방문세팅', 'remote' => '원격세팅', 'design' => '디자인', 'inquiry' => '단순문의', 'as' => 'A/S', 'troubleshoot' => '문제 해결'];
        $stageL = ['consulting' => '상담', 'equipment' => '장비파악', 'proposal' => '일정제안', 'estimate' => '견적/계약', 'payment' => '결제/예약', 'visit' => '세팅', 'as' => 'AS', 'done' => '완료', 'cancelled' => '취소'];
        $sheet3->fromArray(['프로젝트명', '의뢰자', '유형', '단계', '상태', '등록일'], null, 'A1');
        $row = 2;
        Project::with('client')->orderByDesc('created_at')->chunk(200, function ($projects) use ($sheet3, &$row, $typeL, $stageL) {
            foreach ($projects as $p) {
                $sheet3->fromArray([$p->name, $p->client?->name, $typeL[$p->project_type] ?? $p->project_type, $stageL[$p->stage] ?? $p->stage, $p->status, $p->created_at->format('Y.m.d')], null, "A{$row}");
                $row++;
            }
        });
        $sheet3->getStyle('A1:F1')->getFont()->setBold(true);

        $filename = 'drgo-statistics-'.now()->format('Y-m-d').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
