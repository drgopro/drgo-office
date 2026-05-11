<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('app:reset-operational {--force : 확인 없이 실행}')]
#[Description('사원/팀/설정/동적필드 정의를 제외한 모든 운영·마스터 데이터를 초기화합니다.')]
class ResetOperationalData extends Command
{
    /**
     * 오픈 전 운영/테스트 데이터 초기화.
     * 유지: users, teams, settings, remote_settings, client_field_definitions, 시스템 인프라
     * 초기화: CRM, 프로젝트, 견적/결제, 일정, 렌탈/방송룸 운영, 재고, 마스터(장비/상품/위키/게시판), 로그
     *
     * @var array<int, string> FK 의존성 역순 정렬
     */
    private array $tables = [
        // 로그류
        'activity_logs',
        'login_logs',
        'visits',
        'notifications',

        // 일정/캘린더
        'schedule_changes',
        'schedule_logs',
        'schedule_attachments',
        'schedule_assignees',
        'schedules',
        'as_schedules',
        'assignees',

        // 견적/결제
        'payments',
        'estimates',

        // 프로젝트/상담
        'consultations',
        'project_memos',
        'project_documents',
        'projects',

        // CRM
        'client_memos',
        'client_documents',
        'clients',

        // 렌탈 운영 + 마스터
        'rental_logs',
        'rental_contracts',
        'rental_targets',
        'rental_items',
        'rental_groups',
        'rental_categories',

        // 방송룸
        'broadcast_room_usages',
        'broadcast_room_contracts',

        // 재고 + 상품 마스터
        'stock_movements',
        'purchase_orders',
        'inventories',
        'products',
        'product_categories',

        // 위키 / 게시판
        'wiki_attachments',
        'wikis',
        'board_posts',
    ];

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->warn('이 명령은 운영·마스터 데이터를 모두 삭제합니다 (사원/팀/설정/필드정의는 유지).');
            if (! $this->confirm('정말 진행하시겠습니까?', false)) {
                $this->info('취소됨.');

                return self::SUCCESS;
            }
        }

        $deleted = [];
        $skipped = [];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->tables as $table) {
                if (! Schema::hasTable($table)) {
                    $skipped[] = $table.' (존재하지 않음)';

                    continue;
                }
                $count = DB::table($table)->count();
                DB::table($table)->truncate();
                $deleted[] = sprintf('%-30s  %d건 삭제', $table, $count);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->newLine();
        $this->info('초기화 완료:');
        foreach ($deleted as $line) {
            $this->line('  '.$line);
        }

        if (! empty($skipped)) {
            $this->newLine();
            $this->comment('건너뜀:');
            foreach ($skipped as $line) {
                $this->line('  '.$line);
            }
        }

        $this->newLine();
        $this->info('유지: users, teams, settings, remote_settings, client_field_definitions');

        return self::SUCCESS;
    }
}
