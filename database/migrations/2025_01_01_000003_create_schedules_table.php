<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Firebase events / private_events/{uid}/events → MySQL schedules 테이블
     *
     * ─────────────────────────────────────────────────────────────
     * Firebase evData 구조 → MySQL 컬럼 매핑
     * ─────────────────────────────────────────────────────────────
     * title            → title
     * startDate        → start_date
     * endDate          → end_date
     * startTime        → start_time
     * endTime          → end_time
     * allDay           → is_all_day
     * color            → color  (gold/teal/blue/red/green/purple/holiday)
     * name             → client_name     (의뢰인/고객명)
     * address          → address
     * location         → location        (세부 위치)
     * desc             → description
     * assignees[]      → schedule_assignees 피벗 테이블
     * attachments[]    → schedule_attachments 테이블
     * locked           → is_locked
     * notifMinutes     → notif_minutes
     * specialOpts[]    → special_opts (JSON)
     * schedOpt         → sched_opt
     * schedEventOpts[] → sched_event_opts (JSON)
     * schedAfterDays   → sched_after_days
     * schedAfterDate   → sched_after_date (date)
     * schedAfterReason → sched_after_reason
     * gold{}           → gold_data (JSON) -- color='gold' 일 때
     * teal{}           → teal_data (JSON) -- color='teal' 일 때
     * (컬렉션 경로)    → is_private (private_events이면 true)
     * updatedAt        → updated_at (Laravel 자동관리)
     * ─────────────────────────────────────────────────────────────
     *
     * color별 의미:
     *   gold    = 방문의뢰 (gold_data JSON 포함)
     *   teal    = 원격/방송룸 (teal_data JSON 포함)
     *   blue    = 사내업무
     *   red     = 휴가/개인
     *   green   = 촬영/스튜디오
     *   purple  = 미팅/내방
     *   holiday = 공휴일
     *
     * gold_data JSON 필드:
     *   name, phone, nickname, platform, topic, budget, source,
     *   equipment, req_topic, req_detail, special, specialReason,
     *   career, paid, order, delivery, balance, balance_amount,
     *   estimate_amount,
     *   quoteImgs[], refImgs[], roomImgs[]  (이미지 배열)
     *
     * teal_data JSON 필드:
     *   mode (remote|studio), name, platform, content, desc
     */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();

            // ── 기본 정보 ──
            $table->string('title', 200)->default('(제목 없음)')->comment('일정 제목');
            $table->date('start_date')->comment('시작일 (YYYY-MM-DD)');
            $table->date('end_date')->comment('종료일 (YYYY-MM-DD)');
            $table->time('start_time')->nullable()->comment('시작 시간 (시간 일정만)');
            $table->time('end_time')->nullable()->comment('종료 시간 (시간 일정만)');
            $table->boolean('is_all_day')->default(true)->comment('종일 일정 여부');

            // ── 분류 ──
            $table->enum('color', ['gold', 'teal', 'blue', 'red', 'green', 'purple', 'holiday'])
                  ->default('gold')
                  ->comment('색상 = 일정 유형');

            // ── 의뢰/고객 정보 (공통) ──
            $table->string('client_name', 100)->nullable()->comment('고객/의뢰인명 (Firebase name)');
            $table->string('address', 300)->nullable()->comment('주소');
            $table->string('location', 200)->nullable()->comment('세부 위치');
            $table->text('description')->nullable()->comment('설명/메모 (Firebase desc)');

            // ── 알림 ──
            $table->string('notif_minutes', 20)->nullable()
                  ->comment('알림 설정 (allday|숫자분|null)');

            // ── 잠금/비공개 ──
            $table->boolean('is_locked')->default(false)->comment('잠금 여부');
            $table->boolean('is_private')->default(false)
                  ->comment('비공개 일정 (Firebase private_events)');

            // ── 스케줄 옵션 (Firebase specialOpts, schedOpt 등) ──
            $table->json('special_opts')->nullable()->comment('특수 옵션 배열 (Firebase specialOpts)');
            $table->string('sched_opt', 50)->nullable()->comment('스케줄 옵션 (Firebase schedOpt)');
            $table->json('sched_event_opts')->nullable()->comment('스케줄 이벤트 옵션 (Firebase schedEventOpts)');
            $table->integer('sched_after_days')->nullable()->comment('N일 후 (Firebase schedAfterDays)');
            $table->date('sched_after_date')->nullable()->comment('특정일 이후 (Firebase schedAfterDate)');
            $table->string('sched_after_reason', 300)->nullable()
                  ->comment('사후 사유 (Firebase schedAfterReason)');

            // ── 타입별 상세 데이터 (JSON) ──
            // color='gold'일 때: 방문의뢰 상세 (고객명, 전화, 플랫폼, 주제, 예산, 견적 등)
            $table->json('gold_data')->nullable()->comment('방문의뢰 상세 데이터 (color=gold일 때)');

            // color='teal'일 때: 원격/방송룸 상세 (mode, name, platform, content, desc)
            $table->json('teal_data')->nullable()->comment('원격/방송룸 상세 데이터 (color=teal일 때)');

            // ── 생성자 ──
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('생성한 사용자');

            $table->timestamps(); // created_at, updated_at (Firebase updatedAt → updated_at)
            $table->softDeletes(); // deleted_at (삭제 이력 보존)

            // ── 인덱스 ──
            $table->index('start_date');
            $table->index('end_date');
            $table->index(['start_date', 'end_date']);
            $table->index('color');
            $table->index('is_private');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
