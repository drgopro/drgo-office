<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectDocumentController extends Controller
{
    public function store(Request $request, Project $project)
    {
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

        foreach ($request->file('files') as $file) {
            $path = $file->store("projects/{$project->id}");

            $project->documents()->create([
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'note' => $noteText,
            ]);
        }

        $count = count($request->file('files'));

        return back()->with('success', "{$count}개 파일이 업로드되었습니다.");
    }

    /**
     * 보고서 에디터 인라인 업로드 (단일 파일, JSON 응답).
     * 업로드된 파일은 프로젝트 첨부 문서로도 함께 기록됨.
     */
    public function inlineUpload(Request $request, Project $project)
    {
        $request->validate([
            'file' => 'required|file|max:102400', // 100MB
        ]);

        $file = $request->file('file');
        $path = $file->store("projects/{$project->id}");

        $mime = $file->getMimeType() ?? '';
        $isImage = str_starts_with($mime, 'image/');
        $isVideo = str_starts_with($mime, 'video/');

        $document = $project->documents()->create([
            'file_name' => $file->getClientOriginalName(),
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

        return Storage::response($document->file_path, $document->file_name);
    }
}
