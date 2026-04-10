<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\ScheduleChange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalendarController extends Controller
{
    // 캘린더 메인 뷰
    public function index()
    {
        return view('calendar.index');
    }

    // 일정 목록 API (월별 조회)
    public function events(Request $request)
    {
        $start = $request->query('start'); // YYYY-MM-DD
        $end = $request->query('end');   // YYYY-MM-DD

        $events = Schedule::with('assignees')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('start_date', '<=', $start)
                            ->where('end_date', '>=', $end);
                    });
            })
            ->where(function ($q) {
                $q->where('is_private', false)
                    ->orWhere('created_by', Auth::id());
            })
            ->get();

        // guest: 지역 + 시간만 노출
        if (Auth::user()->isGuest()) {
            $events = $events->map(fn ($e) => [
                'id' => $e->id,
                'start_date' => $e->start_date,
                'end_date' => $e->end_date,
                'start_time' => $e->start_time,
                'end_time' => $e->end_time,
                'is_all_day' => $e->is_all_day,
                'location' => $e->location,
                'color' => $e->color,
            ]);
        }

        return response()->json($events);
    }

    // 일정 저장
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'is_all_day' => 'boolean',
            'color' => 'required|in:gold,teal,blue,red,green,purple,holiday',
            'client_name' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:300',
            'location' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'is_private' => 'boolean',
            'assignees' => 'nullable|array',
            'gold_data' => 'nullable|array',
            'teal_data' => 'nullable|array',
            'special_opts' => 'nullable|array',
            'sched_opt' => 'nullable|string|max:50',
            'sched_event_opts' => 'nullable|array',
            'sched_after_days' => 'nullable|integer',
            'sched_after_date' => 'nullable|date',
            'sched_after_reason' => 'nullable|string|max:300',
            'notif_minutes' => 'nullable|string|max:10',
            'is_locked' => 'boolean',
        ]);

        $validated['created_by'] = Auth::id();

        $schedule = Schedule::create($validated);

        // 담당자 연결
        if (! empty($validated['assignees'])) {
            $schedule->assignees()->sync($validated['assignees']);
        }

        return response()->json($schedule, 201);
    }

    // 일정 수정
    public function update(Request $request, Schedule $schedule)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'is_all_day' => 'boolean',
            'color' => 'sometimes|in:gold,teal,blue,red,green,purple,holiday',
            'client_name' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:300',
            'location' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'is_private' => 'boolean',
            'assignees' => 'nullable|array',
            'gold_data' => 'nullable|array',
            'teal_data' => 'nullable|array',
            'special_opts' => 'nullable|array',
            'sched_opt' => 'nullable|string|max:50',
            'sched_event_opts' => 'nullable|array',
            'sched_after_days' => 'nullable|integer',
            'sched_after_date' => 'nullable|date',
            'sched_after_reason' => 'nullable|string|max:300',
            'notif_minutes' => 'nullable|string|max:10',
            'is_locked' => 'boolean',
        ]);

        // 변경 이력 기록
        $diff = [];
        foreach ($validated as $key => $newVal) {
            if ($key === 'assignees') {
                continue;
            }
            $oldVal = $schedule->getOriginal($key);
            if (json_encode($oldVal) !== json_encode($newVal)) {
                $diff[$key] = ['old' => $oldVal, 'new' => $newVal];
            }
        }
        if (! empty($diff)) {
            ScheduleChange::create([
                'schedule_id' => $schedule->id,
                'user_id' => Auth::id(),
                'action' => 'update',
                'changes' => $diff,
            ]);
        }

        $schedule->update($validated);

        if (isset($validated['assignees'])) {
            $schedule->assignees()->sync($validated['assignees']);
        }

        return response()->json($schedule);
    }

    // 일정 상세 API
    public function detail(Schedule $schedule)
    {
        $schedule->load('assignees', 'creator');

        return response()->json($schedule);
    }

    // 수정내역 API
    public function history(Schedule $schedule)
    {
        $changes = $schedule->changes()->with('user')->get()->map(fn ($c) => [
            'id' => $c->id,
            'action' => $c->action,
            'changes' => $c->changes,
            'user_name' => $c->user?->display_name ?? '알 수 없음',
            'created_at' => $c->created_at->format('Y.m.d H:i'),
        ]);

        return response()->json($changes);
    }

    // 일정 삭제
    public function destroy(Schedule $schedule)
    {
        $schedule->delete();

        return response()->json(['ok' => true]);
    }

    // JSON 내보내기
    public function exportJson()
    {
        $events = Schedule::with('assignees')->get();
        $data = $events->map(fn ($e) => [
            'title' => $e->title,
            'start_date' => $e->start_date,
            'end_date' => $e->end_date,
            'start_time' => $e->start_time,
            'end_time' => $e->end_time,
            'is_all_day' => $e->is_all_day,
            'color' => $e->color,
            'client_name' => $e->client_name,
            'address' => $e->address,
            'location' => $e->location,
            'description' => $e->description,
            'notif_minutes' => $e->notif_minutes,
            'is_locked' => $e->is_locked,
            'is_private' => $e->is_private,
            'special_opts' => $e->special_opts,
            'sched_opt' => $e->sched_opt,
            'sched_event_opts' => $e->sched_event_opts,
            'sched_after_reason' => $e->sched_after_reason,
            'gold_data' => $e->gold_data,
            'teal_data' => $e->teal_data,
            'assignee_ids' => $e->assignees->pluck('id')->toArray(),
        ]);

        $filename = 'drgo-calendar-'.now()->format('Y-m-d').'.json';

        return response()->json(['events' => $data, 'exported_at' => now()->toIso8601String()])
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    // JSON 가져오기
    public function importJson(Request $request)
    {
        $request->validate(['file' => 'required|file']);

        $ext = strtolower($request->file('file')->getClientOriginalExtension());
        if (! in_array($ext, ['json', 'txt'])) {
            return response()->json(['error' => 'JSON 또는 TXT 파일만 업로드 가능합니다.'], 422);
        }

        $raw = file_get_contents($request->file('file')->getRealPath());
        $content = json_decode($raw, true);
        if (! $content || ! isset($content['events'])) {
            return response()->json(['error' => '올바르지 않은 JSON 형식입니다. events 키가 필요합니다.'], 422);
        }

        $count = 0;
        foreach ($content['events'] as $item) {
            $schedule = Schedule::create([
                'title' => $item['title'] ?? '(제목 없음)',
                'start_date' => $item['start_date'],
                'end_date' => $item['end_date'] ?? $item['start_date'],
                'start_time' => $item['start_time'] ?? null,
                'end_time' => $item['end_time'] ?? null,
                'is_all_day' => $item['is_all_day'] ?? false,
                'color' => $item['color'] ?? 'gold',
                'client_name' => $item['client_name'] ?? null,
                'address' => $item['address'] ?? null,
                'location' => $item['location'] ?? null,
                'description' => $item['description'] ?? null,
                'notif_minutes' => $item['notif_minutes'] ?? null,
                'is_locked' => $item['is_locked'] ?? false,
                'is_private' => $item['is_private'] ?? false,
                'special_opts' => $item['special_opts'] ?? [],
                'sched_opt' => $item['sched_opt'] ?? null,
                'sched_event_opts' => $item['sched_event_opts'] ?? [],
                'sched_after_reason' => $item['sched_after_reason'] ?? null,
                'gold_data' => $item['gold_data'] ?? null,
                'teal_data' => $item['teal_data'] ?? null,
                'created_by' => Auth::id(),
            ]);
            if (! empty($item['assignee_ids'])) {
                $schedule->assignees()->sync($item['assignee_ids']);
            }
            $count++;
        }

        return response()->json(['message' => "{$count}건의 일정을 가져왔습니다.", 'count' => $count]);
    }

    // iCal 내보내기
    public function exportIcal()
    {
        $events = Schedule::all();
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//DrGo Office//Calendar//KO', 'CALSCALE:GREGORIAN'];

        foreach ($events as $e) {
            $uid = "drgo-{$e->id}@drgo-office";
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:{$uid}";
            if ($e->is_all_day) {
                $lines[] = 'DTSTART;VALUE=DATE:'.str_replace('-', '', $e->start_date);
                $endDate = $e->end_date ? date('Ymd', strtotime($e->end_date.' +1 day')) : str_replace('-', '', $e->start_date);
                $lines[] = "DTEND;VALUE=DATE:{$endDate}";
            } else {
                $start = str_replace('-', '', $e->start_date).'T'.str_replace(':', '', $e->start_time ?? '0000').'00';
                $end = str_replace('-', '', $e->end_date ?? $e->start_date).'T'.str_replace(':', '', $e->end_time ?? '2359').'00';
                $lines[] = "DTSTART:{$start}";
                $lines[] = "DTEND:{$end}";
            }
            $lines[] = 'SUMMARY:'.str_replace(["\r", "\n"], ' ', $e->title ?? '');
            if ($e->location) {
                $lines[] = 'LOCATION:'.str_replace(["\r", "\n"], ' ', $e->location);
            }
            if ($e->description) {
                $lines[] = 'DESCRIPTION:'.str_replace(["\r", "\n"], '\\n', $e->description);
            }
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';

        $filename = 'drgo-calendar-'.now()->format('Y-m-d').'.ics';

        return response(implode("\r\n", $lines), 200)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    // iCal 가져오기
    public function importIcal(Request $request)
    {
        $request->validate(['file' => 'required|file']);

        $content = file_get_contents($request->file('file')->getRealPath());
        $vevents = preg_split('/BEGIN:VEVENT/', $content);
        array_shift($vevents); // 첫 번째는 VCALENDAR 헤더

        $count = 0;
        foreach ($vevents as $vevent) {
            $title = $this->icalProp($vevent, 'SUMMARY');
            $dtstart = $this->icalProp($vevent, 'DTSTART');
            $dtend = $this->icalProp($vevent, 'DTEND');
            $location = $this->icalProp($vevent, 'LOCATION');
            $description = str_replace('\\n', "\n", $this->icalProp($vevent, 'DESCRIPTION') ?? '');

            $isAllDay = strlen($dtstart) === 8;
            if ($isAllDay) {
                $startDate = substr($dtstart, 0, 4).'-'.substr($dtstart, 4, 2).'-'.substr($dtstart, 6, 2);
                $endDate = $dtend ? date('Y-m-d', strtotime(substr($dtend, 0, 4).'-'.substr($dtend, 4, 2).'-'.substr($dtend, 6, 2).' -1 day')) : $startDate;
                $startTime = $endTime = null;
            } else {
                $startDate = substr($dtstart, 0, 4).'-'.substr($dtstart, 4, 2).'-'.substr($dtstart, 6, 2);
                $startTime = substr($dtstart, 9, 2).':'.substr($dtstart, 11, 2);
                $endDate = $dtend ? substr($dtend, 0, 4).'-'.substr($dtend, 4, 2).'-'.substr($dtend, 6, 2) : $startDate;
                $endTime = $dtend ? substr($dtend, 9, 2).':'.substr($dtend, 11, 2) : null;
            }

            Schedule::create([
                'title' => $title ?? '(제목 없음)',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_all_day' => $isAllDay,
                'color' => 'blue',
                'location' => $location,
                'description' => $description ?: null,
                'created_by' => Auth::id(),
            ]);
            $count++;
        }

        return response()->json(['message' => "{$count}건의 일정을 가져왔습니다.", 'count' => $count]);
    }

    private function icalProp(string $vevent, string $name): ?string
    {
        // 속성이 파라미터를 포함할 수 있음 (예: DTSTART;VALUE=DATE:20260410)
        if (preg_match('/^'.preg_quote($name, '/').'[;:]([^\r\n]+)/m', $vevent, $m)) {
            $val = $m[1];
            // 파라미터가 있으면 콜론 뒤가 실제 값
            if (str_contains($m[0], ';') && str_contains($val, ':')) {
                $val = substr($val, strpos($val, ':') + 1);
            }

            return trim($val);
        }

        return null;
    }
}
