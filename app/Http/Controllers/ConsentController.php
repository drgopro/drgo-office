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
        $v = 'v1 (2026-11-06)';
        $draft = '<p style="color:#888">※ 기본 초안입니다 — 관리자 페이지 &gt; 판매처 설정에서 실제 문구로 교체하세요.</p>';

        return [
            ['key' => 'common', 'name' => '공통', 'version' => $v, 'active' => true, 'auto_types' => [],
                'html' => '<p>본 안내는 (주)닥터고블린컴퍼니의 거래 변경·취소에 관한 공통 기준입니다.</p><ul><li>견적 금액은 견적 유효기간 내 결제 기준이며, 기간 경과 시 재견적될 수 있습니다.</li><li>결제 후 변경·취소는 아래 해당 항목의 기준을 따릅니다.</li></ul>'.$draft],
            ['key' => 'visit_setup', 'name' => '방문 세팅', 'version' => $v, 'active' => true, 'auto_types' => ['visit', 'onsite'],
                'html' => '<ul><li>설치 예정일 3일 전까지 일정 변경은 위약금 없이 1회 가능합니다.</li><li>예정일 2일 전~당일 취소 시 출장 준비 비용이 발생할 수 있습니다.</li><li>현장 여건(전기·인터넷·구조물)으로 추가 작업이 필요한 경우 사전 협의 후 추가 비용이 발생할 수 있습니다.</li></ul>'.$draft],
            ['key' => 'remote_setup', 'name' => '원격 세팅', 'version' => $v, 'active' => true, 'auto_types' => ['remote'],
                'html' => '<ul><li>원격 세팅 예약 변경은 예정 시각 24시간 전까지 가능합니다.</li><li>세팅 완료 후에는 작업 범위 내 재설정 요청에 한해 지원합니다.</li></ul>'.$draft],
            ['key' => 'rental', 'name' => '렌탈', 'version' => $v, 'active' => true, 'auto_types' => ['rental'],
                'html' => '<ul><li>렌탈 기간 단축·중도 해지 시 잔여 기간 요금의 일부가 위약금으로 발생할 수 있습니다.</li><li>장비 파손·분실 시 수리비 또는 감가 기준 변상금이 청구됩니다.</li></ul>'.$draft],
            ['key' => 'broadcast_room', 'name' => '방송룸 대여', 'version' => $v, 'active' => true, 'auto_types' => ['broadcast_room'],
                'html' => '<ul><li>이용 예정일 전일까지 취소 시 전액 환불, 당일 취소·노쇼는 이용료가 청구될 수 있습니다.</li><li>시설·장비 파손 시 변상 기준을 따릅니다.</li></ul>'.$draft],
            ['key' => 'goods', 'name' => '장비 판매·배송', 'version' => $v, 'active' => true, 'auto_types' => ['goods'],
                'html' => '<ul><li>발주 전 취소는 전액 환불됩니다.</li><li>발주 후 취소는 공급처 반품 조건에 따라 반품 수수료가 차감될 수 있습니다.</li><li>개봉·설치 후 단순 변심 반품은 불가하며, 하자는 AS 기준에 따릅니다.</li></ul>'.$draft],
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
