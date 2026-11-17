<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 캘린더 대기함 — 보류(hold)·재방문(revisit)·날짜 미정(undated) 일정을 모아 두는 곳.
     * 제품 준비 상태와 배치 가능일은 저장하지 않고 매번 계산한다 (재고와 '오늘'이 바뀌기 때문).
     */
    public function up(): void
    {
        Schema::create('schedule_benches', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10)->comment('hold 보류 | revisit 재방문 | undated 날짜 미정');
            $table->string('status', 10)->default('waiting')->comment('waiting | placed | removed');
            // hold: 그 일정 / revisit: 원래 방문 일정 / undated: null
            $table->foreignId('schedule_id')->nullable()->constrained('schedules')->nullOnDelete();
            $table->date('origin_date')->nullable()->comment('흔적 날짜 — 보류는 원래 일정일, 재방문은 방문일');
            $table->string('title', 200);
            $table->string('color', 30)->comment('일정 카테고리 키');
            $table->string('address', 300)->nullable()->comment('지역(시·구) 추출용');
            $table->json('assignee_ids')->nullable();
            $table->text('reason')->comment('내용 (필수)');
            $table->string('window_type', 10)->default('asap')->comment('asap | after | between');
            $table->date('window_from')->nullable();
            $table->date('window_to')->nullable();
            $table->date('due_date')->nullable()->comment('기한');
            $table->string('block_condition', 300)->nullable()->comment('배치 전 조건 — 해결 전에는 배치 불가');
            $table->timestamp('block_resolved_at')->nullable();
            $table->json('estimate_ids')->nullable()->comment('연결 견적서 — 필요 제품의 출처');
            $table->json('stock_checks')->nullable()->comment('창고 확인 {product_id: {result, by, at}}');
            $table->json('prep_answers')->nullable()->comment('준비 답변 {product_id: {ok, expected_date, by, at}}');
            $table->json('undo_snapshot')->nullable()->comment('배치·빼기 되돌리기용 직전 상태');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('placed_schedule_id')->nullable()->constrained('schedules')->nullOnDelete();
            $table->timestamp('placed_at')->nullable();
            $table->foreignId('placed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('removed_reason', 300)->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('bench_id')->nullable()->index()->comment('대기 중이면 달력 대신 흔적으로 표시');
            $table->timestamp('bench_placed_at')->nullable()->comment('대기함에서 배치된 일정 (↩)');
            $table->boolean('bench_override')->default(false)->comment('알림 무시하고 배치됨 (⚠)');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('order_lead_days')->nullable()->comment('주문 소요일 — 없으면 담당자에게 3일 내 준비 가능 여부를 묻는다');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('order_lead_days');
        });
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['bench_id', 'bench_placed_at', 'bench_override']);
        });
        Schema::dropIfExists('schedule_benches');
    }
};
