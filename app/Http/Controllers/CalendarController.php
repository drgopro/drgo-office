<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
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
        ]);

        $schedule->update($validated);

        if (isset($validated['assignees'])) {
            $schedule->assignees()->sync($validated['assignees']);
        }

        return response()->json($schedule);
    }

    // 일정 삭제
    public function destroy(Schedule $schedule)
    {
        $schedule->delete();

        return response()->json(['ok' => true]);
    }
}
