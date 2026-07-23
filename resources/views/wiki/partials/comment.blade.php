{{-- 회의록 댓글 1건 — $comment, $wiki, $isReply --}}
<div class="wiki-comment{{ $isReply ? ' wiki-reply' : '' }}">
    <div class="wiki-comment-meta">
        <span class="wiki-comment-author">{{ $comment->user?->display_name ?? $comment->user?->username ?? '알 수 없음' }}</span>
        <span>{{ $comment->created_at->format('Y.m.d H:i') }}</span>
        @if($comment->updated_at->gt($comment->created_at))
        <span>(수정됨)</span>
        @endif
        <span class="wiki-comment-actions">
            @if(! $isReply)
            <button type="button" class="wiki-comment-del" onclick="toggleReplyForm({{ $comment->id }})">답글</button>
            @endif
            @if($comment->user_id === auth()->id() || auth()->user()->isAdmin())
            <button type="button" class="wiki-comment-del" onclick="toggleCommentEdit({{ $comment->id }})">수정</button>
            <form method="POST" action="{{ route('wiki.comments.destroy', $comment) }}" onsubmit="return confirm('이 댓글을 삭제하시겠습니까?')" style="margin:0;">
                @csrf @method('DELETE')
                <button type="submit" class="wiki-comment-del">삭제</button>
            </form>
            @endif
        </span>
    </div>
    <div class="wiki-comment-body" id="commentBody{{ $comment->id }}">{{ $comment->body }}</div>
    @if($comment->user_id === auth()->id() || auth()->user()->isAdmin())
    <form method="POST" action="{{ route('wiki.comments.update', $comment) }}" class="wiki-comment-form wiki-comment-edit-form" id="commentEdit{{ $comment->id }}" style="display:none;">
        @csrf @method('PATCH')
        <textarea name="body" required maxlength="2000" rows="3" data-mention>{{ $comment->body }}</textarea>
        <span class="wiki-comment-edit-btns">
            <button type="submit">저장</button>
            <button type="button" class="cancel" onclick="toggleCommentEdit({{ $comment->id }})">취소</button>
        </span>
    </form>
    @endif
</div>
