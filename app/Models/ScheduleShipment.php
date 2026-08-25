<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleShipment extends Model
{
    protected $fillable = [
        'schedule_id',
        'estimate_id',
        'carrier',
        'tracking_no',
        'status',
        'last_event',
        'last_location',
        'delivered_at',
        'checked_at',
        'raw',
        'created_by',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'checked_at' => 'datetime',
        'raw' => 'array',
    ];

    /** @var array<string, string> delivery-tracker carrier id → 한글 표기 */
    public const CARRIERS = [
        'kr.cjlogistics' => 'CJ대한통운',
        'kr.lotte' => '롯데택배',
        'kr.hanjin' => '한진택배',
        'kr.logen' => '로젠택배',
        'kr.epost' => '우체국택배',
        'kr.kdexp' => '경동택배',
        'kr.coupangls' => '쿠팡',
    ];

    /** 택배사 공식 조회 페이지 딥링크 — {no}에 송장번호가 채워져 바로 조회된다 */
    public const TRACKING_URLS = [
        'kr.cjlogistics' => 'https://trace.cjlogistics.com/next/tracking.html?wblNo={no}',
        'kr.lotte' => 'https://www.lotteglogis.com/home/reservation/tracking/linkView?InvNo={no}',
        'kr.hanjin' => 'https://www.hanjin.com/kor/CMS/DeliveryMgr/WaybillResult.do?mCode=MN038&schLang=KR&wblnumText2={no}',
        'kr.logen' => 'https://www.ilogen.com/web/personal/trace/{no}',
        'kr.epost' => 'https://service.epost.go.kr/trace.RetrieveDomRigiTraceList.comm?sid1={no}',
        'kr.kdexp' => 'https://kdexp.com/service/delivery/etc/delivery.do?barcode={no}',
        'kr.coupangls' => 'https://www.coupangls.com/web/modal/invoice/{no}',
    ];

    /** 이 송장의 택배사 조회 페이지 URL (미지원 택배사는 null) */
    public function trackingUrl(): ?string
    {
        $template = self::TRACKING_URLS[$this->carrier] ?? null;

        return $template ? str_replace('{no}', rawurlencode((string) $this->tracking_no), $template) : null;
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /** @return BelongsTo<Estimate, $this> */
    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function carrierLabel(): string
    {
        return self::CARRIERS[$this->carrier] ?? $this->carrier;
    }
}
