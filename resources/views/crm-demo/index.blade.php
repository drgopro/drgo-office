@extends('layouts.app')

@section('title', 'CRM 개편 데모 - 닥터고블린 오피스')

@push('styles')
<style>
    .crm-wrap { max-width:1000px; margin:0 auto; padding:24px 20px 80px; }
    .crm-wrap .date { font-size:13px; color:var(--text-muted); }
    .crm-wrap h1.page { font-size:26px; font-weight:800; margin:2px 0 4px; }
    .crm-wrap .lead { font-size:14px; color:var(--text-muted); margin:0 0 8px; }
    .crm-banner { background:rgba(212,188,150,0.12); border:1px solid var(--accent); border-radius:10px; padding:10px 14px; font-size:13px; margin:14px 0; }
    .crm-sect { margin-top:30px; }
    .crm-sect-h { display:flex; align-items:center; gap:10px; margin-bottom:6px; }
    .crm-sect-n { width:30px; height:30px; border-radius:8px; background:var(--accent); color:var(--accent-text); font-weight:800; display:flex; align-items:center; justify-content:center; }
    .crm-sect-t { font-size:18px; font-weight:800; }
    .crm-sect-d { font-size:13px; color:var(--text-muted); margin:0 0 12px 40px; }
    .crm-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:16px 18px; margin-bottom:12px; }
    .crm-label { font-size:12px; font-weight:800; color:var(--accent); letter-spacing:.4px; margin:0 0 12px; }
    .req-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:10px; }
    .req-item { border:1px solid var(--border); border-radius:10px; padding:12px 14px; }
    .req-item .rn { font-size:14px; font-weight:800; }
    .req-item .rd { font-size:12px; color:var(--text-muted); margin-top:3px; }
    .dep-row { display:grid; grid-template-columns:120px 20px 1fr; align-items:center; gap:8px; padding:9px 0; border-bottom:1px solid var(--border); }
    .dep-row:last-child { border-bottom:none; }
    .dep-type { border:1px solid var(--accent); border-radius:8px; padding:7px 10px; text-align:center; font-weight:800; font-size:13px; color:var(--accent); }
    .dep-arrow { text-align:center; color:var(--text-muted); }
    .dep-work { display:flex; gap:6px; flex-wrap:wrap; }
    .wchip { font-size:12px; color:var(--text); background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:4px 10px; }
    .pl-row { display:grid; grid-template-columns:120px 1fr; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid var(--border); }
    .pl-row:last-child { border-bottom:none; }
    .pl-flow { display:flex; align-items:center; flex-wrap:wrap; gap:4px; }
    .pstep { font-size:12px; padding:6px 10px; border-radius:7px; background:var(--surface2); border:1px solid var(--border); white-space:nowrap; }
    .pstep.billing { background:rgba(36,138,56,0.12); border-color:#86efac; color:#248a38; font-weight:700; }
    .psep { color:var(--text-muted); }
    .dep-badge { font-size:10px; color:var(--text-muted); border:1px solid var(--border); border-radius:5px; padding:1px 6px; margin-left:6px; }
    .crm-note { font-size:12px; color:var(--text-muted); margin-top:10px; }
</style>
@endpush

@section('content')
@php $crm = config('crm'); @endphp
<div class="crm-wrap">
    <p class="date">2026.06.18 회의 반영 · 데모</p>
    <h1 class="page">CRM 개편 데모</h1>
    <p class="lead">신규 분류 · 파이프라인 정의 미리보기 (운영에 영향 없음 · 검증용)</p>
    <div class="crm-banner">⚙️ 이 페이지는 <b>config/crm.php</b> 정의를 그대로 렌더링합니다. 값 확인 후 순차적으로 실제 적용합니다.</div>

    {{-- 1) 의뢰자 유형 --}}
    <div class="crm-sect">
        <div class="crm-sect-h"><span class="crm-sect-n">1</span><span class="crm-sect-t">의뢰자 유형 (4종)</span></div>
        <div class="crm-card">
            <div class="req-grid">
                @foreach($crm['requester_types'] as $r)
                    <div class="req-item"><div class="rn">{{ $r['label'] }}</div><div class="rd">{{ $r['desc'] }}</div></div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- 2) 프로젝트 유형 → 작업 유형 --}}
    <div class="crm-sect">
        <div class="crm-sect-h"><span class="crm-sect-n">2</span><span class="crm-sect-t">프로젝트 유형 → 작업 유형 (9종, 종속)</span></div>
        <p class="crm-sect-d">프로젝트 유형을 고르면 그에 속한 작업 유형만 노출됨</p>
        <div class="crm-card">
            @foreach($crm['project_types'] as $pt)
                <div class="dep-row">
                    <div class="dep-type">{{ $pt['label'] }}</div>
                    <div class="dep-arrow">→</div>
                    <div class="dep-work">
                        @foreach($pt['work_types'] as $w)<span class="wchip">{{ $w }}</span>@endforeach
                        <span class="dep-badge">{{ $crm['departments'][$pt['department']] ?? $pt['department'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- 3) 파이프라인 --}}
    <div class="crm-sect">
        <div class="crm-sect-h"><span class="crm-sect-n">3</span><span class="crm-sect-t">진행 단계 파이프라인</span></div>
        <p class="crm-sect-d"><span style="color:#248a38; font-weight:700;">초록 = 결제(청구) 단계</span></p>
        <div class="crm-card">
            @foreach($crm['project_types'] as $pt)
                <div class="pl-row">
                    <div class="dep-type">{{ $pt['label'] }} <span style="font-size:10px; color:var(--text-muted);">{{ count($pt['pipeline']) }}단계</span></div>
                    <div class="pl-flow">
                        @foreach($pt['pipeline'] as $i => $st)
                            @if($i>0)<span class="psep">→</span>@endif
                            <span class="pstep {{ ($st['billing'] ?? false) ? 'billing' : '' }}">{{ $st['label'] }}</span>
                        @endforeach
                    </div>
                </div>
            @endforeach
            <div class="crm-note">※ 결제 단계 선택 시 '청구' 체크 생성 → 입금/잔금 추적(4번). 방송룸/렌탈은 세일즈 담당.</div>
        </div>
    </div>

    {{-- 5) 취소 사유 --}}
    <div class="crm-sect">
        <div class="crm-sect-h"><span class="crm-sect-n">5</span><span class="crm-sect-t">취소 사유</span></div>
        <div class="crm-card">
            <div class="dep-work">
                @foreach($crm['cancel_reasons'] as $cr)<span class="wchip">{{ $cr }}</span>@endforeach
            </div>
            <div class="crm-note">※ '연락 두절'은 며칠 무응답 시 취소로 볼지 기준 논의 필요.</div>
        </div>
    </div>
</div>
@endsection
