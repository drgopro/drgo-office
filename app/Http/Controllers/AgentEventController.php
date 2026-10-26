<?php

namespace App\Http\Controllers;

use App\Models\AgentEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * AI 에이전트 작업 현황 (/agent)
 * - ingest: 맥북 Claude Code 훅이 토큰(X-Agent-Token)으로 이벤트를 보내는 입구. 세션 인증 밖이라 CSRF 제외.
 * - index / reports: 화면이 읽는 API (auth + master/admin).
 */
class AgentEventController extends Controller
{
    private const MAX_BATCH = 100;

    private const PAGE_LIMIT = 500;

    private const PROJECT = 'drgo-office';

    public function ingest(Request $request): JsonResponse
    {
        $expected = (string) config('services.agent_events.token');
        $provided = (string) ($request->header('X-Agent-Token') ?? $request->bearerToken() ?? '');
        $tokenOk = $expected !== '' && $provided !== '' && hash_equals($expected, $provided);

        if ($request->isMethod('get')) {
            return response()->json([
                'ok' => true,
                'token_configured' => $expected !== '',
                'token_valid' => $tokenOk,
                'hint' => 'POST {events:[...]} 로 이벤트를 보내면 저장됩니다.',
            ]);
        }

        if (! $tokenOk) {
            return response()->json(['message' => '인증 실패'], 401);
        }

        $events = $request->input('events');
        if (! is_array($events)) {
            $events = [$request->all()];
        }
        if (count($events) > self::MAX_BATCH) {
            return response()->json(['message' => '한 번에 '.self::MAX_BATCH.'건까지만 보낼 수 있습니다.'], 422);
        }

        $validated = validator(['events' => $events], [
            'events' => ['required', 'array', 'min:1'],
            'events.*.uid' => ['nullable', 'string', 'max:36'],
            'events.*.session_id' => ['nullable', 'string', 'max:64'],
            'events.*.project' => ['required', 'string', 'max:64'],
            'events.*.event' => ['required', Rule::in(AgentEvent::EVENTS)],
            'events.*.node' => ['required', Rule::in(AgentEvent::NODES)],
            'events.*.summary' => ['required', 'string', 'max:200'],
            'events.*.prompt' => ['nullable', 'string', 'max:20000'],
            'events.*.report' => ['nullable', 'array'],
            'events.*.report.type' => ['required_with:events.*.report', Rule::in(['review', 'commit', 'push', 'deploy'])],
            'events.*.report.title' => ['required_with:events.*.report', 'string', 'max:200'],
            'events.*.report.note' => ['nullable', 'string', 'max:500'],
            'events.*.report.body' => ['nullable', 'string', 'max:8000'],
            'events.*.report.items' => ['nullable', 'array', 'max:20'],
            'events.*.report.items.*.t' => ['required', 'string', 'max:300'],
            'events.*.report.items.*.ok' => ['nullable', 'boolean'],
            'events.*.tool_use_id' => ['nullable', 'string', 'max:64'],
            'events.*.duration_ms' => ['nullable', 'integer', 'min:0', 'max:86400000'],
            'events.*.ts' => ['nullable', 'date'],
        ])->validate();

        $ids = [];
        $skipped = 0;
        DB::transaction(function () use ($validated, &$ids, &$skipped) {
            foreach ($validated['events'] as $ev) {
                // 훅이 응답을 못 받고 재전송하면 같은 uid 가 다시 오므로 건너뜀
                if (! empty($ev['uid']) && AgentEvent::where('uid', $ev['uid'])->exists()) {
                    $skipped++;

                    continue;
                }
                $row = AgentEvent::create([
                    'uid' => $ev['uid'] ?? null,
                    'session_id' => $ev['session_id'] ?? null,
                    'project' => $ev['project'],
                    'event' => $ev['event'],
                    'node' => $ev['node'],
                    'summary' => $ev['summary'],
                    'prompt' => $ev['prompt'] ?? null,
                    'report' => $ev['report'] ?? null,
                    'tool_use_id' => $ev['tool_use_id'] ?? null,
                    'duration_ms' => $ev['duration_ms'] ?? null,
                    'occurred_at' => isset($ev['ts']) ? Carbon::parse($ev['ts'])->setTimezone(config('app.timezone')) : now(),
                ]);
                $ids[] = $row->id;
            }
        });

        return response()->json(['ok' => true, 'stored' => count($ids), 'skipped' => $skipped, 'last_id' => $ids ? end($ids) : null]);
    }

    /**
     * 화면 폴링. since>0 이면 그 이후 이벤트(최대 500건), 아니면 지정 날짜(기본 오늘)의 전체 이벤트.
     */
    public function index(Request $request): JsonResponse
    {
        $since = (int) $request->query('since', 0);
        $query = AgentEvent::query()->where('project', self::PROJECT)->orderBy('id');

        if ($since > 0) {
            $query->where('id', '>', $since)->limit(self::PAGE_LIMIT);
        } else {
            [$from, $to] = $this->dayRange($request->query('date'));
            $query->whereBetween('occurred_at', [$from, $to])->limit(self::PAGE_LIMIT * 4);
        }

        $events = $query->get()->map(fn (AgentEvent $e) => $this->toArray($e))->values();

        return response()->json([
            'events' => $events,
            'last_id' => $events->last()['id'] ?? $since,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** 날짜별 보고서(리뷰·커밋·push·배포)와 그날의 집계, 보고서가 있는 날짜 목록 */
    public function reports(Request $request): JsonResponse
    {
        [$from, $to] = $this->dayRange($request->query('date'));
        $day = AgentEvent::query()->where('project', self::PROJECT)->whereBetween('occurred_at', [$from, $to]);

        $reports = (clone $day)->whereNotNull('report')->orderBy('id')->get()
            ->map(fn (AgentEvent $e) => ['id' => $e->id, 'time' => $e->occurred_at->format('H:i'), 'ts' => $e->occurred_at->toIso8601String()] + $e->report)
            ->values();

        $counts = [
            'prompts' => (clone $day)->where('event', 'UserPromptSubmit')->count(),
            'reviews' => (clone $day)->where('event', 'PostToolUse')->where('node', 'codex')->count(),
            'commits' => (clone $day)->where('event', 'PostToolUse')->where('node', 'git')->count(),
            'pushes' => (clone $day)->where('event', 'PostToolUse')->where('node', 'github')->count(),
        ];

        $dates = AgentEvent::query()->where('project', self::PROJECT)->whereNotNull('report')
            ->where('occurred_at', '>=', now()->subDays(90)->startOfDay())
            ->orderByDesc('occurred_at')->pluck('occurred_at')
            ->map(fn ($d) => $d->format('Y-m-d'))->unique()->values();

        return response()->json([
            'date' => $from->format('Y-m-d'),
            'reports' => $reports,
            'counts' => $counts,
            'dates' => $dates,
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function dayRange(?string $date): array
    {
        $tz = config('app.timezone');
        if ($date === null || $date === '') {
            $day = now($tz);
        } else {
            // 2026-02-31 같은 값이 조용히 3월 3일로 바뀌지 않도록 실제 존재하는 날짜만 허용
            if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                abort(422, '날짜 형식이 잘못되었습니다 (YYYY-MM-DD).');
            }
            $day = Carbon::createFromFormat('Y-m-d', $date, $tz);
        }

        return [$day->copy()->startOfDay(), $day->copy()->endOfDay()];
    }

    private function toArray(AgentEvent $e): array
    {
        return [
            'id' => $e->id,
            'session' => $e->session_id,
            'project' => $e->project,
            'event' => $e->event,
            'node' => $e->node,
            'summary' => $e->summary,
            'prompt' => $e->prompt,
            'report' => $e->report,
            'tool_use_id' => $e->tool_use_id,
            'duration_ms' => $e->duration_ms,
            'ts' => $e->occurred_at->toIso8601String(),
        ];
    }
}
