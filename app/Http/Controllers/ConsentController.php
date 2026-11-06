<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * 의뢰자 동의 — 위약 안내(필수)·마케팅 활용(선택) 전문 열람과 동의 기록.
 *
 * - /terms/{type}: 토큰 없이 보는 공개 전문 (홈페이지에서 링크 게시용)
 * - /estimate-view/{token}/terms/{type}: 전문 + 끝까지 읽으면 동의 버튼 활성화
 * - 동의는 견적서(agreements JSON: 시각·전문 버전·IP)에 기록하고,
 *   마케팅 동의는 의뢰자(marketing_agreed_at)에도 복사 — 세팅 사례 업로드 대상 필터용.
 * - 전문·버전은 관리자 페이지 '판매처 설정'에서 수정 (Setting). 비어 있으면 기본 초안.
 */
class ConsentController extends Controller
{
    public const TYPES = ['penalty', 'marketing'];

    /** 공개 전문 — 홈페이지 게시용 (최신본 단일 원본) */
    public function publicTerms(string $type)
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        return view('terms.show', ['doc' => self::doc($type), 'type' => $type, 'estimate' => null]);
    }

    /** 견적서 전문 — 완독 후 동의 (이미 동의했으면 상태 표시) */
    public function estimateTerms(string $token, string $type)
    {
        abort_unless(in_array($type, self::TYPES, true), 404);
        abort_if(strlen($token) < 32, 404);
        $estimate = Estimate::where('share_token', $token)->firstOrFail();

        return view('terms.show', ['doc' => self::doc($type), 'type' => $type, 'estimate' => $estimate]);
    }

    /** 동의 기록 */
    public function agree(Request $request, string $token)
    {
        abort_if(strlen($token) < 32, 404);
        $estimate = Estimate::where('share_token', $token)->firstOrFail();
        $data = $request->validate(['type' => 'required|in:penalty,marketing']);
        $type = $data['type'];
        $doc = self::doc($type);

        $agreements = $estimate->agreements ?? [];
        if (empty($agreements[$type]['at'])) { // 중복 클릭 — 최초 동의 시각 보존
            $agreements[$type] = [
                'at' => now()->format('Y-m-d H:i:s'),
                'ver' => $doc['version'],
                'ip' => $request->ip(),
            ];
            $estimate->forceFill(['agreements' => $agreements])->save();

            // 마케팅 동의는 의뢰자에도 기록 — 이미 동의한 의뢰자는 최초 기록 유지
            if ($type === 'marketing' && $estimate->client_id) {
                Client::where('id', $estimate->client_id)->whereNull('marketing_agreed_at')
                    ->update(['marketing_agreed_at' => now(), 'marketing_agree_estimate_id' => $estimate->id]);
            }
        }

        return response()->json(['ok' => true, 'at' => $agreements[$type]['at']]);
    }

    /**
     * 전문 문서 — Setting 우선, 비어 있으면 기본 초안.
     *
     * @return array{title: string, html: string, version: string}
     */
    public static function doc(string $type): array
    {
        $html = (string) Setting::get("terms_{$type}_html", '');
        $version = (string) Setting::get("terms_{$type}_version", '');

        if ($type === 'penalty') {
            return [
                'title' => '위약 안내',
                'version' => $version !== '' ? $version : 'v1 (2026-11-06)',
                'html' => $html !== '' ? $html : self::defaultPenalty(),
            ];
        }

        return [
            'title' => '설치 사례 활용(마케팅) 동의',
            'version' => $version !== '' ? $version : 'v1 (2026-11-06)',
            'html' => $html !== '' ? $html : self::defaultMarketing(),
        ];
    }

    private static function defaultPenalty(): string
    {
        return <<<'HTML'
<p>본 안내는 (주)닥터고블린컴퍼니(이하 "회사")의 장비 판매·세팅 서비스 계약의 변경·취소에 관한 기준입니다. 결제 전 반드시 확인해 주세요.</p>
<h3>1. 일정 변경</h3>
<ul>
<li>설치(세팅) 예정일 <b>3일 전까지</b>의 일정 변경은 위약금 없이 1회 가능합니다.</li>
<li>예정일 <b>2일 전~전일</b> 변경 시 출장 준비 비용이 발생할 수 있습니다.</li>
</ul>
<h3>2. 계약 취소·환불</h3>
<ul>
<li>발주 전 취소: 전액 환불됩니다.</li>
<li>발주 후(장비 주문 완료) 취소: 개봉·주문제작 장비는 공급처 반품 조건에 따라 반품 수수료가 차감될 수 있습니다.</li>
<li>설치 완료 후에는 장비 단순 변심 반품이 불가하며, 하자는 AS 기준에 따릅니다.</li>
</ul>
<h3>3. 결제·기타</h3>
<ul>
<li>견적 금액은 견적 유효기간 내 결제 기준이며, 기간 경과 시 재견적될 수 있습니다.</li>
<li>현장 여건(전기·인터넷·구조물 등)으로 추가 작업이 필요한 경우 사전 협의 후 추가 비용이 발생할 수 있습니다.</li>
</ul>
<p style="color:#888">※ 본 문구는 기본 초안입니다 — 관리자 페이지 &gt; 판매처 설정에서 실제 약관으로 교체하세요.</p>
HTML;
    }

    private static function defaultMarketing(): string
    {
        return <<<'HTML'
<p>(주)닥터고블린컴퍼니는 설치(세팅) 사례를 아래와 같이 활용하고자 합니다. <b>본 동의는 선택사항이며, 동의하지 않아도 계약·결제에는 아무런 영향이 없습니다.</b></p>
<h3>1. 활용 내용</h3>
<ul>
<li>설치 완료 후 촬영한 <b>세팅 사진·영상</b> (방송 화면·장비 구성 중심, 인물은 요청 시 제외)</li>
<li>의뢰자의 <b>활동명(채널명·상호)</b> 및 방송 플랫폼 표기</li>
</ul>
<h3>2. 게시 범위</h3>
<ul>
<li>회사 홈페이지(drgo.pro) 세팅 사례 게시판, 회사 운영 SNS·유튜브</li>
</ul>
<h3>3. 보유·철회</h3>
<ul>
<li>동의 철회 시까지 활용하며, 철회는 채널톡 또는 대표전화로 요청하시면 지체 없이 게시물을 내립니다.</li>
<li>철회하셔도 계약·AS 등 서비스 이용에 불이익이 없습니다.</li>
</ul>
<p style="color:#888">※ 본 문구는 기본 초안입니다 — 관리자 페이지 &gt; 판매처 설정에서 실제 문구로 교체하세요.</p>
HTML;
    }
}
