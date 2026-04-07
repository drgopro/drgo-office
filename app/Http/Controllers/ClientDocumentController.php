<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClientDocumentController extends Controller
{
    public function store(Request $request, Client $client)
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|max:20480',
            'category' => 'required|string|max:50',
            'note' => 'nullable|string|max:300',
        ]);

        $noteText = trim(
            $request->category.
            ($request->note ? ' - '.$request->note : '')
        );

        foreach ($request->file('files') as $file) {
            $path = $file->store("clients/{$client->id}");

            $client->documents()->create([
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

    public function download(ClientDocument $document)
    {
        if (! Storage::exists($document->file_path)) {
            abort(404, '파일을 찾을 수 없습니다.');
        }

        return Storage::download($document->file_path, $document->file_name);
    }

    public function destroy(ClientDocument $document)
    {
        Storage::delete($document->file_path);
        $document->delete();

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
