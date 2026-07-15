<?php

namespace App\Http\Controllers;

use App\Models\BroadcastRoomContract;
use App\Models\BroadcastRoomUsage;
use App\Models\CalendarCategory;
use App\Models\Client;
use App\Models\Consultation;
use App\Models\ConsultationType;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectBilling;
use App\Models\ProjectPayment;
use App\Models\RentalContract;
use App\Models\Schedule;
use App\Models\Todo;
use App\Models\Wiki;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        // 캘린더 카테고리 키 — 라벨 기준 조회 (고정 색상 키 하드코딩 방지, 레거시 폴백 포함)
        $visitKeys = CalendarCategory::keysByLabel('방문', ['gold']);
        $remoteKeys = CalendarCategory::keysByLabel('원격', ['teal']);

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
                'schedules_visit' => Schedule::where('start_date', '>=', $sd)->where('start_date', '<=', $ed)->whereIn('color', $visitKeys)->count(),
                'schedules_remote' => Schedule::where('start_date', '>=', $sd)->where('start_date', '<=', $ed)->whereIn('color', $remoteKeys)->count(),
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

        // 규모별 프로젝트 (이번 달 기준) — 컬럼 있을 때만
        $scaleThisMonth = collect();
        $projectByScale = collect();
        if (Schema::hasColumn('projects', 'client_scale')) {
            $mStart = now()->startOfMonth();
            $mEnd = now()->endOfMonth();
            $scaleThisMonth = Project::whereBetween('created_at', [$mStart, $mEnd])
                ->select('client_scale', DB::raw('count(*) as cnt'))
                ->groupBy('client_scale')->pluck('cnt', 'client_scale');
            $projectByScale = Project::select('client_scale', DB::raw('count(*) as cnt'))
                ->whereNotNull('client_scale')
                ->groupBy('client_scale')->pluck('cnt', 'client_scale');
        }

        // 렌탈/방송룸 현황 — 테이블 있을 때만
        $rentalActive = 0;
        $rentalMonthlyRevenue = 0;
        $broadcastActive = 0;
        $broadcastMonthlyRevenue = 0;
        if (Schema::hasTable('rental_contracts')) {
            $rentalActive = RentalContract::where('status', 'active')->count();
            $rentalMonthlyRevenue = RentalContract::where('status', 'active')->sum('monthly_fee');
        }
        if (Schema::hasTable('broadcast_room_contracts')) {
            $broadcastActive = BroadcastRoomContract::where('status', 'active')->count();
            $broadcastMonthlyRevenue = BroadcastRoomContract::where('status', 'active')->sum('monthly_fee');
        }

        // ── 진행 상황 (파이프라인, 실시간 스냅샷) ──
        // 'done'은 project_type에 따라 의미가 다르지만 (상담 완료 / 세팅 완료 / 납품 완료 등)
        // 종착점이 같으므로 하나의 '완료' 카드로 묶어 보여줌
        $pipeline = [
            'consulting' => Project::where('stage', 'consulting')->count(),
            'estimate' => Project::whereIn('stage', ['equipment', 'proposal', 'estimate'])->count(),
            'payment' => Project::where('stage', 'payment')->count(),
            'visit' => Project::where('stage', 'visit')->count(),
            'as' => Project::where('stage', 'as')->count(),
            'done' => Project::where('stage', 'done')->count(),
        ];

        // 최근 상담 대기/진행중 (우선순위가 높은 항목)
        $recentConsults = Consultation::with('client', 'consultant')
            ->whereIn('result', ['in_progress', 'waiting'])
            ->orderByDesc('consulted_at')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'project_id' => $c->project_id,
                'client_id' => $c->client_id,
                'client' => $c->client?->nickname ?: $c->client?->name,
                'result' => $c->result,
                'content' => \Str::limit($c->content, 40),
                'consultant' => $c->consultant?->display_name,
                'date' => $c->consulted_at?->format('m.d'),
            ]);

        // 오늘의 일정 — 오늘과 겹치는 일정 (타인 비공개 제외), 종일 → 시간순
        $today = now()->toDateString();
        $catMap = CalendarCategory::map();
        $catColors = CalendarCategory::colors(); // 대시보드 카테고리 점 색상 — 캘린더 실제 색과 동기화
        $todaySchedules = Schedule::with('assignees:id,name')
            ->whereDate('start_date', '<=', $today)
            ->where(fn ($q) => $q->whereDate('end_date', '>=', $today)->orWhereNull('end_date'))
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('created_by', auth()->id()))
            ->orderByDesc('is_all_day')->orderBy('start_time')
            ->limit(8)->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title ?: '(제목 없음)',
                'time' => $s->is_all_day || ! $s->start_time ? '종일' : substr((string) $s->start_time, 0, 5),
                'time_end' => $s->is_all_day || ! $s->end_time ? null : substr((string) $s->end_time, 0, 5),
                'color' => $s->color,
                'category' => $catMap[$s->color]['label'] ?? $s->color,
                'assignees' => $s->assignees->pluck('name')->implode(', '),
                'completed' => $s->completed_at !== null,
            ]);

        // 주목 프로젝트 — 다가오는 제안/희망/목표/확정 일정 (D-day 오름차순)
        $schedOptLabels = ['suggest' => '제안 일정', 'hope' => '희망 일정', 'target' => '목표 일정', 'confirmed' => '확정 일정'];
        $focusSchedules = Schedule::whereNotNull('sched_opt')
            ->whereIn('sched_opt', array_keys($schedOptLabels))
            ->whereDate('start_date', '>=', $today)
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('created_by', auth()->id()))
            ->orderBy('start_date')->limit(4)->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'dday' => (int) now()->startOfDay()->diffInDays($s->start_date->copy()->startOfDay()),
                'client' => $s->client_name ?: '(의뢰자 미상)',
                'desc' => $s->title ?: '(제목 없음)',
                'date' => $s->start_date->format('m.d'),
                'opt' => $s->sched_opt,
                'opt_label' => $schedOptLabels[$s->sched_opt] ?? $s->sched_opt,
                'project_id' => data_get($s->gold_data, 'project_id'),
            ]);

        // 휴가 — 휴가/개인 카테고리 일정, 오늘부터 D-7까지 (라벨에 '휴가'가 포함된 카테고리, 기본 red)
        $vacationColors = CalendarCategory::keysByLabel('휴가', ['red']);
        $vacationEnd = now()->addDays(7)->toDateString();
        $vacations = Schedule::with('assignees:id,name')
            ->whereIn('color', $vacationColors)
            ->whereDate('start_date', '<=', $vacationEnd)
            ->where(fn ($q) => $q->whereDate('end_date', '>=', $today)
                ->orWhere(fn ($qq) => $qq->whereNull('end_date')->whereDate('start_date', '>=', $today)))
            ->orderBy('start_date')->limit(6)->get()
            ->map(function ($s) use ($today) {
                $start = $s->start_date;
                $end = $s->end_date ?: $start;
                $ongoing = $start->toDateString() <= $today && $end->toDateString() >= $today;

                return [
                    'name' => $s->assignees->pluck('name')->implode(', ') ?: ($s->title ?: '(이름 없음)'),
                    'title' => $s->title,
                    'period' => $start->format('m.d').($end->ne($start) ? ' – '.$end->format('m.d') : ''),
                    'ongoing' => $ongoing,
                    'dday' => $ongoing ? '휴가 중' : 'D-'.max(0, (int) now()->startOfDay()->diffInDays($start->copy()->startOfDay())),
                ];
            });

        // 잔금 미수 — 미입금·부분입금 청구 상위 + 합계
        $outstanding = collect();
        $outstandingTotal = 0;
        $outstandingCount = 0;
        if (auth()->user()->hasPermission('projects.view')) {
            $obs = ProjectBilling::with('project.client')
                ->whereIn('status', ['unpaid', 'partial'])->orderBy('billed_at')->get();
            $outstandingCount = $obs->count();
            $outstandingTotal = $obs->sum(fn ($b) => $b->balance());
            $outstanding = $obs->take(4)->map(fn ($b) => [
                'project_id' => $b->project_id,
                'label' => $b->project?->clientDisplayName() ?: ($b->project?->name ?? '프로젝트 #'.$b->project_id),
                'sub' => trim(($b->project?->name ?? '').' · 청구 '.$b->billed_at?->format('m.d'), ' ·'),
                'balance' => $b->balance(),
            ]);
        }

        // 나의 할 일 — 미완료, 기한 임박·우선순위 순 (우측 카드)
        $myTodoAll = Todo::where('assignee_id', auth()->id())->whereNull('completed_at')
            ->orderByRaw('due_date is null')->orderBy('due_date')
            ->orderByRaw("case priority when 'high' then 0 when 'medium' then 1 else 2 end")
            ->get();
        $myTodoCount = $myTodoAll->count();
        $myTodos = $myTodoAll->take(5)->map(function (Todo $t) {
            $dday = null;
            if ($t->due_date) {
                $diff = (int) now()->startOfDay()->diffInDays($t->due_date->copy()->startOfDay(), false);
                $dday = $diff < 0 ? abs($diff).'일 지남' : ($diff === 0 ? '오늘 마감' : 'D-'.$diff);
            }

            return [
                'id' => $t->id,
                'title' => $t->title,
                'priority' => $t->priority,
                'dday' => $dday,
                'overdue' => $t->due_date && $t->due_date->lt(now()->startOfDay()),
            ];
        });

        // 위키 위젯 — 전체 문서(고정 우선, 최대 5), 최신 등록 문서(최대 3)
        $wikiTotal = Wiki::count();
        $wikiAll = Wiki::orderByDesc('is_pinned')->orderByDesc('updated_at')->limit(5)->get(['id', 'title', 'is_pinned', 'updated_at']);
        $wikiRecent = Wiki::orderByDesc('created_at')->limit(3)->get(['id', 'title', 'created_at']);
        // 공지사항/업데이트 — 최신순 (대시보드 우측 카드: 공지 2건 + 업데이트 1건)
        $wikiNoticeList = Wiki::where('type', 'notice')
            ->orderByDesc('created_at')->limit(2)->get(['id', 'title', 'created_at']);
        $wikiUpdateList = Wiki::where('type', 'update')
            ->orderByDesc('created_at')->limit(1)->get(['id', 'title', 'created_at']);

        return view('dashboard', compact(
            'wikiTotal', 'wikiAll', 'wikiRecent', 'wikiNoticeList', 'wikiUpdateList',
            'todaySchedules', 'focusSchedules', 'outstanding', 'outstandingTotal', 'outstandingCount', 'vacations', 'myTodos', 'myTodoCount', 'catColors',
            'clientTotal', 'clientThisMonth', 'clientByGrade', 'dailyData', 'yearlyData',
            'projectTotal', 'projectActive', 'projectByStage', 'projectByType',
            'estimateTotal', 'estimateByStatus', 'estimateTotalAmount', 'estimatePaidAmount',
            'consultTotal', 'consultThisMonth', 'consultByType',
            'monthlyClients', 'monthlyProjects', 'monthlyConsults', 'monthlyEstimates', 'monthlySchedules',
            'monthlyData', 'r3ProjectTotal', 'r3Visit', 'r3Remote', 'r3Consults', 'r3Clients', 'r3Revenue',
            'scheduleThisMonth', 'scheduleByColor',
            'scaleThisMonth', 'projectByScale',
            'rentalActive', 'rentalMonthlyRevenue', 'broadcastActive', 'broadcastMonthlyRevenue',
            'pipeline', 'recentConsults'
        ));
    }

    public function detail(Request $request, string $type)
    {
        $gradeL = ['normal' => '일반', 'vip' => 'VIP', 'rental' => '렌탈'];
        $stageL = ['consulting' => '상담', 'equipment' => '장비파악', 'proposal' => '일정제안', 'estimate' => '견적/계약', 'payment' => '결제/예약', 'visit' => '세팅', 'as' => 'AS', 'done' => '완료', 'cancelled' => '취소'];
        $typeL = ['visit' => '방문세팅', 'remote' => '원격세팅', 'design' => '디자인', 'inquiry' => '단순문의', 'as' => 'A/S', 'troubleshoot' => '문제 해결'];
        $consultL = ['kakao' => '카카오톡', 'phone' => '전화', 'visit' => '내방상담', 'field' => '현장답사'];
        $statusL = ['created' => '작성중', 'editing' => '수정중', 'completed' => '완료', 'paid' => '결제완료', 'hold' => '보류'];
        $colorL = CalendarCategory::labels();
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
        $colorL = CalendarCategory::labels();
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
        $revenueEstimates = (int) $allPaidEstimates->sum('total_amount');

        // 단순 결제 매출 (project_payments에서 estimate 미연결분 — 환불/취소는 음수로 자동 차감)
        $revenuePaymentOnly = 0;
        $paymentOnlyCount = 0;
        if (Schema::hasTable('project_payments')) {
            $revenuePaymentOnly = (int) ProjectPayment::whereBetween('created_at', [$fromDt, $toDt])
                ->whereNull('estimate_id')
                ->sum('amount');
            $paymentOnlyCount = (int) ProjectPayment::whereBetween('created_at', [$fromDt, $toDt])
                ->whereNull('estimate_id')
                ->where('type', 'charge')
                ->count();
        }
        $revenueTotal = $revenueEstimates + $revenuePaymentOnly;

        // 기타 보조 지표
        $newProjects = Project::whereBetween('created_at', [$fromDt, $toDt])->count();
        $visitProjects = Project::whereBetween('created_at', [$fromDt, $toDt])->where('project_type', 'visit')->count();
        $remoteProjects = Project::whereBetween('created_at', [$fromDt, $toDt])->where('project_type', 'remote')->count();

        // ── 스타일 헬퍼 ──
        $consultDone = $consultationsInPeriod->where('result', 'done')->count();
        $conversionRate = $newProjects > 0 ? round($settingDone / $newProjects * 100, 1).'%' : '—';
        $totalRevenueTxCount = $paidCount + $paymentOnlyCount;
        $avgRevenue = $totalRevenueTxCount > 0 ? number_format((int) ($revenueTotal / $totalRevenueTxCount)).'원' : '—';
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
        $s1->setCellValue("F{$r}", '세팅비 '.number_format($revenueService).'  |  장비판매 '.number_format($revenueProduct).'  |  단순결제 '.number_format($revenuePaymentOnly));
        $applyStyle($s1, "F{$r}", $white, '666666', false, 9);
        $r++;

        $dataRow($s1, $r, ['매출', '결제 완료 건수', $totalRevenueTxCount > 0 ? number_format($totalRevenueTxCount) : '—', '견적서 결제 + 단순 결제']);
        $r++;
        $dataRow($s1, $r, ['매출', '총 매출', number_format($revenueTotal).'원', '견적서 + 단순 결제 (환불/취소 차감)'], true);
        $r++;
        $dataRow($s1, $r, ['매출', 'ㄴ 세팅비', number_format($revenueService).'원', 'service_items 합계']);
        $r++;
        $dataRow($s1, $r, ['매출', 'ㄴ 장비판매', number_format($revenueProduct).'원', 'product_items 합계']);
        $r++;
        $dataRow($s1, $r, ['매출', 'ㄴ 단순 결제', number_format($revenuePaymentOnly).'원', '견적서 없이 직접 결제'], true);
        $r++;
        $dataRow($s1, $r, ['매출', '건당 평균 매출', $avgRevenue, '']);
        $r += 2;

        // ── 마케팅 상세 섹션 (컬럼 있을 때만) ──
        $hasMarketingCols = Schema::hasColumn('clients', 'inflow_source') && Schema::hasColumn('projects', 'client_scale');
        $inflowLabels = ['search' => '검색', 'referral' => '지인 소개', 'sns' => 'SNS', 'ad' => '광고', 'community' => '커뮤니티', 'other' => '기타'];
        $typeLabels = ['personal' => '개인', 'enterprise' => '엔터', 'studio' => '스튜디오'];
        $inflowDist = $hasMarketingCols ? Client::whereBetween('created_at', [$fromDt, $toDt])->select('inflow_source', DB::raw('count(*) as cnt'))->groupBy('inflow_source')->pluck('cnt', 'inflow_source') : collect();
        $typeDist = $hasMarketingCols ? Client::whereBetween('created_at', [$fromDt, $toDt])->select('client_type', DB::raw('count(*) as cnt'))->groupBy('client_type')->pluck('cnt', 'client_type') : collect();
        $scaleDist = $hasMarketingCols ? Project::whereBetween('created_at', [$fromDt, $toDt])->select('client_scale', DB::raw('count(*) as cnt'))->groupBy('client_scale')->pluck('cnt', 'client_scale') : collect();

        $sectionHeader($s1, $r, '🎯  마케팅 상세');
        $r++;
        $subHeader($s1, $r, ['구분', '지표', '건수', '비고']);
        $r++;

        $inflowStripe = true;
        foreach ($inflowLabels as $key => $label) {
            $cnt = $inflowDist[$key] ?? 0;
            if ($cnt > 0) {
                $dataRow($s1, $r, ['유입경로', $label, number_format($cnt), ''], $inflowStripe);
                $r++;
                $inflowStripe = ! $inflowStripe;
            }
        }
        foreach ($typeLabels as $key => $label) {
            $cnt = $typeDist[$key] ?? 0;
            if ($cnt > 0) {
                $dataRow($s1, $r, ['의뢰자 유형', $label, number_format($cnt), ''], $inflowStripe);
                $r++;
                $inflowStripe = ! $inflowStripe;
            }
        }
        $scaleLabels = ['personal' => '개인', 'studio' => '스튜디오', 'corporate' => '기업', 'rental' => '렌탈', 'broadcast_room' => '방송룸'];
        foreach ($scaleLabels as $key => $label) {
            $cnt = $scaleDist[$key] ?? 0;
            if ($cnt > 0) {
                $dataRow($s1, $r, ['프로젝트 규모', $label, number_format($cnt), ''], $inflowStripe);
                $r++;
                $inflowStripe = ! $inflowStripe;
            }
        }
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
                Schedule::where('start_date', $ds)->whereIn('color', CalendarCategory::keysByLabel('방문', ['gold']))->count(),
                Schedule::where('start_date', $ds)->whereIn('color', CalendarCategory::keysByLabel('원격', ['teal']))->count(),
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

        // Sheet 5: 상담 이력 — 프로젝트 컨텍스트 포함
        $consultProjectTypeL = ConsultationType::pluck('label', 'key')->toArray();
        $consultScaleL = ['personal' => '개인', 'studio' => '스튜디오', 'corporate' => '기업', 'rental' => '렌탈', 'broadcast_room' => '방송룸'];
        $consultWorkTypeL = ['setup' => '세팅', 'remote' => '원격', 'survey' => '답사', 'filming' => '촬영중계', 'design' => '디자인', 'as' => 'A/S', 'dispatch' => '파견', 'monthly' => '월 계약', 'hourly' => '시간 대여'];

        $s5 = $spreadsheet->createSheet();
        $s5->setTitle('상담 이력');
        $s5->fromArray([
            '의뢰자', '닉네임', '연락처',
            '프로젝트명', '프로젝트 유형', '규모', '작업 유형', '진행 단계',
            '상담 유형', '진행상황', '내용', '담당자', '상담일',
        ], null, 'A1');
        $bold($s5, 'A1:M1');
        $row = 2;
        Consultation::with('client', 'consultant', 'project')
            ->whereBetween('consulted_at', [$fromDt, $toDt])
            ->orderByDesc('consulted_at')
            ->chunk(200, function ($items) use ($s5, &$row, $consultL, $resultL, $stageL, $consultProjectTypeL, $consultScaleL, $consultWorkTypeL) {
                foreach ($items as $c) {
                    $client = $c->client;
                    $project = $c->project;
                    $s5->fromArray([
                        $client?->name,
                        $client?->nickname,
                        $client?->phone,
                        $project?->name,
                        $project ? ($consultProjectTypeL[$project->project_type] ?? $project->project_type) : null,
                        $project?->client_scale ? ($consultScaleL[$project->client_scale] ?? $project->client_scale) : null,
                        $project?->work_type ? ($consultWorkTypeL[$project->work_type] ?? $project->work_type) : null,
                        $project ? ($stageL[$project->stage] ?? $project->stage) : null,
                        $consultL[$c->consult_type] ?? $c->consult_type,
                        $resultL[$c->result] ?? $c->result,
                        $c->content,
                        $c->consultant?->display_name,
                        $c->consulted_at?->format('Y-m-d'),
                    ], null, "A{$row}");
                    $row++;
                }
            });
        // 열 너비 자동 조정
        foreach (range('A', 'M') as $col) {
            $s5->getColumnDimension($col)->setAutoSize(true);
        }
        // 내용 셀 줄바꿈 + 상단 정렬
        $s5->getStyle("K2:K{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

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

        // ── 마케팅 지표 계산 (컬럼 있을 때만) ──
        $hasMarketingCols2 = Schema::hasColumn('clients', 'inflow_source') && Schema::hasColumn('projects', 'client_scale');
        $inflowL = ['search' => '검색', 'referral' => '지인 소개', 'sns' => 'SNS', 'ad' => '광고', 'community' => '커뮤니티', 'other' => '기타'];
        $clientTypeL = ['personal' => '개인', 'enterprise' => '엔터', 'studio' => '스튜디오'];
        $scaleL = ['personal' => '개인', 'studio' => '스튜디오', 'corporate' => '기업', 'rental' => '렌탈', 'broadcast_room' => '방송룸'];
        $workL = ['setup' => '세팅', 'remote' => '원격', 'survey' => '답사', 'filming' => '촬영중계', 'design' => '디자인', 'as' => 'A/S', 'dispatch' => '파견', 'monthly' => '월 계약', 'hourly' => '시간 대여'];
        $breakdownL = ['setup' => '세팅비', 'product' => '장비판매', 'labor' => '인건비', 'dispatch' => '파견비', 'rush' => '긴급비', 'other' => '기타'];

        $clientsByInflow = $hasMarketingCols2 ? Client::whereBetween('created_at', [$fromDt, $toDt])->select('inflow_source', DB::raw('count(*) as cnt'))->groupBy('inflow_source')->pluck('cnt', 'inflow_source') : collect();
        $clientsByType = $hasMarketingCols2 ? Client::whereBetween('created_at', [$fromDt, $toDt])->select('client_type', DB::raw('count(*) as cnt'))->groupBy('client_type')->pluck('cnt', 'client_type') : collect();

        $platformCounts = [];
        $contentCounts = [];
        Client::whereBetween('created_at', [$fromDt, $toDt])->chunk(200, function ($chunk) use (&$platformCounts, &$contentCounts) {
            foreach ($chunk as $c) {
                foreach ($c->platforms ?? [] as $p) {
                    $platformCounts[$p] = ($platformCounts[$p] ?? 0) + 1;
                }
                foreach ($c->content_types ?? [] as $t) {
                    $contentCounts[$t] = ($contentCounts[$t] ?? 0) + 1;
                }
            }
        });
        arsort($platformCounts);
        arsort($contentCounts);

        $scaleWorkMatrix = $hasMarketingCols2 ? Project::whereBetween('created_at', [$fromDt, $toDt])
            ->select('client_scale', 'work_type', DB::raw('count(*) as cnt'))
            ->groupBy('client_scale', 'work_type')->get() : collect();

        $cancelReasons = Schema::hasColumn('projects', 'cancel_reason') ? Project::whereBetween('cancelled_at', [$fromDt, $toDt])
            ->whereNotNull('cancel_reason')
            ->select('cancel_reason', DB::raw('count(*) as cnt'))
            ->groupBy('cancel_reason')->pluck('cnt', 'cancel_reason') : collect();

        $revenueBreakdown = ['setup' => 0, 'product' => 0, 'labor' => 0, 'dispatch' => 0, 'rush' => 0, 'other' => 0];
        foreach ($allPaidEstimates as $e) {
            if (! Schema::hasColumn('estimates', 'category_breakdown')) {
                break;
            }
            foreach ($e->category_breakdown ?? [] as $key => $val) {
                if (isset($revenueBreakdown[$key])) {
                    $revenueBreakdown[$key] += (int) $val;
                }
            }
        }

        // Sheet 8: 마케팅 지표
        $s8 = $spreadsheet->createSheet();
        $s8->setTitle('마케팅 지표');
        $s8->getColumnDimension('A')->setWidth(22);
        $s8->getColumnDimension('B')->setWidth(14);
        $s8->getColumnDimension('C')->setWidth(14);

        $s8->setCellValue('A1', '📢 마케팅 지표');
        $s8->mergeCells('A1:C1');
        $bold($s8, 'A1');

        // 유입경로
        $s8->setCellValue('A3', '▸ 유입경로별 분포');
        $bold($s8, 'A3');
        $s8->fromArray(['유입경로', '건수', '비율(%)'], null, 'A4');
        $bold($s8, 'A4:C4');
        $row = 5;
        $totalInflow = $clientsByInflow->sum();
        foreach ($inflowL as $key => $label) {
            $cnt = $clientsByInflow[$key] ?? 0;
            $ratio = $totalInflow > 0 ? round(($cnt / $totalInflow) * 100, 1) : 0;
            $s8->fromArray([$label, $cnt, $ratio.'%'], null, "A{$row}");
            $row++;
        }
        if (($clientsByInflow[null] ?? 0) > 0) {
            $s8->fromArray(['미지정', $clientsByInflow[null], $totalInflow > 0 ? round(($clientsByInflow[null] / $totalInflow) * 100, 1).'%' : '0%'], null, "A{$row}");
            $row++;
        }

        // 의뢰자 유형
        $row += 2;
        $s8->setCellValue("A{$row}", '▸ 의뢰자 유형별 분포');
        $bold($s8, "A{$row}");
        $row++;
        $s8->fromArray(['유형', '건수', '비율(%)'], null, "A{$row}");
        $bold($s8, "A{$row}:C{$row}");
        $row++;
        $totalType = $clientsByType->sum();
        foreach ($clientTypeL as $key => $label) {
            $cnt = $clientsByType[$key] ?? 0;
            $ratio = $totalType > 0 ? round(($cnt / $totalType) * 100, 1) : 0;
            $s8->fromArray([$label, $cnt, $ratio.'%'], null, "A{$row}");
            $row++;
        }

        // 플랫폼
        $row += 2;
        $s8->setCellValue("A{$row}", '▸ 플랫폼별 분포');
        $bold($s8, "A{$row}");
        $row++;
        $s8->fromArray(['플랫폼', '건수', ''], null, "A{$row}");
        $bold($s8, "A{$row}:C{$row}");
        $row++;
        if (empty($platformCounts)) {
            $s8->fromArray(['(데이터 없음)', '', ''], null, "A{$row}");
            $row++;
        } else {
            foreach ($platformCounts as $platform => $cnt) {
                $s8->fromArray([$platform, $cnt, ''], null, "A{$row}");
                $row++;
            }
        }

        // 콘텐츠 유형
        $row += 2;
        $s8->setCellValue("A{$row}", '▸ 콘텐츠 유형별 분포');
        $bold($s8, "A{$row}");
        $row++;
        $s8->fromArray(['콘텐츠', '건수', ''], null, "A{$row}");
        $bold($s8, "A{$row}:C{$row}");
        $row++;
        if (empty($contentCounts)) {
            $s8->fromArray(['(데이터 없음)', '', ''], null, "A{$row}");
        } else {
            foreach ($contentCounts as $content => $cnt) {
                $s8->fromArray([$content, $cnt, ''], null, "A{$row}");
                $row++;
            }
        }

        // Sheet 9: 프로젝트 규모×작업유형 매트릭스
        $s9 = $spreadsheet->createSheet();
        $s9->setTitle('규모 매트릭스');
        $workKeys = array_keys($workL);
        $headers = array_merge(['규모 \ 작업'], array_values($workL), ['합계']);
        $s9->fromArray($headers, null, 'A1');
        $bold($s9, 'A1:'.chr(65 + count($headers) - 1).'1');

        // 매트릭스 데이터 준비
        $matrix = [];
        foreach ($scaleWorkMatrix as $entry) {
            $matrix[$entry->client_scale][$entry->work_type] = $entry->cnt;
        }

        $row = 2;
        foreach ($scaleL as $scaleKey => $scaleName) {
            $line = [$scaleName];
            $total = 0;
            foreach ($workKeys as $wt) {
                $cnt = $matrix[$scaleKey][$wt] ?? 0;
                $line[] = $cnt > 0 ? $cnt : '';
                $total += $cnt;
            }
            $line[] = $total;
            $s9->fromArray($line, null, "A{$row}");
            $row++;
        }
        $bold($s9, 'A2:A'.($row - 1));
        foreach (range(0, count($headers) - 1) as $i) {
            $s9->getColumnDimension(chr(65 + $i))->setAutoSize(true);
        }

        // 취소 사유
        if ($cancelReasons->count() > 0) {
            $row += 2;
            $s9->setCellValue("A{$row}", '▸ 취소 사유별 분포');
            $bold($s9, "A{$row}");
            $row++;
            $s9->fromArray(['사유', '건수'], null, "A{$row}");
            $bold($s9, "A{$row}:B{$row}");
            $row++;
            foreach ($cancelReasons as $reason => $cnt) {
                $s9->fromArray([$reason, $cnt], null, "A{$row}");
                $row++;
            }
        }

        // Sheet 10: 매출 세부 카테고리
        $s10 = $spreadsheet->createSheet();
        $s10->setTitle('매출 세부');
        $s10->getColumnDimension('A')->setWidth(22);
        $s10->getColumnDimension('B')->setWidth(18);
        $s10->getColumnDimension('C')->setWidth(14);
        $s10->fromArray(['카테고리', '금액(원)', '비율(%)'], null, 'A1');
        $bold($s10, 'A1:C1');

        $breakdownTotal = array_sum($revenueBreakdown);
        $row = 2;
        foreach ($breakdownL as $key => $label) {
            $val = $revenueBreakdown[$key] ?? 0;
            $ratio = $breakdownTotal > 0 ? round(($val / $breakdownTotal) * 100, 1) : 0;
            $s10->fromArray([$label, $val, $ratio.'%'], null, "A{$row}");
            $row++;
        }
        $s10->fromArray(['합계', $breakdownTotal, '100%'], null, "A{$row}");
        $bold($s10, "A{$row}:C{$row}");

        // Sheet 11: 렌탈/방송룸 현황 (테이블 있을 때만)
        if (Schema::hasTable('rental_contracts') || Schema::hasTable('broadcast_room_contracts')) {
            $s11 = $spreadsheet->createSheet();
            $s11->setTitle('렌탈 방송룸');
            $s11->getColumnDimension('A')->setWidth(30);
            $s11->getColumnDimension('B')->setWidth(20);

            if (Schema::hasTable('rental_contracts')) {
                $s11->setCellValue('A1', '🏠 렌탈 현황');
                $bold($s11, 'A1');
                $s11->fromArray(['진행중 계약', RentalContract::where('status', 'active')->count().'건'], null, 'A3');
                $s11->fromArray(['월 매출 (진행중)', number_format(RentalContract::where('status', 'active')->sum('monthly_fee')).'원'], null, 'A4');
                $s11->fromArray(['기간 내 신규 계약', RentalContract::whereBetween('start_date', [$from, $to])->count().'건'], null, 'A5');
            }

            if (Schema::hasTable('broadcast_room_contracts')) {
                $s11->setCellValue('A7', '🎙 방송룸 현황');
                $bold($s11, 'A7');
                $s11->fromArray(['진행중 월 계약', BroadcastRoomContract::where('status', 'active')->count().'건'], null, 'A9');
                $s11->fromArray(['월 계약 매출', number_format(BroadcastRoomContract::where('status', 'active')->sum('monthly_fee')).'원'], null, 'A10');
                if (Schema::hasTable('broadcast_room_usages')) {
                    $s11->fromArray(['기간 내 시간 대여', BroadcastRoomUsage::whereBetween('used_date', [$from, $to])->count().'회'], null, 'A11');
                    $s11->fromArray(['시간 대여 매출', number_format(BroadcastRoomUsage::whereBetween('used_date', [$from, $to])->sum('fee')).'원'], null, 'A12');
                }
            }
        }

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
