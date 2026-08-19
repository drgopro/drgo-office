<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesFileUploads;
use App\Models\Todo;
use App\Models\TodoAttachment;
use App\Models\TodoChecklistItem;
use App\Models\User;
use App\Rules\SafeAttachment;
use App\Services\ChannelTalkNotifier;
use App\Services\ImageThumbnailService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        [$validated, $assigneeIds] = $this->validateTodo($request);
        $validated['created_by'] = Auth::id();
        $validated['sort_order'] = (int) Todo::where('assignee_id', $validated['assignee_id'])->max('sort_order') + 1;

        $todo = Todo::create($validated);
        $todo->syncAssigneesOrdered($assigneeIds, notify: false); // 아래 '새 할 일' 알림과 중복 방지

        // 담당자에게 채널톡 멘션 알림 (실패해도 등록은 유지)
        app(ChannelTalkNotifier::class)->todoCreated($todo->load('assignees', 'assignee'));

        return response()->json(['todo' => $this->present($todo->load('assignee.team', 'creator', 'attachments', 'assignees'))], 201);
    }

    public function update(Request $request, Todo $todo)
    {
        $this->authorizeTodo($todo);
        [$validated, $assigneeIds] = $this->validateTodo($request);

        // 기한이 바뀌거나 없어지면 보류 상태는 초기화 (새 기한 기준으로 다시 관리)
        $newDue = isset($validated['due_date']) && $validated['due_date']
            ? Carbon::parse($validated['due_date'])->format('Y-m-d')
            : null;
        if ($newDue !== $todo->due_date?->format('Y-m-d')) {
            $validated['due_held_at'] = null;
        }

        $todo->update($validated);
        $todo->syncAssigneesOrdered($assigneeIds);

        return response()->json(['todo' => $this->present($todo->fresh()->load('assignee.team', 'creator', 'attachments', 'assignees'))]);
    }

    /** 드래그 순서 변경 — 전달된 id 순서대로 sort_order 재부여 (멤버는 본인 할 일만) */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:todos,id',
        ]);

        $todos = Todo::whereIn('id', $validated['ids'])->get()->keyBy('id');
        if (! Auth::user()->isAdmin() && $todos->contains(fn (Todo $t) => $t->assignee_id !== Auth::id())) {
            abort(403, '본인 할 일만 순서를 변경할 수 있습니다.');
        }

        foreach (array_values($validated['ids']) as $i => $id) {
            $todos[$id]?->update(['sort_order' => $i + 1]);
        }

        return response()->json(['ok' => true]);
    }

    /** 드래그로 담당자(컬럼) 변경 — 관리자 이상만. 대상이 대표(첫 번째)가 되고 나머지 담당자는 유지 */
    public function assign(Request $request, Todo $todo)
    {
        abort_unless(Auth::user()->isAdmin(), 403, '담당자 변경은 관리자만 가능합니다.');

        $validated = $request->validate(['assignee_id' => 'required|exists:users,id']);
        $target = (int) $validated['assignee_id'];

        $rest = $todo->assignees->pluck('id')->reject(fn ($id) => $id === $target)->values()->all();
        $todo->update([
            'sort_order' => (int) Todo::where('assignee_id', $target)->max('sort_order') + 1,
        ]);
        $todo->syncAssigneesOrdered([$target, ...$rest]);

        return response()->json(['ok' => true]);
    }

    /** 기한 보류 토글 — 기한이 지정된 할 일만 (보류 중에는 임박/지남 경고 대신 보류 표시) */
    public function holdDue(Todo $todo)
    {
        $this->authorizeTodo($todo);
        if (! $todo->due_date) {
            return response()->json(['message' => '기한이 없는 할 일은 보류할 수 없습니다.'], 422);
        }
        $todo->update(['due_held_at' => $todo->due_held_at ? null : now()]);

        return response()->json(['ok' => true, 'due_held' => (bool) $todo->due_held_at]);
    }

    /** 완료 토글 (전체) — 복수 담당 할 일이면 담당자별 체크도 함께 맞춰줌 (관리자 강제 완료 등) */
    public function complete(Todo $todo)
    {
        $this->authorizeTodo($todo);
        $completing = ! $todo->completed_at;
        $todo->update(['completed_at' => $completing ? now() : null]);
        // 전체 완료/해제와 담당자별 체크 상태 동기화
        $todo->assignees()->newPivotStatement()
            ->where('todo_id', $todo->id)
            ->update(['completed_at' => $completing ? now() : null]);

        return response()->json(['ok' => true, 'completed' => (bool) $todo->completed_at]);
    }

    /** 내 완료 토글 (복수 담당) — 전원이 완료하면 할 일 전체가 완료 처리 */
    public function myComplete(Todo $todo)
    {
        $this->authorizeTodo($todo);
        $userId = Auth::id();
        if (! $todo->assignees()->where('users.id', $userId)->exists()) {
            return response()->json(['message' => '담당자만 본인 완료 체크를 할 수 있습니다.'], 403);
        }

        $current = $todo->assignees()->where('users.id', $userId)->first()->pivot->completed_at;
        $todo->assignees()->updateExistingPivot($userId, ['completed_at' => $current ? null : now()]);
        $todo->refreshCompletionFromAssignees();
        $todo->refresh();

        return response()->json([
            'ok' => true,
            'my_completed' => ! $current,
            'completed' => (bool) $todo->completed_at,
        ]);
    }

    // ── 체크리스트 (진행 단계) ──

    public function storeChecklistItem(Request $request, Todo $todo)
    {
        $this->authorizeTodo($todo);
        $validated = $request->validate(['title' => 'required|string|max:200']);

        $item = $todo->checklistItems()->create([
            'title' => $validated['title'],
            'sort_order' => (int) $todo->checklistItems()->max('sort_order') + 1,
        ]);

        return response()->json(['ok' => true, 'id' => $item->id], 201);
    }

    /** 체크리스트 항목 토글/제목 수정 */
    public function updateChecklistItem(Request $request, TodoChecklistItem $item)
    {
        $this->authorizeTodo($item->todo);
        $validated = $request->validate([
            'done' => 'sometimes|boolean',
            'title' => 'sometimes|string|max:200',
        ]);

        if (array_key_exists('done', $validated)) {
            $item->done_at = $validated['done'] ? now() : null;
            $item->done_by = $validated['done'] ? Auth::id() : null;
        }
        if (array_key_exists('title', $validated)) {
            $item->title = $validated['title'];
        }
        $item->save();

        return response()->json(['ok' => true, 'done' => (bool) $item->done_at]);
    }

    /** 체크리스트 순서 변경 — 전달된 id 순서대로 sort_order 재부여 */
    public function reorderChecklist(Request $request, Todo $todo)
    {
        $this->authorizeTodo($todo);
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        foreach (array_values($validated['ids']) as $i => $id) {
            TodoChecklistItem::where('todo_id', $todo->id)->where('id', $id)->update(['sort_order' => $i + 1]);
        }

        return response()->json(['ok' => true]);
    }

    public function destroyChecklistItem(TodoChecklistItem $item)
    {
        $this->authorizeTodo($item->todo);
        $item->delete();

        return response()->json(['ok' => true]);
    }

    public function destroy(Todo $todo)
    {
        $this->authorizeTodo($todo);
        foreach ($todo->attachments as $attachment) {
            Storage::delete($attachment->file_path);
        }
        $todo->delete();

        return response()->json(['ok' => true]);
    }

    // ── 첨부파일 ──

    public function storeAttachments(Request $request, Todo $todo)
    {
        $this->authorizeTodo($todo);
        if ($this->postBodyWasDropped($request)) {
            return response()->json(['message' => $this->uploadLimitMessage()], 422);
        }

        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => ['required', 'file', 'max:102400', new SafeAttachment], // 100MB / 파일
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
        $this->authorizeTodo($attachment->todo);
        Storage::delete($attachment->file_path);
        $attachment->delete();

        return response()->json(['ok' => true]);
    }

    public function serveAttachment(TodoAttachment $attachment)
    {
        $this->authorizeTodo($attachment->todo);
        if (! Storage::exists($attachment->file_path)) {
            abort(404);
        }

        return Storage::response($attachment->file_path, $attachment->file_name, ImageThumbnailService::cacheHeaders());
    }

    // ── 내부 헬퍼 ──

    /** 관리자 또는 담당자(복수 포함)/작성자 본인만 조작 가능 (IDOR 방지) */
    private function authorizeTodo(?Todo $todo): void
    {
        if (! $todo) {
            abort(404);
        }
        $user = Auth::user();
        if ($user->isAdmin() || $todo->assignee_id === $user->id || $todo->created_by === $user->id
            || $todo->assignees()->where('users.id', $user->id)->exists()) {
            return;
        }
        abort(403, '본인의 할 일만 처리할 수 있습니다.');
    }

    /**
     * 검증 + 담당자 목록 해석 — assignee_ids(선택 순서 배열)가 오면 첫 번째가 대표(assignee_id).
     *
     * @return array{0: array<string, mixed>, 1: array<int, int>}
     */
    private function validateTodo(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string|max:10000',
            'priority' => ['required', Rule::in(array_keys(Todo::PRIORITIES))],
            'due_date' => 'nullable|date',
            'assignee_id' => 'required_without:assignee_ids|nullable|exists:users,id',
            'assignee_ids' => 'sometimes|array|min:1',
            'assignee_ids.*' => 'integer|exists:users,id',
            'schedule_id' => 'nullable|exists:schedules,id', // 캘린더 연동용 (선택)
        ]);

        $assigneeIds = collect($validated['assignee_ids'] ?? [$validated['assignee_id']])
            ->map(fn ($id) => (int) $id)->unique()->values();
        unset($validated['assignee_ids']);
        $validated['assignee_id'] = $assigneeIds->first();

        // 일반 멤버는 본인에게만 등록/수정 가능 (타인 지정은 관리자 이상)
        if (! Auth::user()->isAdmin() && $assigneeIds->contains(fn ($id) => $id !== Auth::id())) {
            abort(403, '다른 직원에게 할 일을 지정하는 것은 관리자만 가능합니다.');
        }

        return [$validated, $assigneeIds->all()];
    }

    /** @return array<int, array<string, mixed>> */
    private function boardTodos(): array
    {
        return Todo::with('assignee.team', 'creator', 'attachments', 'assignees', 'checklistItems.doneBy')
            // 일반 멤버는 본인이 담당자로 포함된 할 일만 조회
            ->when(! Auth::user()->isAdmin(), fn ($q) => $q->where(fn ($w) => $w
                ->where('assignee_id', Auth::id())
                ->orWhereHas('assignees', fn ($a) => $a->where('users.id', Auth::id()))))
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
            'due_held' => (bool) $t->due_held_at,
            'due_held_at' => $t->due_held_at?->format('Y.m.d H:i'),
            'assignee_id' => $t->assignee_id,
            'schedule_id' => $t->schedule_id,
            'assignee' => $t->assignee?->display_name ?? '알 수 없음',
            // 전체 담당자 (선택 순서대로) — 첫 번째가 대표(칸반 컬럼 기준)
            'assignee_ids' => $t->assignees->pluck('id')->values()->all() ?: [$t->assignee_id],
            'assignee_names' => $t->assignees->pluck('display_name')->values()->all() ?: [$t->assignee?->display_name ?? '알 수 없음'],
            // 담당자별 완료 체크 (복수 담당 — 전원 완료 시 전체 완료)
            'assignee_completed_ids' => $t->assignees->filter(fn ($u) => $u->pivot->completed_at)->pluck('id')->values()->all(),
            'my_completed' => $t->assignees->firstWhere('id', Auth::id())?->pivot?->completed_at !== null
                && $t->assignees->contains('id', Auth::id()),
            // 체크리스트 진행 단계
            'checklist' => ($t->relationLoaded('checklistItems') ? $t->checklistItems : collect())->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'done' => (bool) $c->done_at,
                'done_by' => $c->doneBy?->display_name,
            ])->values()->all(),
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
