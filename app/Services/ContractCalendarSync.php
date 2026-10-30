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
    /** 진행중(종료일 없음) 계약의 결제 반복 생성 범위 (개월) — 시작일 또는 오늘 중 늦은 쪽부터. 매월 크론이 재동기화해 앞으로 밀어준다 */
    public const ONGOING_MONTHS = 12;

    /** 결제 체크된 결제일의 캘린더 일정 제목 앞에 붙는 표시 */
    public const PAID_PREFIX = '✅ ';

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

        $word = $contract->isServer() ? '서버 렌탈' : '렌탈';
        $product = $contract->displayProductName();
        $suffix = $product ? "{$clientLabel} · {$product}" : $clientLabel; // 상품명이 있으면 '의뢰자 · 상품명'

        return [
            'start' => "{$word} 시작 · {$suffix}",
            'end' => "{$word} 종료 · {$suffix}",
            'pay' => "{$word} 결제 · {$suffix}",
        ];
    }

    /** 캘린더 카테고리 라벨 — 서버 렌탈은 별도 카테고리로 필터·색 구분 */
    private function categoryLabel(RentalContract|BroadcastRoomContract $contract, array $kind): string
    {
        return ($contract instanceof RentalContract && $contract->isServer()) ? '서버 렌탈' : $kind['category'];
    }

    /** 계약 → 캘린더 재동기화 (등록/수정 공용) */
    public function sync(RentalContract|BroadcastRoomContract $contract): void
    {
        $kind = self::KINDS[get_class($contract)];
        $this->removeLinked($contract);

        $contract->loadMissing('client');
        $clientLabel = trim(($contract->client?->nickname ?: $contract->client?->name) ?? '');
        $colorKey = CalendarCategory::ensureByLabel($this->categoryLabel($contract, $kind));
        $start = Carbon::parse($contract->start_date->format('Y-m-d'));
        $end = $contract->end_date ? Carbon::parse($contract->end_date->format('Y-m-d')) : null;

        // 방송룸 월대여: 별도 결제 반복 일정 없이 시작 일정 내용에 결제 내역 기재
        $isBroadcast = $contract instanceof BroadcastRoomContract;
        $description = $contract->memo ?: null;
        if ($contract instanceof RentalContract && $contract->isServer()) {
            // 서버 렌탈: 서버명·사양만 일정 내용에 기재. 접속 정보(IP·계정)는 렌탈 화면(rental.view 권한)에서만 —
            // 캘린더는 전 직원에게 보이고 내보내기·변경 이력에도 사본이 남으므로 복제하지 않는다
            $serverInfo = array_filter([
                $contract->server_name ? "· 서버: {$contract->server_name}" : null,
                $contract->server_spec ? "· 사양: {$contract->server_spec}" : null,
            ]);
            if ($serverInfo) {
                $description = "[서버 정보]\n".implode("\n", $serverInfo).($contract->memo ? "\n\n".$contract->memo : '');
            }
        }
        if ($isBroadcast) {
            $paymentInfo = "[결제 내역]\n"
                .'· 월 요금: '.number_format($contract->monthly_fee).'원'
                ."\n· 결제일: 매월 {$start->day}일"
                ."\n· 계약 기간: {$start->toDateString()} ~ ".($end ? $end->toDateString() : '진행중');
            $description = $paymentInfo.($contract->memo ? "\n\n".$contract->memo : '');
        }

        $base = [
            'is_all_day' => true,
            'color' => $colorKey,
            'client_name' => $clientLabel,
            'description' => $description,
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

        // 매월 결제일 반복 — 렌탈 계약만 (방송룸 월대여는 결제 내역을 일정 내용으로 대체)
        // 해지(terminated)면 종료일 이후 생성 안 함 (until이 자연히 잘라줌)
        // 준비중(설치·개통 전) 렌탈 계약은 결제 반복을 만들지 않는다 — 진행중으로 바뀔 때 재동기화로 생성
        $isPending = $contract instanceof RentalContract && $contract->isPending();
        // 오래된 진행중 계약도 오늘 이후 결제일이 캘린더에 있도록 — 시작일이 아니라 max(시작일, 오늘) 기준으로 12개월
        $anchor = $start->gt(Carbon::today()) ? $start : Carbon::today();
        $until = $end ?? $anchor->copy()->addMonthsNoOverflow(self::ONGOING_MONTHS);
        if (! $isBroadcast && ! $isPending && $until->gt($start)) {
            // 이미 결제 체크된 날짜는 재동기화해도 ✅ 표시 유지
            $paidDates = $contract instanceof RentalContract
                ? $contract->payments()->pluck('due_date')->map(fn ($d) => Carbon::parse($d)->toDateString())->all()
                : [];
            $meta['repeat_group'] = $this->createMonthlyOccurrences(
                $base,
                $titles['pay'].($contract->monthly_fee ? ' ('.number_format($contract->monthly_fee).'원)' : ''),
                $start,
                $until,
                $paidDates,
            );
        }

        // 이벤트 없이 저장되는 경우는 없음(시작 이벤트 항상 생성)
        $contract->forceFill(['calendar_meta' => $meta])->saveQuietly();
    }

    /** 결제 체크 ↔ 캘린더 결제 일정 제목의 ✅ 표시 동기화 (해당 날짜의 반복 일정만) */
    public function markPayment(RentalContract $contract, string $dueDate, bool $paid): void
    {
        $group = $contract->calendar_meta['repeat_group'] ?? null;
        if (! $group) {
            return;
        }
        Schedule::where('repeat_group', $group)->whereDate('start_date', $dueDate)->get()->each(function (Schedule $s) use ($paid) {
            $bare = Str::startsWith($s->title, self::PAID_PREFIX) ? Str::after($s->title, self::PAID_PREFIX) : $s->title;
            $s->forceFill(['title' => ($paid ? self::PAID_PREFIX : '').$bare])->saveQuietly();
        });
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
    private function createMonthlyOccurrences(array $base, string $title, Carbon $first, Carbon $until, array $paidDates = []): string
    {
        $group = (string) Str::uuid();
        $repeatFields = [
            'repeat_group' => $group,
            'repeat_freq' => 'monthly',
            'repeat_until' => $until->toDateString(),
        ];

        for ($i = 0; $i <= 1200; $i++) {
            $date = $first->copy()->addMonthsNoOverflow($i);
            if ($date->gt($until)) {
                break;
            }
            Schedule::create($base + $repeatFields + [
                'title' => (in_array($date->toDateString(), $paidDates, true) ? self::PAID_PREFIX : '').$title,
                'start_date' => $date->toDateString(),
                'end_date' => $date->toDateString(),
            ]);
        }

        return $group;
    }
}
