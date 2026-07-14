@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '잔금 관리 - 닥터고블린 오피스')

@push('styles')
<style>
    .bl-wrap { padding:24px; max-width:1100px; margin:0 auto; }
    .bl-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px; }
    .bl-title { font-size:18px; font-weight:700; }
    .bl-total { font-size:13px; color:var(--text-muted); }
    .bl-total b { color:var(--red); font-size:16px; }
    .bl-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
    .bl-table { width:100%; border-collapse:collapse; font-size:13px; }
    .bl-table th { text-align:left; padding:10px 14px; background:var(--surface2); color:var(--text-muted); font-size:11px; font-weight:600; border-bottom:1px solid var(--border); white-space:nowrap; }
    .bl-table td { padding:12px 14px; border-bottom:1px solid var(--border); vertical-align:middle; }
    .bl-table tr:last-child td { border-bottom:none; }
    .bl-table tr:hover td { background:var(--surface2); }
    .bl-amount { font-weight:600; text-align:right; white-space:nowrap; }
    .bl-balance { color:var(--red); font-weight:700; text-align:right; white-space:nowrap; }
    .bl-status { display:inline-block; font-size:10px; padding:2px 8px; border-radius:10px; font-weight:700; }
    .bl-status.unpaid { background:rgba(200,80,80,0.12); color:var(--red); }
    .bl-status.partial { background:rgba(232,137,74,0.12); color:#e8894a; }
    .bl-link { color:var(--accent); text-decoration:none; font-weight:600; }
    .bl-link:hover { text-decoration:underline; }
    .bl-btn { background:none; border:1px solid var(--border); color:var(--text-muted); padding:4px 10px; border-radius:6px; font-size:11px; cursor:pointer; white-space:nowrap; }
    .bl-btn:hover { border-color:var(--accent); color:var(--accent); }
    .bl-empty { padding:48px 0; text-align:center; color:var(--text-muted); font-size:13px; }
    .bl-back { color:var(--text-muted); text-decoration:none; font-size:13px; }
    .bl-back:hover { color:var(--text); }
    @media (max-width:768px) { .bl-card { overflow-x:auto; } .bl-table { min-width:760px; } }
</style>
@endpush

@section('content')
<div class="bl-wrap">
    <a href="{{ route('projects.index') }}" class="bl-back">← 프로젝트 목록</a>
    <div class="bl-header" style="margin-top:8px;">
        <div class="bl-title">💸 잔금 관리 <span style="font-size:12px;color:var(--text-muted);font-weight:400;">— 미입금·부분입금 청구 모아보기</span></div>
        <div class="bl-total">총 미수 잔금 <b>{{ number_format($totalBalance) }}원</b> · {{ $rows->count() }}건</div>
    </div>

    <div class="bl-card">
        @if($rows->isEmpty())
            <div class="bl-empty">✅ 잔금이 남은 청구가 없습니다.</div>
        @else
        <table class="bl-table">
            <thead>
                <tr>
                    <th>의뢰자</th>
                    <th>프로젝트</th>
                    <th>청구일</th>
                    <th style="text-align:right;">청구액</th>
                    <th style="text-align:right;">입금액</th>
                    <th style="text-align:right;">잔금</th>
                    <th>최근 입금일</th>
                    <th>상태</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                @php($b = $row['billing'])
                <tr>
                    <td>{{ $b->project?->client?->name ?: $b->project?->client?->nickname ?: '-' }}</td>
                    <td>
                        <a class="bl-link" href="/projects/{{ $b->project_id }}" onclick="event.preventDefault(); if(window.parent && window.parent.drgoTabs) window.parent.drgoTabs.openNav('projects','/projects/{{ $b->project_id }}'); else location.href=this.href;">{{ $b->project?->name ?? '프로젝트 #'.$b->project_id }}</a>
                        @if($b->memo)<div style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ Str::limit($b->memo, 40) }}</div>@endif
                    </td>
                    <td style="white-space:nowrap;">{{ $b->billed_at?->format('Y.m.d') }} <span style="color:var(--text-muted);font-size:11px;">({{ (int) $b->billed_at?->diffInDays(now()) }}일 경과)</span></td>
                    <td class="bl-amount">{{ number_format($b->amount) }}원</td>
                    <td class="bl-amount" style="color:var(--text-muted);">{{ number_format($row['paid_total']) }}원</td>
                    <td class="bl-balance">{{ number_format($row['balance']) }}원</td>
                    <td style="white-space:nowrap;">{{ $row['last_paid_at'] ? \Illuminate\Support\Str::before($row['last_paid_at'], ' ') : '—' }}</td>
                    <td><span class="bl-status {{ $b->status }}">{{ $b->status === 'partial' ? '부분입금' : '미입금' }}</span></td>
                    <td style="text-align:right;">
                        @if(Auth::user()->hasPermission('projects.edit'))
                        <button class="bl-btn" onclick="blMarkPaid({{ $b->id }}, '{{ number_format($row['balance']) }}')">완료 처리</button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
const BL_CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
// 수동 완료 — 잔금이 남아 있어도 정리(할인 마감 등) 목적으로 종결
async function blMarkPaid(id, balanceStr){
    if(!confirm(`잔금 ${balanceStr}원이 남아 있습니다.\n이 청구를 완료 처리할까요? (더 이상 잔금 목록에 표시되지 않습니다)`)) return;
    const res = await fetch('/api/project-billings/'+id, {
        method:'PATCH',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':BL_CSRF,'Accept':'application/json'},
        body: JSON.stringify({status:'paid'}),
    });
    if(!res.ok){ alert('완료 처리에 실패했습니다.'); return; }
    location.reload();
}
</script>
@endpush
