<?php

namespace App\Http\Controllers;

use App\Models\Wiki;
use App\Services\UpdateNoteGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/** 배포 커밋을 날짜별로 모아 위키 '업데이트' 게시물 초안을 자동 생성 (관리자 전용) */
class UpdateNoteController extends Controller
{
    public function __construct(public UpdateNoteGenerator $generator) {}

    public function generateDraft(Request $request): RedirectResponse|Response
    {
        $validated = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]);

        $from = $validated['from'] ?? now()->format('Y-m-d');
        $to = $validated['to'] ?? $from;
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        $commits = $this->generator->commitsBetween($from, $to);
        if ($commits === []) {
            return response(
                "해당 기간({$from} ~ {$to})의 배포 커밋이 없습니다. 뒤로가기 후 날짜를 다시 선택해주세요.",
                200,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $wiki = Wiki::create([
            'title' => $this->generator->title($from, $to),
            'type' => 'update',
            'category' => Wiki::SPECIAL_TYPES['update'],
            'content' => $this->generator->buildHtml($commits),
            'is_draft' => true,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('wiki.create', ['draft' => $wiki->id]);
    }
}
