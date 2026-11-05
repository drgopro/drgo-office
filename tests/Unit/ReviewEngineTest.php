<?php

namespace Tests\Unit;

use App\Services\EstimateReview\ReviewEngine;
use App\Services\EstimateReview\ReviewRoleCounter;
use PHPUnit\Framework\TestCase;

/**
 * 견적서 검토 판정 엔진 — 명세 12장 판정 35케이스 + 5장 역할 계산.
 * 순수 함수 테스트 (DB 없음). 제품 규격은 명세 부록 B 초안 기준 픽스처.
 */
class ReviewEngineTest extends TestCase
{
    // 제품 id 픽스처 (부록 B 대응)
    private const R6II = 1;      // vOut Micro HDMI, RF, bat E6계열/E6P

    private const R6III = 2;     // vOut HDMI, RF, bat E6P

    private const RP = 3;        // vOut Mini HDMI, RF, bat E17

    private const R8 = 4;        // vOut Micro HDMI, RF, bat E17

    private const R50V_KIT = 5;  // lensIncl=true

    private const GC311 = 10;    // vIn HDMI ×1

    private const GC570D = 11;   // vIn HDMI ×2

    private const HDMI3M = 20;   // cable HDMI-HDMI

    private const MICRO_CABLE = 21;

    private const MICRO_GENDER = 22; // adapter

    private const MINI_CABLE = 23;

    private const AT2035 = 30;   // aOut XLR

    private const MV7X = 31;     // aOut XLR

    private const AG03 = 32;     // aIn ×1

    private const AG06 = 33;     // aIn ×2

    private const XLR = 34;      // cable XLR-XLR

    private const UFO = 35;      // aOut USB

    private const ADA8200 = 36;  // aIn ×8 + warnAlways

    private const RF50 = 40;     // lensMount RF

    private const RFS_LENS = 41; // lensMount RF-S (테스트용)

    private const DR_E18 = 50;   // batFit E17

    private const DR_E6P = 51;   // batFit E6계열/E6P

    private const DR_E6 = 52;    // batFit E6계열

    private const RAY120 = 60;   // mntTake 보웬스/Ray미니

    private const RAY360 = 61;   // mntTake 보웬스

    private const FS60B = 62;    // mntTake FM/보웬스 + warnIf 보웬스

    private const PANO_KIT = 63; // mntTake [] (마운트 없음 — 입력된 값)

    private const SB_FMM = 70;   // mntFit FM

    private const LT65 = 71;     // mntFit 보웬스

    private const MINI_REF = 72; // mntFit Ray미니

    private const TMW100 = 80;   // 규격 미입력 (카테고리 필수: aOut)

    private const R8_SET = 90;   // 세트: R8 + GC311 + Micro케이블 + RF50 + DR-E18

    /** @return array<int, array<string, mixed>> */
    private function catalog(): array
    {
        $plain = fn (string $name, array $chain = []) => ['name' => $name, 'group_id' => null, 'category_chain' => $chain, 'is_bundle' => false, 'components' => []];

        return [
            self::R6II => $plain('EOS R6 Mark II'),
            self::R6III => $plain('EOS R6 Mark III'),
            self::RP => $plain('EOS RP'),
            self::R8 => $plain('EOS R8'),
            self::R50V_KIT => $plain('EOS R50 V 18-45 KIT'),
            self::GC311 => $plain('GC311G2'),
            self::GC570D => $plain('GC570D'),
            self::HDMI3M => $plain('HDMI 2.0 케이블 3m'),
            self::MICRO_CABLE => $plain('Micro HDMI 케이블 2m'),
            self::MICRO_GENDER => $plain('Micro HDMI 젠더'),
            self::MINI_CABLE => $plain('Mini HDMI 케이블 2m'),
            self::AT2035 => $plain('AT2035'),
            self::MV7X => $plain('SHURE MV7X'),
            self::AG03 => $plain('AG03 MK2'),
            self::AG06 => $plain('AG06 MK2'),
            self::XLR => $plain('XLR 케이블 3m'),
            self::UFO => $plain('UFO Mini'),
            self::ADA8200 => $plain('베링거 ADA8200'),
            self::RF50 => $plain('캐논 RF 50mm F1.8 STM'),
            self::RFS_LENS => $plain('RF-S 테스트 렌즈'),
            self::DR_E18 => $plain('DR-E18 호환배터리'),
            self::DR_E6P => $plain('캐논 DR-E6P'),
            self::DR_E6 => $plain('캐논 DR-E6'),
            self::RAY120 => $plain('amaran Ray 120c'),
            self::RAY360 => $plain('amaran Ray 360c'),
            self::FS60B => $plain('난라이트 FS-60B'),
            self::PANO_KIT => $plain('amaran Pano 60c Kit'),
            self::SB_FMM => $plain('난라이트 SB-FMM-O-60'),
            self::LT65 => $plain('난라이트 LT-65'),
            self::MINI_REF => $plain('amaran mini Reflector'),
            self::TMW100 => $plain('TJ미디어 TMW-100', [801, 800]),
            self::R8_SET => [
                'name' => 'R8 방송 세트', 'group_id' => null, 'category_chain' => [], 'is_bundle' => true,
                'components' => [[self::R8, 1], [self::GC311, 1], [self::MICRO_CABLE, 1], [self::RF50, 1], [self::DR_E18, 1]],
            ],
        ];
    }

    /** 부록 B 초안 규격 */
    private function specs(): array
    {
        return [
            self::R6II => ['vOut' => ['t' => ['Micro HDMI'], 'n' => 1], 'lensTake' => ['RF'], 'lensIncl' => false, 'batTake' => ['LP-E6 계열', 'LP-E6P']],
            self::R6III => ['vOut' => ['t' => ['HDMI'], 'n' => 1], 'lensTake' => ['RF'], 'lensIncl' => false, 'batTake' => ['LP-E6P']],
            self::RP => ['vOut' => ['t' => ['Mini HDMI'], 'n' => 1], 'lensTake' => ['RF'], 'lensIncl' => false, 'batTake' => ['LP-E17']],
            self::R8 => ['vOut' => ['t' => ['Micro HDMI'], 'n' => 1], 'lensTake' => ['RF'], 'lensIncl' => false, 'batTake' => ['LP-E17']],
            self::R50V_KIT => ['vOut' => ['t' => ['Micro HDMI'], 'n' => 1], 'lensTake' => ['RF', 'RF-S'], 'lensIncl' => true, 'batTake' => ['LP-E17']],
            self::GC311 => ['vIn' => ['t' => ['HDMI'], 'n' => 1]],
            self::GC570D => ['vIn' => ['t' => ['HDMI'], 'n' => 2]],
            self::HDMI3M => ['cable' => ['a' => 'HDMI', 'b' => 'HDMI', 'kind' => 'cable']],
            self::MICRO_CABLE => ['cable' => ['a' => 'Micro HDMI', 'b' => 'HDMI', 'kind' => 'cable']],
            self::MICRO_GENDER => ['cable' => ['a' => 'Micro HDMI', 'b' => 'HDMI', 'kind' => 'adapter']],
            self::MINI_CABLE => ['cable' => ['a' => 'Mini HDMI', 'b' => 'HDMI', 'kind' => 'cable']],
            self::AT2035 => ['aOut' => ['t' => ['XLR'], 'n' => 1]],
            self::MV7X => ['aOut' => ['t' => ['XLR'], 'n' => 1]],
            self::AG03 => ['aIn' => ['t' => ['XLR', '5.5 TS/TRS'], 'n' => 1]],
            self::AG06 => ['aIn' => ['t' => ['XLR', '5.5 TS/TRS'], 'n' => 2]],
            self::XLR => ['cable' => ['a' => 'XLR', 'b' => 'XLR', 'kind' => 'cable']],
            self::UFO => ['aOut' => ['t' => ['USB'], 'n' => 1]],
            self::ADA8200 => ['aIn' => ['t' => ['XLR', '5.5 TS/TRS'], 'n' => 8], 'warnAlways' => ['title' => 'ADA8200은 USB가 없습니다', 'desc' => 'ADAT 입력이 있는 오디오 인터페이스가 함께 있어야 컴퓨터에 연결됩니다.']],
            self::RF50 => ['lensMount' => ['RF']],
            self::RFS_LENS => ['lensMount' => ['RF-S']],
            self::DR_E18 => ['batFit' => ['LP-E17']],
            self::DR_E6P => ['batFit' => ['LP-E6 계열', 'LP-E6P']],
            self::DR_E6 => ['batFit' => ['LP-E6 계열']],
            self::RAY120 => ['mntTake' => ['보웬스', 'Ray·Halo 미니']],
            self::RAY360 => ['mntTake' => ['보웬스']],
            self::FS60B => ['mntTake' => ['FM', '보웬스'], 'warnIf' => ['field' => 'mntFit', 'value' => '보웬스', 'title' => 'FS-60B에 보웬스 부속을 쓰려면 어댑터가 필요합니다', 'desc' => '어댑터 포함 여부를 확인하세요']],
            self::PANO_KIT => ['mntTake' => []],
            self::SB_FMM => ['mntFit' => ['FM']],
            self::LT65 => ['mntFit' => ['보웬스']],
            self::MINI_REF => ['mntFit' => ['Ray·Halo 미니']],
            // TMW100은 의도적으로 규격 없음 (미입력)
        ];
    }

    private function config(array $overrides = []): array
    {
        return array_merge([
            'version' => 1,
            'categoryRoles' => [],
            'groupRoles' => [],
            'productRoles' => [],
            'rules' => [],
            'checks' => ['video' => false, 'audio' => false, 'lens' => false, 'battery' => false, 'mount' => false, 'spec' => false],
            'specTemplates' => [],
            'specs' => $this->specs(),
        ], $overrides);
    }

    /** @param array<int, array{0:int,1:int}> $pairs [productId, qty] */
    private function items(array $pairs): array
    {
        return array_map(fn ($p) => ['product_id' => $p[0], 'name' => $this->catalog()[$p[0]]['name'], 'qty' => $p[1], 'price' => 10000], $pairs);
    }

    private function review(array $pairs, array $configOverrides): array
    {
        return ReviewEngine::run($this->items($pairs), $this->catalog(), $this->config($configOverrides));
    }

    private function keys(array $result): array
    {
        return array_column($result['findings'], 'key');
    }

    private function video(array $pairs): array
    {
        return $this->review($pairs, ['checks' => ['video' => true]]);
    }

    private function audio(array $pairs): array
    {
        return $this->review($pairs, ['checks' => ['audio' => true]]);
    }

    // ── 5장: 역할 수량 계산 ──

    public function test_role_counting_follows_spec_example(): void
    {
        // 명세 5.2 예시 — 조명 4, 조명 거치대 2, 다용도 거치대 2
        $catalog = [
            100 => ['name' => 'amaran Halo 60x', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
            101 => ['name' => '룩스패드 K22H + 거치대 세트', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
            102 => ['name' => 'PRO-203A 스탠드', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
            103 => ['name' => '테이블 클램프 3단 스탠드', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
        ];
        $config = $this->config(['productRoles' => [
            100 => [['light', 1]], 101 => [['light', 1], ['mount', 1]], 102 => [['mount', 1]], 103 => [['flex', 1]],
        ]]);
        $items = [
            ['product_id' => 100, 'qty' => 3], ['product_id' => 101, 'qty' => 1],
            ['product_id' => 102, 'qty' => 1], ['product_id' => 103, 'qty' => 2],
        ];

        $counts = ReviewRoleCounter::countAll($items, $catalog, $config);
        $this->assertSame(4, $counts['light']);
        $this->assertSame(2, $counts['mount']);
        $this->assertSame(2, $counts['flex']);
    }

    public function test_deepest_category_empty_roles_means_none(): void
    {
        // 5.1-2 — 하위 카테고리의 빈 배열은 "역할 없음" 지정 (상위와 합치지 않음)
        $catalog = [200 => ['name' => '무선핸드 마이크', 'group_id' => null, 'category_chain' => [12, 11, 10], 'is_bundle' => false, 'components' => []]];
        $config = $this->config(['categoryRoles' => [11 => [['desk_mic', 1]], 12 => []]]);

        $this->assertSame([], ReviewRoleCounter::countAll([['product_id' => 200, 'qty' => 1]], $catalog, $config));
    }

    public function test_bundle_roles_use_max_of_category_and_component_sum(): void
    {
        // 5.1-3 — 세트: max(카테고리 값, 구성품 합계). 구성품은 합계로 센다
        $catalog = [
            300 => ['name' => '조명 2구 세트', 'group_id' => null, 'category_chain' => [20], 'is_bundle' => true, 'components' => [[301, 1], [302, 1]]],
            301 => ['name' => '조명 A', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
            302 => ['name' => '조명 B', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
        ];
        $config = $this->config([
            'categoryRoles' => [20 => [['light', 1]]],
            'productRoles' => [301 => [['light', 1]], 302 => [['light', 1]]],
        ]);

        $counts = ReviewRoleCounter::countAll([['product_id' => 300, 'qty' => 1]], $catalog, $config);
        $this->assertSame(2, $counts['light']); // max(1, 1+1)
    }

    // ── 12장 판정 1~11: 영상 연결 ──

    public function test_case01_micro_out_with_plain_hdmi_is_broken(): void
    {
        $r = $this->video([[self::R6II, 1], [self::GC311, 1], [self::HDMI3M, 1]]);
        $this->assertContains('video:'.self::R6II.':0:nopath', $this->keys($r));
    }

    public function test_case02_micro_cable_connects(): void
    {
        $r = $this->video([[self::R6II, 1], [self::GC311, 1], [self::MICRO_CABLE, 1]]);
        $this->assertSame([], $this->keys($r));
        $this->assertCount(1, $r['paths']);
    }

    public function test_case03_gender_plus_cable_connects_with_four_nodes(): void
    {
        $r = $this->video([[self::R6II, 1], [self::GC311, 1], [self::MICRO_GENDER, 1], [self::HDMI3M, 1]]);
        $this->assertSame([], $this->keys($r));
        $this->assertCount(1, $r['paths']);
        $this->assertCount(2, $r['paths'][0]['via']); // 젠더 + 케이블 = 4개 노드
    }

    public function test_case04_gender_only_is_broken(): void
    {
        // 케이블을 최소 1개 거쳐야 성공 (6.3)
        $r = $this->video([[self::R6II, 1], [self::GC311, 1], [self::MICRO_GENDER, 1]]);
        $this->assertContains('video:'.self::R6II.':0:nopath', $this->keys($r));
    }

    public function test_case05_hdmi_out_with_hdmi_cable_connects(): void
    {
        $r = $this->video([[self::R6III, 1], [self::GC311, 1], [self::HDMI3M, 1]]);
        $this->assertSame([], $this->keys($r));
        $this->assertCount(1, $r['paths']);
    }

    public function test_case06_mini_out_with_micro_cable_is_broken(): void
    {
        $r = $this->video([[self::RP, 1], [self::GC311, 1], [self::MICRO_CABLE, 1]]);
        $this->assertContains('video:'.self::RP.':0:nopath', $this->keys($r));
    }

    public function test_case07_mini_cable_connects(): void
    {
        $r = $this->video([[self::RP, 1], [self::GC311, 1], [self::MINI_CABLE, 1]]);
        $this->assertSame([], $this->keys($r));
    }

    public function test_case08_camera_alone_reports_nosink(): void
    {
        $r = $this->video([[self::R6II, 1]]);
        $finding = collect($r['findings'])->firstWhere('key', 'video:'.self::R6II.':0:nosink');
        $this->assertNotNull($finding);
        $this->assertSame('캡처보드 없음', $finding['title']);
    }

    public function test_case09_two_cameras_one_input_reports_full(): void
    {
        $r = $this->video([[self::R6III, 2], [self::GC311, 1], [self::HDMI3M, 2]]);
        $this->assertCount(1, $r['paths']);
        $this->assertContains('video:'.self::R6III.':1:full', $this->keys($r));
    }

    public function test_case10_dual_input_connects_both(): void
    {
        $r = $this->video([[self::R6III, 2], [self::GC570D, 1], [self::HDMI3M, 2]]);
        $this->assertSame([], $this->keys($r));
        $this->assertCount(2, $r['paths']);
    }

    public function test_case11_dual_input_single_cable_second_broken(): void
    {
        $r = $this->video([[self::R6III, 2], [self::GC570D, 1], [self::HDMI3M, 1]]);
        $this->assertCount(1, $r['paths']);
        $finding = collect($r['findings'])->firstWhere('key', 'video:'.self::R6III.':1:nopath');
        $this->assertNotNull($finding);
        $this->assertStringContainsString('2번째 연결 끊김', $finding['title']);
    }

    // ── 12~16: 배터리 ──

    public function test_case12_wrong_battery_reports_fit(): void
    {
        $r = $this->review([[self::R6II, 1], [self::DR_E18, 1]], ['checks' => ['battery' => true]]);
        $this->assertContains('batfit:'.self::DR_E18, $this->keys($r));
    }

    public function test_case13_matching_battery_no_warning(): void
    {
        $r = $this->review([[self::R8, 1], [self::DR_E18, 1]], ['checks' => ['battery' => true]]);
        $this->assertNotContains('batfit:'.self::DR_E18, $this->keys($r));
        $this->assertNotContains('batlong:'.self::R8, $this->keys($r));
    }

    public function test_case14_e6_battery_with_r6iii_reports_fit(): void
    {
        $r = $this->review([[self::R6III, 1], [self::DR_E6, 1]], ['checks' => ['battery' => true]]);
        $this->assertContains('batfit:'.self::DR_E6, $this->keys($r));
    }

    public function test_case15_e6p_battery_with_r6iii_no_warning(): void
    {
        $r = $this->review([[self::R6III, 1], [self::DR_E6P, 1]], ['checks' => ['battery' => true]]);
        $this->assertSame([], $this->keys($r));
    }

    public function test_case16_unmatched_camera_gets_long_use_question(): void
    {
        $r = $this->review([[self::R6II, 1], [self::R8, 1], [self::DR_E18, 1]], ['checks' => ['battery' => true]]);
        $this->assertContains('batlong:'.self::R6II, $this->keys($r)); // R6 II만 장시간 질문
        $this->assertNotContains('batlong:'.self::R8, $this->keys($r));
        $this->assertNotContains('batfit:'.self::DR_E18, $this->keys($r));
    }

    // ── 17~19: 렌즈 ──

    public function test_case17_rfs_lens_with_rf_camera_reports_both_fits(): void
    {
        $r = $this->review([[self::R6II, 1], [self::RFS_LENS, 1]], ['checks' => ['lens' => true]]);
        $keys = $this->keys($r);
        $this->assertContains('lens:'.self::R6II.':0', $keys);      // 카메라: 맞는 렌즈 없음 (fit)
        $this->assertContains('lensfit:'.self::RFS_LENS, $keys);    // 렌즈: 호환 안 됨
        $this->assertSame('fit', collect($r['findings'])->firstWhere('key', 'lens:'.self::R6II.':0')['kind']);
    }

    public function test_case18_kit_camera_skips_lens_check(): void
    {
        $r = $this->review([[self::R50V_KIT, 1]], ['checks' => ['lens' => true]]);
        $this->assertSame([], $this->keys($r));
    }

    public function test_case19_matching_lens_no_warning(): void
    {
        $r = $this->review([[self::R6II, 1], [self::RF50, 1]], ['checks' => ['lens' => true]]);
        $this->assertSame([], $this->keys($r));
    }

    public function test_camera_without_any_lens_is_miss_per_unit(): void
    {
        // 6.4 — 견적에 렌즈가 하나도 없으면 miss, 바디 2대면 대당 1건 (7.2)
        $r = $this->review([[self::R6II, 2]], ['checks' => ['lens' => true]]);
        $keys = $this->keys($r);
        $this->assertContains('lens:'.self::R6II.':0', $keys);
        $this->assertContains('lens:'.self::R6II.':1', $keys);
        $this->assertSame('miss', $r['findings'][0]['kind']);
    }

    // ── 20~25: 오디오 ──

    public function test_case20_xlr_mic_without_cable_is_broken(): void
    {
        $r = $this->audio([[self::AT2035, 1], [self::AG03, 1]]);
        $this->assertContains('audio:'.self::AT2035.':0:nopath', $this->keys($r));
    }

    public function test_case21_xlr_cable_connects(): void
    {
        $r = $this->audio([[self::AT2035, 1], [self::AG03, 1], [self::XLR, 1]]);
        $this->assertSame([], $this->keys($r));
        $this->assertCount(1, $r['paths']);
    }

    public function test_case22_two_mics_single_input_reports_full(): void
    {
        $r = $this->audio([[self::AT2035, 1], [self::MV7X, 1], [self::AG03, 1], [self::XLR, 2]]);
        $this->assertCount(1, $r['paths']);
        $this->assertCount(1, $r['findings']);
        $this->assertStringEndsWith(':full', $r['findings'][0]['key']);
    }

    public function test_case23_two_mics_dual_input_connects(): void
    {
        $r = $this->audio([[self::AT2035, 1], [self::MV7X, 1], [self::AG06, 1], [self::XLR, 2]]);
        $this->assertSame([], $this->keys($r));
        $this->assertCount(2, $r['paths']);
    }

    public function test_case24_usb_mic_skips_mixer_check(): void
    {
        $r = $this->audio([[self::UFO, 1]]);
        $this->assertSame([], $this->keys($r));
    }

    public function test_case25_ada8200_always_warns(): void
    {
        $r = $this->audio([[self::ADA8200, 1]]);
        $finding = collect($r['findings'])->firstWhere('key', 'warn:'.self::ADA8200);
        $this->assertNotNull($finding);
        $this->assertSame('ADA8200은 USB가 없습니다', $finding['title']);
    }

    // ── 26~32: 조명 마운트 ──

    public function test_case26_fm_softbox_with_bowens_only_light_reports_fit(): void
    {
        $r = $this->review([[self::RAY120, 1], [self::SB_FMM, 1]], ['checks' => ['mount' => true]]);
        $this->assertContains('mount:'.self::SB_FMM.':'.self::RAY120, $this->keys($r));
    }

    public function test_case27_fm_softbox_with_fs60b_no_warning(): void
    {
        $r = $this->review([[self::FS60B, 1], [self::SB_FMM, 1]], ['checks' => ['mount' => true]]);
        $this->assertSame([], $this->keys($r));
    }

    public function test_case28_bowens_accessory_on_fs60b_asks_adapter(): void
    {
        $r = $this->review([[self::FS60B, 1], [self::LT65, 1]], ['checks' => ['mount' => true]]);
        $keys = $this->keys($r);
        $this->assertContains('mountadp:'.self::FS60B.':'.self::LT65, $keys);
        $this->assertCount(1, $keys); // fit 없음
    }

    public function test_case29_bowens_accessory_on_ray_no_warning(): void
    {
        $r = $this->review([[self::RAY120, 1], [self::LT65, 1]], ['checks' => ['mount' => true]]);
        $this->assertSame([], $this->keys($r));
    }

    public function test_case30_mini_reflector_on_ray120_compatible(): void
    {
        $r = $this->review([[self::RAY120, 1], [self::MINI_REF, 1]], ['checks' => ['mount' => true]]);
        $this->assertSame([], $this->keys($r));
    }

    public function test_case31_mini_reflector_on_ray360_reports_fit(): void
    {
        $r = $this->review([[self::RAY360, 1], [self::MINI_REF, 1]], ['checks' => ['mount' => true]]);
        $this->assertContains('mount:'.self::MINI_REF.':'.self::RAY360, $this->keys($r));
    }

    public function test_case32_pano_with_empty_mount_value_reports_fit(): void
    {
        // mntTake []는 "마운트 없음"이라는 입력된 값 — 미입력과 달리 판정에 그대로 쓴다 (6.7)
        $r = $this->review([[self::PANO_KIT, 1], [self::LT65, 1]], ['checks' => ['mount' => true]]);
        $this->assertContains('mount:'.self::LT65.':'.self::PANO_KIT, $this->keys($r));
    }

    // ── 33~35: 공백·세트·표준 견적 ──

    public function test_case33_missing_required_spec_asks(): void
    {
        $r = $this->review([[self::TMW100, 1]], [
            'checks' => ['spec' => true],
            'specTemplates' => [801 => ['aOut']],
        ]);
        $finding = collect($r['findings'])->firstWhere('key', 'spec:'.self::TMW100);
        $this->assertNotNull($finding);
        $this->assertStringContainsString('규격 미입력', $finding['title']);
    }

    public function test_case34_bundle_expands_and_connects_cleanly(): void
    {
        // R8 세트 — 구성품 전개로 연결·호환 경고 0, 경로 1
        $r = $this->review([[self::R8_SET, 1]], [
            'checks' => ['video' => true, 'audio' => true, 'lens' => true, 'battery' => true, 'mount' => true, 'spec' => true],
        ]);
        $this->assertSame([], $this->keys($r));
        $this->assertCount(1, $r['paths']);
    }

    public function test_case35_standard_estimate_reports_exactly_four_findings(): void
    {
        // 명세 12장 #35 — 기대 키: pc-speaker:1:0 / light-flex:2:0 / light-power:4:0 / video:{R8}:0:nosink
        $catalog = $this->catalog() + [
            110 => ['name' => '송출용 PC', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
            111 => ['name' => '모니터', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
            112 => ['name' => '키보드', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
            113 => ['name' => '마우스', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
            114 => ['name' => 'Windows 11', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
            115 => ['name' => '삼각대', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
            116 => ['name' => 'DSLR 세팅', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
            117 => ['name' => 'amaran Halo 60x', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
            118 => ['name' => '룩스패드 세트', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
            119 => ['name' => 'PRO-203A 스탠드', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
            120 => ['name' => '테이블 클램프', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
        ];
        $rules = [
            ['id' => 'pc-monitor', 'type' => 'need', 'group' => '컴퓨터', 'on' => ['pc'], 'need' => ['monitor'], 'mode' => 'perUnit', 'amt' => 1, 'what' => '모니터', 'active' => true],
            ['id' => 'pc-keyboard', 'type' => 'need', 'group' => '컴퓨터', 'on' => ['pc'], 'need' => ['keyboard'], 'mode' => 'perUnit', 'amt' => 1, 'what' => '키보드', 'active' => true],
            ['id' => 'pc-mouse', 'type' => 'need', 'group' => '컴퓨터', 'on' => ['pc'], 'need' => ['mouse'], 'mode' => 'perUnit', 'amt' => 1, 'what' => '마우스', 'active' => true],
            ['id' => 'pc-speaker', 'type' => 'need', 'group' => '컴퓨터', 'on' => ['pc'], 'need' => ['speaker'], 'mode' => 'perUnit', 'amt' => 1, 'what' => '스피커', 'active' => true],
            ['id' => 'pc-cable', 'type' => 'need', 'group' => '컴퓨터', 'on' => ['monitor'], 'need' => ['display_cable'], 'mode' => 'perUnit', 'amt' => 1, 'what' => '모니터 영상 케이블', 'active' => true],
            ['id' => 'pc-os', 'type' => 'need', 'group' => '컴퓨터', 'on' => ['pc'], 'need' => ['os'], 'mode' => 'perUnit', 'amt' => 1, 'what' => '운영체제', 'active' => true],
            ['id' => 'cam-mount', 'type' => 'need', 'group' => '카메라', 'on' => ['camera'], 'need' => ['cam_mount'], 'mode' => 'perUnit', 'amt' => 1, 'what' => '카메라 거치대', 'active' => true],
            ['id' => 'cam-setup', 'type' => 'need', 'group' => '카메라', 'on' => ['camera'], 'need' => ['dslr_setup'], 'mode' => 'atLeast', 'amt' => 1, 'what' => 'DSLR 세팅비', 'active' => true],
            ['id' => 'light-mount', 'type' => 'need', 'group' => '조명', 'on' => ['light'], 'need' => ['mount', 'flex'], 'mode' => 'perUnit', 'amt' => 1, 'what' => '조명 거치대', 'active' => true],
            ['id' => 'light-flex', 'type' => 'ask', 'group' => '조명', 'on' => ['flex'], 'cond' => 'always', 'cmp' => [], 'min' => 1, 'title' => '다용도 거치대 {n}개의 용도를 확인하세요', 'choices' => ['웹캠·카메라용', '마이크용', '조명용'], 'active' => true],
            ['id' => 'light-power', 'type' => 'ask', 'group' => '조명', 'on' => ['light'], 'cond' => 'always', 'cmp' => [], 'min' => 1, 'title' => '조명 {n}대를 꽂을 전원이 충분한가요?', 'choices' => ['확인함'], 'active' => true],
        ];
        $config = $this->config([
            'rules' => $rules,
            'checks' => ['video' => true],
            'productRoles' => [
                110 => [['pc', 1]], 111 => [['monitor', 1]], 112 => [['keyboard', 1]], 113 => [['mouse', 1]],
                114 => [['os', 1]], 115 => [['cam_mount', 1]], 116 => [['dslr_setup', 1]],
                117 => [['light', 1]], 118 => [['light', 1], ['mount', 1]], 119 => [['mount', 1]], 120 => [['flex', 1]],
                self::HDMI3M => [['display_cable', 1]], self::R8 => [['camera', 1]],
            ],
        ]);
        $items = array_map(fn ($p) => ['product_id' => $p[0], 'name' => $catalog[$p[0]]['name'], 'qty' => $p[1], 'price' => 10000], [
            [110, 1], [111, 1], [112, 1], [113, 1], [114, 1], [self::HDMI3M, 1],
            [self::R8, 1], [115, 1], [116, 1],
            [117, 3], [118, 1], [119, 1], [120, 2],
        ]);

        $r = ReviewEngine::run($items, $catalog, $config);
        $keys = $this->keys($r);
        sort($keys);
        $expected = ['light-flex:2:0', 'light-power:4:0', 'pc-speaker:1:0', 'video:'.self::R8.':0:nosink'];
        sort($expected);
        $this->assertSame($expected, $keys);
        $this->assertSame(1, $r['counts']['miss_qty']);
        $this->assertSame(1, $r['counts']['link_fit']);
        $this->assertSame(2, $r['counts']['ask']);
    }

    // ── 수량 규칙·확인 질문 개별 의미 (6.1/6.2/7.2) ──

    public function test_need_rule_counts_one_finding_regardless_of_shortage(): void
    {
        // 컴퓨터 3대 스피커 1개 → 1건, key pc-speaker:3:1 (7.2)
        $catalog = [
            110 => ['name' => 'PC', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
            121 => ['name' => '스피커', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
        ];
        $config = $this->config([
            'rules' => [['id' => 'pc-speaker', 'type' => 'need', 'group' => '컴퓨터', 'on' => ['pc'], 'need' => ['speaker'], 'mode' => 'perUnit', 'amt' => 1, 'what' => '스피커', 'active' => true]],
            'productRoles' => [110 => [['pc', 1]], 121 => [['speaker', 1]]],
        ]);

        $r = ReviewEngine::run([['product_id' => 110, 'qty' => 3], ['product_id' => 121, 'qty' => 1]], $catalog, $config);
        $this->assertCount(1, $r['findings']);
        $this->assertSame('pc-speaker:3:1', $r['findings'][0]['key']);
        $this->assertSame('qty', $r['findings'][0]['kind']);
        $this->assertSame(2, $r['findings'][0]['short']);
        $this->assertStringContainsString('2개 부족', $r['findings'][0]['title']);
    }

    public function test_rule_with_zero_trigger_counts_nowhere(): void
    {
        // 해당 없는 규칙은 통과에도 세지 않음 (7.2)
        $config = $this->config([
            'rules' => [['id' => 'pc-speaker', 'type' => 'need', 'on' => ['pc'], 'need' => ['speaker'], 'mode' => 'perUnit', 'amt' => 1, 'what' => '스피커', 'active' => true]],
        ]);
        $r = ReviewEngine::run([], $this->catalog(), $config);
        $this->assertSame(0, $r['counts']['passed']);
        $this->assertSame([], $r['findings']);
    }

    public function test_ask_exceeds_condition(): void
    {
        // light-extra: 거치대가 조명보다 많으면 {d}개 남음 질문 (6.2)
        $catalog = [
            130 => ['name' => '조명', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
            131 => ['name' => '스탠드', 'group_id' => null, 'category_chain' => [], 'is_bundle' => false, 'components' => []],
        ];
        $config = $this->config([
            'rules' => [['id' => 'light-extra', 'type' => 'ask', 'on' => ['mount'], 'cond' => 'exceeds', 'cmp' => ['light'], 'min' => 1, 'title' => '조명 거치대 {d}개가 남습니다', 'active' => true]],
            'productRoles' => [130 => [['light', 1]], 131 => [['mount', 1]]],
        ]);

        $over = ReviewEngine::run([['product_id' => 130, 'qty' => 1], ['product_id' => 131, 'qty' => 3]], $catalog, $config);
        $this->assertSame('light-extra:3:1', $over['findings'][0]['key']);
        $this->assertStringContainsString('2개가 남습니다', $over['findings'][0]['title']);

        $even = ReviewEngine::run([['product_id' => 130, 'qty' => 2], ['product_id' => 131, 'qty' => 2]], $catalog, $config);
        $this->assertSame([], $even['findings']);
    }

    public function test_manual_equipment_items_warn_once(): void
    {
        // 수기 장비 품목 N개 → 요약 1건, 서비스(세팅비)는 제외 (6.8)
        $items = [
            ['product_id' => null, 'name' => 'ATEM MINI', 'qty' => 1, 'price' => 300000, 'manual' => true],
            ['product_id' => null, 'name' => '커스텀 케이블', 'qty' => 2, 'price' => 5000, 'manual' => true],
            ['product_id' => null, 'name' => '출장 세팅비', 'qty' => 1, 'price' => 100000, 'manual' => true, 'is_service' => true],
        ];
        $r = ReviewEngine::run($items, $this->catalog(), $this->config());
        $this->assertCount(1, $r['findings']);
        $this->assertStringStartsWith('manual:', $r['findings'][0]['key']);
        $this->assertStringContainsString('수기 품목 2개', $r['findings'][0]['title']);
    }

    public function test_fingerprint_ignores_price_and_order_but_not_qty(): void
    {
        // 지문 (8.2) — 가격·순서 무관, 수량·구성에는 민감. 대체 항목 제외
        $a = [['product_id' => 1, 'qty' => 1, 'price' => 100], ['product_id' => 2, 'qty' => 2, 'price' => 200]];
        $b = [['product_id' => 2, 'qty' => 2, 'price' => 999], ['product_id' => 1, 'qty' => 1, 'price' => 1]];
        $this->assertSame(ReviewEngine::fingerprint($a), ReviewEngine::fingerprint($b));

        $qtyChanged = [['product_id' => 1, 'qty' => 2], ['product_id' => 2, 'qty' => 2]];
        $this->assertNotSame(ReviewEngine::fingerprint($a), ReviewEngine::fingerprint($qtyChanged));

        $withReplaced = [...$a, ['product_id' => 3, 'qty' => 1, 'replaced' => true]];
        $this->assertSame(ReviewEngine::fingerprint($a), ReviewEngine::fingerprint($withReplaced));
    }

    public function test_response_never_contains_purchase_price(): void
    {
        // 명세 3장 — 검토 응답 어디에도 purchase_price가 없어야 한다
        $r = $this->review([[self::R6II, 1], [self::GC311, 1]], ['checks' => ['video' => true, 'lens' => true, 'battery' => true]]);
        $this->assertStringNotContainsString('purchase_price', json_encode($r));
    }
}
