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
use App\Models\ProjectPayment;
use App\Models\RentalContract;
use App\Models\Schedule;
use App\Models\WorkType;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        // 취소 사유 — 취소 카드와 동일 코호트(기간 내 유입 프로젝트 중 취소)로 집계해 합계가 항상 일치
        // (기존 cancelled_at 기준은 과거 데이터 일괄 백필 시 특정 월에 전체 취소가 몰려 보이는 문제)
        $cancelReasons = collect();
        if (Schema::hasColumn('projects', 'cancel_reason')) {
            $cancelReasons = Project::whereBetween('created_at', [$fromDt, $toDt])
                ->where('stage', 'cancelled')
                ->selectRaw("coalesce(nullif(cancel_reason, ''), '미기재') as reason, count(*) as cnt")
                ->groupBy('reason')->orderByDesc('cnt')->pluck('cnt', 'reason');
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

        // ── 일정 지표 (마케팅 사양서 기준) — RAW 매핑을 엑셀 추출과 공유 ──
        $rawRows = $this->rawScheduleRows($from, $to);
        $requestRows = $rawRows->reject(fn ($r) => $r['internal']); // 사내업무·휴가 제외 = 의뢰 건
        $revenueRows = $requestRows->filter(fn ($r) => $r['amount'] > 0);
        $hasAttr = fn ($r, $a) => in_array($a, $r['attrs'], true);
        $schedStats = [
            'total' => $requestRows->count(),
            'all' => $rawRows->count(),
            'revenue_cnt' => $revenueRows->count(),
            'revenue_rate' => $requestRows->count() ? round($revenueRows->count() / $requestRows->count() * 100, 1) : 0,
            'revenue_sum' => (int) $revenueRows->sum('amount'),
            'avg_price' => $revenueRows->count() ? (int) round($revenueRows->sum('amount') / $revenueRows->count()) : 0,
            'free_cnt' => $requestRows->count() - $revenueRows->count(),
            'by_type' => $rawRows->countBy('type')->sortDesc()->all(),
            'by_client_type' => $requestRows->groupBy(fn ($r) => $r['client_type'] !== '' ? $r['client_type'] : '미입력')
                ->map(fn ($g) => ['cnt' => $g->count(), 'sum' => (int) $g->sum('amount')])
                ->sortByDesc('cnt')->all(),
            // 플랫폼×유형 교차 (의뢰 건, 플랫폼 미입력 제외)
            'platform_types' => $requestRows->filter(fn ($r) => $r['platform'] !== '' && $r['platform'] !== '해당없음')
                ->groupBy('platform')->map(fn ($g) => $g->countBy('type')->sortDesc()->all())
                ->sortByDesc(fn ($types) => array_sum($types))->all(),
            'by_assignee' => $requestRows->filter(fn ($r) => $r['main'] !== '')->countBy('main')->sortDesc()->all(),
            'by_dow' => collect(['월', '화', '수', '목', '금', '토', '일'])
                ->mapWithKeys(fn ($d) => [$d => $requestRows->where('dow', $d)->count()])->all(),
            'by_region' => $requestRows->filter(fn ($r) => $r['region'] !== '' && $r['region'] !== '해당없음')
                ->countBy('region')->sortDesc()->take(10)->all(),
            'revisit_cnt' => $requestRows->filter(fn ($r) => $hasAttr($r, '재방문'))->count(),
            'revisit_rate' => $requestRows->count() ? round($requestRows->filter(fn ($r) => $hasAttr($r, '재방문'))->count() / $requestRows->count() * 100, 1) : 0,
            'rush_cnt' => $requestRows->filter(fn ($r) => $hasAttr($r, '급행'))->count(),
            'rush_avg' => ($rushRows = $requestRows->filter(fn ($r) => $hasAttr($r, '급행') && $r['amount'] > 0))->count()
                ? (int) round($rushRows->sum('amount') / $rushRows->count()) : 0,
            'survey_cnt' => $requestRows->filter(fn ($r) => $hasAttr($r, '사전답사'))->count(),
            'autopay_cnt' => $requestRows->filter(fn ($r) => $hasAttr($r, '자동결제'))->count(),
            'autopay_sum' => (int) $requestRows->filter(fn ($r) => $hasAttr($r, '자동결제'))->sum('amount'),
            'consulting_cnt' => $requestRows->filter(fn ($r) => mb_stripos($r['title'], '컨설팅') !== false)->count(),
        ];

        return view('marketing-report.index', compact(
            'from', 'to', 'schedStats',
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

    /** 매출 상세 페이지 — 결제 발생 프로젝트 목록 (통계 총 매출 카드에서 진입, 메뉴 미노출) */
    public function revenuePage(Request $request)
    {
        return view('marketing-report.revenue', [
            'from' => $request->query('from', now()->startOfMonth()->format('Y-m-d')),
            'to' => $request->query('to', now()->endOfMonth()->format('Y-m-d')),
        ]);
    }

    /**
     * 일정 통계 엑셀 — 기간 내 일정을 날짜별로 나열 (의뢰자 플랫폼·경력·결제 포함).
     * 기간이 여러 달에 걸치면 월별 시트로 분리, 각 시트 상단에 유형 분포 요약.
     */
    public function schedulesExport(Request $request): StreamedResponse
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', now()->endOfMonth()->format('Y-m-d'));

        $catMap = CalendarCategory::map();
        $optLabels = ['suggest' => '제안', 'hope' => '희망', 'target' => '목표', 'confirmed' => '확정'];
        $dows = ['일', '월', '화', '수', '목', '금', '토'];

        $schedules = Schedule::topLevel()->with('assignees:id,name')
            ->whereBetween('start_date', [$from, $to])
            ->orderBy('start_date')->orderBy('is_all_day', 'desc')->orderBy('start_time')
            ->get();

        // 연동 의뢰자 일괄 로딩 (플랫폼 폴백용)
        $clientIds = $schedules->map(fn ($s) => data_get($s->request_data, 'client_id'))->filter()->unique();
        $clients = Client::whereIn('id', $clientIds)->get()->keyBy('id');

        $rowFor = function (Schedule $s) use ($catMap, $optLabels, $dows, $clients): array {
            $g = (array) ($s->request_data ?? []);
            $client = ! empty($g['client_id']) ? $clients->get($g['client_id']) : null;

            $platform = trim((string) ($g['platform'] ?? ''));
            if ($platform === '' && $client) {
                $platform = implode(', ', $client->platforms ?? []);
            }
            if ($platform === '' && is_array($s->remote_data)) {
                $platform = trim((string) ($s->remote_data['platform'] ?? ''));
            }

            $amount = (int) preg_replace('/\D/', '', (string) ($g['estimate_amount'] ?? ''));
            $paidRaw = (string) ($g['paid'] ?? '');
            $paidClass = ($paidRaw === '결제완료' || $amount > 0 || ($g['balance'] ?? '') === 'O')
                ? '유상'
                : ($paidRaw === '미결제' ? '유상(미결제)' : '무상/미기재');

            return [
                'date' => $s->start_date->format('Y-m-d'),
                'dow' => $dows[$s->start_date->dayOfWeek],
                'time' => $s->is_all_day || ! $s->start_time ? '종일' : substr((string) $s->start_time, 0, 5),
                'title' => $s->title,
                'category' => $catMap[$s->color]['label'] ?? $s->color,
                'opt' => $optLabels[$s->sched_opt] ?? '',
                'client' => $s->client_name ?: ($g['nickname'] ?? '') ?: ($g['name'] ?? '') ?: ($client->nickname ?? ''),
                'platform' => $platform,
                'career' => (string) (($g['career'] ?? '') ?: ($client->career ?? '')),
                'paid' => $paidClass,
                'amount' => $amount,
                'balance' => ($g['balance'] ?? '') === 'O' ? (int) preg_replace('/\D/', '', (string) ($g['balance_amount'] ?? '')) : 0,
                'assignees' => $s->assignees->pluck('name')->implode(', '),
                'completed' => $s->completed_at ? 'O' : '',
            ];
        };

        $spreadsheet = new Spreadsheet;
        $bold = fn ($sheet, $range) => $sheet->getStyle($range)->getFont()->setBold(true);
        $byMonth = $schedules->groupBy(fn ($s) => $s->start_date->format('Y-m'));

        $header = ['날짜', '요일', '시간', '제목', '유형', '일정 성격', '의뢰자', '플랫폼', '경력', '결제', '결제금액', '잔금', '담당자', '완료'];
        $firstSheet = true;

        foreach ($byMonth as $ym => $monthSchedules) {
            $sheet = $firstSheet ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $firstSheet = false;
            $sheet->setTitle($ym);

            $rows = $monthSchedules->map($rowFor);

            // ── 요약: 유형 분포 + 결제 유무 ──
            $sheet->setCellValue('A1', "{$ym} 일정 통계");
            $bold($sheet, 'A1');
            $sheet->setCellValue('A2', '기간');
            $sheet->setCellValue('B2', max($from, $ym.'-01').' ~ '.min($to, $monthSchedules->max(fn ($s) => $s->start_date->format('Y-m-d'))));
            $sheet->setCellValue('A3', '총 일정');
            $sheet->setCellValue('B3', $rows->count().'건');

            $r = 5;
            $sheet->setCellValue("A{$r}", '■ 유형 분포');
            $bold($sheet, "A{$r}");
            $r++;
            $sheet->fromArray(['유형', '건수', '비중'], null, "A{$r}");
            $bold($sheet, "A{$r}:C{$r}");
            $r++;
            foreach ($rows->countBy('category')->sortDesc() as $label => $cnt) {
                $sheet->fromArray([$label, $cnt, round($cnt / max(1, $rows->count()) * 100).'%'], null, "A{$r}");
                $r++;
            }

            $r++;
            $sheet->setCellValue("A{$r}", '■ 결제 유무');
            $bold($sheet, "A{$r}");
            $r++;
            foreach ($rows->countBy('paid')->sortDesc() as $label => $cnt) {
                $sheet->fromArray([$label, $cnt], null, "A{$r}");
                $r++;
            }

            // ── 일정 목록 ──
            $r += 1;
            $sheet->setCellValue("A{$r}", '■ 일정 목록');
            $bold($sheet, "A{$r}");
            $r++;
            $sheet->fromArray($header, null, "A{$r}");
            $bold($sheet, "A{$r}:N{$r}");
            $r++;
            foreach ($rows as $row) {
                $sheet->fromArray(array_values($row), null, "A{$r}");
                $r++;
            }
            foreach (range('A', 'N') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        if ($byMonth->isEmpty()) {
            $spreadsheet->getActiveSheet()->setTitle('일정 없음')
                ->setCellValue('A1', "{$from} ~ {$to} 기간에 일정이 없습니다.");
        }

        $filename = "일정통계_{$from}_{$to}.xlsx";

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename*=UTF-8''".rawurlencode($filename),
        ]);
    }

    /**
     * 마케팅 사양서 기준 일정 RAW 행 매핑 — 엑셀 추출과 통계 지표가 공유.
     * 입력이 없는 값은 공백(=누락), 사내업무·휴가는 '해당없음' 규칙.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function rawScheduleRows(string $from, string $to): Collection
    {
        $dows = ['일', '월', '화', '수', '목', '금', '토'];
        $catMap = CalendarCategory::map();

        $schedules = Schedule::topLevel()->with('assignees:id,name')
            ->whereBetween('start_date', [$from, $to])
            ->orderBy('start_date')->orderBy('is_all_day', 'desc')->orderBy('start_time')->orderBy('id')
            ->get();

        // 연동 의뢰자/프로젝트 일괄 로딩 (의뢰자 유형·플랫폼 폴백)
        $clientIds = $schedules->map(fn ($s) => data_get($s->request_data, 'client_id'))->filter()->unique();
        $clients = Client::whereIn('id', $clientIds)->get()->keyBy('id');
        $projectIds = $schedules->map(fn ($s) => data_get($s->request_data, 'project_id'))->filter()->unique();
        $projects = Project::whereIn('id', $projectIds)->get(['id', 'client_scale'])->keyBy('id');

        // 재방문 판정용 — 의뢰자 식별키별 최초 일정일 (기간 이전 이력 포함 전체 조회)
        $clientKey = function (Schedule $s): ?string {
            $cid = data_get($s->request_data, 'client_id');
            if ($cid) {
                return 'c'.$cid;
            }
            $name = trim((string) ($s->client_name ?: data_get($s->request_data, 'nickname') ?: data_get($s->request_data, 'name') ?: ''));

            return $name !== '' ? 'n'.mb_strtolower($name) : null;
        };
        $firstSeen = [];
        Schedule::topLevel()->orderBy('start_date')
            ->get(['id', 'start_date', 'client_name', 'request_data'])
            ->each(function (Schedule $s) use ($clientKey, &$firstSeen) {
                $key = $clientKey($s);
                if ($key && ! isset($firstSeen[$key])) {
                    $firstSeen[$key] = $s->start_date->format('Y-m-d').'#'.$s->id;
                }
            });

        // 유형 매핑 — 카테고리 라벨 + 원격/방송룸 모드 + 계약 동기화 출처
        $typeOf = function (Schedule $s) use ($catMap): string {
            $label = $catMap[$s->color]['label'] ?? $s->color;
            if ($s->source_type === 'rental_contract') {
                return '장비 렌탈';
            }
            if ($s->source_type === 'broadcast_contract') {
                return '방송룸 대여';
            }

            return match ($label) {
                '방문의뢰' => '방문세팅',
                '원격/방송룸' => data_get($s->remote_data, 'mode') === 'studio' ? '방송룸 대여' : '원격',
                '촬영/스튜디오' => '촬영',
                '미팅/내방' => '내방',
                '사내업무' => '사내업무',
                '휴가/개인' => '휴가/개인',
                '렌탈' => '장비 렌탈',
                '방송룸 대여' => '방송룸 대여',
                default => $label,
            };
        };

        $platformNorm = function (string $raw): string {
            $v = mb_strtolower(trim($raw));
            if ($v === '') {
                return '';
            }
            if (str_contains($v, ',') || str_contains($v, '동시') || str_contains($v, '멀티')) {
                return '동시';
            }
            foreach ([
                '숲' => ['숲', 'soop', '아프리카', 'afreeca'],
                '치지직' => ['치지직', 'chzzk'],
                '유튜브' => ['유튜브', '유투브', 'youtube'],
                '틱톡' => ['틱톡', 'tiktok'],
                '팬더' => ['팬더', 'panda'],
                '플렉스' => ['플렉스', 'flex'],
                '팝콘' => ['팝콘', 'popkon'],
            ] as $code => $variants) {
                foreach ($variants as $needle) {
                    if (str_contains($v, $needle)) {
                        return $code;
                    }
                }
            }

            return '기타';
        };

        // 지역 — 주소에서 시/도 + 시/군/구 추출 (근사치)
        $regionOf = function (string $addr): string {
            $addr = trim(preg_replace('/\s+/', ' ', $addr));
            if ($addr === '') {
                return '';
            }
            $tokens = explode(' ', $addr);
            $first = preg_replace('/(특별시|광역시|특별자치시|특별자치도)$/u', '', $tokens[0]);

            return trim($first.' '.($tokens[1] ?? ''));
        };

        return $schedules->map(function (Schedule $s) use ($dows, $clients, $projects, $typeOf, $platformNorm, $regionOf, $clientKey, $firstSeen): array {
            $g = (array) ($s->request_data ?? []);
            $client = ! empty($g['client_id']) ? $clients->get($g['client_id']) : null;
            $type = $typeOf($s);
            $isInternal = in_array($type, ['사내업무', '휴가/개인'], true);

            // 의뢰자 유형 — 연결 프로젝트 규모값 (엔터 구분은 현재 입력에 없음 → 공백)
            $scale = ! empty($g['project_id']) ? ($projects->get($g['project_id'])->client_scale ?? null) : null;
            $clientType = $isInternal ? '해당없음'
                : (['personal' => '개인', 'studio' => '스튜디오', 'corporate' => '기업'][$scale] ?? '');

            $clientName = trim((string) ($s->client_name ?: ($g['nickname'] ?? '') ?: ($g['name'] ?? '') ?: ($client->nickname ?? '')));
            $platform = trim((string) (($g['platform'] ?? '') ?: implode(', ', $client->platforms ?? []) ?: data_get($s->remote_data, 'platform', '')));
            $career = trim((string) (($g['career'] ?? '') ?: ($client->career ?? '')));
            $career = in_array($career, ['처음', '신규'], true) ? '처음' : (in_array($career, ['초보', '경력'], true) ? $career : '');

            // 속성 — 급행/사전답사는 제목 표기, 자동결제는 계약 동기화 결제 일정, 재방문은 과거 이력 자동 판정
            $attrs = [];
            if (mb_stripos($s->title, '급행') !== false) {
                $attrs[] = '급행';
            }
            if (mb_stripos($s->title, '답사') !== false) {
                $attrs[] = '사전답사';
            }
            $key = $clientKey($s);
            if (! $isInternal && $key && isset($firstSeen[$key]) && $firstSeen[$key] < $s->start_date->format('Y-m-d').'#'.$s->id) {
                $attrs[] = '재방문';
            }
            if (in_array($s->source_type, ['rental_contract', 'broadcast_contract'], true) && str_contains($s->title, '결제')) {
                $attrs[] = '자동결제';
            }

            $assignees = $s->assignees->pluck('name')->values();
            $region = $regionOf((string) ($s->address ?: $s->location ?: ''));

            return [
                'id_label' => 'CAL-'.$s->start_date->format('Ym').'-'.str_pad((string) $s->id, 4, '0', STR_PAD_LEFT),
                'date' => $s->start_date->format('Y-m-d'),
                'dow' => $dows[$s->start_date->dayOfWeek],
                'start' => $s->is_all_day || ! $s->start_time ? '종일' : substr((string) $s->start_time, 0, 5),
                'end' => $s->is_all_day ? '종일' : ($s->end_time ? substr((string) $s->end_time, 0, 5) : ''),
                'title' => $s->title,
                'type' => $type,
                'internal' => $isInternal,
                'client_type' => $clientType,
                'client' => $isInternal ? '해당없음' : $clientName,
                'platform' => $isInternal ? '해당없음' : $platformNorm($platform),
                'career' => $isInternal ? '해당없음' : $career,
                'amount' => (int) preg_replace('/\D/', '', (string) ($g['estimate_amount'] ?? '')),
                'attrs' => $attrs,
                'status' => $s->completed_at ? '완료' : '예정',
                'main' => (string) ($assignees[0] ?? ''),
                'subs' => $assignees->slice(1)->implode(';'),
                'region' => $region !== '' ? $region : (($isInternal || $type === '원격') ? '해당없음' : ''),
            ];
        });
    }

    /**
     * 일정통계 RAW 엑셀 — 마케팅부 추출 사양서(17컬럼, 1건=1행) 형식.
     * 현재 입력 데이터에서 채울 수 있는 값은 매핑·정규화로 채우고, 입력이 없는 값은 공백(=누락)으로 출력.
     */
    public function schedulesExportRaw(Request $request): StreamedResponse
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', now()->endOfMonth()->format('Y-m-d'));

        $header = ['일정ID', '날짜', '요일', '시작시간', '종료시간', '제목', '유형', '의뢰자 유형', '의뢰자명', '플랫폼', '경력', '결제금액', '속성', '상태', '주담당자', '보조담당자', '지역'];
        $rows = $this->rawScheduleRows($from, $to)->map(fn (array $r) => [
            $r['id_label'], $r['date'], $r['dow'], $r['start'], $r['end'], $r['title'], $r['type'], $r['client_type'],
            $r['client'], $r['platform'], $r['career'], $r['amount'], implode(';', $r['attrs']), $r['status'],
            $r['main'], $r['subs'], $r['region'],
        ]);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('일정통계 RAW');
        $sheet->fromArray($header, null, 'A1');
        $sheet->getStyle('A1:Q1')->getFont()->setBold(true);
        $r = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($row, null, "A{$r}");
            $sheet->getCell("L{$r}")->setValue((int) $row[11]); // 결제금액은 숫자형 유지
            $r++;
        }
        foreach (range('A', 'Q') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "일정통계RAW_{$from}_{$to}.xlsx";

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename*=UTF-8''".rawurlencode($filename),
        ]);
    }

    /** 총 매출 드릴다운 — 기간 내 결제가 발생한 프로젝트별 순매출 목록 (JSON) */
    public function revenueProjects(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->query('to', now()->endOfMonth()->format('Y-m-d'));
        $fromDt = $from.' 00:00:00';
        $toDt = $to.' 23:59:59';

        $typeLabels = ConsultationType::map(false);
        $workLabels = WorkType::map(false);
        $items = collect();

        if (Schema::hasTable('project_payments')) {
            $grouped = ProjectPayment::whereBetween('project_payments.created_at', [$fromDt, $toDt])
                ->join('projects', 'projects.id', '=', 'project_payments.project_id')
                ->leftJoin('clients', 'clients.id', '=', 'projects.client_id')
                ->selectRaw('projects.id, projects.name, projects.project_type, projects.work_type, projects.manual_client_name,
                    clients.nickname as client_nickname, clients.name as client_name,
                    sum(project_payments.amount) as total, count(*) as pay_count, max(project_payments.paid_at) as last_paid_at')
                ->groupBy('projects.id', 'projects.name', 'projects.project_type', 'projects.work_type', 'projects.manual_client_name', 'clients.nickname', 'clients.name')
                ->get();

            // 프로젝트 내 개별 결제 건 (모달에서 펼쳐 보기)
            $paymentRows = ProjectPayment::whereBetween('created_at', [$fromDt, $toDt])
                ->whereIn('project_id', $grouped->pluck('id'))
                ->orderByDesc('paid_at')->orderByDesc('id')
                ->get(['project_id', 'type', 'amount', 'method', 'paid_at', 'memo'])
                ->groupBy('project_id');
            $payTypeLabels = ['charge' => '결제', 'refund' => '환불', 'cancel' => '취소'];

            $items = $grouped->map(fn ($r) => [
                'project_id' => $r->id,
                'name' => $r->name,
                'client' => $r->client_nickname ?: $r->client_name ?: $r->manual_client_name ?: '(의뢰자 없음)',
                'type' => $typeLabels[$r->project_type] ?? $r->project_type,
                'work' => $r->work_type ? ($workLabels[$r->work_type] ?? $r->work_type) : '미지정',
                'amount' => (int) $r->total,
                'pay_count' => (int) $r->pay_count,
                'last_paid_at' => $r->last_paid_at,
                'source' => 'payment',
                'payments' => ($paymentRows[$r->id] ?? collect())->map(fn ($p) => [
                    'label' => $payTypeLabels[$p->type] ?? $p->type,
                    'amount' => (int) $p->amount,
                    'method' => $p->method,
                    'paid_at' => $p->paid_at?->format('Y-m-d'),
                    'memo' => $p->memo ? mb_substr($p->memo, 0, 80) : null,
                ])->values()->all(),
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
                'work' => $e->project?->work_type ? ($workLabels[$e->project->work_type] ?? $e->project->work_type) : '미지정',
                'amount' => (int) $e->total_amount,
                'pay_count' => 1,
                'last_paid_at' => $e->created_at->format('Y-m-d'),
                'source' => 'estimate',
                'payments' => [[
                    'label' => '견적 결제완료',
                    'amount' => (int) $e->total_amount,
                    'method' => null,
                    'paid_at' => $e->created_at->format('Y-m-d'),
                    'memo' => null,
                ]],
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
