<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RentalContract extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    /** 계약 종류 — 장비 렌탈(기본) / 서버 렌탈 */
    public const KIND_EQUIPMENT = 'equipment';

    public const KIND_SERVER = 'server';

    public const KINDS = [self::KIND_EQUIPMENT, self::KIND_SERVER];

    public const KIND_LABELS = [self::KIND_EQUIPMENT => '장비 렌탈', self::KIND_SERVER => '서버 렌탈'];

    /** 상태 — 준비중(계약 후 설치·개통 전) / 진행중 / 해지 */
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_TERMINATED = 'terminated';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_ACTIVE, self::STATUS_TERMINATED];

    protected $fillable = [
        'client_id', 'kind', 'product_id', 'product_name', 'start_date', 'end_date', 'monthly_fee', 'fee_manual',
        'status', 'memo', 'calendar_meta', 'server_name', 'server_spec', 'server_access',
    ];

    protected $attributes = [
        'kind' => self::KIND_EQUIPMENT,
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'monthly_fee' => 'integer',
        'fee_manual' => 'boolean',
        'calendar_meta' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function product()
    {
        return $this->belongsTo(RentalProduct::class, 'product_id');
    }

    public function payments()
    {
        return $this->hasMany(RentalPayment::class, 'contract_id');
    }

    /** 결제 현황에 미리 보여줄 다음 결제일 범위 (오늘 + N개월) */
    public const PAYMENT_LOOKAHEAD_MONTHS = 1;

    /**
     * 결제(갱신)일 목록 — 시작일부터 매월 같은 '일'(말일 클램프), 종료일 또는 오늘+1개월까지.
     * 준비중(설치·개통 전) 계약은 결제일이 없다. 캘린더 결제 반복과 같은 규칙.
     *
     * @return list<string> Y-m-d
     */
    public function dueDates(?Carbon $today = null): array
    {
        if ($this->isPending() || ! $this->start_date) {
            return [];
        }
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $start = Carbon::parse($this->start_date->format('Y-m-d'));
        $limit = $today->copy()->addMonthsNoOverflow(self::PAYMENT_LOOKAHEAD_MONTHS);
        if ($this->end_date) {
            $end = Carbon::parse($this->end_date->format('Y-m-d'));
            $limit = $end->lt($limit) ? $end : $limit;
        }

        $dates = [];
        for ($i = 0; $i <= 1200; $i++) {
            $date = $start->copy()->addMonthsNoOverflow($i);
            if ($date->gt($limit)) {
                break;
            }
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    /**
     * 결제일별 상태 — paid(결제됨) / due(오늘 결제일) / overdue(지났는데 미결제) / upcoming(예정).
     * 시작일·종료일을 바꿔 현재 결제 일정에서 벗어난 결제 기록도 숨기지 않고 paid 로 함께 보여준다(해제 가능).
     * payments 관계를 미리 로드해 두면 추가 쿼리 없음.
     *
     * @return list<array{due_date:string, state:string, amount:int, paid_at:?string, paid_by:?string}>
     */
    public function paymentStatus(?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $paid = $this->payments->keyBy(fn (RentalPayment $p) => $p->due_date->format('Y-m-d'));

        $dates = $this->dueDates($today);
        $orphans = $paid->keys()->reject(fn (string $d) => in_array($d, $dates, true))->all();
        $all = array_values(array_unique([...$dates, ...$orphans]));
        sort($all);

        return array_map(function (string $date) use ($paid, $today) {
            $p = $paid->get($date);
            $due = Carbon::parse($date);
            $state = $p ? 'paid' : ($due->lt($today) ? 'overdue' : ($due->eq($today) ? 'due' : 'upcoming'));

            return [
                'due_date' => $date,
                'state' => $state,
                'amount' => $p ? (int) $p->amount : (int) $this->monthly_fee,
                'paid_at' => $p?->paid_at?->format('Y-m-d H:i'),
                'paid_by' => $p?->payer ? ($p->payer->display_name ?? $p->payer->username) : null,
            ];
        }, $all);
    }

    /** 카드에 표시할 상품명 — 선택 당시 스냅샷 우선, 없으면 현재 상품명 */
    public function displayProductName(): ?string
    {
        return $this->product_name ?: $this->product?->name;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isServer(): bool
    {
        return $this->kind === self::KIND_SERVER;
    }

    public function kindLabel(): string
    {
        return self::KIND_LABELS[$this->kind] ?? self::KIND_LABELS[self::KIND_EQUIPMENT];
    }
}
