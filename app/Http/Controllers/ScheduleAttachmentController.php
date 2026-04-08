<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\ScheduleAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ScheduleAttachmentController extends Controller
{
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
            ])
        );
    }

    public function store(Request $request, Schedule $schedule)
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|max:20480',
            'attachment_type' => 'required|in:general,quote,reference,room',
        ]);

        $attachments = [];

        foreach ($request->file('files') as $file) {
            $path = $file->store("schedules/{$schedule->id}");

            $attachments[] = $schedule->attachments()->create([
                'attachment_type' => $request->attachment_type,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }

        return response()->json($attachments, 201);
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

        return Storage::response($attachment->file_path, $attachment->file_name);
    }
}
