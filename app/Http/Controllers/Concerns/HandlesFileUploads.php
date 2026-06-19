<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * 파일 업로드 공통 처리 — 간헐적 업로드 실패의 주요 원인을 방어.
 *
 * 1) post_max_size 초과 시 PHP가 본문을 폐기 → 빈 요청 감지 후 명확한 422 반환
 * 2) 업로드된 파일별 isValid() 검사 + try/catch → 실패 파일은 건너뛰고 사유 수집
 * 3) UPLOAD_ERR_* 코드를 한글 메시지로 변환
 */
trait HandlesFileUploads
{
    /**
     * post_max_size 초과로 본문이 통째로 폐기되었는지 감지.
     * (CONTENT_LENGTH는 있는데 $_POST/$_FILES가 비어있으면 초과로 판단)
     */
    protected function postBodyWasDropped(Request $request): bool
    {
        if (! $request->isMethod('post') && ! $request->isMethod('put') && ! $request->isMethod('patch')) {
            return false;
        }

        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
        $contentType = (string) $request->server('CONTENT_TYPE', '');

        $isMultipart = str_contains($contentType, 'multipart/form-data');

        return $contentLength > 0
            && $isMultipart
            && empty($_POST)
            && empty($_FILES);
    }

    /**
     * 사람이 읽는 최대 업로드 한계 메시지 (PHP 설정 기반).
     */
    protected function uploadLimitMessage(): string
    {
        $uploadMax = ini_get('upload_max_filesize') ?: '?';
        $postMax = ini_get('post_max_size') ?: '?';

        return "서버가 허용하는 한도를 초과했습니다. (파일당 최대 {$uploadMax}, 전체 요청 최대 {$postMax})";
    }

    /**
     * UPLOAD_ERR_* 코드 → 한글 사유.
     */
    protected function uploadErrorReason(UploadedFile $file): string
    {
        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE => '파일이 서버 허용 크기(upload_max_filesize)를 초과했습니다.',
            UPLOAD_ERR_FORM_SIZE => '파일이 폼 허용 크기를 초과했습니다.',
            UPLOAD_ERR_PARTIAL => '파일이 일부만 전송되었습니다(네트워크 중단).',
            UPLOAD_ERR_NO_FILE => '파일이 전송되지 않았습니다.',
            UPLOAD_ERR_NO_TMP_DIR => '서버 임시 폴더가 없습니다.',
            UPLOAD_ERR_CANT_WRITE => '서버 디스크 쓰기에 실패했습니다.',
            UPLOAD_ERR_EXTENSION => '서버 확장에 의해 업로드가 중단되었습니다.',
            default => '알 수 없는 업로드 오류입니다.',
        };
    }

    /**
     * 업로드 파일 배열을 안전하게 저장.
     *
     * @param  array<UploadedFile>  $files
     * @param  string  $dir  저장 디렉터리 (디스크 내 상대 경로)
     * @param  callable  $persist  fn(UploadedFile $file, string $path): void — DB 기록 콜백
     * @return array{saved:int, failed:array<string>}
     */
    protected function storeUploadedFiles(array $files, string $dir, callable $persist): array
    {
        $saved = 0;
        $failed = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $name = $file->getClientOriginalName() ?: '(이름없음)';

            // PHP 단계에서 실패한 업로드
            if (! $file->isValid()) {
                $failed[] = "{$name}: ".$this->uploadErrorReason($file);

                continue;
            }

            try {
                $path = $file->store($dir);
                if (! $path) {
                    $failed[] = "{$name}: 저장 경로 생성 실패";

                    continue;
                }
                $persist($file, $path);
                $saved++;
            } catch (\Throwable $e) {
                Log::warning('파일 저장 실패', ['file' => $name, 'error' => $e->getMessage()]);
                $failed[] = "{$name}: 저장 중 오류 ({$e->getMessage()})";
            }
        }

        return ['saved' => $saved, 'failed' => $failed];
    }
}
