@extends('layouts.app')

@section('title', '관리 - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:1000px; }
    .page-title { font-size:22px; font-weight:700; margin-bottom:20px; }

    .tab-bar { display:flex; gap:2px; background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:4px; margin-bottom:20px; }
    .tab-btn { flex:1; padding:10px 0; text-align:center; font-size:13px; font-weight:600; border:none; background:none; color:var(--text-muted); cursor:pointer; border-radius:8px; transition:all 0.15s; }
    .tab-btn.active { background:var(--accent); color:#1a1207; }
    .tab-btn:not(.active):hover { color:var(--text); background:var(--surface2); }
    .tab-panel { display:none; }
    .tab-panel.active { display:block; }

    .data-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
    .data-table { width:100%; border-collapse:collapse; }
    .data-table th { font-size:11px; color:var(--text-muted); font-weight:600; text-align:left; padding:11px 14px; background:var(--surface2); border-bottom:1px solid var(--border); }
    .data-table td { font-size:13px; padding:12px 14px; border-bottom:1px solid var(--border); }
    .data-table tr:last-child td { border-bottom:none; }
    .data-table tr:hover td { background:var(--surface2); }
    .text-muted { color:var(--text-muted); font-size:12px; }

    .badge { display:inline-block; font-size:10px; padding:2px 8px; border-radius:4px; font-weight:600; }
    .badge-success { background:#1a2a1a; color:#7ac87a; }
    .badge-fail { background:#2a1a1a; color:#c87a7a; }

    .pagination-wrap { display:flex; justify-content:center; margin-top:16px; }
    .pagination-wrap nav span, .pagination-wrap nav a { display:inline-block; padding:6px 12px; margin:0 2px; border-radius:6px; font-size:12px; border:1px solid var(--border); color:var(--text-muted); text-decoration:none; }
    .pagination-wrap nav span[aria-current] { background:var(--accent); color:#1a1207; border-color:var(--accent); }
    .pagination-wrap nav a:hover { border-color:var(--accent); color:var(--accent); }

    .settings-form { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:24px; max-width:600px; }
    .settings-form .field-group { margin-bottom:16px; }
    .settings-form .field-label { font-size:11px; color:var(--text-muted); margin-bottom:6px; }
    .settings-form .field-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13px; outline:none; }
    .settings-form .field-input:focus { border-color:var(--accent); }
    .btn-save { background:var(--accent); color:#1a1207; border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
    .save-msg { font-size:12px; color:var(--green); margin-left:10px; display:none; }
</style>
@endpush

@section('content')
<div class="page-wrap">
    <div class="page-title">관리</div>

    <div class="tab-bar">
        <button class="tab-btn active" onclick="adminTab('logs')">로그인 기록</button>
        <button class="tab-btn" onclick="adminTab('seller')">판매처 설정</button>
    </div>

    <div class="tab-panel active" id="panel-logs">
        <div class="data-card">
            <table class="data-table">
                <thead>
                    <tr><th>일시</th><th>사용자</th><th>아이디</th><th>결과</th><th>IP</th><th>브라우저</th></tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td class="text-muted">{{ $log->created_at->format('Y.m.d H:i:s') }}</td>
                        <td>{{ $log->user?->display_name ?? '-' }}</td>
                        <td class="text-muted">{{ $log->username }}</td>
                        <td>
                            @if($log->success)
                                <span class="badge badge-success">성공</span>
                            @else
                                <span class="badge badge-fail">실패</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $log->ip_address }}</td>
                        <td class="text-muted" style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $log->user_agent }}">
                            @php
                                $ua = $log->user_agent ?? '';
                                if (str_contains($ua, 'Chrome')) $browser = 'Chrome';
                                elseif (str_contains($ua, 'Safari')) $browser = 'Safari';
                                elseif (str_contains($ua, 'Firefox')) $browser = 'Firefox';
                                elseif (str_contains($ua, 'Edge')) $browser = 'Edge';
                                else $browser = mb_substr($ua, 0, 30);
                            @endphp
                            {{ $browser }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted);">로그인 기록이 없습니다.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="pagination-wrap">{{ $logs->links() }}</div>
        @endif
    </div>

    <div class="tab-panel" id="panel-seller">
        <div class="settings-form">
            <div class="field-group">
                <div class="field-label">상호명</div>
                <input class="field-input" id="sellerName" value="{{ $sellerSettings['seller_name'] ?? '' }}">
            </div>
            <div class="field-group">
                <div class="field-label">사업자번호</div>
                <input class="field-input" id="sellerBizNo" value="{{ $sellerSettings['seller_biz_no'] ?? '' }}" placeholder="000-00-00000">
            </div>
            <div class="field-group">
                <div class="field-label">주소</div>
                <input class="field-input" id="sellerAddress" value="{{ $sellerSettings['seller_address'] ?? '' }}">
            </div>
            <div class="field-group">
                <div class="field-label">업태</div>
                <input class="field-input" id="sellerBizType" value="{{ $sellerSettings['seller_biz_type'] ?? '' }}">
            </div>
            <div class="field-group">
                <div class="field-label">종목</div>
                <input class="field-input" id="sellerBizItem" value="{{ $sellerSettings['seller_biz_item'] ?? '' }}">
            </div>
            <div class="field-group">
                <div class="field-label">대표전화</div>
                <input class="field-input" id="sellerPhone" value="{{ $sellerSettings['seller_phone'] ?? '' }}">
            </div>
            <div style="display:flex; align-items:center;">
                <button class="btn-save" onclick="saveSellerSettings()">저장</button>
                <span class="save-msg" id="sellerSaveMsg">저장되었습니다.</span>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function adminTab(name) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.textContent.includes(name==='logs'?'로그인':'판매처')));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('active', p.id==='panel-'+name));
}

async function saveSellerSettings() {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const body = {
        seller_name: document.getElementById('sellerName').value,
        seller_biz_no: document.getElementById('sellerBizNo').value,
        seller_address: document.getElementById('sellerAddress').value,
        seller_biz_type: document.getElementById('sellerBizType').value,
        seller_biz_item: document.getElementById('sellerBizItem').value,
        seller_phone: document.getElementById('sellerPhone').value,
    };
    await fetch('/api/settings', {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify(body)
    });
    const msg = document.getElementById('sellerSaveMsg');
    msg.style.display = 'inline';
    setTimeout(() => msg.style.display = 'none', 2000);
}
</script>
@endpush
