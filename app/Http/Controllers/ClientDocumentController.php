<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesFileUploads;
use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClientDocumentController extends Controller
{
    use HandlesFileUploads;

    public function store(Request $request, Client $client)
    {
        if ($this->postBodyWasDropped($request)) {
            $msg = $this->uploadLimitMessage();
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return back()->withErrors(['files' => $msg]);
        }

        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|max:102400', // 100MB / 파일. Nginx client_max_body_size / PHP upload_max_filesize 도 같이 조정 필요
            'category' => 'required|string|max:50',
            'note' => 'nullable|string|max:300',
        ]);

        $noteText = trim(
            $request->category.
            ($request->note ? ' - '.$request->note : '')
        );

        $result = $this->storeUploadedFiles(
            $request->file('files'),
            "clients/{$client->id}",
            function ($file, $path) use ($client, $noteText) {
                $client->documents()->create([
                    'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'note' => $noteText,
                ]);
            }
        );

        $saved = $result['saved'];
        $msg = "{$saved}개 파일이 업로드되었습니다.";
        if (! empty($result['failed'])) {
            $msg .= ' / 실패 '.count($result['failed']).'건: '.implode(' | ', $result['failed']);
        }

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'success' => $saved > 0,
                'count' => $saved,
                'failed' => $result['failed'],
                'message' => $msg,
            ], $saved > 0 ? 200 : 422);
        }

        return $saved > 0 ? back()->with('success', $msg) : back()->withErrors(['files' => $msg]);
    }

    public function download(ClientDocument $document)
    {
        if (! Storage::exists($document->file_path)) {
            abort(404, '파일을 찾을 수 없습니다.');
        }

        return Storage::download($document->file_path, $document->file_name);
    }

    public function destroy(Request $request, ClientDocument $document)
    {
        Storage::delete($document->file_path);
        $document->delete();

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['success' => true, 'message' => '파일이 삭제되었습니다.']);
        }

        return back()->with('success', '파일이 삭제되었습니다.');
    }

    public function serve(ClientDocument $document)
    {
        if (! Storage::exists($document->file_path)) {
            abort(404);
        }

        return Storage::response($document->file_path, $document->file_name);
    }
}
