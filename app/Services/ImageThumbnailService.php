<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 첨부 이미지 온디맨드 썸네일 (GD + WebP, 패키지 의존 없음).
 *
 * 첫 요청 시 원본에서 최대 640px WebP를 생성해 storage(thumbs/)에 캐시하고,
 * 이후에는 캐시된 파일을 그대로 서빙한다. 그리드/요약 미리보기용이며 원본은 그대로 보존.
 */
class ImageThumbnailService
{
    public const MAX_EDGE = 640;

    public const QUALITY = 78;

    /**
     * 썸네일 storage 경로 확보 (없으면 생성). 이미지가 아니거나 실패 시 null.
     */
    public function ensure(string $originalPath): ?string
    {
        if (! Storage::exists($originalPath)) {
            return null;
        }

        $thumbPath = 'thumbs/'.md5($originalPath).'.webp';
        if (Storage::exists($thumbPath)) {
            return $thumbPath;
        }

        $data = Storage::get($originalPath);
        $img = @imagecreatefromstring($data);
        if ($img === false) {
            return null; // 이미지 아님 (PDF 등)
        }

        $obLevel = ob_get_level();
        try {
            return $this->convert($img, $data, $thumbPath);
        } catch (\Throwable $e) {
            while (ob_get_level() > $obLevel) {
                ob_end_clean(); // 변환 중 예외로 남은 출력 버퍼 정리
            }
            Log::warning("썸네일 생성 실패 ({$originalPath}): ".$e->getMessage());

            return null; // 원본 서빙으로 폴백
        }
    }

    /** GD 리소스를 WebP 썸네일로 변환해 저장. */
    private function convert(\GdImage $img, string $data, string $thumbPath): ?string
    {
        // EXIF 회전 보정 (JPEG 세로 사진)
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($data));
            $orientation = (int) ($exif['Orientation'] ?? 1);
            if (in_array($orientation, [3, 6, 8], true)) {
                $angle = [3 => 180, 6 => -90, 8 => 90][$orientation];
                $rotated = imagerotate($img, $angle, 0);
                if ($rotated !== false) {
                    imagedestroy($img);
                    $img = $rotated;
                }
            }
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, self::MAX_EDGE / max($w, $h));
        if ($scale < 1) {
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $dst;
        }

        // 팔레트(인덱스 컬러) 이미지는 imagewebp가 지원하지 않음 — 트루컬러로 승격 (8비트 PNG/GIF/BMP)
        if (! imageistruecolor($img)) {
            imagepalettetotruecolor($img);
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }

        ob_start();
        imagewebp($img, null, self::QUALITY);
        $out = ob_get_clean();
        imagedestroy($img);

        if ($out === false || $out === '') {
            return null;
        }

        // 생성 결과 검증 — 깨진 webp가 캐시되어 영구히 잘려 보이는 사고 방지 (디코딩 실패 시 원본 폴백)
        $check = @imagecreatefromstring($out);
        if ($check === false) {
            return null;
        }
        imagedestroy($check);

        Storage::put($thumbPath, $out);

        return $thumbPath;
    }

    /**
     * 썸네일 응답 (실패 시 원본으로 폴백). 불변 파일이므로 장기 캐시 헤더.
     */
    public function response(string $originalPath, string $fileName)
    {
        $thumb = $this->ensure($originalPath);

        return Storage::response($thumb ?? $originalPath, $fileName, self::cacheHeaders());
    }

    /**
     * 첨부는 업로드 후 불변(삭제만 가능) — 장기 캐시 허용.
     *
     * @return array<string, string>
     */
    public static function cacheHeaders(): array
    {
        return ['Cache-Control' => 'private, max-age=31536000, immutable'];
    }
}
