<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 렌탈/방송룸 권한 분리 백필 — 지금까지 장비 위치는 재고, 렌탈 계약·방송룸은
     * 의뢰자 권한으로 접근했으므로, 기존 팀이 배포 후 갑자기 접근을 잃지 않도록
     * 같은 범위의 새 권한을 부여한다. 이후 팀 관리에서 팀별로 조정하면 된다.
     */
    public function up(): void
    {
        foreach (DB::table('teams')->get(['id', 'permissions']) as $team) {
            $perms = json_decode($team->permissions ?? '[]', true) ?: [];
            $add = [];
            if (in_array('inventory.view', $perms, true) || in_array('clients.view', $perms, true)) {
                $add[] = 'rental.view';
            }
            if (in_array('inventory.edit', $perms, true) || in_array('clients.edit', $perms, true)) {
                $add[] = 'rental.edit';
            }
            if (in_array('clients.view', $perms, true)) {
                $add[] = 'broadcast.view';
            }
            if (in_array('clients.edit', $perms, true)) {
                $add[] = 'broadcast.edit';
            }
            // 연락처·주소 조회 분리 — 기존 조회 팀은 지금까지처럼 보이도록 부여 후 팀별 조정
            if (in_array('clients.view', $perms, true)) {
                $add[] = 'clients.pii';
            }

            $merged = array_values(array_unique(array_merge($perms, $add)));
            if ($merged !== $perms) {
                DB::table('teams')->where('id', $team->id)
                    ->update(['permissions' => json_encode($merged)]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('teams')->get(['id', 'permissions']) as $team) {
            $perms = json_decode($team->permissions ?? '[]', true) ?: [];
            $filtered = array_values(array_diff($perms, ['rental.view', 'rental.edit', 'broadcast.view', 'broadcast.edit', 'clients.pii']));
            if ($filtered !== $perms) {
                DB::table('teams')->where('id', $team->id)
                    ->update(['permissions' => json_encode($filtered)]);
            }
        }
    }
};
