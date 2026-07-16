<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * 첨부파일 위험 확장자 차단 — 저장형 XSS·서버 실행 방지.
 *
 * 업무 특성상 다양한 파일(hwp, zip, 설정 파일 등)을 허용해야 하므로
 * 화이트리스트 대신 실행 가능한 유형만 차단한다:
 * - 브라우저가 활성 콘텐츠로 렌더링하는 것 (html/svg 계열 — inline 서빙 시 XSS)
 * - 서버에서 실행될 수 있는 것 (php 계열)
 * - OS 실행 파일 (exe/bat 등)
 */
class SafeAttachment implements ValidationRule
{
    private const BLOCKED_EXTENSIONS = [
        // 브라우저 활성 콘텐츠
        'html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz', 'xml', 'xsl', 'swf',
        // 스크립트
        'js', 'mjs', 'vbs', 'wsf',
        // 서버 실행
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        'jsp', 'jspx', 'asp', 'aspx', 'cgi',
        // OS 실행 파일
        'exe', 'dll', 'com', 'msi', 'scr', 'bat', 'cmd', 'ps1', 'sh', 'app', 'jar', 'hta',
    ];

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('올바른 파일이 아닙니다.');

            return;
        }

        // 클라이언트 확장자와 내용 기반 추정 확장자 둘 다 검사 (우회 방지)
        $extensions = array_filter([
            strtolower($value->getClientOriginalExtension()),
            strtolower((string) $value->guessExtension()),
        ]);

        foreach ($extensions as $ext) {
            if (in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
                $fail("보안상 허용되지 않는 파일 형식입니다. (.{$ext})");

                return;
            }
        }
    }
}
