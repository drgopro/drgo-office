@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

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
@include('calendar.js.11-misc')
</script>
@endpush
