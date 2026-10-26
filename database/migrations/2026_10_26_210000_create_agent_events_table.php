<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI 에이전트 작업 이벤트 — 개발자 맥북의 Claude Code 훅이 토큰 인증으로 전송한 기록.
 * /agent 화면이 2초 폴링으로 읽어 실시간 흐름·작업 대화·보고서를 그린다. 90일 지나면 스케줄러가 정리.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_events', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 36)->nullable()->unique();   // 훅이 붙이는 이벤트 고유값 (재전송 중복 방지)
            $table->string('session_id', 64)->nullable()->index();
            $table->string('project', 64)->index();           // 작업 폴더 이름 (drgo-office)
            $table->string('event', 32);                       // SessionStart | UserPromptSubmit | PreToolUse | PostToolUse | PostToolUseFailure | Stop
            $table->string('node', 16);                        // user | claude | codex | payapp | git | github | forge
            $table->string('summary', 200);                    // 사람이 읽을 한 줄 요약
            $table->text('prompt')->nullable();                // 사용자 지시 원문 (비밀값 패턴은 전송 전에 가림)
            $table->json('report')->nullable();                // 리뷰/커밋/push 보고서 {type,title,note,items,body}
            $table->string('tool_use_id', 64)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->dateTime('occurred_at')->index();
            $table->dateTime('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_events');
    }
};
