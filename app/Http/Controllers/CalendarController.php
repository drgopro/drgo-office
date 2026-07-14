<?php

namespace App\Http\Controllers;

use App\Models\BroadcastRoomContract;
use App\Models\BroadcastRoomUsage;
use App\Models\CalendarCategory;
use App\Models\Client;
use App\Models\RentalContract;
use App\Models\Schedule;
use App\Models\ScheduleChange;
use App\Notifications\ScheduleCreated;
use App\Notifications\ScheduleUpdated;
use App\Services\ContractCalendarSync;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CalendarController extends Controller
{
    // 캘린더 메인 뷰 — 첫 화면(이번 달 42칸)의 이벤트를 서버에서 주입해 초기 API 왕복 제거
    public function index()
    {
        $first = now()->startOfMonth();
        $gridStart = $first->copy()->subDays($first->dayOfWeek); // 일요일 시작
        $gridEnd = $gridStart->copy()->addDays(41);

        return view('calendar.index', [
            'initialEvents' => $this->eventsBetween($gridStart->format('Y-m-d'), $gridEnd->format('Y-m-d')),
        ]);
    }

    // 일정 목록 API (월별 조회)
    public function events(Request $request)
    {
        return response()->json(
            $this->eventsBetween($request->query('start'), $request->query('end'))
        );
    }

    /**
     * 기간 내 일정 조회 (비공개 필터 + guest 마스킹 포함) — index/events 공용.
     */
    private function eventsBetween(?string $start, ?string $end)
    {
        $events = Schedule::with('assignees')
            ->withCount([
                'shipments',
                'shipments as shipments_delivered_count' => fn ($q) => $q->where('status', 'delivered'),
            ])
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('start_date', '<=', $start)
                            ->where('end_date', '>=', $end);
                    });
            })
            ->where(function ($q) {
                $q->where('is_private', false)
                    ->orWhere('created_by', Auth::id());
            })
            ->get();

        // guest: 지역 + 시간만 노출
        if (Auth::user()->isGuest()) {
            $events = $events->map(fn ($e) => [
                'id' => $e->id,
                'start_date' => $e->start_date,
                'end_date' => $e->end_date,
                'start_time' => $e->start_time,
                'end_time' => $e->end_time,
                'is_all_day' => $e->is_all_day,
                'location' => $e->location,
                'color' => $e->color,
                'completed_at' => $e->completed_at,
            ]);
        }

        return $events;
    }

    // 일정 검색 (제목/의뢰자/장소/주소, 전체 기간)
    public function search(Request $request)
    {
        // 게스트는 일정 내용 비노출 — 검색 불가
        if (Auth::user()->isGuest()) {
            return response()->json([]);
        }

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }

        $limit = max(1, min((int) $request->query('limit', 30), 100));

        $like = '%'.$q.'%';
        $events = Schedule::where(function ($w) use ($like) {
            $w->where('title', 'like', $like)
                ->orWhere('client_name', 'like', $like)
                ->orWhere('location', 'like', $like)
                ->orWhere('address', 'like', $like);
        })
            ->where(function ($p) {
                $p->where('is_private', false)
                    ->orWhere('created_by', Auth::id());
            })
            ->orderByDesc('start_date')
            ->limit($limit)
            ->get(['id', 'title', 'start_date', 'end_date', 'start_time', 'end_time', 'is_all_day', 'color', 'client_name', 'location', 'completed_at']);

        return response()->json($events);
    }

    // 일정 저장
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'is_all_day' => 'boolean',
            'exclude_weekends' => 'boolean',
            'ship_icon_override' => 'nullable|in:all,part,none',
            'color' => 'required|string|max:30',
            'client_name' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:300',
            'location' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'special_note' => 'nullable|string|max:2000',
            'handover_note' => 'nullable|string|max:2000',
            'is_private' => 'boolean',
            'assignees' => 'nullable|array',
            'notify_assignees' => 'nullable|array',
            'gold_data' => 'nullable|array',
            'teal_data' => 'nullable|array',
            'special_opts' => 'nullable|array',
            'sched_opt' => 'nullable|string|max:50',
            'sched_event_opts' => 'nullable|array',
            'sched_after_days' => 'nullable|integer',
            'sched_after_date' => 'nullable|date',
            'sched_after_reason' => 'nullable|string|max:300',
            'notif_minutes' => 'nullable|string|max:10',
            'is_locked' => 'boolean',
            'repeat_freq' => 'nullable|in:daily,weekly,monthly,custom',
            'repeat_interval' => 'nullable|integer|min:1|max:99',
            'repeat_unit' => 'nullable|in:day,week,month',
            'repeat_until' => 'nullable|date|after:start_date|required_with:repeat_freq',
            'broadcast_rental' => 'nullable|array',
            'broadcast_rental.mode' => 'required_with:broadcast_rental|in:hourly,monthly,rental',
            'broadcast_rental.room_no' => 'nullable|string|max:20',
            'broadcast_rental.fee' => 'nullable|integer|min:0',
        ], [
            'repeat_until.required_with' => '반복 종료일을 선택해주세요.',
            'repeat_until.after' => '반복 종료일이 시작일보다 늦어야 합니다.',
        ]);

        $repeat = collect($validated)->only(['repeat_freq', 'repeat_interval', 'repeat_unit', 'repeat_until'])->all();
        $brRental = $validated['broadcast_rental'] ?? null;
        $validated = collect($validated)->except(['repeat_freq', 'repeat_interval', 'repeat_unit', 'repeat_until', 'broadcast_rental'])->all();
        $validated = $this->stripClientLinkForExcludedColors($validated, $validated['color'] ?? null);
        $validated['created_by'] = Auth::id();

        // 캘린더 → 방송룸 대여 이력 등록 (체크 시)
        if ($brRental) {
            return $this->storeWithBroadcastRental($validated, $brRental);
        }

        $schedule = Schedule::create($validated);

        // 담당자 연결
        if (! empty($validated['assignees'])) {
            $schedule->assignees()->sync($validated['assignees']);
        }

        if (! empty($repeat['repeat_freq'])) {
            $this->createRepeatOccurrences($schedule, $repeat);
        }

        $this->notifyAssigneesOfCreation($schedule);

        return response()->json($schedule, 201);
    }

    /**
     * 캘린더에서 '대여 이력 등록' 체크로 등록 — 방송룸(월/시간제)·렌탈 페이지와 양방향 연동.
     * - 시간제(hourly): 일정을 표준 제목('{의뢰자} 방송룸 n호실 대여')으로 만들고 시간 대여 이력을 연결
     * - 월대여(monthly)/렌탈(rental): 폼의 시작~종료일로 계약을 만들고 ContractCalendarSync가
     *   표준 일정을 생성 — 폼 일정은 따로 만들지 않음
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $brRental
     */
    private function storeWithBroadcastRental(array $validated, array $brRental)
    {
        $clientId = data_get($validated, 'gold_data.client_id');
        if (! $clientId) {
            return response()->json([
                'message' => '대여 이력 등록에는 의뢰자 연동이 필요합니다.',
                'errors' => ['broadcast_rental' => ['의뢰자를 먼저 연동해주세요.']],
            ], 422);
        }
        $client = Client::find($clientId);
        $titleName = $client ? ($client->nickname ?: $client->name) : '';
        $roomLabel = ! empty($brRental['room_no']) ? "방송룸 {$brRental['room_no']}호실" : '방송룸';

        if (in_array($brRental['mode'], ['monthly', 'rental'], true)) {
            $contract = DB::transaction(function () use ($validated, $brRental, $clientId) {
                $attrs = [
                    'client_id' => $clientId,
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'] !== $validated['start_date'] ? $validated['end_date'] : null,
                    'monthly_fee' => (int) ($brRental['fee'] ?? 0),
                    'status' => 'active',
                    'memo' => $validated['description'] ?? null,
                ];
                $contract = $brRental['mode'] === 'rental'
                    ? RentalContract::create($attrs)
                    : BroadcastRoomContract::create($attrs + ['room_no' => $brRental['room_no'] ?? null]);
                app(ContractCalendarSync::class)->sync($contract);

                return $contract;
            });

            // 프론트가 첨부 업로드 등에 쓰도록 동기화로 생성된 시작 일정을 반환
            return response()->json(Schedule::find($contract->fresh()->calendar_meta['start_id']), 201);
        }

        // 시간제 — 종일 일정으로는 시간 계산이 불가
        if (! empty($validated['is_all_day']) || empty($validated['start_time']) || empty($validated['end_time'])) {
            return response()->json([
                'message' => '시간 대여 등록에는 시작/종료 시간이 필요합니다.',
                'errors' => ['broadcast_rental' => ['종일이 아닌 시간 지정 일정으로 등록해주세요.']],
            ], 422);
        }

        $schedule = DB::transaction(function () use ($validated, $brRental, $clientId, $titleName, $roomLabel) {
            $validated['title'] = trim(($titleName ? $titleName.' ' : '')."{$roomLabel} 대여");
            $schedule = Schedule::create($validated);
            if (! empty($validated['assignees'])) {
                $schedule->assignees()->sync($validated['assignees']);
            }

            $start = Carbon::parse($validated['start_date'].' '.$validated['start_time']);
            $end = Carbon::parse($validated['end_date'].' '.$validated['end_time']);
            BroadcastRoomUsage::create([
                'client_id' => $clientId,
                'room_no' => $brRental['room_no'] ?? null,
                'used_date' => $start->toDateString(),
                'start_at' => $start,
                'end_at' => $end,
                'hours' => round($start->diffInMinutes($end) / 60, 2),
                'fee' => (int) ($brRental['fee'] ?? 0),
                'memo' => $validated['description'] ?? null,
                'schedule_id' => $schedule->id,
            ]);

            return $schedule;
        });

        $this->notifyAssigneesOfCreation($schedule);

        return response()->json($schedule, 201);
    }

    /** 의뢰자 연동을 지원하지 않는 카테고리 — 사내업무(blue)/휴가·개인(red). 프론트 setColor 규칙과 동일 */
    public const CLIENT_LINK_EXCLUDED_COLORS = ['blue', 'red'];

    /**
     * 연동 미지원 카테고리에는 의뢰자 이름/연결 데이터를 저장하지 않음.
     * 과거 프론트 상태 누수로 오염된 client_name·gold_data가 저장 시마다 재기록되던 문제 차단.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function stripClientLinkForExcludedColors(array $validated, ?string $color): array
    {
        if (in_array($color, self::CLIENT_LINK_EXCLUDED_COLORS, true)) {
            $validated['client_name'] = null;
            $validated['gold_data'] = null;
        }

        return $validated;
    }

    /**
     * 반복 일정 생성 — 기준 일정을 종료일까지 복제 (최대 60회, 그룹 uuid 공유).
     *
     * @param  array{repeat_freq:string, repeat_interval?:int|string|null, repeat_unit?:string|null, repeat_until:string}  $repeat
     */
    private function createRepeatOccurrences(Schedule $base, array $repeat): void
    {
        $group = (string) Str::uuid();
        // 반복 설정을 기준 일정에 저장 → 편집 시 현재 설정 표시/재조정 가능. replicate로 반복 생성분에도 복사됨
        $base->update([
            'repeat_group' => $group,
            'repeat_freq' => $repeat['repeat_freq'],
            'repeat_interval' => $repeat['repeat_freq'] === 'custom' ? max(1, (int) ($repeat['repeat_interval'] ?? 1)) : null,
            'repeat_unit' => $repeat['repeat_freq'] === 'custom' ? ($repeat['repeat_unit'] ?? 'day') : null,
            'repeat_until' => $repeat['repeat_until'],
        ]);

        $start = Carbon::parse($base->start_date->format('Y-m-d'));
        $spanDays = $start->diffInDays(Carbon::parse($base->end_date->format('Y-m-d')));
        $until = Carbon::parse($repeat['repeat_until'])->min($start->copy()->addYear());
        $interval = max(1, (int) ($repeat['repeat_interval'] ?? 1));
        $assigneeIds = $base->assignees()->pluck('assignees.id')->all();

        for ($i = 1; $i <= 60; $i++) {
            $next = match ($repeat['repeat_freq']) {
                'daily' => $start->copy()->addDays($i),
                'weekly' => $start->copy()->addWeeks($i),
                'monthly' => $start->copy()->addMonthsNoOverflow($i),
                'custom' => match ($repeat['repeat_unit'] ?? 'day') {
                    'week' => $start->copy()->addWeeks($i * $interval),
                    'month' => $start->copy()->addMonthsNoOverflow($i * $interval),
                    default => $start->copy()->addDays($i * $interval),
                },
            };
            if ($next->gt($until)) {
                break;
            }

            $copy = $base->replicate(['notified_at', 'completed_at']);
            $copy->start_date = $next->format('Y-m-d');
            $copy->end_date = $next->copy()->addDays($spanDays)->format('Y-m-d');
            $copy->repeat_group = $group;
            $copy->save();

            if ($assigneeIds) {
                $copy->assignees()->sync($assigneeIds);
            }
        }
    }

    /** 등록 알림 — 담당자로 연결된 사용자에게 웹푸시 (발송 실패가 저장을 막지 않도록 보호) */
    private function notifyAssigneesOfCreation(Schedule $schedule): void
    {
        try {
            // 담당자도 알림 대상 지정도 없으면 발송 안 함 (등록자 본인 셀프 알림 방지)
            if ($schedule->assignees()->count() === 0 && empty($schedule->notify_assignees)) {
                return;
            }
            foreach ($schedule->notificationRecipients() as $user) {
                $user->notify(new ScheduleCreated($schedule));
            }
        } catch (\Throwable $e) {
            Log::warning('일정 등록 푸시 발송 실패: '.$e->getMessage());
        }
    }

    /** 날짜/시간 변경 알림 — 알림 대상에게 웹푸시 (발송 실패가 저장을 막지 않도록 보호) */
    private function notifyScheduleChanged(Schedule $schedule): void
    {
        try {
            if ($schedule->assignees()->count() === 0 && empty($schedule->notify_assignees)) {
                return;
            }
            foreach ($schedule->notificationRecipients() as $user) {
                $user->notify(new ScheduleUpdated($schedule));
            }
        } catch (\Throwable $e) {
            Log::warning('일정 변경 푸시 발송 실패: '.$e->getMessage());
        }
    }

    // 일정 수정
    public function update(Request $request, Schedule $schedule)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'is_all_day' => 'boolean',
            'exclude_weekends' => 'boolean',
            'ship_icon_override' => 'nullable|in:all,part,none',
            'color' => 'sometimes|string|max:30',
            'client_name' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:300',
            'location' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'special_note' => 'nullable|string|max:2000',
            'handover_note' => 'nullable|string|max:2000',
            'is_private' => 'boolean',
            'assignees' => 'nullable|array',
            'gold_data' => 'nullable|array',
            'teal_data' => 'nullable|array',
            'special_opts' => 'nullable|array',
            'sched_opt' => 'nullable|string|max:50',
            'sched_event_opts' => 'nullable|array',
            'sched_after_days' => 'nullable|integer',
            'sched_after_date' => 'nullable|date',
            'sched_after_reason' => 'nullable|string|max:300',
            'notif_minutes' => 'nullable|string|max:10',
            'is_locked' => 'boolean',
            'notify_assignees' => 'nullable|array',
            'reason' => 'nullable|string|max:500',
            'repeat_freq' => 'nullable|in:daily,weekly,monthly,custom',
            'repeat_interval' => 'nullable|integer|min:1|max:99',
            'repeat_unit' => 'nullable|in:day,week,month',
            'repeat_until' => 'nullable|date|required_with:repeat_freq',
        ], [
            'repeat_until.required_with' => '반복 종료일을 선택해주세요.',
        ]);

        // schedule 업데이트에는 reason 포함하지 않음 (변경 이력에만 기록)
        $reason = $validated['reason'] ?? null;
        unset($validated['reason']);

        // 반복 파라미터 분리 — 이미 반복 그룹에 속한 일정은 중복 생성 방지를 위해 무시
        $repeat = collect($validated)->only(['repeat_freq', 'repeat_interval', 'repeat_unit', 'repeat_until'])->all();
        $validated = collect($validated)->except(['repeat_freq', 'repeat_interval', 'repeat_unit', 'repeat_until'])->all();
        $validated = $this->stripClientLinkForExcludedColors($validated, $validated['color'] ?? $schedule->color);

        // 변경 이력 기록 (날짜/시간은 포맷 차이로 인한 오탐 방지를 위해 정규화 후 비교)
        $dtChanged = false;
        $diff = [];
        foreach ($validated as $key => $newVal) {
            if (in_array($key, ['assignees', 'notify_assignees'], true)) {
                continue;
            }
            $oldVal = $schedule->getOriginal($key);
            if ($this->normalizeForDiff($key, $oldVal) !== $this->normalizeForDiff($key, $newVal)) {
                $diff[$key] = ['old' => $oldVal, 'new' => $newVal];
            }
        }
        if (! empty($diff)) {
            // 변경 사유: 방문의뢰(gold)·원격/방송룸(teal)에서 날짜/시간이 바뀔 때만 필수
            $dtKeys = ['start_date', 'end_date', 'start_time', 'end_time', 'is_all_day'];
            $dtChanged = array_intersect($dtKeys, array_keys($diff)) !== [];
            $reasonColor = $validated['color'] ?? $schedule->color;
            $needsReason = in_array($reasonColor, ['gold', 'teal'], true) && $dtChanged;
            if ($needsReason && empty(trim((string) $reason))) {
                return response()->json([
                    'message' => '일정(날짜/시간) 변경 사유를 입력해주세요.',
                    'errors' => ['reason' => ['날짜/시간 변경 시 사유는 필수입니다.']],
                ], 422);
            }
            ScheduleChange::create([
                'schedule_id' => $schedule->id,
                'user_id' => Auth::id(),
                'action' => 'update',
                'changes' => $diff,
                'reason' => trim((string) $reason) ?: null,
            ]);
        }

        $schedule->update($validated);

        if (isset($validated['assignees'])) {
            $schedule->assignees()->sync($validated['assignees']);
        }

        // 날짜/시간이 바뀌면 사전 알림을 새 시각 기준으로 다시 발송 + 변경 즉시 알림
        if ($dtChanged) {
            $schedule->forceFill(['notified_at' => null])->save();
            $this->notifyScheduleChanged($schedule);
        }

        // 반복 그룹: 카테고리 변경은 이 일정 이후의 반복 회차에도 전파 (지난 회차는 그대로)
        if ($schedule->repeat_group && isset($diff['color']) && isset($validated['color'])) {
            Schedule::where('repeat_group', $schedule->repeat_group)
                ->where('id', '!=', $schedule->id)
                ->where('start_date', '>=', $schedule->start_date->format('Y-m-d'))
                ->update(['color' => $validated['color']]);
        }

        if (! empty($repeat['repeat_freq']) && ! $schedule->repeat_group) {
            $schedule->refresh();
            if (Carbon::parse($repeat['repeat_until'])->lte(Carbon::parse($schedule->start_date->format('Y-m-d')))) {
                return response()->json([
                    'message' => '반복 종료일이 시작일보다 늦어야 합니다.',
                    'errors' => ['repeat_until' => ['반복 종료일이 시작일보다 늦어야 합니다.']],
                ], 422);
            }
            $this->createRepeatOccurrences($schedule, $repeat);
        } elseif (! empty($repeat['repeat_freq']) && $schedule->repeat_group && $this->repeatSettingsChanged($schedule, $repeat)) {
            // 반복 그룹 소속 일정의 주기/종료일 재조정 — 이 일정 이후의 기존 반복을 지우고 새 설정으로 재생성
            $schedule->refresh();
            if (Carbon::parse($repeat['repeat_until'])->lte(Carbon::parse($schedule->start_date->format('Y-m-d')))) {
                return response()->json([
                    'message' => '반복 종료일이 시작일보다 늦어야 합니다.',
                    'errors' => ['repeat_until' => ['반복 종료일이 시작일보다 늦어야 합니다.']],
                ], 422);
            }

            $removed = Schedule::where('repeat_group', $schedule->repeat_group)
                ->where('id', '!=', $schedule->id)
                ->where('start_date', '>', $schedule->start_date->format('Y-m-d'))
                ->get();
            $removed->each->delete(); // 소프트 삭제 — 휴지통에서 복구 가능

            ScheduleChange::create([
                'schedule_id' => $schedule->id,
                'user_id' => Auth::id(),
                'action' => 'update',
                'changes' => ['반복 설정' => [
                    'old' => $this->repeatSummary($schedule->repeat_freq, $schedule->repeat_interval, $schedule->repeat_unit, $schedule->repeat_until?->format('Y-m-d')),
                    'new' => $this->repeatSummary($repeat['repeat_freq'], $repeat['repeat_interval'] ?? null, $repeat['repeat_unit'] ?? null, $repeat['repeat_until']),
                ]],
                'reason' => '반복 설정 변경 — 이후 반복 '.$removed->count().'건 재생성',
            ]);

            $this->createRepeatOccurrences($schedule, $repeat);
        }

        return response()->json($schedule);
    }

    /** 저장된 반복 설정과 요청 설정이 다른지 (custom이 아닐 땐 간격/단위 무시) */
    private function repeatSettingsChanged(Schedule $schedule, array $repeat): bool
    {
        $norm = fn (?string $freq, $interval, ?string $unit, ?string $until) => $freq === 'custom'
            ? [$freq, max(1, (int) ($interval ?: 1)), $unit ?: 'day', $until]
            : [$freq, null, null, $until];

        return $norm($schedule->repeat_freq, $schedule->repeat_interval, $schedule->repeat_unit, $schedule->repeat_until?->format('Y-m-d'))
            !== $norm($repeat['repeat_freq'], $repeat['repeat_interval'] ?? null, $repeat['repeat_unit'] ?? null, $repeat['repeat_until'] ?? null);
    }

    /** 변경 이력용 반복 설정 요약 문자열 */
    private function repeatSummary(?string $freq, $interval, ?string $unit, ?string $until): string
    {
        if (! $freq) {
            return '(반복 설정 없음)';
        }
        $freqLabel = ['daily' => '매일', 'weekly' => '매주', 'monthly' => '매월'][$freq]
            ?? (max(1, (int) ($interval ?: 1)).(['day' => '일', 'week' => '주', 'month' => '개월'][$unit ?? 'day'] ?? '일').'마다');

        return $freqLabel.' · ~'.($until ?: '?');
    }

    // 일정 상세 API
    public function detail(Schedule $schedule)
    {
        $schedule->load('assignees', 'creator');

        return response()->json($schedule);
    }

    // 수정내역 API (소프트삭제된 일정도 조회 가능)
    public function history($id)
    {
        $schedule = Schedule::withTrashed()->findOrFail($id);

        $changes = $schedule->changes()->with('user')->get()->map(fn ($c) => [
            'id' => $c->id,
            'action' => $c->action,
            'changes' => $c->changes,
            'reason' => $c->reason,
            'user_name' => $c->user?->display_name ?? '알 수 없음',
            'created_at' => $c->created_at->format('Y.m.d H:i'),
        ]);

        return response()->json([
            'schedule' => [
                'id' => $schedule->id,
                'title' => $schedule->title,
                'start_date' => $schedule->start_date,
                'end_date' => $schedule->end_date,
                'color' => $schedule->color,
                'completed_at' => $schedule->completed_at,
                'deleted_at' => $schedule->deleted_at,
            ],
            'changes' => $changes,
        ]);
    }

    // 일정 삭제 (soft delete + 이력 기록)
    public function destroy(Request $request, Schedule $schedule)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'scope' => 'nullable|in:one,future',
        ], [
            'reason.required' => '삭제 사유를 입력해주세요.',
        ]);

        // 반복 일정 일괄 삭제 — 이 일정 및 같은 그룹의 이후 일정 전부
        if (($validated['scope'] ?? 'one') === 'future' && $schedule->repeat_group) {
            $targets = Schedule::where('repeat_group', $schedule->repeat_group)
                ->where('start_date', '>=', $schedule->start_date->format('Y-m-d'))
                ->get();
            foreach ($targets as $target) {
                ScheduleChange::create([
                    'schedule_id' => $target->id,
                    'user_id' => Auth::id(),
                    'action' => 'delete',
                    'changes' => ['snapshot' => collect($target->getAttributes())->only([
                        'title', 'start_date', 'end_date', 'start_time', 'end_time',
                        'is_all_day', 'color', 'client_name', 'address', 'location',
                        'description', 'is_private',
                    ])->toArray()],
                    'reason' => $validated['reason'].' (반복 일괄 삭제)',
                ]);
                $target->delete();
            }

            return response()->json(['ok' => true, 'deleted' => $targets->count()]);
        }

        // 삭제 시점의 스냅샷을 changes에 보존
        $snapshot = collect($schedule->getAttributes())
            ->only([
                'title', 'start_date', 'end_date', 'start_time', 'end_time',
                'is_all_day', 'color', 'client_name', 'address', 'location',
                'description', 'is_private',
            ])
            ->toArray();

        ScheduleChange::create([
            'schedule_id' => $schedule->id,
            'user_id' => Auth::id(),
            'action' => 'delete',
            'changes' => ['snapshot' => $snapshot],
            'reason' => $validated['reason'] ?? null,
        ]);

        $schedule->delete(); // SoftDeletes → deleted_at만 set

        return response()->json(['ok' => true]);
    }

    // 완료 토글 (사용자가 명시적으로 ✓ 클릭)
    public function complete(Schedule $schedule)
    {
        $schedule->update(['completed_at' => now()]);

        ScheduleChange::create([
            'schedule_id' => $schedule->id,
            'user_id' => Auth::id(),
            'action' => 'complete',
            'changes' => ['completed_at' => ['old' => null, 'new' => $schedule->completed_at?->toIso8601String()]],
        ]);

        return response()->json(['ok' => true, 'completed_at' => $schedule->completed_at]);
    }

    public function uncomplete(Schedule $schedule)
    {
        $previous = $schedule->completed_at;
        $schedule->update(['completed_at' => null]);

        ScheduleChange::create([
            'schedule_id' => $schedule->id,
            'user_id' => Auth::id(),
            'action' => 'uncomplete',
            'changes' => ['completed_at' => ['old' => $previous?->toIso8601String(), 'new' => null]],
        ]);

        return response()->json(['ok' => true]);
    }

    // === 휴지통 ===

    public function trashed()
    {
        $items = Schedule::onlyTrashed()
            ->with('creator:id,display_name')
            ->orderByDesc('deleted_at')
            ->get(['id', 'title', 'start_date', 'end_date', 'color', 'client_name', 'created_by', 'deleted_at'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'start_date' => $s->start_date,
                'end_date' => $s->end_date,
                'color' => $s->color,
                'client_name' => $s->client_name,
                'creator' => $s->creator?->display_name,
                'deleted_at' => $s->deleted_at?->format('Y-m-d H:i'),
            ]);

        return response()->json($items);
    }

    public function restore($id)
    {
        $schedule = Schedule::onlyTrashed()->findOrFail($id);
        $schedule->restore();

        ScheduleChange::create([
            'schedule_id' => $schedule->id,
            'user_id' => Auth::id(),
            'action' => 'restore',
            'changes' => null,
        ]);

        return response()->json(['ok' => true]);
    }

    public function forceDestroy($id)
    {
        $schedule = Schedule::onlyTrashed()->findOrFail($id);
        $schedule->forceDelete(); // schedule_changes는 cascadeOnDelete로 함께 삭제

        return response()->json(['ok' => true]);
    }

    /**
     * 휴지통 일괄 정리 — 전부(empty) 또는 선택(ids)
     */
    public function emptyTrash(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
        ]);

        $query = Schedule::onlyTrashed();
        if (! empty($validated['ids'])) {
            $query->whereIn('id', $validated['ids']);
        }

        $count = $query->forceDelete();

        return response()->json(['ok' => true, 'deleted' => $count]);
    }

    // === 캘린더 이력 페이지 ===

    public function historyIndex()
    {
        return view('calendar.history');
    }

    /**
     * 캘린더 이력용 이벤트 — 일정당 최종 이력만 노출한다 (전체 로그는 DB에 그대로 보존).
     *
     * - 활성/완료 일정: 현재 위치에 active|completed chip + 마지막 위치 변경 1건만 modified shadow
     * - 삭제 일정: 삭제 시점의 start_date에 deleted shadow만 (과거 변경 흔적 미노출)
     */
    public function historyEvents(Request $request)
    {
        $start = $request->query('start');
        $end = $request->query('end');

        $schedules = Schedule::withTrashed()
            // 관계 기본 정렬(orderByDesc)을 재정의해 오래된 순으로 — 마지막 항목이 최종 변경
            ->with(['changes' => fn ($q) => $q->where('action', 'update')->reorder('created_at')])
            ->withCount('changes')
            ->where(function ($q) {
                $q->where('is_private', false)
                    ->orWhere('created_by', Auth::id());
            })
            ->get();

        $chips = collect();

        foreach ($schedules as $s) {
            $base = [
                'schedule_id' => $s->id,
                'title' => $s->title,
                'color' => $s->color,
                'client_name' => $s->client_name,
                'location' => $s->location,
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
                'is_all_day' => $s->is_all_day,
                'changes_count' => $s->changes_count,
                'completed_at' => $s->completed_at,
                'deleted_at' => $s->deleted_at,
            ];

            // 1. 현재 위치 chip — 삭제되지 않은 경우만 (정상 표시)
            if ($s->deleted_at === null) {
                $chips->push(array_merge($base, [
                    'chip_id' => 'cur-'.$s->id,
                    'display_start_date' => $s->start_date->format('Y-m-d'),
                    'display_end_date' => optional($s->end_date)->format('Y-m-d') ?? $s->start_date->format('Y-m-d'),
                    'state' => $s->completed_at !== null ? 'completed' : 'active',
                    'is_shadow' => false,
                ]));
            }

            // 2. 과거 위치 흔적 — 마지막(최종) 위치 변경 1건만 modified shadow로 표시.
            //    삭제된 일정은 삭제 chip만 노출하고, 이 시점의 start_time/end_time/is_all_day도 함께 복원.
            if ($s->deleted_at === null) {
                $lastShadow = null;
                foreach ($s->changes as $c) {
                    $changes = $c->changes ?? [];
                    if (! isset($changes['start_date']['old'])) {
                        continue;
                    }
                    $oldStart = $this->normalizeDate($changes['start_date']['old']);
                    $oldEnd = isset($changes['end_date']['old'])
                        ? $this->normalizeDate($changes['end_date']['old'])
                        : $oldStart;
                    if (! $oldStart) {
                        continue;
                    }

                    $shadow = array_merge($base, [
                        'chip_id' => 'sh-'.$c->id,
                        'display_start_date' => $oldStart,
                        'display_end_date' => $oldEnd ?? $oldStart,
                        'state' => 'modified',
                        'is_shadow' => true,
                        'change_at' => $c->created_at,
                    ]);
                    // 시간/종일 필드도 변경 이력이 있으면 그 시점의 값 사용
                    if (array_key_exists('start_time', $changes)) {
                        $shadow['start_time'] = $changes['start_time']['old'] ?? null;
                    }
                    if (array_key_exists('end_time', $changes)) {
                        $shadow['end_time'] = $changes['end_time']['old'] ?? null;
                    }
                    if (array_key_exists('is_all_day', $changes)) {
                        $shadow['is_all_day'] = (bool) ($changes['is_all_day']['old'] ?? false);
                    }

                    $lastShadow = $shadow; // created_at 오름차순이므로 마지막 것이 최종 변경
                }
                if ($lastShadow) {
                    $chips->push($lastShadow);
                }
            }

            // 3. 삭제된 경우, 삭제 시점의 위치에 deleted shadow
            if ($s->deleted_at !== null) {
                $chips->push(array_merge($base, [
                    'chip_id' => 'del-'.$s->id,
                    'display_start_date' => $s->start_date->format('Y-m-d'),
                    'display_end_date' => optional($s->end_date)->format('Y-m-d') ?? $s->start_date->format('Y-m-d'),
                    'state' => 'deleted',
                    'is_shadow' => true,
                ]));
            }
        }

        // 표시 기간으로 필터 (display_start ~ display_end가 [start, end]와 겹치는 칩만)
        $filtered = $chips->filter(fn ($c) => $c['display_start_date'] <= $end && $c['display_end_date'] >= $start)
            ->values();

        return response()->json($filtered);
    }

    /**
     * 변경 비교용 정규화 — 날짜(Y-m-d)/시간(H:i)/불리언 포맷 차이로 인한 오탐 방지.
     */
    private function normalizeForDiff(string $key, mixed $val): mixed
    {
        if (in_array($key, ['start_date', 'end_date', 'sched_after_date'], true)) {
            if (! $val) {
                return null;
            }

            return $val instanceof \DateTimeInterface ? $val->format('Y-m-d') : substr((string) $val, 0, 10);
        }
        if (in_array($key, ['start_time', 'end_time'], true)) {
            return $val ? substr((string) $val, 0, 5) : null;
        }
        if (in_array($key, ['is_all_day', 'is_locked', 'is_private'], true)) {
            return (bool) $val;
        }

        return json_encode($val);
    }

    /**
     * ISO datetime 또는 'YYYY-MM-DD' 문자열을 KST 기준 'YYYY-MM-DD'로 정규화.
     */
    private function normalizeDate(mixed $val): ?string
    {
        if (! $val) {
            return null;
        }
        $s = (string) $val;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return $s;
        }
        try {
            return Carbon::parse($s)
                ->setTimezone(config('app.timezone', 'Asia/Seoul'))
                ->format('Y-m-d');
        } catch (\Exception) {
            return substr($s, 0, 10) ?: null;
        }
    }

    // JSON 내보내기
    public function exportJson()
    {
        $events = Schedule::with('assignees')->get();
        $data = $events->map(fn ($e) => [
            'title' => $e->title,
            'start_date' => $e->start_date,
            'end_date' => $e->end_date,
            'start_time' => $e->start_time,
            'end_time' => $e->end_time,
            'is_all_day' => $e->is_all_day,
            'color' => $e->color,
            'client_name' => $e->client_name,
            'address' => $e->address,
            'location' => $e->location,
            'description' => $e->description,
            'special_note' => $e->special_note,
            'handover_note' => $e->handover_note,
            'notif_minutes' => $e->notif_minutes,
            'is_locked' => $e->is_locked,
            'is_private' => $e->is_private,
            'special_opts' => $e->special_opts,
            'sched_opt' => $e->sched_opt,
            'sched_event_opts' => $e->sched_event_opts,
            'sched_after_reason' => $e->sched_after_reason,
            'gold_data' => $e->gold_data,
            'teal_data' => $e->teal_data,
            'assignee_ids' => $e->assignees->pluck('id')->toArray(),
        ]);

        $filename = 'drgo-calendar-'.now()->format('Y-m-d').'.json';

        return response()->json(['events' => $data, 'exported_at' => now()->toIso8601String()])
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    // JSON 가져오기
    public function importJson(Request $request)
    {
        $request->validate(['file' => 'required|file']);

        $ext = strtolower($request->file('file')->getClientOriginalExtension());
        if (! in_array($ext, ['json', 'txt'])) {
            return response()->json(['error' => 'JSON 또는 TXT 파일만 업로드 가능합니다.'], 422);
        }

        $raw = file_get_contents($request->file('file')->getRealPath());
        $content = json_decode($raw, true);
        if (! $content || ! isset($content['events'])) {
            return response()->json(['error' => '올바르지 않은 JSON 형식입니다. events 키가 필요합니다.'], 422);
        }

        $count = 0;
        foreach ($content['events'] as $item) {
            $schedule = Schedule::create([
                'title' => $item['title'] ?? '(제목 없음)',
                'start_date' => $item['start_date'],
                'end_date' => $item['end_date'] ?? $item['start_date'],
                'start_time' => $item['start_time'] ?? null,
                'end_time' => $item['end_time'] ?? null,
                'is_all_day' => $item['is_all_day'] ?? false,
                'color' => $item['color'] ?? 'gold',
                'client_name' => $item['client_name'] ?? null,
                'address' => $item['address'] ?? null,
                'location' => $item['location'] ?? null,
                'description' => $item['description'] ?? null,
                'notif_minutes' => $item['notif_minutes'] ?? null,
                'is_locked' => $item['is_locked'] ?? false,
                'is_private' => $item['is_private'] ?? false,
                'special_opts' => $item['special_opts'] ?? [],
                'sched_opt' => $item['sched_opt'] ?? null,
                'sched_event_opts' => $item['sched_event_opts'] ?? [],
                'sched_after_reason' => $item['sched_after_reason'] ?? null,
                'gold_data' => $item['gold_data'] ?? null,
                'teal_data' => $item['teal_data'] ?? null,
                'created_by' => Auth::id(),
            ]);
            if (! empty($item['assignee_ids'])) {
                $schedule->assignees()->sync($item['assignee_ids']);
            }
            $count++;
        }

        return response()->json(['message' => "{$count}건의 일정을 가져왔습니다.", 'count' => $count]);
    }

    // iCal 내보내기
    public function exportIcal()
    {
        $events = Schedule::all();
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//DrGo Office//Calendar//KO', 'CALSCALE:GREGORIAN'];

        foreach ($events as $e) {
            $uid = "drgo-{$e->id}@drgo-office";
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:{$uid}";
            if ($e->is_all_day) {
                $lines[] = 'DTSTART;VALUE=DATE:'.str_replace('-', '', $e->start_date);
                $endDate = $e->end_date ? date('Ymd', strtotime($e->end_date.' +1 day')) : str_replace('-', '', $e->start_date);
                $lines[] = "DTEND;VALUE=DATE:{$endDate}";
            } else {
                $start = str_replace('-', '', $e->start_date).'T'.str_replace(':', '', $e->start_time ?? '0000').'00';
                $end = str_replace('-', '', $e->end_date ?? $e->start_date).'T'.str_replace(':', '', $e->end_time ?? '2359').'00';
                $lines[] = "DTSTART:{$start}";
                $lines[] = "DTEND:{$end}";
            }
            $lines[] = 'SUMMARY:'.str_replace(["\r", "\n"], ' ', $e->title ?? '');
            if ($e->location) {
                $lines[] = 'LOCATION:'.str_replace(["\r", "\n"], ' ', $e->location);
            }
            if ($e->description) {
                $lines[] = 'DESCRIPTION:'.str_replace(["\r", "\n"], '\\n', $e->description);
            }
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';

        $filename = 'drgo-calendar-'.now()->format('Y-m-d').'.ics';

        return response(implode("\r\n", $lines), 200)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    // iCal 가져오기
    /**
     * iCal(.ics) 가져오기 — TickTick 등 외부 캘린더의 CATEGORIES를 앱 카테고리로 매핑.
     * dry=1이면 저장 없이 매핑 요약만 반환 (프론트에서 미리보기 확인 후 실제 실행).
     */
    public function importIcal(Request $request)
    {
        $request->validate(['file' => 'required|file', 'until' => 'nullable|date']);
        $dry = $request->boolean('dry');
        $until = $request->input('until'); // 이 날짜(포함) 이후에 시작하는 일정은 제외

        $content = file_get_contents($request->file('file')->getRealPath());
        // RFC5545 줄접기(folding) 해제 — 연속줄(공백/탭 시작)을 이전 줄에 이어붙임
        $content = preg_replace('/\r?\n[ \t]/', '', $content);

        $vevents = preg_split('/BEGIN:VEVENT/', $content);
        array_shift($vevents); // 첫 번째는 VCALENDAR 헤더

        $categoryMap = CalendarCategory::map();
        $labelToKey = [];
        foreach ($categoryMap as $key => $c) {
            $labelToKey[$c['label']] = $key;
        }

        // 기존 import_uid 셋 (소프트 삭제 포함) — 재실행 시 중복 방지
        $existingUids = Schedule::withTrashed()->whereNotNull('import_uid')->pluck('import_uid')->flip();

        $summary = [
            'total' => 0, 'imported' => 0, 'duplicates' => 0, 'repaired' => 0, 'skipped_holiday' => 0, 'skipped_after' => 0,
            'rrule_count' => 0, 'by_category' => [], 'unmapped' => [], 'will_create_categories' => [],
        ];
        $rows = [];

        foreach ($vevents as $vevent) {
            $summary['total']++;

            $uid = $this->icalProp($vevent, 'UID');
            if ($uid && isset($existingUids[$uid])) {
                $summary['duplicates']++;
                // 이전 가져오기에서 gold/teal의 내용이 화면에 안 보이는 description에 저장된 행 치유
                if ($this->repairImportedContent($uid, $dry)) {
                    $summary['repaired']++;
                }

                continue;
            }

            $categories = array_filter(array_map('trim', explode(',', $this->icalUnescape($this->icalProp($vevent, 'CATEGORIES') ?? ''))));
            $mapped = $this->mapIcalCategories($categories, $labelToKey);
            if ($mapped === null) { // 공휴일 → 스킵
                $summary['skipped_holiday']++;

                continue;
            }
            [$colorKey, $newCategoryLabel, $isUnmapped] = $mapped;
            if ($newCategoryLabel && ! in_array($newCategoryLabel, $summary['will_create_categories'], true)) {
                $summary['will_create_categories'][] = $newCategoryLabel;
            }
            if ($isUnmapped) {
                $orig = implode(',', $categories);
                if (! in_array($orig, $summary['unmapped'], true)) {
                    $summary['unmapped'][] = $orig;
                }
            }
            if ($this->icalProp($vevent, 'RRULE')) {
                $summary['rrule_count']++; // 반복 규칙은 첫 회차만 가져옴
            }

            $title = $this->icalUnescape($this->icalProp($vevent, 'SUMMARY') ?? '(제목 없음)');
            // ✓ 완료 마커: 제목에서 제거하고 완료 처리
            $completed = false;
            if (preg_match('/^[✓☑✔]\s*/u', $title)) {
                $completed = true;
                $title = preg_replace('/^[✓☑✔]\s*/u', '', $title) ?: '(제목 없음)';
            }

            $dtstart = $this->icalDateTime($this->icalProp($vevent, 'DTSTART'));
            $dtend = $this->icalDateTime($this->icalProp($vevent, 'DTEND'));
            if (! $dtstart) {
                continue;
            }
            // 기준일 이후 시작하는 일정 제외 (예: 2026-06-30까지만 가져오기)
            if ($until && $dtstart['date'] > $until) {
                $summary['skipped_after']++;

                continue;
            }

            $isAllDay = $dtstart['allDay'];
            $startDate = $dtstart['date'];
            $startTime = $isAllDay ? null : $dtstart['time'];
            if ($isAllDay) {
                // iCal 종일 DTEND는 exclusive → 하루 빼기
                $endDate = $dtend ? date('Y-m-d', strtotime($dtend['date'].' -1 day')) : $startDate;
                if ($endDate < $startDate) {
                    $endDate = $startDate;
                }
                $endTime = null;
            } else {
                $endDate = $dtend['date'] ?? $startDate;
                $endTime = $dtend['time'] ?? null;
            }

            // 내용은 카테고리별로 실제 화면에 보이는 필드에 저장
            // (gold/teal 편집 폼에는 description(상세 설명) 필드가 없음 — gold는 요청상세, teal은 상세설명)
            $description = $this->icalUnescape($this->icalProp($vevent, 'DESCRIPTION') ?? '') ?: null;
            $goldData = null;
            $tealData = null;
            if ($description && $colorKey === 'gold') {
                $goldData = ['req_detail' => $description];
                $description = null;
            } elseif ($description && $colorKey === 'teal') {
                $tealData = ['desc' => $description];
                $description = null;
            }

            $rows[] = [
                'title' => $title,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_all_day' => $isAllDay,
                'color' => $colorKey,       // 신규 카테고리는 라벨 문자열 → 저장 직전 키로 치환
                'description' => $description,
                'gold_data' => $goldData,
                'teal_data' => $tealData,
                'location' => $this->icalUnescape($this->icalProp($vevent, 'LOCATION') ?? '') ?: null,
                'completed_at' => $completed ? ($endDate.' '.($endTime ?: '23:59').':00') : null,
                'import_uid' => $uid ? mb_substr($uid, 0, 100) : null,
                'created_by' => Auth::id(),
            ];

            $label = $newCategoryLabel ?: ($categoryMap[$colorKey]['label'] ?? $colorKey);
            $summary['by_category'][$label] = ($summary['by_category'][$label] ?? 0) + 1;
        }

        if ($dry) {
            return response()->json($summary);
        }

        DB::transaction(function () use (&$rows, &$summary) {
            // 신규 커스텀 카테고리 생성 (라벨 → 키)
            $newKeys = [];
            foreach ($summary['will_create_categories'] as $label) {
                $newKeys[$label] = CalendarCategory::ensureByLabel($label);
            }
            foreach ($rows as $row) {
                if (isset($newKeys[$row['color']])) {
                    $row['color'] = $newKeys[$row['color']];
                }
                Schedule::create($row);
                $summary['imported']++;
            }
        });

        $msg = "{$summary['imported']}건의 일정을 가져왔습니다.";
        if ($summary['duplicates']) {
            $msg .= " (중복 {$summary['duplicates']}건 스킵)";
        }
        if ($summary['skipped_holiday']) {
            $msg .= " (공휴일 {$summary['skipped_holiday']}건 제외)";
        }
        if ($summary['skipped_after']) {
            $msg .= " ({$until} 이후 {$summary['skipped_after']}건 제외)";
        }
        if ($summary['repaired']) {
            $msg .= " (기존 {$summary['repaired']}건 내용 위치 치유)";
        }

        return response()->json(['message' => $msg, 'count' => $summary['imported']] + $summary);
    }

    /**
     * TickTick CATEGORIES 토큰 → 앱 카테고리. 우선순위 순서대로 첫 매칭.
     * 반환: null=스킵(공휴일), [colorKeyOrNewLabel, 생성할 라벨|null, 미매칭 여부]
     *
     * @param  list<string>  $categories
     * @param  array<string, string>  $labelToKey
     * @return array{0:string,1:?string,2:bool}|null
     */
    private function mapIcalCategories(array $categories, array $labelToKey): ?array
    {
        $has = fn (string $needle) => collect($categories)->contains(fn ($c) => str_contains($c, $needle));

        if ($has('공휴일')) {
            return null;
        }
        if ($has('휴가') || $has('생일')) {
            return ['red', null, false];
        }
        if ($has('내방')) {
            return ['purple', null, false];
        }
        // '렌탈&방송룸월대여'가 방송룸보다 먼저 걸리도록 렌탈을 우선 평가
        foreach ([
            ['needles' => ['렌탈', '장비렌탈'], 'label' => '렌탈'],
        ] as $rule) {
            foreach ($rule['needles'] as $n) {
                if ($has($n)) {
                    return isset($labelToKey[$rule['label']])
                        ? [$labelToKey[$rule['label']], null, false]
                        : [$rule['label'], $rule['label'], false];
                }
            }
        }
        if ($has('원격') || $has('방송룸')) {
            return ['teal', null, false];
        }
        foreach (['스튜디오', '촬영', '디자인'] as $label) {
            if ($has($label)) {
                // 라벨 정확 일치 → 부분 일치(예: '촬영/스튜디오') 순으로 탐색
                $key = $labelToKey[$label] ?? null;
                if (! $key) {
                    foreach ($labelToKey as $l => $k) {
                        if (str_contains($l, $label)) {
                            $key = $k;
                            break;
                        }
                    }
                }

                return $key ? [$key, null, false] : [$label, $label, false];
            }
        }
        if ($has('개인의뢰') || $has('의뢰자')) {
            return ['gold', null, false];
        }
        if ($has('프로젝트') || $has('일반')) {
            return ['blue', null, false];
        }

        return ['blue', null, true]; // 미매칭 → 사내업무 + 보고
    }

    /**
     * 이미 가져온 gold/teal 일정의 내용이 화면에 안 보이는 description에 남아있으면
     * 카테고리별 표시 필드(gold→req_detail, teal→desc)로 이동. 치유 대상이면 true.
     */
    private function repairImportedContent(string $uid, bool $dry): bool
    {
        $row = Schedule::where('import_uid', $uid)->first();
        if (! $row || ! in_array($row->color, ['gold', 'teal'], true) || ! trim((string) $row->description)) {
            return false;
        }
        $field = $row->color === 'gold' ? 'gold_data' : 'teal_data';
        $subKey = $row->color === 'gold' ? 'req_detail' : 'desc';
        $data = $row->{$field} ?? [];
        if (! empty($data[$subKey])) {
            return false; // 이미 보이는 필드에 내용 있음 — 덮어쓰지 않음
        }
        if (! $dry) {
            $data[$subKey] = trim((string) $row->description);
            $row->update([$field => $data, 'description' => null]);
        }

        return true;
    }

    /**
     * iCal 날짜/시간 파싱 — YYYYMMDD(종일) 또는 YYYYMMDDTHHMMSS[Z].
     * 끝에 Z(UTC)가 붙으면 KST(+9)로 변환.
     *
     * @return array{date:string,time:?string,allDay:bool}|null
     */
    private function icalDateTime(?string $raw): ?array
    {
        if (! $raw || ! preg_match('/^(\d{8})(T(\d{4})\d{0,2}(Z?))?/', $raw, $m)) {
            return null;
        }
        $date = substr($m[1], 0, 4).'-'.substr($m[1], 4, 2).'-'.substr($m[1], 6, 2);
        if (empty($m[2])) {
            return ['date' => $date, 'time' => null, 'allDay' => true];
        }
        $time = substr($m[3], 0, 2).':'.substr($m[3], 2, 2);
        if (($m[4] ?? '') === 'Z') {
            $kst = Carbon::parse($date.' '.$time, 'UTC')->setTimezone('Asia/Seoul');
            $date = $kst->format('Y-m-d');
            $time = $kst->format('H:i');
        }

        return ['date' => $date, 'time' => $time, 'allDay' => false];
    }

    /** iCal 텍스트 언이스케이프 (\n \, \; \\) */
    private function icalUnescape(string $val): string
    {
        return trim(str_replace(['\\n', '\\N', '\\,', '\\;', '\\\\'], ["\n", "\n", ',', ';', '\\'], $val));
    }

    private function icalProp(string $vevent, string $name): ?string
    {
        // 속성이 파라미터를 포함할 수 있음 (예: DTSTART;VALUE=DATE:20260410)
        if (preg_match('/^'.preg_quote($name, '/').'[;:]([^\r\n]+)/m', $vevent, $m)) {
            $val = $m[1];
            // 파라미터가 있으면 콜론 뒤가 실제 값
            if (str_contains($m[0], ';') && str_contains($val, ':')) {
                $val = substr($val, strpos($val, ':') + 1);
            }

            return trim($val);
        }

        return null;
    }
}
