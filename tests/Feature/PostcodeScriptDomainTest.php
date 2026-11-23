<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** 카카오 우편번호 스크립트 — t1.daumcdn.net 종료(2026-11-30) 대비 t1.kakaocdn.net 사용 */
class PostcodeScriptDomainTest extends TestCase
{
    public function test_views_load_postcode_script_from_kakaocdn_only(): void
    {
        $old = [];
        $new = 0;
        foreach (File::allFiles(resource_path('views')) as $file) {
            $src = $file->getContents();
            if (str_contains($src, 'daumcdn.net')) {
                $old[] = $file->getRelativePathname();
            }
            $new += substr_count($src, 'https://t1.kakaocdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js');
        }

        $this->assertSame([], $old, 'daumcdn.net 참조가 남아 있습니다');
        $this->assertGreaterThan(0, $new);
    }
}
