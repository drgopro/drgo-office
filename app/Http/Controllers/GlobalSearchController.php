<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\Schedule;
use App\Models\Wiki;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * 오피스 전체 통합 검색 — 의뢰자/프로젝트/일정/위키/견적서를 한 번에.
 * 사이드바 검색(Ctrl+K)에서 사용. 섹션별 권한을 따르고 최대 5건씩 반환.
 */
class GlobalSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 1) {
            return response()->json(['sections' => []]);
        }

        $user = Auth::user();
        $like = "%{$q}%";
        $sections = [];

        // 의뢰자
        if ($user->hasPermission('clients.view')) {
            $items = Client::where(fn ($w) => $w
                ->where('name', 'like', $like)
                ->orWhere('nickname', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhereHas('contacts', fn ($cq) => $cq
                    ->where('name', 'like', $like)->orWhere('phone', 'like', $like)))
                ->with(['contacts' => fn ($cq) => $cq
                    ->where('name', 'like', $like)->orWhere('phone', 'like', $like)])
                ->orderByDesc('updated_at')->limit(5)
                ->get(['id', 'name', 'nickname', 'phone'])
                ->map(function ($c) {
                    $matched = $c->contacts->first(); // 관계자(매니저)로 매칭된 경우 병기
                    $sub = trim(($c->name && $c->nickname ? $c->name.' · ' : '').($c->phone ?? ''));
                    if ($matched) {
                        $sub = trim($sub.($sub ? ' · ' : '').'관계자 '.$matched->name.($matched->relation ? " ({$matched->relation})" : ''));
                    }

                    return [
                        'label' => $c->nickname ?: $c->name,
                        'sub' => $sub,
                        'nav' => 'clients',
                        'url' => '/clients?open='.$c->id,
                        'client_id' => $c->id,
                    ];
                });
            if ($items->isNotEmpty()) {
                $sections[] = ['key' => 'clients', 'label' => '의뢰자', 'items' => $items];
            }
        }

        // 프로젝트
        if ($user->hasPermission('projects.view')) {
            $items = Project::with('client:id,name,nickname')
                ->where(fn ($w) => $w
                    ->where('name', 'like', $like)
                    ->orWhere('manual_client_name', 'like', $like)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $like)->orWhere('nickname', 'like', $like)))
                ->orderByDesc('updated_at')->limit(5)
                ->get()
                ->map(fn ($p) => [
                    'label' => $p->name,
                    'sub' => trim(($p->clientDisplayName() ?? '').' · '.$p->stageLabel(), ' ·'),
                    'nav' => 'projects',
                    'url' => '/projects/'.$p->id,
                ]);
            if ($items->isNotEmpty()) {
                $sections[] = ['key' => 'projects', 'label' => '프로젝트', 'items' => $items];
            }
        }

        // 일정 (다른 사람의 비공개 일정 제외)
        $items = Schedule::where(fn ($w) => $w->where('is_private', false)->orWhere('created_by', $user->id))
            ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('client_name', 'like', $like)->orWhere('location', 'like', $like))
            ->orderByDesc('start_date')->limit(5)
            ->get(['id', 'title', 'start_date', 'client_name'])
            ->map(fn ($s) => [
                'label' => $s->title ?: '(제목 없음)',
                'sub' => trim(($s->start_date?->format('Y.m.d') ?? '').' '.($s->client_name ?? '')),
                'nav' => 'calendar',
                // 딥링크 — 캘린더가 해당 날짜로 이동해 일정 요약을 자동으로 연다
                'url' => '/calendar?date='.($s->start_date?->format('Y-m-d') ?? '').'&focus='.$s->id,
            ]);
        if ($items->isNotEmpty()) {
            $sections[] = ['key' => 'schedules', 'label' => '일정', 'items' => $items];
        }

        // 위키
        $items = Wiki::published()->visibleTo(Auth::user())->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('content', 'like', $like))
            ->orderByDesc('created_at')->limit(5)
            ->get(['id', 'title', 'category', 'created_at'])
            ->map(fn ($w) => [
                'label' => $w->title,
                'sub' => trim(($w->category ?? '').' · '.$w->created_at->format('Y.m.d'), ' ·'),
                'nav' => 'wiki',
                'url' => '/wiki/'.$w->id,
            ]);
        if ($items->isNotEmpty()) {
            $sections[] = ['key' => 'wiki', 'label' => '위키', 'items' => $items];
        }

        // 견적서
        if ($user->hasPermission('estimates.view')) {
            $items = Estimate::where(fn ($w) => $w
                ->where('client_name', 'like', $like)
                ->orWhere('client_nickname', 'like', $like))
                ->orderByDesc('created_at')->limit(5)
                ->get(['id', 'client_name', 'client_nickname', 'total_amount', 'created_at'])
                ->map(fn ($e) => [
                    'label' => '#'.$e->id.' '.($e->client_nickname ?: $e->client_name ?: '의뢰자 미상'),
                    'sub' => number_format((int) $e->total_amount).'원 · '.$e->created_at->format('Y.m.d'),
                    'nav' => 'estimates',
                    'url' => '/estimates/'.$e->id.'/edit',
                ]);
            if ($items->isNotEmpty()) {
                $sections[] = ['key' => 'estimates', 'label' => '견적서', 'items' => $items];
            }
        }

        return response()->json(['sections' => $sections, 'q' => Str::limit($q, 50)]);
    }
}
