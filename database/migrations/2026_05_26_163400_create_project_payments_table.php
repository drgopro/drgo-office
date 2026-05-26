<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('parent_payment_id')->nullable()->constrained('project_payments')->nullOnDelete()
                ->comment('환불/취소가 어떤 결제에 대한 것인지');
            $table->enum('type', ['charge', 'refund', 'cancel'])->default('charge');
            $table->foreignId('estimate_id')->nullable()->constrained('estimates')->nullOnDelete()
                ->comment('견적서 연결 (추후 견적서 항목 직접 결제 시 사용)');
            $table->integer('amount')->default(0)
                ->comment('금액 (charge는 양수, refund/cancel은 음수로 저장)');
            $table->json('items')->nullable()
                ->comment('항목 [{name, qty, price, source_estimate_item_id?, source_payment_item_id?}]');
            $table->string('method', 30)->nullable();
            $table->date('paid_at')->nullable();
            $table->text('memo')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
            $table->index('type');
        });

        // 기존 payment_info(JSON)을 charge 트랜잭션으로 시드
        if (Schema::hasColumn('projects', 'payment_info')) {
            $projects = DB::table('projects')->whereNotNull('payment_info')->get(['id', 'payment_info', 'created_at']);
            foreach ($projects as $p) {
                $info = json_decode($p->payment_info, true);
                if (! is_array($info) || (int) ($info['amount'] ?? 0) <= 0) {
                    continue;
                }
                DB::table('project_payments')->insert([
                    'project_id' => $p->id,
                    'parent_payment_id' => null,
                    'type' => 'charge',
                    'estimate_id' => $info['estimate_id'] ?? null,
                    'amount' => (int) ($info['amount'] ?? 0),
                    'items' => isset($info['items']) ? json_encode($info['items'], JSON_UNESCAPED_UNICODE) : null,
                    'method' => $info['method'] ?? null,
                    'paid_at' => $info['paid_at'] ?? null,
                    'memo' => $info['memo'] ?? null,
                    'recorded_by' => $info['recorded_by'] ?? null,
                    'created_at' => $info['recorded_at'] ?? $p->created_at,
                    'updated_at' => $info['recorded_at'] ?? $p->created_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_payments');
    }
};
