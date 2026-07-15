@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '사용 가이드 - 닥터고블린 오피스')

@push('styles')
<style>
    .guide-wrap { padding:28px 24px 60px; max-width:880px; margin:0 auto; }
    .guide-header { margin-bottom:22px; }
    .guide-header h1 { font-size:21px; font-weight:800; display:flex; align-items:center; gap:9px; }
    .guide-header p { font-size:13px; color:var(--text-muted); margin-top:5px; }
    .guide-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:14px; }
    .guide-card { display:flex; gap:14px; align-items:flex-start; background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:18px 20px; text-decoration:none; color:var(--text); transition:all .15s; }
    .guide-card:hover { border-color:var(--accent); box-shadow:0 4px 14px rgba(24,39,64,.08); text-decoration:none; }
    .guide-ico { flex-shrink:0; width:40px; height:40px; border-radius:11px; background:#1f2b40; color:#fff; display:flex; align-items:center; justify-content:center; font-size:19px; }
    .guide-card h2 { font-size:15px; font-weight:700; margin:1px 0 4px; }
    .guide-card p { font-size:12.5px; color:var(--text-muted); line-height:1.6; margin:0; }
    .guide-card .go { margin-top:7px; display:inline-block; font-size:12px; font-weight:700; color:var(--accent); }
</style>
@endpush

@section('content')
<div class="guide-wrap">
    <div class="guide-header">
        <h1>📖 사용 가이드</h1>
        <p>닥터고블린 오피스의 기능별 사용 방법을 안내합니다. 문서 안에서 목차 이동과 검색이 가능합니다.</p>
    </div>
    <div class="guide-grid">
        <a class="guide-card" href="{{ route('guide.show', 'calendar') }}">
            <span class="guide-ico">📅</span>
            <span>
                <h2>캘린더 · 일정</h2>
                <p>일정 유형, 담당자·알림, 의뢰자 연결, 반복·배송·방송룸 이용, 휴지통·백업까지.</p>
                <span class="go">가이드 보기 →</span>
            </span>
        </a>
        <a class="guide-card" href="{{ route('guide.show', 'projects') }}">
            <span class="guide-ico">📁</span>
            <span>
                <h2>프로젝트 관리</h2>
                <p>유형별 진행 단계, 상세 카드, 결제 내역, 청구·잔금 관리, 취소·삭제와 권한.</p>
                <span class="go">가이드 보기 →</span>
            </span>
        </a>
        <a class="guide-card" href="{{ route('guide.show', 'clients') }}">
            <span class="guide-ico">👤</span>
            <span>
                <h2>의뢰자 관리</h2>
                <p>의뢰자 등록·등급, 상세 탭(프로젝트·첨부·견적·메모), 맞춤 항목과 권한.</p>
                <span class="go">가이드 보기 →</span>
            </span>
        </a>
        <a class="guide-card" href="{{ route('guide.show', 'rental-broadcast') }}">
            <span class="guide-ico">🏷</span>
            <span>
                <h2>렌탈 · 방송룸</h2>
                <p>렌탈 계약, 방송룸 월 계약·시간 대여, 캘린더 자동 연동, 장비 위치 보드.</p>
                <span class="go">가이드 보기 →</span>
            </span>
        </a>
    </div>
</div>
@endsection
