@extends('layouts.app')

@section('title', '내 연차 - 닥터고블린 오피스')

@section('content')
<div style="max-width:760px; margin:0 auto; padding:20px 16px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
        <h2 style="margin:0; font-size:19px;">🌴 내 연차</h2>
        <div style="display:flex; gap:8px; align-items:center;">
            <select onchange="location.href='/leave?year='+this.value" style="padding:6px 10px; border:1px solid var(--border); border-radius:8px; background:var(--surface); color:var(--text); font-size:13px;">
                @for($y = now()->year + 1; $y >= now()->year - 3; $y--)
                    <option value="{{ $y }}" {{ $y === $year ? 'selected' : '' }}>{{ $y }}년</option>
                @endfor
            </select>
            @if($canManage)
                <a href="/leave/manage?year={{ $year }}" style="padding:6px 14px; border:1px solid var(--accent); color:var(--accent); border-radius:8px; font-size:13px; text-decoration:none;">연차 관리</a>
            @endif
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:10px; margin-bottom:16px;">
        <div style="background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:14px 16px;">
            <div style="font-size:12px; color:var(--text-muted);">입사일</div>
            <div style="font-size:16px; font-weight:700; margin-top:4px;">{{ $summary['hire_date'] ?? '미등록' }}</div>
            @if($summary['suggest'])
                <div style="font-size:11px; color:var(--text-muted); margin-top:3px;">{{ $summary['suggest']['label'] }}</div>
            @endif
        </div>
        <div style="background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:14px 16px;">
            <div style="font-size:12px; color:var(--text-muted);">{{ $year }}년 부여</div>
            <div style="font-size:20px; font-weight:800; margin-top:4px;">{{ $summary['granted'] !== null ? rtrim(rtrim(number_format($summary['granted'], 1), '0'), '.').'일' : '미확정' }}</div>
            @if($summary['grant_note'])
                <div style="font-size:11px; color:var(--text-muted); margin-top:3px;">{{ $summary['grant_note'] }}</div>
            @endif
        </div>
        <div style="background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:14px 16px;">
            <div style="font-size:12px; color:var(--text-muted);">사용</div>
            <div style="font-size:20px; font-weight:800; margin-top:4px;">{{ rtrim(rtrim(number_format($summary['used'], 1), '0'), '.') }}일</div>
        </div>
        <div style="background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:14px 16px;">
            <div style="font-size:12px; color:var(--text-muted);">잔여</div>
            <div style="font-size:20px; font-weight:800; margin-top:4px; color:{{ ($summary['remaining'] ?? 1) < 0 ? '#dc2626' : 'var(--accent)' }};">
                {{ $summary['remaining'] !== null ? rtrim(rtrim(number_format($summary['remaining'], 1), '0'), '.').'일' : '—' }}
            </div>
        </div>
    </div>

    @if($summary['granted'] === null)
        <div style="background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:12px 14px; font-size:12.5px; color:var(--text-muted); margin-bottom:16px;">
            {{ $year }}년 부여 연차가 아직 확정되지 않았습니다. 경영지원팀에 문의해주세요.
        </div>
    @endif

    <div style="background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:16px;">
        <div style="font-size:14px; font-weight:700; margin-bottom:10px;">사용 내역 <span style="font-size:12px; color:var(--text-muted); font-weight:400;">— 캘린더의 휴가 일정에서 '연차 차감'을 체크하면 자동으로 기록됩니다</span></div>
        @if(empty($summary['usages']))
            <div style="font-size:13px; color:var(--text-muted); padding:12px 0;">{{ $year }}년 사용 내역이 없습니다.</div>
        @else
            <div style="display:flex; flex-direction:column;">
                @foreach($summary['usages'] as $u)
                    <div style="display:flex; align-items:center; gap:10px; padding:8px 2px; border-bottom:1px solid var(--border); font-size:13px; flex-wrap:wrap;">
                        <span style="font-weight:600; white-space:nowrap;">{{ $u['used_on'] }}</span>
                        <span style="font-size:11px; padding:2px 8px; border-radius:10px; background:var(--surface2); white-space:nowrap;">{{ $u['type'] }} {{ rtrim(rtrim(number_format($u['days'], 1), '0'), '.') }}일</span>
                        @if($u['from_calendar'])
                            <span style="font-size:11px; color:var(--text-muted); white-space:nowrap;">캘린더 연동</span>
                        @endif
                        <span style="color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; min-width:0;">{{ $u['note'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
