@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '대시보드 - 닥터고블린 오피스')

@push('styles')
<style>
    .dash-wrap { padding:24px; max-width:1200px; margin:0 auto; }
    .dash-header { margin-bottom:24px; }
    .dash-header h1 { font-size:20px; font-weight:700; }
    .dash-header p { font-size:12px; color:var(--text-muted); margin-top:4px; }

    .stat-cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:14px; margin-bottom:24px; }
    .stat-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:18px 20px; }
    .stat-label { font-size:10px; letter-spacing:0.15em; color:var(--text-muted); text-transform:uppercase; margin-bottom:8px; }
    .stat-value { font-size:28px; font-weight:700; color:var(--text); }
    .stat-sub { font-size:11px; color:var(--text-muted); margin-top:4px; }
    .stat-badge { display:inline-block; font-size:10px; padding:2px 8px; border-radius:10px; font-weight:600; margin-right:4px; }

    .chart-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px; }
    .chart-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px; }
    .chart-card.full { grid-column:1/-1; }
    .chart-title { font-size:13px; font-weight:600; color:var(--accent); margin-bottom:14px; display:flex; align-items:center; gap:6px; }
    .chart-wrap { position:relative; height:220px; }
    .chart-wrap.short { height:180px; }

    .detail-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:8px; }
    .detail-item { display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:var(--surface2); border-radius:8px; font-size:12px; }
    .detail-item-label { color:var(--text-muted); }
    .detail-item-value { font-weight:600; color:var(--text); }

    @media (max-width:768px) {
        .stat-cards { grid-template-columns:repeat(2,1fr); }
        .chart-grid { grid-template-columns:1fr; }
    }
</style>
@endpush

@section('content')
<div class="dash-wrap">
    <div class="dash-header">
        <h1>📊 대시보드</h1>
        <p>{{ now()->format('Y년 m월 d일') }} 기준 통계</p>
    </div>

    {{-- 주요 수치 카드 --}}
    <div class="stat-cards">
        <div class="stat-card">
            <div class="stat-label">총 의뢰자</div>
            <div class="stat-value">{{ number_format($clientTotal) }}</div>
            <div class="stat-sub">이번 달 +{{ $clientThisMonth }}명</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">진행 중 프로젝트</div>
            <div class="stat-value">{{ number_format($projectActive) }}</div>
            <div class="stat-sub">전체 {{ $projectTotal }}건</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">이번 달 상담</div>
            <div class="stat-value">{{ number_format($consultThisMonth) }}</div>
            <div class="stat-sub">전체 {{ $consultTotal }}건</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">이번 달 일정</div>
            <div class="stat-value">{{ number_format($scheduleThisMonth) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">견적 총액 (완료+결제)</div>
            <div class="stat-value" style="font-size:22px;">{{ number_format($estimateTotalAmount) }}원</div>
            <div class="stat-sub">결제 완료 {{ number_format($estimatePaidAmount) }}원</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">총 견적서</div>
            <div class="stat-value">{{ number_format($estimateTotal) }}</div>
            <div class="stat-sub">
                @foreach($estimateByStatus as $status => $cnt)
                    @php $sl=['created'=>'작성중','editing'=>'수정중','completed'=>'완료','paid'=>'결제완료','hold'=>'보류']; @endphp
                    <span class="stat-badge" style="background:var(--surface2);">{{ $sl[$status] ?? $status }} {{ $cnt }}</span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- 그래프 --}}
    <div class="chart-grid">
        {{-- 월별 추이 --}}
        <div class="chart-card full">
            <div class="chart-title">📈 월별 추이 (최근 6개월)</div>
            <div class="chart-wrap"><canvas id="chartMonthly"></canvas></div>
        </div>

        {{-- 프로젝트 단계별 --}}
        <div class="chart-card">
            <div class="chart-title">📁 프로젝트 단계별</div>
            <div class="chart-wrap short"><canvas id="chartProjectStage"></canvas></div>
        </div>

        {{-- 프로젝트 유형별 --}}
        <div class="chart-card">
            <div class="chart-title">📋 프로젝트 유형별</div>
            <div class="chart-wrap short"><canvas id="chartProjectType"></canvas></div>
        </div>

        {{-- 상담 유형별 --}}
        <div class="chart-card">
            <div class="chart-title">💬 상담 유형별</div>
            <div class="chart-wrap short"><canvas id="chartConsultType"></canvas></div>
        </div>

        {{-- 일정 유형별 --}}
        <div class="chart-card">
            <div class="chart-title">📅 일정 유형별</div>
            <div class="chart-wrap short"><canvas id="chartScheduleColor"></canvas></div>
        </div>
    </div>

    {{-- 상세 수치 --}}
    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-title">👤 의뢰자 등급</div>
            <div class="detail-grid">
                @php $gl=['normal'=>'일반','vip'=>'VIP','rental'=>'렌탈']; @endphp
                @foreach($clientByGrade as $grade => $cnt)
                    <div class="detail-item"><span class="detail-item-label">{{ $gl[$grade] ?? $grade }}</span><span class="detail-item-value">{{ $cnt }}명</span></div>
                @endforeach
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-title">📁 프로젝트 단계 상세</div>
            <div class="detail-grid">
                @php $stl=['consulting'=>'상담','equipment'=>'장비파악','proposal'=>'일정제안','estimate'=>'견적/계약','payment'=>'결제/예약','visit'=>'세팅','as'=>'AS','done'=>'완료','cancelled'=>'취소']; @endphp
                @foreach($projectByStage as $stage => $cnt)
                    <div class="detail-item"><span class="detail-item-label">{{ $stl[$stage] ?? $stage }}</span><span class="detail-item-value">{{ $cnt }}건</span></div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
const textColor = isDark ? '#a09890' : '#5a6070';
const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
Chart.defaults.color = textColor;
Chart.defaults.borderColor = gridColor;

// 월별 추이
new Chart(document.getElementById('chartMonthly'), {
    type: 'line',
    data: {
        labels: @json(array_column($monthlyClients, 'label')),
        datasets: [
            { label:'의뢰자', data:@json(array_column($monthlyClients, 'value')), borderColor:'#c8b08a', backgroundColor:'rgba(200,176,138,0.1)', fill:true, tension:0.3 },
            { label:'프로젝트', data:@json(array_column($monthlyProjects, 'value')), borderColor:'#8ab4c8', backgroundColor:'rgba(138,180,200,0.1)', fill:true, tension:0.3 },
            { label:'상담', data:@json(array_column($monthlyConsults, 'value')), borderColor:'#7ac87a', backgroundColor:'rgba(122,200,122,0.1)', fill:true, tension:0.3 },
        ]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'top',labels:{font:{size:11}}}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}} }
});

// 프로젝트 단계별
const stageLabels = {consulting:'상담',equipment:'장비파악',proposal:'일정제안',estimate:'견적/계약',payment:'결제/예약',visit:'세팅',as:'AS',done:'완료',cancelled:'취소'};
const stageData = @json($projectByStage);
new Chart(document.getElementById('chartProjectStage'), {
    type: 'doughnut',
    data: {
        labels: Object.keys(stageData).map(k=>stageLabels[k]||k),
        datasets: [{ data:Object.values(stageData), backgroundColor:['#c8b08a','#8ab4c8','#9b70c8','#e8894a','#4ecdc4','#7ac87a','#c87a7a','#666','#999'] }]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom',labels:{font:{size:10},padding:8}}} }
});

// 프로젝트 유형별
const typeLabels = {visit:'방문세팅',remote:'원격세팅',design:'디자인',inquiry:'단순문의',as:'A/S',troubleshoot:'문제 해결'};
const typeData = @json($projectByType);
new Chart(document.getElementById('chartProjectType'), {
    type: 'bar',
    data: {
        labels: Object.keys(typeData).map(k=>typeLabels[k]||k),
        datasets: [{ data:Object.values(typeData), backgroundColor:['#c8b08a','#8ab4c8','#9b70c8','#e8894a','#c87a7a','#7ac87a'] }]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}} }
});

// 상담 유형별
const consultLabels = {kakao:'카카오톡',phone:'전화',visit:'내방상담',field:'현장답사'};
const consultData = @json($consultByType);
new Chart(document.getElementById('chartConsultType'), {
    type: 'doughnut',
    data: {
        labels: Object.keys(consultData).map(k=>consultLabels[k]||k),
        datasets: [{ data:Object.values(consultData), backgroundColor:['#e8894a','#8ab4c8','#7ac87a','#9b70c8'] }]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom',labels:{font:{size:10},padding:8}}} }
});

// 일정 유형별
const schedLabels = {gold:'방문의뢰',teal:'원격/방송룸',blue:'사내업무',red:'휴가/개인',green:'촬영/스튜디오',purple:'미팅/내방',holiday:'공휴일'};
const schedColors = {gold:'#c8b08a',teal:'#e8894a',blue:'#8ab4c8',red:'#c87a7a',green:'#7ac87a',purple:'#9b70c8',holiday:'#c87a7a'};
const schedData = @json($scheduleByColor);
new Chart(document.getElementById('chartScheduleColor'), {
    type: 'doughnut',
    data: {
        labels: Object.keys(schedData).map(k=>schedLabels[k]||k),
        datasets: [{ data:Object.values(schedData), backgroundColor:Object.keys(schedData).map(k=>schedColors[k]||'#999') }]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom',labels:{font:{size:10},padding:8}}} }
});
</script>
@endpush
