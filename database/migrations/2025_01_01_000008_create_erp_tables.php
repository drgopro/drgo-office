<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 재고/ERP + 알림 + 게시판 테이블
     */
    public function up(): void
    {
        // ── 1. 제품/부품 마스터 ──────────────────────────────────────
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique()->comment('SKU 코드');
            $table->string('name', 200)->comment('제품명');
            $table->string('category', 100)->nullable()->comment('카테고리');
            $table->unsignedBigInteger('purchase_price')->default(0)->comment('매입가 (원)');
            $table->unsignedBigInteger('sale_price')->default(0)->comment('판매가 (원)');
            $table->integer('safety_stock')->default(0)->comment('안전재고 수량');
            $table->text('memo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // ── 2. 재고 현황 ─────────────────────────────────────────────
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->integer('quantity')->default(0)->comment('현재 재고 수량');
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();
        });

        // ── 3. 입출고 이력 ───────────────────────────────────────────
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('movement_type', ['in', 'out', 'adjust', 'return'])
                  ->comment('입고/출고/조정/반품');
            $table->integer('quantity')->comment('변동 수량 (출고는 음수)');
            $table->integer('quantity_after')->comment('이동 후 재고');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete()
                  ->comment('연결된 프로젝트 (출고 시)');
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->index('product_id');
            $table->index('movement_type');
        });

        // ── 4. 발주 관리 ─────────────────────────────────────────────
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['requested', 'approved', 'ordered', 'received', 'cancelled'])
                  ->default('requested')
                  ->comment('요청→승인→발주→입고→취소');
            $table->string('supplier', 200)->nullable()->comment('구매처');
            $table->json('items')->nullable()
                  ->comment('[{product_id, qty, unit_price}] 발주 항목');
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('expected_date')->nullable()->comment('입고 예정일');
            $table->date('received_date')->nullable()->comment('실제 입고일');
            $table->text('memo')->nullable();
            $table->timestamps();
        });

        // ── 5. 알림 ──────────────────────────────────────────────────
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', [
                'schedule_reminder', // 일정 리마인드
                'payment_done',      // 결제 완료
                'stock_shortage',    // 재고 부족
                'as_expire',         // AS 만료 예정
                'project_update',    // 프로젝트 상태 변경
                'system',            // 시스템
            ]);
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->json('data')->nullable()->comment('추가 데이터 (링크 등)');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
            $table->index('created_at');
        });

        // ── 6. 사내 게시판 ───────────────────────────────────────────
        Schema::create('board_posts', function (Blueprint $table) {
            $table->id();
            $table->enum('category', ['notice', 'meeting', 'issue', 'tech', 'general'])
                  ->default('general')
                  ->comment('공지/회의록/이슈/기술/일반');
            $table->string('title', 300);
            $table->text('content');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_pinned')->default(false)->comment('고정 여부');
            $table->json('attachments')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('category');
            $table->index('is_pinned');
        });

        // ── 7. 원격 세팅 이력 ────────────────────────────────────────
        Schema::create('remote_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

            $table->enum('billing_type', ['paid', 'free'])->default('free')
                  ->comment('유료/무료');
            $table->unsignedBigInteger('fee')->default(0)->comment('원격 세팅 비용');
            $table->boolean('is_new_setup')->default(true)
                  ->comment('처음 세팅(신규)이면 true → AS 자동 적용');

            $table->text('report')->nullable()->comment('원격 세팅 보고서');
            $table->text('special_notes')->nullable();

            $table->enum('status', ['scheduled', 'done', 'cancelled'])->default('scheduled');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // ── 8. 견적서 ────────────────────────────────────────────────
        Schema::create('estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

            // 제품(자산) 항목
            $table->json('product_items')->nullable()
                  ->comment('[{name,qty,unit_price,product_id}] 자산 항목');
            // 서비스 항목
            $table->json('service_items')->nullable()
                  ->comment('[{name,amount}] 서비스 항목');

            $table->unsignedBigInteger('product_total')->default(0)->comment('자산 합계');
            $table->unsignedBigInteger('service_total')->default(0)->comment('서비스 합계');
            $table->unsignedBigInteger('total_amount')->default(0)->comment('최종 합계');

            // 홈페이지 견적 API 연동 (Gnuboard5 estimate_api.php)
            $table->string('gnuboard_estimate_id', 100)->nullable()
                  ->comment('그누보드 견적번호 (홈페이지 연동)');

            $table->enum('status', ['draft', 'sent', 'approved', 'rejected'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimates');
        Schema::dropIfExists('remote_settings');
        Schema::dropIfExists('board_posts');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('products');
    }
};
