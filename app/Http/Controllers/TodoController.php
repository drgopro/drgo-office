<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesFileUploads;
use App\Models\Todo;
use App\Models\TodoAttachment;
use App\Models\User;
use App\Services\ImageThumbnailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TodoController extends Controller
{
    use HandlesFileUploads;

    /** 담당자별 칸반 보드 */
    public function index()
    {
        return view('todos.index', [
            'todos' => $this->boardTodos(),
            'members' => User::where('role', '!=', 'guest')->with('team')
                ->orderBy('display_name')->get(['id', 'display_name', 'team_id']),
        ]);
    }

    /** 보드 데이터 (JSON) — 저장/드래그 후 갱신용 */
    public function board()
    {
        return response()->json(['todos' => $this->boardTodos()]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateTodo($request);
        $validated['created_by'] = Auth::id();
        $validated['sort_order'] = (int) Todo::where('assignee_id', $validated['assignee_id'])->max('sort_order') + 1;

        $todo = Todo::create($validated);

        return response()->json(['todo' => $this->present($todo->load('assignee.team', 'creator', 'attachments'))], 201);
    }

    public function update(Request $request, Todo $todo)
    {
        $todo->update($this->validateTodo($request));

        return response()->json(['todo' => $this->present($todo->fresh()->load('assignee.team', 'creator', 'attachments'))]);
    }

    /** 드래그로 담당자 변경 — 관리자 이상만 */
    public function assign(Request $request, Todo $todo)
    {
        abort_unless(Auth::user()->isAdmin(), 403, '담당자 변경은 관리자만 가능합니다.');

        $validated = $request->validate(['assignee_id' => 'required|exists:users,id']);

        $todo->update([
            'assignee_id' => $validated['assignee_id'],
            'sort_order' => (int) Todo::where('assignee_id', $validated['assignee_id'])->max('sort_order') + 1,
        ]);

        return response()->json(['ok' => true]);
    }

    /** 완료 토글 */
    public function complete(Todo $todo)
    {
        $todo->update(['completed_at' => $todo->completed_at ? null : now()]);

        return response()->json(['ok' => true, 'completed' => (bool) $todo->completed_at]);
    }

    public function destroy(Todo $todo)
    {
        foreach ($todo->attachments as $attachment) {
            Storage::delete($attachment->file_path);
        }
        $todo->delete();

        return response()->json(['ok' => true]);
    }

    // ── 첨부파일 ──

    public function storeAttachments(Request $request, Todo $todo)
    {
        if ($this->postBodyWasDropped($request)) {
            return response()->json(['message' => $this->uploadLimitMessage()], 422);
        }

        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|max:102400', // 100MB / 파일
        ]);

        $result = $this->storeUploadedFiles(
            $request->file('files'),
            "todos/{$todo->id}",
            function ($file, $path) use ($todo) {
                $todo->attachments()->create([
                    'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        );

        if ($result['saved'] === 0 && ! empty($result['failed'])) {
            return response()->json(['message' => '업로드 실패: '.implode(' | ', $result['failed'])], 422);
        }

        return response()->json(['saved' => $result['saved'], 'failed' => $result['failed']], 201);
    }

    public function destroyAttachment(TodoAttachment $attachment)
    {
        Storage::delete($attachment->file_path);
        $attachment->delete();

        return response()->json(['ok' => true]);
    }

    public function serveAttachment(TodoAttachment $attachment)
    {
        if (! Storage::exists($attachment->file_path)) {
            abort(404);
        }

        return Storage::response($attachment->file_path, $attachment->file_name, ImageThumbnailService::cacheHeaders());
    }

    // ── 내부 헬퍼 ──

    /** @return array<string, mixed> */
    private function validateTodo(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string|max:10000',
            'priority' => ['required', Rule::in(array_keys(Todo::PRIORITIES))],
            'due_date' => 'nullable|date',
            'assignee_id' => 'required|exists:users,id',
            'schedule_id' => 'nullable|exists:schedules,id', // 캘린더 연동용 (선택)
        ]);

        // 일반 멤버는 본인에게만 등록/수정 가능 (타인 지정은 관리자 이상)
        if (! Auth::user()->isAdmin() && (int) $validated['assignee_id'] !== Auth::id()) {
            abort(403, '다른 직원에게 할 일을 지정하는 것은 관리자만 가능합니다.');
        }

        return $validated;
    }

    /** @return array<int, array<string, mixed>> */
    private function boardTodos(): array
    {
        return Todo::with('assignee.team', 'creator', 'attachments')
            // 일반 멤버는 본인 할 일만 조회
            ->when(! Auth::user()->isAdmin(), fn ($q) => $q->where('assignee_id', Auth::id()))
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (Todo $t) => $this->present($t))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function present(Todo $t): array
    {
        return [
            'id' => $t->id,
            'title' => $t->title,
            'content' => $t->content,
            'priority' => $t->priority,
            'due_date' => $t->due_date?->format('Y-m-d'),
            'assignee_id' => $t->assignee_id,
            'schedule_id' => $t->schedule_id,
            'assignee' => $t->assignee?->display_name ?? '알 수 없음',
            'team' => $t->assignee?->team?->name,
            'creator' => $t->creator?->display_name,
            'completed' => (bool) $t->completed_at,
            'completed_at' => $t->completed_at?->format('Y.m.d H:i'),
            'created_at' => $t->created_at->format('Y.m.d H:i'),
            'attachments' => $t->attachments->map(fn ($a) => [
                'id' => $a->id,
                'file_name' => $a->file_name,
                'mime_type' => $a->mime_type,
                'file_size' => $a->file_size,
                'url' => route('todo-attachments.serve', $a),
            ])->values()->all(),
        ];
    }
}
