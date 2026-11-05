<?php

namespace App\Services\EstimateReview;

/**
 * 견적서 검토 기본 설정 — 직원 제작 시안(2026-11-04 오피스 제품 기준)에서 이식.
 *
 * 저장 형식: 제품/카테고리는 이름(경로) 키 — 제품 id 변경에 안전하며,
 * EstimateReviewService가 실행 시점에 id 키로 변환한다.
 * 운영 설정은 Setting('estimate_review.config')이 우선하고, 없으면 이 기본값을 쓴다.
 */
class DefaultReviewConfig
{
    /** @return array<string, mixed> */
    public static function make(): array
    {
        return [
            'version' => 1,
            'gate' => true, // 검토 미완료 견적서의 출력·발행 잠금
            'checks' => ['video' => true, 'audio' => true, 'lens' => true, 'battery' => true, 'mount' => true, 'spec' => true],
            // 규격값 선택지 — 관리 화면의 규격 입력에서 고르는 값. 같아야 맞는 제품으로 판단한다
            'types' => [
                'video' => ['Micro HDMI', 'Mini HDMI', 'HDMI'],
                'audio' => ['XLR', '5.5 TS/TRS', 'USB'],
                'lens' => ['RF', 'RF-S'],
                'battery' => ['LP-E6 계열', 'LP-E6P', 'LP-E17', 'LP-E12'],
                'mount' => ['보웬스', 'FM', 'Ray·Halo 미니'],
            ],
            'roles' => self::roles(),
            'rules' => self::rules(),
            'categoryRolesByPath' => self::categoryRolesByPath(),
            'productRolesByName' => self::productRolesByName(),
            'specTemplatesByPath' => self::specTemplatesByPath(),
            'specsByName' => self::specsByName(),
            'specsByPath' => self::specsByPath(),
        ];
    }

    /** @return array<int, array{id:string,label:string,group:string}> */
    private static function roles(): array
    {
        $rows = [
            ['pc', '컴퓨터 본체', '컴퓨터'], ['monitor', '모니터', '컴퓨터'], ['keyboard', '키보드', '컴퓨터'],
            ['mouse', '마우스', '컴퓨터'], ['speaker', '스피커', '컴퓨터'], ['os', '운영체제', '컴퓨터'],
            ['display_cable', '모니터 영상 케이블', '컴퓨터'],
            ['camera', 'DSLR·미러리스', '카메라'], ['cam_mount', '카메라 거치대', '카메라'], ['dslr_setup', 'DSLR 세팅비', '카메라'],
            ['light', '조명', '조명'], ['mount', '조명 거치대', '조명'], ['flex', '다용도 거치대', '조명'],
            ['verge', 'Verge 조명', '조명'], ['verge_adp', 'Verge 스탠드 어댑터', '조명'], ['strip', '멀티탭', '조명'],
            ['desk_mic', '거치형 마이크', '마이크'], ['mic_stand', '마이크 거치대', '마이크'],
        ];

        return array_map(fn ($r) => ['id' => $r[0], 'label' => $r[1], 'group' => $r[2]], $rows);
    }

    /** @return array<int, array<string, mixed>> */
    private static function rules(): array
    {
        $need = fn (string $group, string $id, array $on, array $needRoles, string $what, string $why, string $fix = '') => [
            'type' => 'need', 'group' => $group, 'id' => $id, 'on' => $on, 'need' => $needRoles,
            'mode' => 'perUnit', 'amt' => 1, 'what' => $what, 'why' => $why, 'fix' => $fix, 'active' => true,
        ];
        $ask = fn (string $group, string $id, array $on, string $cond, array $cmp, string $title, string $desc, array $choices, string $fix = '') => [
            'type' => 'ask', 'group' => $group, 'id' => $id, 'on' => $on, 'cond' => $cond, 'cmp' => $cmp,
            'min' => 1, 'title' => $title, 'desc' => $desc, 'choices' => $choices, 'fix' => $fix, 'active' => true,
        ];

        return [
            $need('컴퓨터', 'pc-monitor', ['pc'], ['monitor'], '모니터', '컴퓨터에 연결할 화면이 필요합니다.'),
            $need('컴퓨터', 'pc-keyboard', ['pc'], ['keyboard'], '키보드', '컴퓨터 대수만큼 필요합니다.'),
            $need('컴퓨터', 'pc-mouse', ['pc'], ['mouse'], '마우스', '컴퓨터 대수만큼 필요합니다.'),
            $need('컴퓨터', 'pc-speaker', ['pc'], ['speaker'], '스피커', '소리를 확인할 스피커가 필요합니다. 스피커 내장 모니터도 인정합니다.'),
            $need('컴퓨터', 'pc-cable', ['monitor'], ['display_cable'], '모니터 영상 케이블', '표준 견적은 모니터마다 영상 케이블을 넣습니다. 모니터 동봉 케이블을 쓰면 사유를 남기세요.', 'HDMI 2.0 케이블 3m'),
            $need('컴퓨터', 'pc-os', ['pc'], ['os'], '운영체제', '컴퓨터 대수만큼 필요합니다.'),
            $need('카메라', 'cam-mount', ['camera'], ['cam_mount'], '카메라 거치대', '카메라 대수만큼 거치대가 필요합니다.'),
            array_merge($need('카메라', 'cam-setup', ['camera'], ['dslr_setup'], 'DSLR 세팅비', '표준 견적은 카메라에 DSLR 세팅비를 넣습니다.', 'DSLR 세팅'), ['mode' => 'atLeast']),
            $need('조명', 'light-mount', ['light'], ['mount', 'flex'], '조명 거치대', '조명 1대마다 거치대 1개가 필요합니다. 세트 구성과 클램프 스탠드도 계산에 넣습니다.'),
            $ask('조명', 'light-flex', ['flex'], 'always', [], '다용도 거치대 {n}개의 용도를 확인하세요', '조명 거치대 수량에 포함해 계산했습니다. 실제 용도가 맞는지 확인하세요.', ['웹캠·카메라용', '마이크용', '조명용']),
            $ask('조명', 'light-extra', ['mount'], 'exceeds', ['light'], '조명 거치대 {d}개가 남습니다', '조명이 빠졌거나 거치대를 많이 넣었을 수 있습니다.', ['예비로 포함', '다른 장비용']),
            $ask('조명', 'verge-adp', ['verge'], 'short', ['verge_adp'], 'Verge 조명을 스탠드에 올리나요?', '스탠드에 올리려면 전용 어댑터가 필요합니다.', ['스탠드에 올리지 않음', '고객 보유'], 'amaran Stand Adapter(Verge & Verge max용)'),
            $ask('조명', 'light-power', ['light'], 'always', [], '조명 {n}대를 꽂을 전원이 충분한가요?', '설치 장소의 콘센트 위치와 멀티탭 수량을 확인하세요.', ['확인함']),
            $need('마이크', 'mic-stand', ['desk_mic'], ['mic_stand'], '마이크 거치대', '마이크 대수만큼 거치대가 필요합니다.'),
        ];
    }

    /** 카테고리 경로(이름 '>' 연결) → [[역할, 수량]] — 빈 배열은 "역할 없음" 명시 */
    private static function categoryRolesByPath(): array
    {
        $m = fn (string ...$roles) => array_map(fn ($r) => [$r, 1], $roles);

        return [
            'PC>PC 본체>송출용' => $m('pc'), 'PC>PC 본체>게임용' => $m('pc'),
            'PC>PC 본체>투컴게임용AMD' => $m('pc'), 'PC>PC 본체>원컴게임용' => $m('pc'),
            'PC>PC 본체>OS' => $m('os'),
            'PC>주변기기>모니터' => $m('monitor'), 'PC>주변기기>키보드' => $m('keyboard'), 'PC>주변기기>마우스' => $m('mouse'),
            '오디오>스피커' => $m('speaker'),
            '비디오>카메라>R 시리즈' => $m('camera'),
            '케이블>비디오>HDMI' => $m('display_cable'), '케이블>비디오>광HDMI' => $m('display_cable'), '케이블>비디오>DP' => $m('display_cable'),
            '비디오>거치대>삼각대' => $m('cam_mount'), '비디오>거치대>모노포드' => $m('cam_mount'), '비디오>거치대>클램프 스탠드' => $m('cam_mount'),
            '조명>조명본체>패널 조명' => $m('light'), '조명>조명본체>COB 조명' => $m('light'), '조명>조명본체>소프트박스' => $m('light'),
            '조명>거치대>삼각대' => $m('mount'), '조명>거치대>클램프 스탠드' => $m('flex'), '조명>거치대>클램프/암' => $m('flex'),
            '케이블>멀티탭' => $m('strip'),
            '오디오>콘덴서 마이크 (원통형)' => $m('desk_mic'), '오디오>다이나믹 마이크' => $m('desk_mic'),
            '오디오>다이나믹 마이크>핸드' => [], '오디오>다이나믹 마이크>무선핸드' => [],
            '오디오>마이크거치대' => $m('mic_stand'),
        ];
    }

    /** 제품명 → [[역할, 수량]] — 카테고리 지정보다 우선 */
    private static function productRolesByName(): array
    {
        $m = fn (array ...$pairs) => $pairs;

        return [
            '[필립스] 27E1N2100A 120 시력보호 4면 보더리스 내장 스피커' => $m(['monitor', 1], ['speaker', 1]),
            '[필립스] 27E1N2100AW 120 시력보호 4면 보더리스 스피커 화이트' => $m(['monitor', 1], ['speaker', 1]),
            '[알파스캔] AOC 16T20 IPS USB-C 포터블 스피커 시력보호 [무결점] (충전기 별도)' => $m(['monitor', 1], ['speaker', 1]),
            '젬볼 RGB 소프트박스 + 스탠드 세트' => $m(['light', 1], ['mount', 1]),
            'DSLR 세팅' => $m(['dslr_setup', 1]),
            'DSLR 세팅(카메라 추가 비용)' => $m(['dslr_setup', 1]),
            'amaran Pano 60c 2-Light Kit 차콜 (2대 세트, 스탠드 포함)' => $m(['light', 2], ['mount', 2]),
            'amaran Pano 60c 2-Light Kit 화이트 (2대 세트, 스탠드 포함)' => $m(['light', 2], ['mount', 2]),
            'amaran Verge 화이트 (돼지코 포함)' => $m(['light', 1], ['verge', 1]),
            'amaran Verge 차콜 (돼지코 포함)' => $m(['light', 1], ['verge', 1]),
            'amaran Verge Max 화이트' => $m(['light', 1], ['verge', 1]),
            'amaran Verge Max 차콜' => $m(['light', 1], ['verge', 1]),
            'amaran Stand Adapter(Verge & Verge max용)' => $m(['verge_adp', 1]),
        ];
    }

    /** 카테고리 경로 → 필수 규격 필드 (6.8 규격 미입력 검사) */
    private static function specTemplatesByPath(): array
    {
        return [
            '비디오>카메라>R 시리즈' => ['vOut', 'lensTake', 'lensIncl', 'batTake'],
            '비디오>렌즈>단렌즈' => ['lensMount'], '비디오>렌즈>줌렌즈' => ['lensMount'], '비디오>렌즈>RF-S' => ['lensMount'],
            '비디오>캡처보드' => ['vIn'],
            '비디오>배터리' => ['batFit'],
            '조명>조명본체>COB 조명' => ['mntTake'],
            '조명>조명부속>젬볼' => ['mntFit'], '조명>조명부속>돔/원형 소프트박스' => ['mntFit'],
            '조명>조명부속>팔각 소프트박스' => ['mntFit'], '조명>조명부속>리플렉터' => ['mntFit'],
            '오디오>콘덴서 마이크 (원통형)' => ['aOut'], '오디오>다이나믹 마이크' => ['aOut'],
            '오디오>오디오믹서' => ['aIn'],
            '케이블>비디오>HDMI' => ['cable'], '케이블>비디오>광HDMI' => ['cable'],
            '케이블>오디오>XLR' => ['cable'], '케이블>오디오>TS / TRS' => ['cable'],
        ];
    }

    /** 카테고리 경로 기본 규격 — 제품명 지정이 없을 때 적용 */
    private static function specsByPath(): array
    {
        return [
            '비디오>캡처보드>외장형' => ['vIn' => ['t' => ['HDMI'], 'n' => 1]],
            '비디오>캡처보드>내장형' => ['vIn' => ['t' => ['HDMI'], 'n' => 1]],
            '비디오>렌즈>단렌즈' => ['lensMount' => ['RF']],
            '비디오>렌즈>줌렌즈' => ['lensMount' => ['RF']],
            '비디오>렌즈>RF-S' => ['lensMount' => ['RF-S']],
            '케이블>비디오>HDMI' => ['cable' => ['a' => 'HDMI', 'b' => 'HDMI', 'kind' => 'cable']],
            '케이블>비디오>광HDMI' => ['cable' => ['a' => 'HDMI', 'b' => 'HDMI', 'kind' => 'cable']],
            '케이블>오디오>XLR' => ['cable' => ['a' => 'XLR', 'b' => 'XLR', 'kind' => 'cable']],
            '케이블>오디오>TS / TRS' => ['cable' => ['a' => '5.5 TS/TRS', 'b' => '5.5 TS/TRS', 'kind' => 'cable']],
            '오디오>콘덴서 마이크 (원통형)' => ['aOut' => ['t' => ['XLR'], 'n' => 1]],
            '오디오>다이나믹 마이크' => ['aOut' => ['t' => ['XLR'], 'n' => 1]],
            '오디오>다이나믹 마이크>핸드' => ['aOut' => ['t' => ['XLR'], 'n' => 1]],
            '오디오>다이나믹 마이크>SM7B 스타일' => ['aOut' => ['t' => ['XLR'], 'n' => 1]],
        ];
    }

    /** 제품명 지정 규격 — 시안의 조사 초안 이식 */
    private static function specsByName(): array
    {
        $port = fn (array $t, int $n = 1) => ['t' => $t, 'n' => $n];
        $cab = fn (string $a, string $b, string $kind = 'cable') => ['a' => $a, 'b' => $b, 'kind' => $kind];
        $cam = fn (string $v, array $bat, bool $kit = false, array $lens = ['RF']) => [
            'vOut' => $port([$v]), 'lensTake' => $lens, 'lensIncl' => $kit, 'batTake' => $bat,
        ];
        $out = [
            'HDMI 리피터 젠더형' => ['cable' => $cab('', '', 'none')],
            'Micro HDMI 케이블 2m (R5/R6M2/R8/R50/R50V)' => ['cable' => $cab('Micro HDMI', 'HDMI')],
            'Micro HDMI 젠더 (R5/R6M2/R8/R50/R50V)' => ['cable' => $cab('Micro HDMI', 'HDMI', 'adapter')],
            'Mini HDMI 케이블 2m (RP)' => ['cable' => $cab('Mini HDMI', 'HDMI')],
            'Mini HDMI 젠더' => ['cable' => $cab('Mini HDMI', 'HDMI', 'adapter')],
            'EOS R6 V' => $cam('HDMI', ['LP-E6P']),
            'EOS R6 Mark III' => $cam('HDMI', ['LP-E6P']),
            'EOS R6 Mark II' => $cam('Micro HDMI', ['LP-E6 계열', 'LP-E6P']),
            'EOS R5' => $cam('Micro HDMI', ['LP-E6 계열', 'LP-E6P']),
            'EOS R8' => $cam('Micro HDMI', ['LP-E17']),
            'EOS RP' => $cam('Mini HDMI', ['LP-E17']),
            'EOS R50 V (Body)' => $cam('Micro HDMI', ['LP-E17'], false, ['RF', 'RF-S']),
            'EOS R50 V 14-30 KIT (블랙)' => $cam('Micro HDMI', ['LP-E17'], true, ['RF', 'RF-S']),
            'EOS R50 V 18-45 KIT (블랙)' => $cam('Micro HDMI', ['LP-E17'], true, ['RF', 'RF-S']),
            'EOS R50 V 18-45 KIT (화이트)' => $cam('Micro HDMI', ['LP-E17'], true, ['RF', 'RF-S']),
            '캐논 DR-E6P + PD-E2 정품배터리' => ['batFit' => ['LP-E6 계열', 'LP-E6P']],
            '캐논 DR-E6 + AC-E6N 정품배터리' => ['batFit' => ['LP-E6 계열']],
            '캐논 DR-E18 + AC-E6N 정품배터리' => ['batFit' => ['LP-E17']],
            'DR-E6 호환 배터리 (충전기 별도)' => ['batFit' => ['LP-E6 계열']],
            'DR-E12 호환 배터리 (충전기 별도)' => ['batFit' => ['LP-E12']],
            'DR-E18 호환배터리 (usb c타입 / 충전기 별도)' => ['batFit' => ['LP-E17']],
            'amaran Ray 60c' => ['mntTake' => ['보웬스', 'Ray·Halo 미니']],
            'amaran Ray 120c' => ['mntTake' => ['보웬스', 'Ray·Halo 미니']],
            'amaran Ray 360c' => ['mntTake' => ['보웬스']],
            'amaran Halo 60x' => ['mntTake' => ['보웬스', 'Ray·Halo 미니']],
            'amaran Halo 100x' => ['mntTake' => ['보웬스', 'Ray·Halo 미니']],
            'amaran mini Reflector (Ray/Halo 60c & 120c용)' => ['mntFit' => ['Ray·Halo 미니']],
            '난라이트 RF-FMM-45-S 리플렉터 45도 FMM 마운트 전용' => ['mntFit' => ['FM']],
            '난라이트 LT-65 보웬스 마운트 타입 랜턴 젬볼 65' => ['mntFit' => ['보웬스']],
            'amaran lantern 65' => ['mntFit' => ['보웬스']],
            'amaran light dome 60' => ['mntFit' => ['보웬스']],
            'araman Octa Dome 30' => ['mntFit' => ['보웬스']],
            '울란지 AS-045 퀵릴리즈 보웬스 마운트 타입 조명 팔각 파라볼릭 소프트박스' => ['mntFit' => ['보웬스']],
            '난라이트 SB-FMM-O-60 FMM 마운트 타입 옥타 60 소프트박스' => ['mntFit' => ['FM']],
            '난라이트 SB-FMM-O-40 FMM 마운트 타입 옥타 40 소프트박스' => ['mntFit' => ['FM']],
            '에버미디어 Live Gamer DUO GC570D' => ['vIn' => $port(['HDMI'], 2)],
            'ATEM MINI PRO' => ['vIn' => $port(['HDMI'], 4)],
            '야마하 MG12XU' => ['aIn' => $port(['XLR', '5.5 TS/TRS'], 6)],
            '스테인버그 UR24C 오디오 인터페이스' => ['aIn' => $port(['XLR', '5.5 TS/TRS'], 2)],
            '스테인버그 UR44C 오디오 인터페이스' => ['aIn' => $port(['XLR', '5.5 TS/TRS'], 4)],
            'RODE Caster Pro II' => ['aIn' => $port(['XLR', '5.5 TS/TRS'], 4)],
            'RME Babyface Pro FS 베이비페이스 오디오 인터페이스' => ['aIn' => $port(['XLR'], 2)],
            '베링거 ADA8200 오디오인터페이스' => [
                'aIn' => $port(['XLR', '5.5 TS/TRS'], 8),
                'warnAlways' => ['title' => 'ADA8200은 USB가 없습니다', 'desc' => 'ADAT 입력이 있는 오디오 인터페이스가 함께 있어야 컴퓨터에 연결됩니다.'],
            ],
            'INFRASONIC UFO Mini' => ['aOut' => $port(['USB'])],
            '젠하이저 XSW 1-825' => ['aOut' => $port(['XLR', '5.5 TS/TRS'])],
            '젠하이저 XSW 1-835' => ['aOut' => $port(['XLR', '5.5 TS/TRS'])],
            '젠하이저 XSW 1-825 Dual' => ['aOut' => $port(['XLR', '5.5 TS/TRS'], 2)],
            '젠하이저 XSW 1-835 Dual' => ['aOut' => $port(['XLR', '5.5 TS/TRS'], 2)],
            '젠하이저 EW-D 835-S SET' => ['aOut' => $port(['XLR', '5.5 TS/TRS'])],
            '젠하이저 EW-D 845-S SET' => ['aOut' => $port(['XLR', '5.5 TS/TRS'])],
        ];
        // Pano 60c 계열 — 마운트 없음(명시된 빈 값)
        foreach ([
            'amaran Pano 60c Kit 화이트 (스탠드 미포함)', 'amaran Pano 60c Kit 차콜 (스탠드 미포함)',
            'amaran Pano 60c 2-Light Kit 차콜 (2대 세트, 스탠드 포함)', 'amaran Pano 60c 2-Light Kit 화이트 (2대 세트, 스탠드 포함)',
        ] as $n) {
            $out[$n] = ['mntTake' => []];
        }
        // FS/FC 계열 — FM+보웬스, 보웬스 부속이면 어댑터 확인 질문
        $adp = '제조사 구성에는 보웬스 어댑터가 있지만 이 상품은 본체 단품입니다. 어댑터 포함 여부를 확인하세요';
        foreach (['FS-60B' => '난라이트 FS-60B 스튜디오 LED 조명 (본체 단품)', 'FC-60B' => '난라이트 FC-60B 스튜디오 소형 LED 스팟 조명 (본체 단품)', 'FC-120B' => '난라이트 FC-120B 스튜디오 소형 LED 스팟 조명 (본체 단품)'] as $model => $n) {
            $out[$n] = [
                'mntTake' => ['FM', '보웬스'],
                'warnIf' => ['field' => 'mntFit', 'value' => '보웬스', 'title' => "{$model}에 보웬스 부속을 쓰려면 어댑터가 필요합니다", 'desc' => $adp],
            ];
        }
        // 야마하 AG 시리즈
        foreach (['화이트', '블랙'] as $x) {
            $out["야마하 AG03 MK2 {$x}"] = ['aIn' => $port(['XLR', '5.5 TS/TRS'], 1)];
            $out["야마하 AG06 MK2 {$x}"] = ['aIn' => $port(['XLR', '5.5 TS/TRS'], 2)];
            $out["야마하 AG08 {$x}"] = ['aIn' => $port(['XLR', '5.5 TS/TRS'], 2)];
            $out["TOPPING E2x2 OTG 오디오인터페이스 {$x}"] = ['aIn' => $port(['XLR', '5.5 TS/TRS'], 2)];
        }

        return $out;
    }
}
