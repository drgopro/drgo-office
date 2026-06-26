<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demo_project_id')->constrained('demo_projects')->cascadeOnDelete();
            $table->foreignId('parent_payment_id')->nullable()->constrained('demo_payments')->nullOnDelete();
            $table->enum('type', ['charge', 'refund', 'cancel'])->default('charge');
            $table->integer('amount')->default(0)->comment('charge 양수, refund/cancel 음수');
            $table->json('items')->nullable();
            $table->string('method', 30)->nullable();
            $table->date('paid_at')->nullable();
            $table->text('memo')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['demo_project_id', 'created_at']);
        });

        // demo_projects: 잔금 정보 보관용 (운영 payment_info의 잔금 부분)
        Schema::table('demo_projects', function (Blueprint $table) {
            if (! Schema::hasColumn('demo_projects', 'has_balance')) {
                $table->boolean('has_balance')->default(false);
            }
            if (! Schema::hasColumn('demo_projects', 'balance_amount')) {
                $table->integer('balance_amount')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_payments');
        Schema::table('demo_projects', function (Blueprint $table) {
            foreach (['has_balance', 'balance_amount'] as $c) {
                if (Schema::hasColumn('demo_projects', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
