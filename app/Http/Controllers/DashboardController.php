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

        // 월별 추이 (최근 6개월) — 신규/누적 분리
        $monthlyClients = [];
        $monthlyProjects = [];
        $monthlyConsults = [];
        $monthlyEstimates = [];
        $monthlySchedules = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $label = $m->format('Y.m');
            $start = $m->copy()->startOfMonth();
            $end = $m->copy()->endOfMonth();

            $newClients = Client::whereBetween('created_at', [$start, $end])->count();
            $newProjects = Project::whereBetween('created_at', [$start, $end])->count();
            $newConsults = Consultation::whereBetween('consulted_at', [$start, $end])->count();
            $estAmount = (int) Estimate::whereBetween('created_at', [$start, $end])->sum('total_amount');
            $estPaid = (int) Estimate::where('status', 'paid')->whereBetween('created_at', [$start, $end])->sum('total_amount');
            $estCount = Estimate::whereBetween('created_at', [$start, $end])->count();
            $schedCount = Schedule::where('start_date', '>=', $start->format('Y-m-d'))->where('start_date', '<=', $end->format('Y-m-d'))->count();
            $cumulClients = Client::where('created_at', '<=', $end)->count();

            $monthlyClients[] = ['label' => $label, 'value' => $newClients, 'cumul' => $cumulClients];
            $monthlyProjects[] = ['label' => $label, 'value' => $newProjects];
            $monthlyConsults[] = ['label' => $label, 'value' => $newConsults];
            $monthlyEstimates[] = ['label' => $label, 'value' => $estAmount, 'paid' => $estPaid, 'count' => $estCount];
            $monthlySchedules[] = ['label' => $label, 'value' => $schedCount];
        }

        // 일정 통계
        $scheduleThisMonth = Schedule::where('start_date', '>=', now()->startOfMonth())->where('start_date', '<=', now()->endOfMonth())->count();
        $scheduleByColor = Schedule::select('color', DB::raw('count(*) as cnt'))->groupBy('color')->pluck('cnt', 'color');

        return view('dashboard', compact(
            'clientTotal', 'clientThisMonth', 'clientByGrade',
            'projectTotal', 'projectActive', 'projectByStage', 'projectByType',
            'estimateTotal', 'estimateByStatus', 'estimateTotalAmount', 'estimatePaidAmount',
            'consultTotal', 'consultThisMonth', 'consultByType',
            'monthlyClients', 'monthlyProjects', 'monthlyConsults', 'monthlyEstimates', 'monthlySchedules',
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

        return match ($type) {
            'clients' => response()->json(
                Client::orderByDesc('created_at')->limit(100)->get()->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name, 'nickname' => $c->nickname, 'phone' => $c->phone,
                    'grade' => $gradeL[$c->grade] ?? $c->grade, 'created_at' => $c->created_at->format('Y.m.d'),
                    'url' => '/clients?open='.$c->id,
                ])
            ),
            'projects' => response()->json(
                Project::with('client')->orderByDesc('created_at')->limit(100)->get()->map(fn ($p) => [
                    'id' => $p->id, 'name' => $p->name, 'client' => $p->client?->name,
                    'type' => $typeL[$p->project_type] ?? $p->project_type,
                    'stage' => $stageL[$p->stage] ?? $p->stage,
                    'created_at' => $p->created_at->format('Y.m.d'),
                    'url' => '/projects/'.$p->id,
                ])
            ),
            'consultations' => response()->json(
                Consultation::with('client', 'consultant')->orderByDesc('consulted_at')->limit(100)->get()->map(fn ($c) => [
                    'id' => $c->id, 'client' => $c->client?->name, 'type' => $consultL[$c->consult_type] ?? $c->consult_type,
                    'result' => $c->result, 'content' => \Str::limit($c->content, 60),
                    'consultant' => $c->consultant?->display_name, 'date' => $c->consulted_at->format('Y.m.d'),
                ])
            ),
            'estimates' => response()->json(
                Estimate::with('creator')->orderByDesc('created_at')->limit(100)->get()->map(fn ($e) => [
                    'id' => $e->id, 'client' => $e->client_nickname ?: $e->client_name,
                    'status' => $statusL[$e->status] ?? $e->status, 'total' => number_format($e->total_amount ?? 0),
                    'creator' => $e->creator?->display_name, 'created_at' => $e->created_at->format('Y.m.d'),
                    'url' => '/estimates/'.$e->id.'/edit',
                ])
            ),
            'schedules' => response()->json(
                Schedule::where('start_date', '>=', now()->startOfMonth())->orderBy('start_date')->limit(100)->get()->map(fn ($s) => [
                    'id' => $s->id, 'title' => $s->title, 'color' => $colorL[$s->color] ?? $s->color,
                    'client' => $s->client_name, 'date' => $s->start_date,
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
        $sheet->fromArray(['월', '신규 의뢰자', '누적 의뢰자', '신규 프로젝트', '상담 건수', '견적 건수', '견적 금액', '결제 금액', '일정 수'], null, 'A1');
        $row = 2;
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $start = $m->copy()->startOfMonth();
            $end = $m->copy()->endOfMonth();
            $sheet->fromArray([
                $m->format('Y.m'),
                Client::whereBetween('created_at', [$start, $end])->count(),
                Client::where('created_at', '<=', $end)->count(),
                Project::whereBetween('created_at', [$start, $end])->count(),
                Consultation::whereBetween('consulted_at', [$start, $end])->count(),
                Estimate::whereBetween('created_at', [$start, $end])->count(),
                (int) Estimate::whereBetween('created_at', [$start, $end])->sum('total_amount'),
                (int) Estimate::where('status', 'paid')->whereBetween('created_at', [$start, $end])->sum('total_amount'),
                Schedule::where('start_date', '>=', $start->format('Y-m-d'))->where('start_date', '<=', $end->format('Y-m-d'))->count(),
            ], null, "A{$row}");
            $row++;
        }
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        foreach (range('A', 'I') as $col) {
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
