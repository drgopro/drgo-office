@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '대시보드 - 닥터고블린 오피스')

@push('styles')
<style>
    .dash-wrap { padding:24px; max-width:1200px; margin:0 auto; }
    .dash-header { margin-bottom:24px; }
    .dash-header h1 { font-size:20px; font-weight:700; }
    .dash-header p { font-size:12px; color:var(--text-muted); margin-top:4px; }

    .stat-cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:14px; margin-bottom:24px; }
    .stat-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:18px 20px; cursor:pointer; transition:all 0.15s; }
    .stat-card:hover { border-color:var(--accent); transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,0.08); }
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

    /* 상세 모달 */
    .dash-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9000; align-items:center; justify-content:center; backdrop-filter:blur(3px); }
    .dash-modal.open { display:flex; }
    .dash-modal-body { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:100%; max-width:700px; max-height:80vh; display:flex; flex-direction:column; }
    .dash-modal-header { padding:16px 20px 12px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); flex-shrink:0; }
    .dash-modal-header h3 { font-size:15px; font-weight:600; }
    .dash-modal-content { flex:1; overflow-y:auto; padding:16px 20px; }
    .dash-table { width:100%; border-collapse:collapse; font-size:13px; }
    .dash-table th { text-align:left; padding:8px 10px; font-size:10px; letter-spacing:0.1em; color:var(--text-muted); text-transform:uppercase; border-bottom:2px solid var(--border); position:sticky; top:0; background:var(--surface); }
    .dash-table td { padding:8px 10px; border-bottom:1px solid var(--border); }
    .dash-table tr:hover td { background:var(--surface2); }
    .dash-table a { color:var(--accent); text-decoration:none; }
    .dash-table a:hover { text-decoration:underline; }
    .dash-filter { display:flex; gap:8px; margin-bottom:12px; flex-wrap:wrap; }
    .dash-filter select { background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:6px 10px; color:var(--text); font-size:12px; cursor:pointer; }

    @media (max-width:768px) {
        .stat-cards { grid-template-columns:repeat(2,1fr); }
        .chart-grid { grid-template-columns:1fr; }
        .dash-modal-body { max-width:95vw; }
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
        <div class="stat-card" onclick="openDetail('clients')">
            <div class="stat-label">총 의뢰자</div>
            <div class="stat-value">{{ number_format($clientTotal) }}</div>
            <div class="stat-sub">이번 달 +{{ $clientThisMonth }}명</div>
        </div>
        <div class="stat-card" onclick="openDetail('projects')">
            <div class="stat-label">진행 중 프로젝트</div>
            <div class="stat-value">{{ number_format($projectActive) }}</div>
            <div class="stat-sub">전체 {{ $projectTotal }}건</div>
        </div>
        <div class="stat-card" onclick="openDetail('consultations')">
            <div class="stat-label">이번 달 상담</div>
            <div class="stat-value">{{ number_format($consultThisMonth) }}</div>
            <div class="stat-sub">전체 {{ $consultTotal }}건</div>
        </div>
        <div class="stat-card" onclick="openDetail('schedules')">
            <div class="stat-label">이번 달 일정</div>
            <div class="stat-value">{{ number_format($scheduleThisMonth) }}</div>
        </div>
        <div class="stat-card" onclick="openDetail('estimates')">
            <div class="stat-label">견적 총액 (완료+결제)</div>
            <div class="stat-value" style="font-size:22px;">{{ number_format($estimateTotalAmount) }}원</div>
            <div class="stat-sub">결제 완료 {{ number_format($estimatePaidAmount) }}원</div>
        </div>
        <div class="stat-card" onclick="openDetail('estimates')">
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
<!-- 상세 모달 -->
<div class="dash-modal" id="dashModal" onclick="if(event.target===this)closeDashModal()">
    <div class="dash-modal-body">
        <div class="dash-modal-header">
            <h3 id="dashModalTitle">상세</h3>
            <button onclick="closeDashModal()" style="background:none;border:1px solid var(--border);color:var(--text-muted);width:30px;height:30px;border-radius:8px;cursor:pointer;font-size:14px;">✕</button>
        </div>
        <div class="dash-modal-content" id="dashModalContent">
            <div style="padding:20px;text-align:center;color:var(--text-muted);">로딩 중...</div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
// 상세 모달
const DETAIL_TITLES={clients:'👤 의뢰자 상세',projects:'📁 프로젝트 상세',consultations:'💬 상담 이력 상세',estimates:'📄 견적서 상세',schedules:'📅 이번 달 일정'};
const DETAIL_COLS={
    clients:['이름','닉네임','전화번호','등급','등록일',''],
    projects:['프로젝트명','의뢰자','유형','단계','등록일',''],
    consultations:['의뢰자','유형','결과','내용','담당자','날짜'],
    estimates:['#','의뢰자','상태','총액','작성자','등록일'],
    schedules:['제목','유형','의뢰자','날짜','시간',''],
};

function openDetail(type){
    document.getElementById('dashModalTitle').textContent=DETAIL_TITLES[type]||'상세';
    document.getElementById('dashModalContent').innerHTML='<div style="padding:20px;text-align:center;color:var(--text-muted);">로딩 중...</div>';
    document.getElementById('dashModal').classList.add('open');
    fetch('/api/dashboard/'+type,{headers:{'Accept':'application/json'}}).then(r=>r.json()).then(data=>{
        if(!data.length){document.getElementById('dashModalContent').innerHTML='<div style="padding:30px;text-align:center;color:var(--text-muted);">데이터가 없습니다.</div>';return;}
        const cols=DETAIL_COLS[type]||[];
        let html='<table class="dash-table"><thead><tr>'+cols.map(c=>'<th>'+c+'</th>').join('')+'</tr></thead><tbody>';
        data.forEach(row=>{
            const link=row.url?`onclick="navTo('${row.url}')" style="cursor:pointer;"`:'';
            if(type==='clients') html+=`<tr ${link}><td>${row.name}</td><td>${row.nickname||''}</td><td>${row.phone||''}</td><td>${row.grade}</td><td>${row.created_at}</td><td></td></tr>`;
            else if(type==='projects') html+=`<tr ${link}><td>${row.name}</td><td>${row.client||''}</td><td>${row.type}</td><td>${row.stage}</td><td>${row.created_at}</td><td></td></tr>`;
            else if(type==='consultations') html+=`<tr><td>${row.client||''}</td><td>${row.type}</td><td>${row.result}</td><td>${row.content||''}</td><td>${row.consultant||''}</td><td>${row.date}</td></tr>`;
            else if(type==='estimates') html+=`<tr ${link}><td>#${row.id}</td><td>${row.client||''}</td><td>${row.status}</td><td>${row.total}원</td><td>${row.creator||''}</td><td>${row.created_at}</td></tr>`;
            else if(type==='schedules') html+=`<tr><td>${row.title}</td><td>${row.color}</td><td>${row.client||''}</td><td>${row.date}</td><td>${row.time}</td><td></td></tr>`;
        });
        html+='</tbody></table>';
        document.getElementById('dashModalContent').innerHTML=html;
    }).catch(()=>{document.getElementById('dashModalContent').innerHTML='<div style="padding:20px;text-align:center;color:var(--red);">로드 실패</div>';});
}
function closeDashModal(){document.getElementById('dashModal').classList.remove('open');}
function navTo(url){
    closeDashModal();
    if(window.parent&&window.parent.drgoTabs){
        const type=url.startsWith('/clients')?'clients':url.startsWith('/projects')?'projects':url.startsWith('/estimates')?'estimates':'page';
        window.parent.drgoTabs.openNav(type,url);
    } else { location.href=url; }
}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeDashModal();});

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
