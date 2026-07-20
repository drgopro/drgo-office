<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\ScheduleAttachment;
use App\Models\User;
use App\Services\ImageThumbnailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private function makePng(int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 100, 50));
        ob_start();
        imagepng($img);
        imagedestroy($img);

        return ob_get_clean();
    }

    public function test_thumbnail_is_generated_and_downscaled(): void
    {
        Storage::fake('local');
        Storage::put('schedules/1/big.png', $this->makePng(2000, 1000));

        $thumb = app(ImageThumbnailService::class)->ensure('schedules/1/big.png');

        $this->assertNotNull($thumb);
        Storage::assertExists($thumb);
        $info = getimagesizefromstring(Storage::get($thumb));
        $this->assertSame(640, max($info[0], $info[1]));

        // 재호출 시 캐시 재사용 (같은 경로)
        $this->assertSame($thumb, app(ImageThumbnailService::class)->ensure('schedules/1/big.png'));
    }

    private function makePalettePng(int $w, int $h): string
    {
        // 팔레트(인덱스 컬러) PNG — imagewebp가 직접 변환하지 못하는 형식
        $img = imagecreate($w, $h);
        imagecolorallocate($img, 200, 100, 50); // 배경색 (팔레트 첫 색상)
        ob_start();
        imagepng($img);
        imagedestroy($img);

        return ob_get_clean();
    }

    public function test_small_palette_image_is_converted_without_error(): void
    {
        // 640px 이하 팔레트 이미지는 리사이즈를 건너뛰어 팔레트 그대로 imagewebp에 전달되던 버그
        // (imagewebp(): Palette image not supported by webp)
        Storage::fake('local');
        Storage::put('schedules/1/small-palette.png', $this->makePalettePng(100, 80));

        $thumb = app(ImageThumbnailService::class)->ensure('schedules/1/small-palette.png');

        $this->assertNotNull($thumb);
        Storage::assertExists($thumb);
        $this->assertSame('image/webp', getimagesizefromstring(Storage::get($thumb))['mime']);
    }

    public function test_large_palette_image_is_converted(): void
    {
        Storage::fake('local');
        Storage::put('schedules/1/big-palette.png', $this->makePalettePng(1500, 900));

        $thumb = app(ImageThumbnailService::class)->ensure('schedules/1/big-palette.png');

        $this->assertNotNull($thumb);
        $info = getimagesizefromstring(Storage::get($thumb));
        $this->assertSame(640, max($info[0], $info[1]));
    }

    public function test_non_image_returns_null(): void
    {
        Storage::fake('local');
        Storage::put('schedules/1/doc.pdf', '%PDF-1.4 fake');

        $this->assertNull(app(ImageThumbnailService::class)->ensure('schedules/1/doc.pdf'));
    }

    public function test_thumb_endpoint_serves_with_long_cache(): void
    {
        Storage::fake('local');
        Storage::put('schedules/1/photo.png', $this->makePng(1200, 800));

        $user = User::factory()->create(['role' => 'master']);
        $schedule = Schedule::create(['title' => '방문', 'start_date' => '2026-07-10', 'end_date' => '2026-07-10', 'color' => 'gold']);
        $att = ScheduleAttachment::create([
            'schedule_id' => $schedule->id,
            'attachment_type' => 'room',
            'file_name' => 'photo.png',
            'file_path' => 'schedules/1/photo.png',
            'mime_type' => 'image/png',
            'file_size' => 1234,
        ]);

        $res = $this->actingAs($user)->get("/schedule-attachments/{$att->id}/thumb");

        $res->assertOk();
        $this->assertStringContainsString('immutable', $res->headers->get('Cache-Control'));
    }
}
