<?php

namespace App\Services;

use App\Models\BroadcastRoomContract;
use App\Models\CalendarCategory;
use App\Models\RentalContract;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * 렌탈 계약·방송룸 월계약 ↔ 캘린더 동기화.
 *
 * 계약당 캘린더 일정 3종을 관리한다:
 *  - 시작 이벤트 (종일)
 *  - 종료 이벤트 (end_date 있을 때만, 종일)
 *  - 매월 결제일 반복 (시작일의 '일' 기준, 종료일 또는 +12개월까지)
 *
 * 동기화는 멱등 full-resync — 기존 연결 일정을 지우고 다시 생성.
 * 캘린더에서 개별 일정을 지워도 원본 계약은 유지(연결만 해제)되며, 다음 resync 때 재생성된다.
 */
class ContractCalendarSync
{
    /** 진행중(종료일 없음) 계약의 결제 반복 생성 범위 (개월) */
    public const ONGOING_MONTHS = 12;

    /** @var array<class-string, array{source:string, category:string}> */
    private const KINDS = [
        RentalContract::class => ['source' => 'rental_contract', 'category' => '렌탈'],
        BroadcastRoomContract::class => ['source' => 'broadcast_contract', 'category' => '방송룸 대여'],
    ];

    /**
     * 계약 종류별 일정 제목 — 방송룸은 '{의뢰자} 방송룸 n호실 월대여 시작/종료/결제' 형식.
     *
     * @return array{start:string, end:string, pay:string}
     */
    private function titles(RentalContract|BroadcastRoomContract $contract, string $clientLabel): array
    {
        if ($contract instanceof BroadcastRoomContract) {
            $room = $contract->room_no ? "방송룸 {$contract->room_no}호실" : '방송룸';
            $prefix = trim("{$clientLabel} {$room} 월대여");

            return ['start' => "{$prefix} 시작", 'end' => "{$prefix} 종료", 'pay' => "{$prefix} 결제"];
        }

        return [
            'start' => "렌탈 시작 · {$clientLabel}",
            'end' => "렌탈 종료 · {$clientLabel}",
            'pay' => "렌탈 결제 · {$clientLabel}",
        ];
    }

    /** 계약 → 캘린더 재동기화 (등록/수정 공용) */
    public function sync(RentalContract|BroadcastRoomContract $contract): void
    {
        $kind = self::KINDS[get_class($contract)];
        $this->removeLinked($contract);

        $contract->loadMissing('client');
        $clientLabel = trim(($contract->client?->nickname ?: $contract->client?->name) ?? '');
        $colorKey = CalendarCategory::ensureByLabel($kind['category']);
        $start = Carbon::parse($contract->start_date->format('Y-m-d'));
        $end = $contract->end_date ? Carbon::parse($contract->end_date->format('Y-m-d')) : null;

        $base = [
            'is_all_day' => true,
            'color' => $colorKey,
            'client_name' => $clientLabel,
            'description' => $contract->memo ?: null,
            'source_type' => $kind['source'],
            'source_id' => $contract->id,
            'created_by' => Auth::id(),
        ];

        $titles = $this->titles($contract, $clientLabel);
        $meta = ['start_id' => null, 'end_id' => null, 'repeat_group' => null];

        // 시작 이벤트
        $meta['start_id'] = Schedule::create($base + [
            'title' => $titles['start'],
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
        ])->id;

        // 종료 이벤트
        if ($end) {
            $meta['end_id'] = Schedule::create($base + [
                'title' => $titles['end'],
                'start_date' => $end->toDateString(),
                'end_date' => $end->toDateString(),
            ])->id;
        }

        // 매월 결제일 반복 — 해지(terminated)면 종료일 이후 생성 안 함 (until이 자연히 잘라줌)
        $until = $end ?? $start->copy()->addMonthsNoOverflow(self::ONGOING_MONTHS);
        if ($until->gt($start)) {
            $meta['repeat_group'] = $this->createMonthlyOccurrences(
                $base,
                $titles['pay'].($contract->monthly_fee ? ' ('.number_format($contract->monthly_fee).'원)' : ''),
                $start,
                $until,
            );
        }

        // 이벤트 없이 저장되는 경우는 없음(시작 이벤트 항상 생성)
        $contract->forceFill(['calendar_meta' => $meta])->saveQuietly();
    }

    /** 계약 삭제 시 연결 일정 정리 */
    public function remove(RentalContract|BroadcastRoomContract $contract): void
    {
        $this->removeLinked($contract);
        $contract->forceFill(['calendar_meta' => null])->saveQuietly();
    }

    /** calendar_meta 기준으로 연결 일정 소프트 삭제 (이미 캘린더에서 지운 건 무시) */
    private function removeLinked(RentalContract|BroadcastRoomContract $contract): void
    {
        $meta = $contract->calendar_meta ?? [];
        $ids = array_filter([$meta['start_id'] ?? null, $meta['end_id'] ?? null]);
        if ($ids) {
            Schedule::whereIn('id', $ids)->get()->each->delete();
        }
        if (! empty($meta['repeat_group'])) {
            Schedule::where('repeat_group', $meta['repeat_group'])->get()->each->delete();
        }
    }

    /**
     * 매월 반복(시작일의 '일' 기준, 말일 클램프) 종일 일정 생성.
     * 캘린더 반복 UI와 일관되도록 repeat_group/repeat_* 필드 기록.
     */
    private function createMonthlyOccurrences(array $base, string $title, Carbon $first, Carbon $until): string
    {
        $group = (string) Str::uuid();
        $repeatFields = [
            'repeat_group' => $group,
            'repeat_freq' => 'monthly',
            'repeat_until' => $until->toDateString(),
        ];

        for ($i = 0; $i <= 60; $i++) {
            $date = $first->copy()->addMonthsNoOverflow($i);
            if ($date->gt($until)) {
                break;
            }
            Schedule::create($base + $repeatFields + [
                'title' => $title,
                'start_date' => $date->toDateString(),
                'end_date' => $date->toDateString(),
            ]);
        }

        return $group;
    }
}
