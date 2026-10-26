<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** AI 에이전트 작업 이벤트 (맥북 Claude Code 훅 → /api/agent-events/ingest) */
class AgentEvent extends Model
{
    public $timestamps = false;

    public const EVENTS = ['SessionStart', 'UserPromptSubmit', 'PreToolUse', 'PostToolUse', 'PostToolUseFailure', 'Stop', 'SessionEnd'];

    public const NODES = ['user', 'claude', 'codex', 'payapp', 'git', 'github', 'forge'];

    protected $fillable = [
        'uid',
        'session_id',
        'project',
        'event',
        'node',
        'summary',
        'prompt',
        'report',
        'tool_use_id',
        'duration_ms',
        'occurred_at',
    ];

    protected $casts = [
        'report' => 'array',
        'duration_ms' => 'integer',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
