<?php

namespace App\Http\Controllers;

use App\Models\Assignee;
use App\Models\CalendarCategory;
use App\Models\Estimate;
use App\Models\Product;
use App\Models\Schedule;
use App\Models\ScheduleBench;
use App\Models\ScheduleChange;
use App\Notifications\ScheduleCreated;
use App\Notifications\ScheduleUpdated;
use App\Services\BenchCalculator;
use App\Services\EstimatePaymentSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * 캘린더 대기함 — 보류·재방문·날짜 미정 일정을 모아 두고 다시 배치한다.
 * 계산과 차단 규칙은 BenchCalculator 한 곳에 있고, 여기서는 DB에서 입력을 모아 넘기기만 한다.
 * 시스템은 날짜·시간·담당자를 추천하지 않는다 (명세 2장 결정 사항).
 */
class BenchController extends Controller
{
    private const KIND_LABELS = ['hold' => '보류', 'revisit' => '재방문', 'undated' => '날짜 미정'];

    /** 재방문 일정을 새로 만들 때 원래 일정에서 이어받는 의뢰자 식별 정보 — 결제·청구 연결 키는 복사하지 않는다 */
    private const REQUEST_DATA_CARRY = ['client_id', 'project_id', 'name', 'phone', 'nickname', 'platform'];

    /** @var Collection<int, Estimate>|null */
    private ?Collection $estimates = null;

    /** @var Collection<int, Product>|null */
    private ?Collection $products = null;

    /** @var array<string, int> 세트 구성품 이름 → 제품 id */
    private array $productIdsByName = [];

    /** 대기 일정 목록 + 계산 결과 (정렬은 6장: 단계 → 기한 → 재방문 → 대기 시작일) */
    public function index(): JsonResponse
    {
        $benches = ScheduleBench::where('status', 'waiting')->get();
        $this->preload($benches);
        $items = BenchCalculator::sort($benches->map(fn (ScheduleBench $b): array => $this->payload($b))->all());

        return response()->json([
            'today' => $this->today(),
            'items' => $items,
            'total' => count($items),
            'urgent' => count(array_filter($items, fn (array $i): bool => $i['urgent'])),
        ]);
    }

    /** 상세 — 목록 항목 + 같은 지역 일정 (사실만, 순위·점수 없음) */
    public function show(ScheduleBench $bench): JsonResponse
    {
        $this->preload(collect([$bench]));
        $item = $this->payload($bench);

        return response()->json($item + ['same_area' => $this->sameAreaSchedules($bench, $item)]);
    }

    /** 일정을 대기함으로 (보류·재방문) */
    public function send(Request $request, Schedule $schedule): JsonResponse
    {
        $validated = $request->validate([
            'kind' => 'required|in:hold,revisit',
            'reason' => 'required|string|max:2000',
            'estimate_ids' => 'nullable|array',
            'estimate_ids.*' => 'integer|exists:estimates,id',
            'window_type' => 'required|in:asap,after',
            'window_from' => 'nullable|required_if:window_type,after|date_format:Y-m-d',
            'due_date' => 'nullable|date_format:Y-m-d',
            'block_condition' => 'nullable|string|max:300',
        ], ['reason.required' => '내용을 적어 주세요. 다시 배치할 때 판단 근거가 됩니다.']);

        if ($msg = $this->sendError($schedule, $validated['kind'])) {
            return response()->json(['message' => $msg], 422);
        }

        $bench = DB::transaction(function () use ($schedule, $validated, $request): ScheduleBench {
            $isRevisit = $validated['kind'] === 'revisit';
            // 같은 일정을 동시에 두 번 보류하면 대기 카드가 두 장 생긴다 — 잠그고, 이미 대기함에 있으면(기본 조회에서 빠짐) 막는다
            abort_unless($isRevisit || Schedule::whereKey($schedule->id)->lockForUpdate()->exists(), 409, '이미 대기함에 있는 일정입니다. 새로고침해 주세요.');
            $bench = ScheduleBench::create([
                'kind' => $validated['kind'],
                'schedule_id' => $schedule->id,
                'origin_date' => $schedule->start_date->format('Y-m-d'),
                'title' => $isRevisit ? preg_replace('/( 재방문)?$/u', ' 재방문', $schedule->title, 1) : $schedule->title,
                'color' => $schedule->color,
                'address' => $schedule->address,
                'assignee_ids' => $schedule->assignees()->pluck('assignees.id')->all(),
                'reason' => trim($validated['reason']),
                'window_type' => $validated['window_type'],
                'window_from' => $validated['window_type'] === 'after' ? $validated['window_from'] : null,
                'due_date' => $validated['due_date'] ?? null,
                'block_condition' => trim((string) ($validated['block_condition'] ?? '')) ?: null,
                // 견적서를 따로 고르지 않았으면 일정에 이미 연동된 견적서를 이어받는다
                'estimate_ids' => $request->has('estimate_ids')
                    ? array_values($validated['estimate_ids'] ?? [])
                    : EstimatePaymentSync::scheduleEstimateIds($schedule->request_data ?? []),
                'created_by' => Auth::id(),
            ]);
            if (! $isRevisit) {
                $schedule->update(['bench_id' => $bench->id]); // 보류: 달력에서 빠지고 흔적만 남는다
            }
            $this->log($schedule, 'benched', '대기함으로 보냄('.self::KIND_LABELS[$bench->kind].'): '.$bench->reason, [
                'bench' => ['kind' => self::KIND_LABELS[$bench->kind], 'reason' => $bench->reason],
            ]);

            return $bench;
        });

        return $this->itemResponse($bench, 201);
    }

    /** 날짜 미정 대기 일정을 일정 없이 바로 만들기 */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'color' => ['required', Rule::in(array_keys(CalendarCategory::map()))],
            'reason' => 'required|string|max:2000',
            'address' => 'nullable|string|max:300',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'integer|exists:assignees,id',
            'estimate_ids' => 'nullable|array',
            'estimate_ids.*' => 'integer|exists:estimates,id',
            'window_type' => 'required|in:asap,after,between',
            'window_from' => 'nullable|required_unless:window_type,asap|date_format:Y-m-d',
            'window_to' => 'nullable|required_if:window_type,between|date_format:Y-m-d|after_or_equal:window_from',
            'due_date' => 'nullable|date_format:Y-m-d',
            'block_condition' => 'nullable|string|max:300',
        ], ['reason.required' => '내용을 적어 주세요. 다시 배치할 때 판단 근거가 됩니다.']);

        $bench = ScheduleBench::create([
            'kind' => 'undated',
            'title' => $validated['title'],
            'color' => $validated['color'],
            'address' => $validated['address'] ?? null,
            'assignee_ids' => array_values($validated['assignee_ids'] ?? []),
            'reason' => trim($validated['reason']),
            'window_type' => $validated['window_type'],
            'window_from' => $validated['window_type'] === 'asap' ? null : $validated['window_from'],
            'window_to' => $validated['window_type'] === 'between' ? $validated['window_to'] : null,
            'due_date' => $validated['due_date'] ?? null,
            'block_condition' => trim((string) ($validated['block_condition'] ?? '')) ?: null,
            'estimate_ids' => array_values($validated['estimate_ids'] ?? []),
            'created_by' => Auth::id(),
        ]);

        return $this->itemResponse($bench, 201);
    }

    /** 창고 확인 — 전산 재고와 실재고가 다를 수 있어 담당자가 직접 확인한다. result를 비우면 전산 재고로 되돌린다 */
    public function stockCheck(Request $request, ScheduleBench $bench, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'result' => ['nullable', Rule::in([BenchCalculator::CHECK_IN_WAREHOUSE, BenchCalculator::CHECK_MISSING])],
        ]);
        $this->abortUnlessWaiting($bench);

        $checks = $bench->stock_checks ?? [];
        if (empty($validated['result'])) {
            unset($checks[$product->id]);
        } else {
            $checks[$product->id] = ['result' => $validated['result'], 'by' => Auth::id(), 'at' => now()->toIso8601String()];
        }
        $bench->update(['stock_checks' => $checks]);

        return $this->itemResponse($bench);
    }

    /** 준비 가능 답변 — 주문 소요일이 없는 제품을 3일 안에 준비할 수 있는지. answer를 비우면 답변 취소 */
    public function prepAnswer(Request $request, ScheduleBench $bench, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'answer' => 'nullable|in:ok,no',
            'expected_date' => 'nullable|date_format:Y-m-d|after:today',
        ], ['expected_date.after' => '준비 예상일은 내일 이후로 넣어 주세요.']);
        $this->abortUnlessWaiting($bench);

        $answers = $bench->prep_answers ?? [];
        if (empty($validated['answer'])) {
            unset($answers[$product->id]);
        } else {
            $answers[$product->id] = [
                'ok' => $validated['answer'] === 'ok',
                'expected_date' => $validated['answer'] === 'no' ? ($validated['expected_date'] ?? null) : null,
                'by' => Auth::id(),
                'at' => now()->toIso8601String(),
            ];
        }
        $bench->update(['prep_answers' => $answers]);

        return $this->itemResponse($bench);
    }

    /** 배치 전 조건 해결 */
    public function resolveBlock(ScheduleBench $bench): JsonResponse
    {
        $this->abortUnlessWaiting($bench);
        $bench->update(['block_resolved_at' => now()]);

        return $this->itemResponse($bench);
    }

    /** 배치 폼용 미리 검사 — 차단·알림·안내를 화면이 서버와 같은 규칙으로 보여 준다 */
    public function check(Request $request, ScheduleBench $bench): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'integer',
        ]);
        $this->preload(collect([$bench]));

        return response()->json($this->placementCheck($bench, $validated['date'], $validated['assignee_ids'] ?? []));
    }

    /**
     * 배치 — 서버에서 5~7장 검사를 다시 한다.
     * 차단 조건이면 422, 알림이 있는데 무시 사유가 없으면 409.
     */
    public function place(Request $request, ScheduleBench $bench): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'time' => 'nullable|date_format:H:i',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'integer|exists:assignees,id',
            'sched_opt' => 'required|in:confirmed,suggest,hope,target',
            'override_reason' => 'nullable|string|max:500',
        ]);
        $this->abortUnlessWaiting($bench);
        $this->preload(collect([$bench]));

        $assigneeIds = array_values($validated['assignee_ids'] ?? []);
        $check = $this->placementCheck($bench, $validated['date'], $assigneeIds);
        if ($check['error']) {
            return response()->json(['message' => $check['error']], 422);
        }
        $overrideReason = trim((string) ($validated['override_reason'] ?? ''));
        if ($check['alerts'] && $overrideReason === '') {
            return response()->json(['message' => '알림을 무시하는 사유를 적어 주세요.', 'alerts' => $check['alerts']], 409);
        }
        $overridden = $check['alerts'] !== [];
        $item = $this->payload($bench);

        $schedule = DB::transaction(function () use ($bench, $validated, $assigneeIds, $overridden): Schedule {
            // 동시에 두 번 배치(더블클릭 등)되면 일정이 두 개 생긴다 — 잠그고 상태를 다시 본다
            abort_unless(ScheduleBench::whereKey($bench->id)->lockForUpdate()->value('status') === 'waiting', 409, '이미 처리된 대기 일정입니다. 새로고침해 주세요.');
            $dates = [
                'start_date' => $validated['date'],
                'end_date' => $validated['date'],
                'start_time' => $validated['time'] ?? null,
                'end_time' => null, // 종료 시간은 받지 않는다 — 작업 시간을 알 수 없다
                'is_all_day' => empty($validated['time']),
                'sched_opt' => $validated['sched_opt'],
                'bench_placed_at' => now(),
                'bench_override' => $overridden,
            ];

            if ($bench->kind === 'hold' && ($schedule = $bench->schedule)) {
                $snapshot = collect($schedule->getAttributes())
                    ->only(['start_date', 'end_date', 'start_time', 'end_time', 'is_all_day', 'sched_opt', 'bench_placed_at', 'bench_override'])
                    ->all() + ['request_data' => $schedule->request_data, 'assignee_ids' => $schedule->assignees()->pluck('assignees.id')->all()];
                // 보낼 때 고른 견적서로 준비를 검사했으니, 일정의 견적서 연결도 그 선택에 맞춘다
                $data = $schedule->request_data ?? [];
                $ids = array_map('intval', $bench->estimate_ids ?? []);
                if ($ids !== EstimatePaymentSync::scheduleEstimateIds($data)) {
                    $data['estimate_ids'] = $ids;
                    if (isset($data['estimate_id'])) {
                        $data['estimate_id'] = $ids[0] ?? null;
                    }
                    $dates['request_data'] = $data;
                }
                $schedule->update($dates + ['bench_id' => null]);
                $schedule->forceFill(['notified_at' => null])->save(); // 사전 알림을 새 날짜 기준으로 다시
            } else {
                $snapshot = null;
                $origin = $bench->schedule;
                $schedule = Schedule::create($dates + [
                    'title' => $bench->title,
                    'color' => $bench->color,
                    'address' => $bench->address,
                    'client_name' => $origin?->client_name,
                    'location' => $origin?->location,
                    'entrance_info' => $origin?->entrance_info,
                    'pet_info' => $origin?->pet_info,
                    'description' => $bench->reason,
                    'request_data' => array_filter(
                        collect($origin?->request_data ?? [])->only(self::REQUEST_DATA_CARRY)->all()
                        + ['estimate_ids' => $bench->estimate_ids ?: null]
                    ) ?: null,
                    'created_by' => Auth::id(),
                ]);
            }
            $schedule->syncAssigneesOrdered($assigneeIds);
            $bench->update([
                'status' => 'placed',
                'placed_schedule_id' => $schedule->id,
                'placed_at' => now(),
                'placed_by' => Auth::id(),
                'undo_snapshot' => $snapshot,
            ]);

            return $schedule;
        });

        // 이력 — 종류와 내용, 견적서와 준비 상태, 대기 일수와 배치일, 무시했다면 알림 전체와 사유
        // 사유 문장은 이력 화면에 그대로 보이고, changes.bench는 같은 내용을 항목별로 보존한다
        $estimateNos = implode(', ', array_map(fn (array $e): string => '#'.$e['display_no'], $item['estimates']));
        $this->log($schedule, $overridden ? 'override' : 'placed',
            '대기함에서 배치('.self::KIND_LABELS[$bench->kind].'): '.$bench->reason
                .($estimateNos !== '' ? ' · 견적서 '.$estimateNos.': '.$item['prep']['label'] : '')
                .' · 대기 '.$item['wait_days'].'일 후 '.BenchCalculator::mdw($validated['date']).' 배치'
                .($overridden ? ' · 알림 무시: '.implode(' / ', $check['alerts']).' · 사유: '.$overrideReason : ''),
            ['bench' => array_filter([
                'kind' => self::KIND_LABELS[$bench->kind],
                'reason' => $bench->reason,
                'estimates' => $estimateNos ?: null,
                'prep' => $item['estimates'] ? $item['prep']['label'] : null,
                'wait_days' => $item['wait_days'],
                'placed_on' => $validated['date'],
                'alerts' => $overridden ? $check['alerts'] : null,
                'override_reason' => $overridden ? $overrideReason : null,
            ], fn ($v): bool => $v !== null)]
        );
        $this->notify($schedule, $bench->kind === 'hold' ? new ScheduleUpdated($schedule) : new ScheduleCreated($schedule));

        return response()->json(['ok' => true, 'schedule_id' => $schedule->id, 'overridden' => $overridden, 'notices' => $check['notices']]);
    }

    /** 대기함에서 빼기 — 보류 일정은 휴지통으로 간다 (되돌리기·휴지통에서 복구 가능) */
    public function destroy(Request $request, ScheduleBench $bench): JsonResponse
    {
        $validated = $request->validate(['reason' => 'nullable|string|max:300']);
        $this->abortUnlessWaiting($bench);

        DB::transaction(function () use ($bench, $validated): void {
            $reason = trim((string) ($validated['reason'] ?? ''));
            if ($bench->kind === 'hold' && ($schedule = $bench->schedule)) {
                $this->log($schedule, 'delete', '대기함에서 뺌'.($reason !== '' ? ': '.$reason : ''), [
                    'snapshot' => collect($schedule->getAttributes())->only([
                        'title', 'start_date', 'end_date', 'start_time', 'end_time',
                        'is_all_day', 'color', 'client_name', 'address', 'location', 'description', 'is_private',
                    ])->all(),
                ]);
                $schedule->update(['bench_id' => null]);
                $schedule->delete();
            }
            $bench->update(['status' => 'removed', 'removed_reason' => $reason ?: null]);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * 되돌리기 — 보내기(waiting)·빼기(removed)·배치(placed)를 직전 상태로.
     * 화면이 알고 있는 상태(from)와 실제 상태가 다르면 409 (다른 사람이 이미 처리).
     */
    public function undo(Request $request, ScheduleBench $bench): JsonResponse
    {
        $validated = $request->validate(['from' => 'required|in:waiting,removed,placed']);
        if ($bench->status !== $validated['from']) {
            return response()->json(['message' => '이미 다른 상태로 바뀌어 되돌릴 수 없습니다. 새로고침해 주세요.'], 409);
        }

        DB::transaction(function () use ($bench): void {
            $holdSchedule = $bench->kind === 'hold'
                ? Schedule::withoutGlobalScope('not_benched')->withTrashed()->find($bench->schedule_id)
                : null;

            if ($bench->status === 'waiting') { // 보내기 취소 — 보류 일정은 원래 날짜로 달력에 돌아온다
                if ($holdSchedule) {
                    $holdSchedule->update(['bench_id' => null]);
                    $this->log($holdSchedule, 'update', '대기함 보내기 취소', null);
                }
                $bench->delete();

                return;
            }

            if ($bench->status === 'removed') {
                if ($holdSchedule) {
                    $holdSchedule->restore();
                    $holdSchedule->update(['bench_id' => $bench->id]);
                    $this->log($holdSchedule, 'restore', '대기함에서 빼기 취소', null);
                }
            } elseif ($holdSchedule) { // 보류 배치 취소 — 배치 전 날짜·시간·담당으로 되돌리고 다시 대기
                $snapshot = $bench->undo_snapshot ?? [];
                $holdSchedule->update(collect($snapshot)->except('assignee_ids')->all() + ['bench_id' => $bench->id]);
                $holdSchedule->syncAssigneesOrdered($snapshot['assignee_ids'] ?? [], notify: false);
                $this->log($holdSchedule, 'update', '대기함 배치 취소', null);
            } elseif ($placed = Schedule::find($bench->placed_schedule_id)) { // 재방문·날짜 미정 배치 취소 — 만든 일정을 휴지통으로
                $this->log($placed, 'delete', '대기함 배치 취소', ['snapshot' => collect($placed->getAttributes())->only(['title', 'start_date', 'end_date', 'color'])->all()]);
                $placed->delete();
            }
            $bench->update([
                'status' => 'waiting', 'removed_reason' => null, 'undo_snapshot' => null,
                'placed_schedule_id' => null, 'placed_at' => null, 'placed_by' => null,
            ]);
        });

        return response()->json(['ok' => true]);
    }

    /** 주소에서 지역(시·군·구) 추출 — 광역 단위(서울·경기도 등)는 건너뛴다. 못 찾으면 null */
    public static function areaOf(?string $address): ?string
    {
        foreach (preg_split('/\s+/u', trim((string) $address)) ?: [] as $token) {
            if (preg_match('/^(서울|부산|대구|인천|광주|대전|울산|세종|경기|강원|충북|충남|전북|전남|경북|경남|제주|충청[남북]|전라[남북]|경상[남북])(특별시|광역시|특별자치시|특별자치도|시|도)?$/u', $token)) {
                continue;
            }

            return preg_match('/^[^\d]+(시|군|구)$/u', $token) ? $token : null;
        }

        return null;
    }

    private function today(): string
    {
        return now()->toDateString();
    }

    private function abortUnlessWaiting(ScheduleBench $bench): void
    {
        abort_unless($bench->status === 'waiting', 409, '이미 처리된 대기 일정입니다. 새로고침해 주세요.');
    }

    /** 대기함으로 보낼 수 없는 일정이면 사유 (1차 범위: 방문·원격의 단독 일정만) */
    private function sendError(Schedule $schedule, string $kind): ?string
    {
        $sendable = [...CalendarCategory::keysByLabel('방문', ['gold']), ...CalendarCategory::keysByLabel('원격', ['teal'])];

        return match (true) {
            ! in_array($schedule->color, $sendable, true) => '방문·원격 일정만 대기함으로 보낼 수 있습니다.',
            $schedule->parent_id !== null || $schedule->children()->exists() => '하위 일정이 있는 장기 일정은 대기함으로 보낼 수 없습니다.',
            filled($schedule->repeat_group) => '반복 일정은 대기함으로 보낼 수 없습니다.',
            filled($schedule->source_type) => '다른 화면에서 연동된 일정은 대기함으로 보낼 수 없습니다.',
            (bool) $schedule->is_private => '비공개 일정은 대기함으로 보낼 수 없습니다.',
            $kind === 'hold' && $schedule->is_locked => '잠긴 일정은 보류할 수 없습니다.',
            $kind === 'hold' && in_array('no_change', $schedule->sched_event_opts ?? [], true) => '일정 변경 불가로 표시된 일정은 보류할 수 없습니다.',
            $kind === 'hold' && $schedule->completed_at !== null => '완료된 일정은 보류할 수 없습니다. 다시 가야 하면 재방문을 골라 주세요.',
            default => null,
        };
    }

    /**
     * 목록·상세를 그릴 때 필요한 견적서와 제품을 한 번에 읽어 둔다 (N+1 방지).
     *
     * @param  Collection<int, ScheduleBench>  $benches
     */
    private function preload(Collection $benches): void
    {
        $estimateIds = $benches->flatMap(fn (ScheduleBench $b): array => $b->estimate_ids ?? [])->unique()->values();
        $this->estimates = Estimate::whereIn('id', $estimateIds)->get()->keyBy('id');
        $productIds = $benches->flatMap(fn (ScheduleBench $b): array => array_keys($this->needs($b) ?? []))->unique()->values();
        $this->products = Product::withTrashed()->with('inventory')->whereIn('id', $productIds)->get()->keyBy('id');
    }

    /**
     * 연결 견적서에서 준비해야 할 제품과 수량 — 같은 제품이 여러 행이면 합친다. 견적서 미연결이면 null.
     * 세트는 구성품 단위로 풀고, 수기 항목·서비스·환불·이미 주문완료된 품목은 뺀다.
     *
     * @return array<int, int>|null product_id => 수량
     */
    private function needs(ScheduleBench $bench): ?array
    {
        $estimates = collect($bench->estimate_ids ?? [])->map(fn ($id) => $this->estimates?->get((int) $id))->filter();
        if ($estimates->isEmpty()) {
            return null;
        }

        $needs = [];
        $add = function (array $item, int $multiplier) use (&$needs): void {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = (int) ($item['qty'] ?? 1) * $multiplier;
            if ($productId && $qty > 0 && empty($item['ordered']) && empty($item['refunded']) && empty($item['is_service'])) {
                $needs[$productId] = ($needs[$productId] ?? 0) + $qty;
            }
        };
        foreach ($estimates as $estimate) {
            foreach ($estimate->product_items ?? [] as $item) {
                $qty = (int) ($item['qty'] ?? 1);
                if ($qty <= 0 || ! empty($item['ordered']) || ! empty($item['refunded']) || ! empty($item['is_service'])) {
                    continue; // 세트는 부모 행이 빠지면 구성품도 같이 빠진다
                }
                $components = is_array($item['bundle_items'] ?? null) ? $item['bundle_items'] : [];
                if ($components === []) {
                    $add($item, 1);

                    continue;
                }
                // 세트 구성품 스냅샷은 이름만 있는 경우가 많다 — EstimateStockSync와 같이 제품명으로 찾는다
                foreach ($components as $component) {
                    $component['product_id'] = (int) ($component['product_id'] ?? 0) ?: $this->productIdByName((string) ($component['name'] ?? ''));
                    $add($component, $qty);
                }
            }
        }

        return $needs;
    }

    private function productIdByName(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }

        return $this->productIdsByName[$name] ??= (int) (Product::where('name', $name)->value('id') ?? 0);
    }

    /**
     * 계산기에 넘길 제품 줄 — 전산 재고·주문 소요일·창고 확인·준비 답변.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function lines(ScheduleBench $bench): ?array
    {
        $needs = $this->needs($bench);
        if ($needs === null) {
            return null;
        }

        $lines = [];
        foreach ($needs as $productId => $qty) {
            $product = $this->products?->get($productId);
            $answer = $bench->prep_answers[$productId] ?? null;
            $lines[] = [
                'product_id' => $productId,
                'name' => $product?->name ?? '삭제된 제품 #'.$productId,
                'qty' => $qty,
                'stock' => max(0, (int) ($product?->inventory?->quantity ?? 0)),
                'reserved' => 0, // shortcut: 다른 일정에 배정된 수량 개념이 아직 없어 0, 재고 예약 기능이 생기면 여기서 뺀다
                'lead_days' => $product?->order_lead_days,
                'check' => $bench->stock_checks[$productId]['result'] ?? null,
                'answer' => $answer ? ['ok' => (bool) $answer['ok'], 'expected_date' => $answer['expected_date'] ?? null] : null,
            ];
        }

        return $lines;
    }

    /** @return array<string, mixed> */
    private function calcInput(ScheduleBench $bench): array
    {
        return [
            'kind' => $bench->kind,
            'created_on' => $bench->created_at->toDateString(),
            'due_date' => $bench->due_date?->format('Y-m-d'),
            'window_type' => $bench->window_type,
            'window_from' => $bench->window_from?->format('Y-m-d'),
            'window_to' => $bench->window_to?->format('Y-m-d'),
            'blocked' => $bench->isBlocked(),
        ];
    }

    /**
     * 화면에 내려주는 대기 일정 한 건 — 저장된 값 + 계산 결과.
     *
     * @return array<string, mixed>
     */
    private function payload(ScheduleBench $bench): array
    {
        $input = $this->calcInput($bench);
        $assigneeIds = array_map('intval', $bench->assignee_ids ?? []);

        return [
            'id' => $bench->id,
            'title' => $bench->title,
            'color' => $bench->color,
            'schedule_id' => $bench->schedule_id,
            'origin_date' => $bench->origin_date?->format('Y-m-d'),
            'reason' => $bench->reason,
            'area' => self::areaOf($bench->address),
            'assignee_ids' => $assigneeIds,
            'assignee_names' => Assignee::whereIn('id', $assigneeIds)->pluck('name', 'id')->only($assigneeIds)->values()->all(),
            'block_condition' => $bench->isBlocked() ? $bench->block_condition : null,
            'answers' => (object) ($bench->prep_answers ?? []),
            'estimates' => collect($bench->estimate_ids ?? [])
                ->map(fn ($id) => $this->estimates?->get((int) $id))->filter()
                ->map(fn (Estimate $e): array => ['id' => $e->id, 'display_no' => $e->display_no, 'title' => $e->title ?: $e->client_name])
                ->values()->all(),
        ] + $input + BenchCalculator::evaluate($input, $this->lines($bench), $this->today());
    }

    private function itemResponse(ScheduleBench $bench, int $status = 200): JsonResponse
    {
        $this->preload(collect([$bench]));

        return response()->json($this->payload($bench), $status);
    }

    /**
     * 배치 검사 — 계산기 결과에 담당자 휴가 안내를 덧붙인다.
     *
     * @param  array<int, int|string>  $assigneeIds
     * @return array{error:?string, alerts:array<int, string>, notices:array<int, string>}
     */
    private function placementCheck(ScheduleBench $bench, string $date, array $assigneeIds): array
    {
        $input = $this->calcInput($bench);
        $today = $this->today();
        $check = BenchCalculator::checkPlacement($input, BenchCalculator::evaluate($input, $this->lines($bench), $today), $date, $today);

        if ($check['error'] === null && $assigneeIds) {
            $onLeave = Assignee::whereIn('id', $assigneeIds)
                ->whereHas('schedules', fn ($q) => $q
                    ->whereIn('color', CalendarCategory::keysByLabel('휴가', ['red']))
                    ->where('start_date', '<=', $date)->where('end_date', '>=', $date))
                ->pluck('name');
            foreach ($onLeave as $name) {
                $check['notices'][] = $name.' 휴가일입니다.';
            }
        }

        return $check;
    }

    /**
     * 같은 지역 일정 (7.3) — 오늘 이후 ~ (마감일 또는 오늘+35일)+7일, 날짜순. 순위·점수 없음.
     *
     * @param  array<string, mixed>  $item  payload() 결과
     * @return array<int, array<string, mixed>>
     */
    private function sameAreaSchedules(ScheduleBench $bench, array $item): array
    {
        if ($item['area'] === null || $item['blocked']) {
            return [];
        }
        $today = $this->today();
        $until = BenchCalculator::addDays($item['latest'] ?? BenchCalculator::addDays($today, 35), 7);
        $labels = ['ok' => '', 'lead' => '제품 준비 전', 'early' => '희망 시점 전', 'late' => '기한 이후'];

        return Schedule::with('assignees')->topLevel()
            ->where('start_date', '>', $today)->where('start_date', '<=', $until)
            ->whereNotNull('address')->where('is_private', false)
            ->orderBy('start_date')->orderBy('start_time')
            ->get()
            ->filter(fn (Schedule $s): bool => self::areaOf($s->address) === $item['area'])
            ->map(function (Schedule $s) use ($item, $labels): array {
                $date = $s->start_date->format('Y-m-d');
                $status = BenchCalculator::dateStatus($item, $date);

                return [
                    'id' => $s->id,
                    'date' => $date,
                    'date_label' => BenchCalculator::mdw($date),
                    'start_time' => $s->is_all_day ? null : substr((string) $s->start_time, 0, 5),
                    'end_time' => $s->is_all_day || ! $s->end_time ? null : substr((string) $s->end_time, 0, 5),
                    'title' => $s->title,
                    'assignee_ids' => $s->assignees->pluck('id')->all(),
                    'assignee_names' => $s->assignees->pluck('name')->all(),
                    'status' => $status,
                    'status_label' => $labels[$status],
                ];
            })->values()->all();
    }

    /** @param  array<string, mixed>|null  $changes */
    private function log(Schedule $schedule, string $action, string $reason, ?array $changes): void
    {
        ScheduleChange::create([
            'schedule_id' => $schedule->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'changes' => $changes,
            'reason' => $reason,
        ]);
    }

    /** 알림 대상에게 웹푸시 — 발송 실패가 배치를 막지 않도록 보호 (CalendarController와 같은 규칙) */
    private function notify(Schedule $schedule, Notification $notification): void
    {
        try {
            if ($schedule->assignees()->count() === 0 && empty($schedule->notify_assignees)) {
                return;
            }
            foreach ($schedule->notificationRecipients() as $user) {
                $user->notify($notification);
            }
        } catch (\Throwable $e) {
            Log::warning('대기함 배치 푸시 발송 실패: '.$e->getMessage());
        }
    }
}
