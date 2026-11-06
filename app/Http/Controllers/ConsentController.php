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

    /** 공개 전문 — 홈페이지 게시용 (최신본 단일 원본). 위약은 활성 섹션 전체를 담는다 */
    public function publicTerms(string $type)
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        return view('terms.show', ['doc' => self::doc($type), 'type' => $type, 'estimate' => null]);
    }

    /** 견적서 전문 — 완독 후 동의. 위약은 이 견적서에 해당하는 섹션만 조립 */
    public function estimateTerms(string $token, string $type)
    {
        abort_unless(in_array($type, self::TYPES, true), 404);
        abort_if(strlen($token) < 32, 404);
        $estimate = Estimate::where('share_token', $token)->firstOrFail();
        $doc = $type === 'penalty' ? self::penaltyDocFor($estimate->terms_sections) : self::doc($type);

        return view('terms.show', ['doc' => $doc, 'type' => $type, 'estimate' => $estimate]);
    }

    /** 동의 기록 */
    public function agree(Request $request, string $token)
    {
        abort_if(strlen($token) < 32, 404);
        $estimate = Estimate::where('share_token', $token)->firstOrFail();
        $data = $request->validate(['type' => 'required|in:penalty,marketing']);
        $type = $data['type'];
        $doc = $type === 'penalty' ? self::penaltyDocFor($estimate->terms_sections) : self::doc($type);

        $agreements = $estimate->agreements ?? [];
        if (empty($agreements[$type]['at'])) { // 중복 클릭 — 최초 동의 시각 보존
            $agreements[$type] = array_filter([
                'at' => now()->format('Y-m-d H:i:s'),
                'ver' => $doc['version'],
                'ip' => $request->ip(),
                // 위약: 어떤 섹션 조합(각 버전)에 동의했는지 — 유형별 개정 대비
                'sections' => $doc['sections'] ?? null,
            ]);
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
            return self::penaltyDocFor(null); // 선택 없음 = 활성 섹션 전체 (공개 전문)
        }

        return [
            'title' => '설치 사례 활용(마케팅) 동의',
            'version' => $version !== '' ? $version : 'v1 (2026-11-06)',
            'html' => $html !== '' ? $html : self::defaultMarketing(),
        ];
    }

    /**
     * 위약 안내 섹션 목록 — Setting('terms_penalty_sections') JSON 또는 기본 초안.
     * 각 섹션: {key, name, version, active, auto_types[], html}
     * auto_types: 연동 프로젝트 유형(crm.project_types 키)과 일치하면 견적서에 자동 제안.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function penaltySections(): array
    {
        $json = Setting::get('terms_penalty_sections');
        if ($json) {
            $decoded = json_decode((string) $json, true);
            if (is_array($decoded) && $decoded !== []) {
                return $decoded;
            }
        }

        return self::defaultPenaltySections();
    }

    /**
     * 선택된 섹션 키로 위약 전문 조립 — 공통은 항상 포함, null이면 활성 섹션 전체(공개 전문용).
     *
     * @return array{title: string, html: string, version: string, sections: array<string, string>}
     */
    public static function penaltyDocFor(?array $selectedKeys): array
    {
        $sections = array_values(array_filter(self::penaltySections(), fn ($s) => ! empty($s['active'])));
        $picked = array_values(array_filter($sections, fn ($s) => ($s['key'] ?? '') === 'common'
            || $selectedKeys === null
            || in_array($s['key'] ?? '', $selectedKeys, true)));

        $html = '';
        $versions = [];
        foreach ($picked as $s) {
            if (($s['key'] ?? '') !== 'common') {
                $html .= '<h2 style="font-size:17px; color:#1d2d3d; border-bottom:2px solid #dfe3e9; padding-bottom:6px; margin:28px 0 10px;">'.e($s['name'] ?? '').'</h2>';
            }
            $html .= (string) ($s['html'] ?? '');
            $versions[(string) ($s['key'] ?? '')] = (string) ($s['version'] ?? 'v1');
        }

        return [
            'title' => '위약 안내',
            'version' => collect($picked)->map(fn ($s) => ($s['name'] ?? $s['key']).' '.($s['version'] ?? 'v1'))->implode(' · '),
            'html' => $html !== '' ? $html : '<p>등록된 위약 안내가 없습니다.</p>',
            'sections' => $versions,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function defaultPenaltySections(): array
    {
        // 방문(pf 108)·원격(pf 109)·방송룸(pf 269) — drgo.pro 공식 안내 게시물 기준 (2026-11-06)
        $v = 'v1 (2026-11-06)';
        $draft = '<p style="color:#888">※ 기본 초안입니다 — 관리자 페이지 &gt; 판매처 설정에서 실제 문구로 교체하세요.</p>';

        return [
            ['key' => 'common', 'name' => '공통', 'version' => $v, 'active' => true, 'auto_types' => [],
                'html' => <<<'HTML'
<p>본 안내는 (주)닥터고블린컴퍼니 서비스의 일정 변경·취소·환불에 관한 기준입니다. 결제 전 반드시 확인해 주세요.</p>
<ul>
<li>모든 서비스는 사전 상담 후 <b>예약제·선결제</b>로 진행됩니다.</li>
<li>결제 완료 후 <b>당일 업무 종료 전까지</b>는 철회 및 전액 환불 요청이 가능합니다.</li>
<li>예약 취소 시에는 취소 시점에 따라 <b>위약금이 차감된 후</b> 환불이 진행됩니다.</li>
<li>서비스가 이미 완료된 경우에는 환불이 불가합니다.</li>
<li>위약금 산정은 <b>영업일 기준</b>이며, 일요일 및 휴무일은 제외됩니다.</li>
</ul>
HTML],
            ['key' => 'visit_setup', 'name' => '방문 세팅', 'version' => $v, 'active' => true, 'auto_types' => ['visit', 'onsite'],
                'html' => <<<'HTML'
<h3>일정 변경·취소 위약금</h3>
<ul>
<li>방문 4일 전 취소/연기: 위약금 없음</li>
<li>방문 3일 전 취소/연기: 5만원</li>
<li>방문 2일 전 취소/연기: 10만원</li>
<li>방문 1일 전 취소/연기: 20만원</li>
<li>방문 당일 취소/연기: 30만원 (추가 옵션 비용과 출장비만 환불)</li>
<li>노쇼(기술자 출발 이후): 기본 세팅비와 출장비는 환불되지 않으며, 추가 옵션 비용만 환불 가능</li>
</ul>
<p>위약금은 영업일 기준으로 계산되며 일요일·휴무일은 제외됩니다. 예를 들어 월요일 예약은 일요일이 제외되므로 위약금 없이 취소 가능한 날짜는 전 주 목요일입니다.</p>
<ul>
<li>제주도 방문: 일정 변경·취소 시 항공권 취소 수수료가 별도로 발생할 수 있습니다.</li>
<li>해외 방문: 예약 확정 후 취소 시 항공권 취소 수수료가 발생할 수 있습니다.</li>
</ul>
<h3>추가 비용</h3>
<ul>
<li>장비 추가 설치, 조립 작업, 배치 변경·이동, 가구 이동, 짐 정리·공간 확보 등 사전 협의되지 않은 작업이 현장에서 추가되는 경우 추가 비용이 발생할 수 있습니다.</li>
<li>조명 5개 초과 설치 시 추가 비용이 발생할 수 있습니다.</li>
</ul>
<h3>제품 반품</h3>
<ul>
<li>방문 일정 전 제품 박스를 임의로 개봉해 파손 흔적이 있거나, 박스·포장재를 버린 경우 반품이 불가합니다.</li>
<li>제품 환불은 미개봉 제품에 한해 배송비 제외 후 가능하며, 배송 완료일로부터 14일 이내입니다.</li>
<li>당사 주문 제작 PC는 조립 진행 상태에 따라 환불이 불가하거나 감가 후 환불될 수 있습니다.</li>
</ul>
<h3>A/S 기준</h3>
<ul>
<li>세팅 A/S는 원격 진행이 원칙이며, 기간은 2주입니다. 이후 요청은 내용에 따라 비용이 발생할 수 있습니다.</li>
<li>1개월 이내 추가 방문 요청 시 20만원(출장비 별도), 의뢰자 과실로 인한 재방문은 동일 방문 비용이 청구됩니다.</li>
<li>카메라 화면감·음색·볼륨감·톤·밸런스 등 주관적인 변경 요청과, 세팅 완료 후 조명 위치·밝기·색온도를 임의 변경한 경우의 화면 조정은 원격 A/S 대상이 아니며 재방문(비용 발생 가능)으로만 진행됩니다.</li>
<li>세팅 이후 발생한 컴퓨터 자체의 오작동은 A/S 대상이 아닙니다. 장비·제품 자체의 A/S는 각 제조사 규정을 따릅니다.</li>
</ul>
<p>자세한 준비 사항은 <a href="https://drgo.pro/bbs/board.php?bo_table=pf&wr_id=108" target="_blank" rel="noopener">방문 서비스 안내 전문</a>을 확인해 주세요.</p>
HTML],
            ['key' => 'remote_setup', 'name' => '원격 세팅', 'version' => $v, 'active' => true, 'auto_types' => ['remote'],
                'html' => <<<'HTML'
<h3>일정 변경·취소 위약금</h3>
<ul>
<li>원격 서비스 1일 전 취소/연기: 위약금 없음</li>
<li>원격 서비스 당일 취소/연기: 1만원</li>
<li>원격 서비스 노쇼: 2만원</li>
</ul>
<p>위약금은 영업일 기준으로 계산되며, 기준 시간은 영업 종료 시간인 오후 9시입니다. 일요일·휴무일은 제외됩니다. 예를 들어 월요일 원격 예약은 토요일 오후 9시 전까지 연락 주셔야 위약금 없이 취소·연기가 가능합니다.</p>
<h3>진행 조건</h3>
<ul>
<li>컴퓨터 사양이 방송 진행이 어려운 수준이거나 원격 프로그램이 원활하게 작동하지 않는 경우, 원격 서비스 진행이 어려울 수 있으며 예약이 취소될 수 있습니다.</li>
<li>카메라·조명 설치, 위치 조정, 공간 배치에 따른 화면 개선은 원격으로 어려우며 방문 서비스로만 가능합니다.</li>
</ul>
<h3>A/S 기준</h3>
<ul>
<li>처음 세팅 원격 서비스의 A/S는 원격 진행이 원칙이며 2주간 무상입니다.</li>
<li>문제 해결 원격 서비스의 A/S는 연관·파생된 문제에 한해 진행되며, 1주일 이내 요청해 주셔야 합니다.</li>
<li>카메라 화면감·음색·볼륨감·톤·밸런스 등 주관적인 변경 요청은 무상 A/S 대상이 아닐 수 있으며, 내용에 따라 추가 비용이 발생하거나 방문 서비스가 필요할 수 있습니다.</li>
<li>당사를 통해 구매하지 않은 컴퓨터의 오작동은 A/S 대상이 아닙니다. 구매 제품의 A/S는 각 제조사 규정을 따릅니다.</li>
</ul>
<p>자세한 준비 사항은 <a href="https://drgo.pro/bbs/board.php?bo_table=pf&wr_id=109" target="_blank" rel="noopener">원격 서비스 안내 전문</a>을 확인해 주세요.</p>
HTML],
            ['key' => 'rental', 'name' => '렌탈', 'version' => $v, 'active' => true, 'auto_types' => ['rental'],
                'html' => '<ul><li>렌탈 기간 단축·중도 해지 시 잔여 기간 요금의 일부가 위약금으로 발생할 수 있습니다.</li><li>장비 파손·분실 시 수리비 또는 감가 기준 변상금이 청구됩니다.</li></ul>'.$draft],
            ['key' => 'broadcast_room', 'name' => '방송룸 대여', 'version' => $v, 'active' => true, 'auto_types' => ['broadcast_room'],
                'html' => <<<'HTML'
<h3>환불·일정 변경 위약금</h3>
<ul>
<li>호스트 승인 전 또는 사용 불가 판정 시: 전액 환불</li>
<li>사용일 1일 전(업무시간 내) 취소/변경 요청: 위약금 없음</li>
<li>사용일 당일 취소/변경 요청: 위약금 발생 — <b>예약한 시간 × 30,000원 (최대 90,000원)</b></li>
<li>사용일 1일 전 또는 당일에 다른 일자로 변경 희망 시: 기존 예약은 취소 후 재예약으로 처리되며 위 규정이 동일 적용됩니다.</li>
<li>계약한 대여 기간보다 적게 사용하셔도 대관비는 환불되지 않습니다.</li>
</ul>
<p>취소·변경은 업무시간 기준으로 접수됩니다: 월–목 12:00–21:00 / 금 12:00–19:00 (위약 판단 기준 시간 동일)</p>
<h3>연장·초과 이용</h3>
<ul>
<li>다음 예약이 없을 때만 연장 가능하며, 1시간 단위로 추가 결제됩니다.</li>
<li>초과 이용 추가 요금은 최대 200,000원까지 부과됩니다(일일 임대 상한과 동일). 일일 임대 갱신 시간은 오전 5시이며, 이후는 새 일자 요금으로 재계산됩니다.</li>
</ul>
<h3>시설·배상</h3>
<ul>
<li>장비·컴퓨터·책상·의자·벽지 등 시설 파손 시 현장 확인 → 사진·기록 → 수리 견적 → 배상 요청 절차로 진행되며, 손해액 배상 의무와 보증금 청구가 발생할 수 있습니다.</li>
<li>방범·분실·기물 파손·인원 확인 목적으로 CCTV가 상시 녹화됩니다.</li>
<li>사전 고지 인원과 실제 출입 인원이 다를 경우(최대 3인) 예약 취소 또는 추가 결제가 요청될 수 있습니다.</li>
</ul>
<p>요금·이용 규칙 전체는 <a href="https://drgo.pro/bbs/board.php?bo_table=pf&wr_id=269" target="_blank" rel="noopener">방송룸 임대 안내 전문</a>을 확인해 주세요.</p>
HTML],
            ['key' => 'goods', 'name' => '장비 판매·배송', 'version' => $v, 'active' => true, 'auto_types' => ['goods'],
                'html' => '<ul><li>발주 전 취소는 전액 환불됩니다.</li><li>발주 후 취소는 공급처 반품 조건에 따라 반품 수수료가 차감될 수 있습니다.</li><li>제품 환불은 미개봉 제품에 한해 배송비 제외 후 가능하며, 배송 완료일로부터 14일 이내입니다.</li><li>개봉·설치 후 단순 변심 반품은 불가하며, 하자는 AS 기준에 따릅니다.</li></ul>'.$draft],
        ];
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
