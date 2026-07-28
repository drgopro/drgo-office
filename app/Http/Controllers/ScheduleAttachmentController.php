<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesFileUploads;
use App\Models\Schedule;
use App\Models\ScheduleAttachment;
use App\Rules\SafeAttachment;
use App\Services\ImageThumbnailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ScheduleAttachmentController extends Controller
{
    use HandlesFileUploads;

    public function index(Schedule $schedule)
    {
        return response()->json(
            $schedule->attachments()->get()->map(fn ($a) => [
                'id' => $a->id,
                'attachment_type' => $a->attachment_type,
                'file_name' => $a->file_name,
                'mime_type' => $a->mime_type,
                'file_size' => $a->file_size,
                'url' => route('schedule-attachments.serve', $a),
                'thumb_url' => str_starts_with((string) $a->mime_type, 'image/') ? route('schedule-attachments.thumb', $a).'?v=2' : null,
            ])
        );
    }

    public function store(Request $request, Schedule $schedule)
    {
        if ($this->postBodyWasDropped($request)) {
            return response()->json(['message' => $this->uploadLimitMessage()], 422);
        }

        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => ['required', 'file', 'max:102400', new SafeAttachment], // 100MB / 파일
            'attachment_type' => 'required|in:general,quote,reference,room',
        ]);

        $attachments = [];
        $result = $this->storeUploadedFiles(
            $request->file('files'),
            "schedules/{$schedule->id}",
            function ($file, $path) use ($schedule, $request, &$attachments) {
                $attachments[] = $schedule->attachments()->create([
                    'attachment_type' => $request->attachment_type,
                    'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        );

        if ($result['saved'] === 0 && ! empty($result['failed'])) {
            return response()->json([
                'message' => '업로드 실패: '.implode(' | ', $result['failed']),
                'failed' => $result['failed'],
            ], 422);
        }

        return response()->json([
            'attachments' => $attachments,
            'saved' => $result['saved'],
            'failed' => $result['failed'],
        ], 201);
    }

    public function destroy(ScheduleAttachment $attachment)
    {
        Storage::delete($attachment->file_path);
        $attachment->delete();

        return response()->json(['ok' => true]);
    }

    public function serve(ScheduleAttachment $attachment)
    {
        if (! Storage::exists($attachment->file_path)) {
            abort(404);
        }

        return Storage::response($attachment->file_path, $attachment->file_name, ImageThumbnailService::cacheHeaders());
    }

    /** 그리드/요약용 썸네일 (640px WebP, 온디맨드 생성·캐시) */
    public function thumb(ScheduleAttachment $attachment, ImageThumbnailService $thumbs)
    {
        if (! Storage::exists($attachment->file_path)) {
            abort(404);
        }

        return $thumbs->response($attachment->file_path, $attachment->file_name);
    }
}
