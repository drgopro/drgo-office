<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesFileUploads;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Services\ImageThumbnailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectDocumentController extends Controller
{
    use HandlesFileUploads;

    public function store(Request $request, Project $project)
    {
        // post_max_size 초과로 본문이 폐기된 경우 — 명확한 안내
        if ($this->postBodyWasDropped($request)) {
            return $this->uploadLimitResponse($request);
        }

        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|max:102400', // 100MB / 파일
            'category' => 'required|string|max:50',
            'note' => 'nullable|string|max:300',
        ]);

        $noteText = trim(
            $request->category.
            ($request->note ? ' - '.$request->note : '')
        );

        $result = $this->storeUploadedFiles(
            $request->file('files'),
            "projects/{$project->id}",
            function ($file, $path) use ($project, $noteText) {
                $project->documents()->create([
                    'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'note' => $noteText,
                ]);
            }
        );

        return $this->uploadResultResponse($request, $result);
    }

    /**
     * post_max_size 초과 응답.
     */
    private function uploadLimitResponse(Request $request)
    {
        $msg = $this->uploadLimitMessage();
        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $msg], 422);
        }

        return back()->withErrors(['files' => $msg]);
    }

    /**
     * 저장 결과(일부 실패 포함) 응답.
     *
     * @param  array{saved:int, failed:array<string>}  $result
     */
    private function uploadResultResponse(Request $request, array $result)
    {
        $saved = $result['saved'];
        $failed = $result['failed'];
        $msg = "{$saved}개 파일이 업로드되었습니다.";
        if (! empty($failed)) {
            $msg .= ' / 실패 '.count($failed).'건: '.implode(' | ', $failed);
        }

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'success' => $saved > 0,
                'saved' => $saved,
                'failed' => $failed,
                'message' => $msg,
            ], $saved > 0 ? 200 : 422);
        }

        if ($saved === 0 && ! empty($failed)) {
            return back()->withErrors(['files' => $msg]);
        }

        return back()->with('success', $msg);
    }

    /**
     * 보고서 에디터 인라인 업로드 (단일 파일, JSON 응답).
     * 업로드된 파일은 프로젝트 첨부 문서로도 함께 기록됨.
     */
    public function inlineUpload(Request $request, Project $project)
    {
        if ($this->postBodyWasDropped($request)) {
            return response()->json(['error' => $this->uploadLimitMessage()], 422);
        }

        $request->validate([
            'file' => 'required|file|max:102400', // 100MB
        ]);

        $file = $request->file('file');
        if (! $file->isValid()) {
            return response()->json(['error' => $this->uploadErrorReason($file)], 422);
        }

        try {
            $path = $file->store("projects/{$project->id}");
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => '파일 저장 중 오류: '.$e->getMessage()], 500);
        }

        $mime = $file->getMimeType() ?? '';
        $isImage = str_starts_with($mime, 'image/');
        $isVideo = str_starts_with($mime, 'video/');

        $document = $project->documents()->create([
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'file_path' => $path,
            'mime_type' => $mime,
            'file_size' => $file->getSize(),
            'note' => '방문 보고서 · '.($isImage ? '이미지' : ($isVideo ? '영상' : '파일')),
        ]);

        return response()->json([
            'id' => $document->id,
            'url' => route('project-documents.serve', $document),
            'name' => $document->file_name,
            'mime_type' => $mime,
            'is_image' => $isImage,
            'is_video' => $isVideo,
        ]);
    }

    public function download(ProjectDocument $document)
    {
        if (! Storage::exists($document->file_path)) {
            abort(404, '파일을 찾을 수 없습니다.');
        }

        return Storage::download($document->file_path, $document->file_name);
    }

    public function destroy(ProjectDocument $document)
    {
        Storage::delete($document->file_path);
        $document->delete();

        return back()->with('success', '파일이 삭제되었습니다.');
    }

    public function serve(ProjectDocument $document)
    {
        if (! Storage::exists($document->file_path)) {
            abort(404);
        }

        return Storage::response($document->file_path, $document->file_name, ImageThumbnailService::cacheHeaders());
    }

    /** 그리드/앨범용 썸네일 (640px WebP, 온디맨드 생성·캐시) */
    public function thumb(ProjectDocument $document, ImageThumbnailService $thumbs)
    {
        if (! Storage::exists($document->file_path)) {
            abort(404);
        }

        return $thumbs->response($document->file_path, $document->file_name);
    }
}
