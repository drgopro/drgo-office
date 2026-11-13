@extends(config('view.standalone') ? 'layouts.standalone' : (config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app'))

@section('title', '캘린더 - 닥터고블린 오피스')
{{-- 캘린더 전용 PWA — 홈 화면에 추가 시 캘린더로 시작 --}}
@section('pwa_manifest', '/manifest-calendar.json')
@section('pwa_title', '닥터고블린 캘린더')

@push('styles')
@include('calendar.partials.styles-main')
@endpush

@push('styles')
@include('calendar.partials.styles-light')
@endpush

@push('styles')
@include('calendar.partials.styles-v2')
@endpush

@push('styles')
@include('partials.ux-v2-styles')
@endpush

@push('styles')
<style>
/* 작성 모드 — 일정 창만 전체 화면(그리드는 가림), 메인 캘린더의 진입 버튼 */
body.cal-write #modalOverlay { background:var(--bg); backdrop-filter:none; }
body.cal-write .cal-wm-new, body.cal-write #calAddFab { display:none !important; }
.cal-wm-new { width:auto !important; min-width:0; padding:0 10px !important; white-space:nowrap; } /* nav-btn의 고정 정사각 폭을 풀어 글자 넘침 방지 */
#calWriteIdle { display:none; position:fixed; inset:0; z-index:250; background:var(--bg); align-items:center; justify-content:center; }
#calWriteIdle .cwi-in { text-align:center; }
#calWriteIdle .cwi-msg { font-size:15px; color:var(--text-muted); margin-bottom:14px; }
#calWriteIdle .cwi-btns { display:flex; gap:8px; justify-content:center; }
.cal-wm-btn { width:auto !important; padding:0 9px !important; font-size:13px !important; white-space:nowrap; }
@media (max-width:768px) { .cal-wm-new { display:none; } }
</style>
@endpush

@section('content')
@include('calendar.partials.layout')
@include('calendar.partials.modal')
@include('calendar.partials.extras')
@endsection

<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
@push('scripts')
<script>
@include('calendar.js.01-core')
@include('calendar.js.02-chips-search')
@include('calendar.js.03-events-agenda')
@include('calendar.js.04-month-compact')
@include('calendar.js.05-timeline-form')
@include('calendar.js.06-lock-summary')
@include('calendar.js.07-client-project')
@include('calendar.js.08-ship-reqitems')
@include('calendar.js.09-modal-open-edit')
@include('calendar.js.10-save')
@include('calendar.js.12-write-mode')
@include('calendar.js.11-misc')
</script>
@endpush
