<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;

/**
 * 좌측 사이드바 메뉴 구성 — 관리 페이지에서 순서/섹션 라벨/구분선을 조정한다.
 * 저장 형식(Setting 'sidebar_menu_layout'): [{t:'menu', k:'dashboard'}, {t:'label', text:'워크스페이스'}, {t:'gap'}]
 * 메뉴 자체(주소·아이콘·권한)는 여기 레지스트리가 단일 기준이고, 설정은 배치만 바꾼다.
 */
class SidebarMenu
{
    public const SETTING_KEY = 'sidebar_menu_layout';

    /**
     * 메뉴 레지스트리 — key → [label, href, tab(드고탭 키), nav(열 주소), pattern(active 판정), icon(svg)]
     *
     * @return array<string, array{label: string, href: string, tab: string, nav: string, pattern: string, icon: string}>
     */
    public static function registry(): array
    {
        $i = fn (string $paths) => '<svg class="nav-ico" viewBox="0 0 24 24">'.$paths.'</svg>';

        return [
            'dashboard' => ['label' => '대시보드', 'href' => '/', 'tab' => 'dashboard', 'nav' => '/', 'pattern' => '/',
                'icon' => $i('<rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect>')],
            'calendar' => ['label' => '캘린더', 'href' => '/calendar', 'tab' => 'calendar', 'nav' => '/calendar', 'pattern' => 'calendar*',
                'icon' => $i('<rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path>')],
            'todos' => ['label' => '할 일', 'href' => '/todos', 'tab' => 'todos', 'nav' => '/todos', 'pattern' => 'todos*',
                'icon' => $i('<path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>')],
            'clients' => ['label' => '의뢰자', 'href' => '/clients', 'tab' => 'clients', 'nav' => '/clients', 'pattern' => 'clients*',
                'icon' => $i('<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>')],
            'projects' => ['label' => '프로젝트', 'href' => '/projects', 'tab' => 'projects', 'nav' => '/projects', 'pattern' => 'projects*',
                'icon' => $i('<path d="M4 4h16v16H4z"></path><path d="M4 9h16M9 4v16"></path>')],
            'estimates' => ['label' => '견적서', 'href' => '/estimates', 'tab' => 'estimates', 'nav' => '/estimates', 'pattern' => 'estimates*',
                'icon' => $i('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6M9 13h6M9 17h4"></path>')],
            'inventory' => ['label' => '재고', 'href' => '/inventory', 'tab' => 'inventory', 'nav' => '/inventory', 'pattern' => 'inventory*',
                'icon' => $i('<path d="M21 8v13H3V8M1 3h22v5H1zM10 12h4"></path>')],
            'notices' => ['label' => '공지사항', 'href' => '/notices', 'tab' => 'notices', 'nav' => '/notices', 'pattern' => 'notices*',
                'icon' => $i('<path d="M3 11l18-5v12L3 13v-2z"></path><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"></path>')],
            'wiki' => ['label' => '위키', 'href' => '/wiki', 'tab' => 'wiki', 'nav' => '/wiki', 'pattern' => 'wiki*',
                'icon' => $i('<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>')],
            'rental' => ['label' => '장비 위치', 'href' => '/rental-equipment', 'tab' => 'rental', 'nav' => '/rental-equipment', 'pattern' => 'rental-equipment*',
                'icon' => $i('<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>')],
            'rental-contracts' => ['label' => '렌탈', 'href' => '/rental-contracts', 'tab' => 'rental-contracts', 'nav' => '/rental-contracts', 'pattern' => 'rental-contracts*',
                'icon' => $i('<rect x="2" y="7" width="20" height="14" rx="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>')],
            'broadcast-room' => ['label' => '방송룸', 'href' => '/broadcast-room', 'tab' => 'broadcast-room', 'nav' => '/broadcast-room', 'pattern' => 'broadcast-room*',
                'icon' => $i('<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"></path>')],
            'deposits' => ['label' => '입금 내역', 'href' => '/deposits', 'tab' => 'deposits', 'nav' => '/deposits', 'pattern' => 'deposits*',
                'icon' => $i('<rect x="2" y="5" width="20" height="14" rx="2"></rect><path d="M2 10h20M6 15h4"></path>')],
            'marketing-report' => ['label' => '통계', 'href' => '/marketing-report', 'tab' => 'marketing-report', 'nav' => '/marketing-report', 'pattern' => 'marketing-report*',
                'icon' => $i('<path d="M3 3v18h18"></path><path d="m7 15 4-4 3 3 5-6"></path>')],
            'feedback' => ['label' => '피드백', 'href' => '/feedback', 'tab' => 'feedback', 'nav' => '/feedback', 'pattern' => 'feedback*',
                'icon' => $i('<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path><path d="M8 9h8M8 12h5"></path>')],
            'leave' => ['label' => '연차', 'href' => '/leave', 'tab' => 'leave', 'nav' => '/leave', 'pattern' => 'leave*',
                'icon' => $i('<circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path>')],
        ];
    }

    /** 메뉴별 노출 권한 — 기존 사이드바 @if 조건과 동일 */
    public static function allowed(User $user, string $key): bool
    {
        return match ($key) {
            'dashboard', 'todos' => ! $user->isGuest(),
            'calendar', 'notices', 'wiki' => true,
            'clients' => $user->hasPermission('clients.view'),
            'projects' => $user->hasPermission('projects.view'),
            'estimates' => $user->hasPermission('estimates.view'),
            'inventory' => $user->hasPermission('inventory.view'),
            'rental', 'rental-contracts' => $user->hasPermission('rental.view'),
            'broadcast-room' => $user->hasPermission('broadcast.view'),
            'deposits' => $user->hasPermission('deposits.view'),
            'marketing-report' => $user->hasPermission('stats.view'),
            'feedback', 'leave' => in_array($user->role, ['master', 'admin', 'member'], true),
            default => false,
        };
    }

    /**
     * 기본 배치 — 기존 하드코딩 순서 그대로.
     *
     * @return array<int, array<string, string>>
     */
    public static function defaultLayout(): array
    {
        return [
            ['t' => 'label', 'text' => '워크스페이스'],
            ['t' => 'menu', 'k' => 'dashboard'],
            ['t' => 'menu', 'k' => 'calendar'],
            ['t' => 'menu', 'k' => 'todos'],
            ['t' => 'menu', 'k' => 'clients'],
            ['t' => 'menu', 'k' => 'projects'],
            ['t' => 'menu', 'k' => 'estimates'],
            ['t' => 'gap'],
            ['t' => 'label', 'text' => '지식 · 자산'],
            ['t' => 'menu', 'k' => 'inventory'],
            ['t' => 'menu', 'k' => 'notices'],
            ['t' => 'menu', 'k' => 'wiki'],
            ['t' => 'menu', 'k' => 'rental'],
            ['t' => 'menu', 'k' => 'rental-contracts'],
            ['t' => 'menu', 'k' => 'broadcast-room'],
            ['t' => 'gap'],
            ['t' => 'menu', 'k' => 'deposits'],
            ['t' => 'menu', 'k' => 'marketing-report'],
            ['t' => 'menu', 'k' => 'feedback'],
            ['t' => 'menu', 'k' => 'leave'],
        ];
    }

    /**
     * 저장된 배치(없으면 기본) — 유효하지 않은 행은 버리고, 빠진 메뉴는 맨 아래에 붙여
     * 새 메뉴가 생겨도 사이드바에서 사라지지 않게 한다.
     *
     * @return array<int, array<string, string>>
     */
    public static function layout(): array
    {
        $raw = json_decode((string) Setting::get(self::SETTING_KEY, ''), true);
        if (! is_array($raw) || $raw === []) {
            return self::defaultLayout();
        }

        $registry = self::registry();
        $rows = [];
        $seen = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['t'] ?? '') === 'menu' && isset($registry[$row['k'] ?? '']) && ! isset($seen[$row['k']])) {
                $seen[$row['k']] = true;
                $rows[] = ['t' => 'menu', 'k' => $row['k']];
            } elseif (($row['t'] ?? '') === 'label' && trim((string) ($row['text'] ?? '')) !== '') {
                $rows[] = ['t' => 'label', 'text' => mb_substr(trim($row['text']), 0, 30)];
            } elseif (($row['t'] ?? '') === 'gap') {
                $rows[] = ['t' => 'gap'];
            }
        }
        foreach (array_keys($registry) as $key) {
            if (! isset($seen[$key])) {
                $rows[] = ['t' => 'menu', 'k' => $key];
            }
        }

        return $rows;
    }

    /**
     * 렌더용 — 권한 없는 메뉴를 제외하고, 비어버린 섹션 라벨과 겹친 구분선을 정리한다.
     *
     * @return array<int, array<string, string>>
     */
    public static function visibleRows(User $user): array
    {
        $registry = self::registry();
        $rows = [];
        foreach (self::layout() as $row) {
            if ($row['t'] === 'menu') {
                if (self::allowed($user, $row['k'])) {
                    $rows[] = $row;
                }

                continue;
            }
            $rows[] = $row;
        }

        // 라벨 뒤에 메뉴가 하나도 없으면(다음 라벨/끝 전까지) 라벨 제거, 연속·가장자리 구분선 정리
        $pruned = [];
        $count = count($rows);
        foreach ($rows as $i => $row) {
            if ($row['t'] === 'label') {
                $hasMenu = false;
                for ($j = $i + 1; $j < $count && $rows[$j]['t'] !== 'label'; $j++) {
                    if ($rows[$j]['t'] === 'menu') {
                        $hasMenu = true;
                        break;
                    }
                }
                if (! $hasMenu) {
                    continue;
                }
            }
            if ($row['t'] === 'gap' && ($pruned === [] || end($pruned)['t'] === 'gap')) {
                continue;
            }
            $pruned[] = $row;
        }
        while ($pruned !== [] && end($pruned)['t'] === 'gap') {
            array_pop($pruned);
        }

        return $pruned;
    }
}
