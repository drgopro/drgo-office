<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** AS 현황판 — 사무실로 들어온 AS 건 (물건을 받은 시점부터 기재). 의뢰자·프로젝트 연동 필수 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('as_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('product_name', 200)->comment('AS 대상 제품명');
            $table->text('description')->nullable()->comment('상세설명 — 같이 온 부속·특이사항');
            $table->string('status', 20)->default('received')->index()
                ->comment('received=사무실 접수, in_repair=AS 접수/진행, repaired=AS 완료, done=완료(의뢰자 배송)');
            $table->string('cost_type', 10)->nullable()->comment('free=무상, paid=유상');
            $table->unsignedInteger('cost_amount')->nullable()->comment('유상 처리 비용(원)');
            $table->timestamp('received_at')->nullable()->comment('사무실 접수(물건 수령) 시각');
            $table->timestamp('sent_at')->nullable()->comment('AS 진행처 발송 시각');
            $table->timestamp('repaired_at')->nullable()->comment('AS 완료 통보 시각');
            $table->timestamp('done_at')->nullable()->comment('의뢰자 배송 완료 시각');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('as_requests');
    }
};
