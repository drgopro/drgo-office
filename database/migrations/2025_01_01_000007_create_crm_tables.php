<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CRM 핵심 3계층: clients → projects → consultations/stages
     *
     * 기획서 기반:
     *   의뢰자(clients) 1:N 프로젝트(projects) 1:N 이력(consultations 등)
     */
    public function up(): void
    {
        // ── 1. 의뢰자(고객) ──────────────────────────────────────────
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('실명');
            $table->string('nickname', 100)->nullable()->comment('닉네임 (스트리머명)');
            $table->string('phone', 30)->nullable()->comment('대표 연락처');
            $table->json('phones')->nullable()->comment('추가 연락처 배열');
            $table->string('address', 300)->nullable()->comment('주소');
            $table->string('address_detail', 200)->nullable()->comment('상세주소');
            $table->enum('grade', ['normal', 'vip', 'rental'])->default('normal')->comment('등급');
            $table->json('platforms')->nullable()->comment('플랫폼 배열 [유튜브,트위치,...]');
            $table->json('content_types')->nullable()->comment('콘텐츠 유형 배열 [게임,소통,...]');
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('affiliation', 200)->nullable()->comment('소속 (예: #엔터테인 8기)');
            $table->text('important_memo')->nullable()->comment('중요 메모');
            $table->text('memo')->nullable()->comment('일반 메모');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete()
                  ->comment('담당 사원');
            $table->enum('status', ['active', 'inactive', 'blacklist'])->default('active');
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('nickname');
            $table->index('status');
        });

        // 증빙 서류 (의뢰자별 파일)
        Schema::create('client_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('note', 300)->nullable()->comment('현금영수증/사업자등록증 등');
            $table->timestamps();
        });

        // ── 2. 프로젝트 ──────────────────────────────────────────────
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name', 200)->comment('프로젝트명 (직접 입력)');
            $table->enum('project_type', ['visit', 'remote', 'as'])->default('visit')
                  ->comment('방문세팅/원격세팅/AS');

            // 7단계 프로세스 바
            $table->enum('stage', [
                'consulting',   // 상담
                'equipment',    // 장비파악
                'proposal',     // 일정제안
                'estimate',     // 견적/계약
                'payment',      // 결제/예약
                'visit',        // 방문(또는 원격)
                'as',           // AS
                'done',         // 완료
                'cancelled',    // 취소
            ])->default('consulting');

            $table->enum('status', ['active', 'done', 'cancelled'])->default('active');

            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete()
                  ->comment('담당 사원');
            $table->text('memo')->nullable();

            // AS 마감일 (방문 완료 후 14일 자동)
            $table->date('as_deadline')->nullable()->comment('AS 만료일');
            $table->timestamp('completed_at')->nullable()->comment('완료 처리 시각');
            $table->timestamps();
            $table->softDeletes();

            $table->index('client_id');
            $table->index('stage');
            $table->index('status');
        });

        // ── 3. 상담 이력 ─────────────────────────────────────────────
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

            $table->date('consulted_at')->comment('상담일');
            $table->foreignId('consultant_id')->nullable()->constrained('users')->nullOnDelete()
                  ->comment('상담사');

            $table->enum('consult_type', [
                'phone',       // 전화상담
                'official',    // 공식(방문자)
                'partner',     // 제휴문의
                'blog',        // 블로그문의
                'free_visit',  // 방문무료
                'simple',      // 단순질문
                'other',
            ])->default('phone');

            $table->enum('result', [
                'in_progress', // 진행중(대화)
                'waiting',     // 대기
                'valid',       // 유효
                'invalid',     // 무효
                'done',        // 완료
            ])->default('in_progress');

            $table->text('content')->nullable()->comment('상담 내용');
            $table->boolean('is_important')->default(false)->comment('중요 별표');
            $table->json('attachments')->nullable()->comment('첨부파일 경로 배열');

            // 결제/예약 toggle (상담 폼에서 바로 처리)
            $table->boolean('has_payment')->default(false);
            $table->boolean('has_reservation')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index('project_id');
            $table->index('consulted_at');
        });

        // ── 4. 결제 이력 ─────────────────────────────────────────────
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

            $table->enum('status', ['pending', 'completed', 'cancelled', 'refunded'])
                  ->default('pending');
            $table->enum('method', ['payapp', 'cash', 'transfer', 'card', 'other'])
                  ->default('payapp');

            $table->unsignedBigInteger('amount')->comment('금액 (원)');
            $table->string('payapp_order_id', 100)->nullable()->comment('페이앱 주문ID');
            $table->string('payapp_receipt_url', 500)->nullable();

            $table->json('items')->nullable()->comment('결제 항목 배열 [{name,qty,price}]');
            $table->text('memo')->nullable();

            $table->timestamp('paid_at')->nullable()->comment('결제 완료 시각');
            $table->timestamps();

            $table->index('project_id');
            $table->index('status');
        });

        // ── 5. 방문 세팅 이력 ────────────────────────────────────────
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

            $table->date('visit_date')->comment('방문일');
            $table->time('visit_time')->nullable();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete()
                  ->comment('방문 기술자');

            $table->enum('status', ['scheduled', 'in_progress', 'done', 'cancelled'])
                  ->default('scheduled');

            // 체크리스트 (취소선 처리용)
            $table->json('checklist')->nullable()->comment('[{label,done}] 세팅 항목 체크리스트');

            $table->text('report')->nullable()->comment('방문 보고서');
            $table->text('special_notes')->nullable()->comment('특이사항');

            // 추가 결제/재방문 예약 (현장)
            $table->boolean('has_extra_payment')->default(false);
            $table->boolean('has_revisit')->default(false);

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── 6. AS 스케줄러 ───────────────────────────────────────────
        Schema::create('as_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

            $table->date('start_date')->comment('AS 시작일 (방문/원격 완료일)');
            $table->date('expire_date')->comment('AS 만료일 (기본: 시작일+14일)');
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');

            $table->text('memo')->nullable();
            $table->timestamps();

            $table->index('expire_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('as_schedules');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('consultations');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('client_documents');
        Schema::dropIfExists('clients');
    }
};
