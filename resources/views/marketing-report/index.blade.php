@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', '마케팅 통계 - 닥터고블린 오피스')

@push('styles')
<style>
    .mk-wrap { padding:24px; max-width:1200px; margin:0 auto; }
    .mk-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
    .mk-title { font-size:20px; font-weight:700; }
    .mk-filter { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .mk-filter input, .mk-filter button { background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:6px 10px; color:var(--text); font-size:12px; }
    .mk-filter button { background:var(--accent); color:var(--accent-text); border:none; font-weight:600; cursor:pointer; }
    [data-theme="light"] .mk-filter button { color:#fff; }
    .mk-filter .mk-navbtn { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border); font-weight:700; padding:6px 10px; }
    .mk-filter .mk-navbtn:hover { border-color:var(--accent); color:var(--accent); }
    .mk-preset-select { background:var(--surface2); color:var(--text); border:1px solid var(--border); border-radius:6px; padding:6px 10px; font-size:12px; cursor:pointer; }
    [data-theme="light"] .mk-filter .mk-navbtn { color:var(--text-muted); }

    .mk-section { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:16px; }
    .mk-section-title { font-size:14px; font-weight:700; color:var(--accent); margin-bottom:16px; display:flex; align-items:center; gap:8px; padding-bottom:10px; border-bottom:1px solid var(--border); }
    .mk-clickable { cursor:pointer; transition:all 0.15s; }
    .mk-clickable:hover { border-color:var(--accent); box-shadow:0 3px 10px rgba(24,39,64,.08); }

    .mk-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:12px; }
    .mk-card { background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:14px 16px; }
    .mk-card .mk-label { font-size:10px; color:var(--text-muted); letter-spacing:0.1em; text-transform:uppercase; margin-bottom:6px; }
    .mk-card .mk-value { font-size:22px; font-weight:700; }
    .mk-card .mk-sub { font-size:11px; color:var(--text-muted); margin-top:4px; }

    .mk-two-col { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .mk-chart-wrap { position:relative; width:100%; height:240px; }
    .mk-chart-wrap canvas { position:absolute; inset:0; width:100% !important; height:100% !important; }

    .mk-list { display:flex; flex-direction:column; gap:6px; }
    .mk-list-item { display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:var(--surface2); border-radius:6px; font-size:13px; }
    .mk-list-item .mk-list-label { color:var(--text); }
    .mk-list-item .mk-list-value { font-weight:700; color:var(--accent); }

    .mk-bar { background:var(--surface2); border-radius:4px; overflow:hidden; height:20px; display:flex; align-items:center; padding:0 8px; position:relative; }
    .mk-bar-fill { position:absolute; left:0; top:0; bottom:0; background:rgba(200,176,138,0.3); z-index:0; }
    .mk-bar-label { font-size:11px; color:var(--text); z-index:1; }
    .mk-bar-value { margin-left:auto; font-weight:700; color:var(--accent); font-size:12px; z-index:1; }

    .mk-matrix { width:100%; border-collapse:collapse; font-size:12px; }
    .mk-matrix th, .mk-matrix td { padding:8px 10px; border:1px solid var(--border); text-align:center; }
    .mk-matrix th { background:var(--surface2); color:var(--text-muted); font-weight:600; }
    .mk-matrix td { background:var(--surface); }
    .mk-matrix .cnt-cell { font-weight:700; color:var(--accent); }
    .mk-matrix .cnt-zero { color:var(--text-muted); font-weight:400; }

    @media (max-width:768px) {
        .mk-two-col { grid-template-columns:1fr; }
        .mk-matrix { font-size:11px; }
    }
</style>
@endpush

@section('content')
@php
    $inflowL = ['search'=>'검색','referral'=>'지인 소개','sns'=>'SNS','ad'=>'광고','community'=>'커뮤니티','other'=>'기타'];
    $typeL = ['personal'=>'개인','enterprise'=>'엔터','studio'=>'스튜디오'];
    $gradeL = ['normal'=>'일반','vip'=>'VIP','rental'=>'렌탈'];
    $scaleL = ['personal'=>'개인','studio'=>'스튜디오','corporate'=>'기업','rental'=>'렌탈','broadcast_room'=>'방송룸'];
    $workL = ['setup'=>'세팅','remote'=>'원격','survey'=>'답사','filming'=>'촬영중계','design'=>'디자인','as'=>'A/S','dispatch'=>'파견','monthly'=>'월 계약','hourly'=>'시간 대여'];
    $breakdownL = ['setup'=>'세팅비','product'=>'장비판매','labor'=>'인건비','dispatch'=>'파견비','rush'=>'긴급비','payment_only'=>'단순 결제','other'=>'기타'];
@endphp

<div class="mk-wrap">
    <div class="mk-header">
        <div>
            <div class="mk-title"><x-icon name="chart" :size="18"/> 마케팅 통계</div>
        </div>
        <form method="GET" class="mk-filter" id="mkFilter">
            <button type="button" class="mk-navbtn" onclick="mkMonthNav(-1)" title="이전 달">◀</button>
            <input type="date" name="from" id="mkFrom" value="{{ $from }}">
            <span style="color:var(--text-muted);">~</span>
            <input type="date" name="to" id="mkTo" value="{{ $to }}">
            <button type="button" class="mk-navbtn" onclick="mkMonthNav(1)" title="다음 달">▶</button>
            <button type="submit">조회</button>
            <a href="/api/dashboard-export/excel?from={{ $from }}&to={{ $to }}" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:6px 12px;border-radius:6px;font-size:12px;text-decoration:none;"><x-icon name="download" :size="13"/> 엑셀</a>
            <a href="{{ route('marketing-report.schedules-export') }}?from={{ $from }}&to={{ $to }}" title="기간 내 일정을 날짜별로 나열 (의뢰자 플랫폼·경력·결제 포함, 월별 시트)" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:6px 12px;border-radius:6px;font-size:12px;text-decoration:none;"><x-icon name="download" :size="13"/> 일정 엑셀</a>
            <a href="{{ route('marketing-report.schedules-export-raw') }}?from={{ $from }}&to={{ $to }}" title="마케팅부 추출 사양서 형식 (17컬럼, 1건=1행 RAW) — 현재 입력 데이터 기준 샘플" style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:6px 12px;border-radius:6px;font-size:12px;text-decoration:none;"><x-icon name="download" :size="13"/> 엑셀 샘플 출력</a>
            <select class="mk-preset-select" onchange="mkApplyPreset(this.value); this.selectedIndex=0;">
                <option value="">기간 선택…</option>
                <option value="today">오늘</option>
                <option value="yesterday">어제</option>
                <option value="thisweek">이번 주 (일~오늘)</option>
                <option value="last7">최근 7일</option>
                <option value="lastweek">지난주 (일~토)</option>
                <option value="last14">최근 14일</option>
                <option value="thismonth">이번 달</option>
                <option value="last30">최근 30일</option>
                <option value="lastmonth">지난달</option>
                <option value="all">전체</option>
            </select>
        </form>
    </div>

    {{-- 섹션 1: 마케팅 지표 --}}
    <div class="mk-section">
        <div class="mk-section-title"><x-icon name="chart-bar" :size="15"/> 마케팅 지표</div>
        <div style="font-size:12px; color:var(--text-muted); margin:-8px 0 16px;">📅 조회 기간: {{ $from }} ~ {{ $to }}</div>

        <div class="mk-grid" style="margin-bottom:20px;">
            <div class="mk-card">
                <div class="mk-label">신규 의뢰자</div>
                <div class="mk-value">{{ number_format($newClients) }}</div>
                <div class="mk-sub">기간 내 새로 등록</div>
            </div>
            <div class="mk-card">
                <div class="mk-label">총 상담</div>
                <div class="mk-value">{{ number_format($totalConsults) }}</div>
                <div class="mk-sub">재상담 {{ $reConsultCount }}건</div>
            </div>
            <div class="mk-card">
                <div class="mk-label">유입경로 다양성</div>
                <div class="mk-value">{{ count($clientsByInflow) }}</div>
                <div class="mk-sub">등록된 유입경로 수</div>
            </div>
            <div class="mk-card">
                <div class="mk-label">플랫폼 다양성</div>
                <div class="mk-value">{{ count($platformCounts) }}</div>
                <div class="mk-sub">활동 플랫폼 수</div>
            </div>
        </div>

        <div class="mk-two-col">
            {{-- 유입경로 --}}
            <div>
                <div style="font-size:12px; font-weight:600; margin-bottom:10px; color:var(--text-muted);">유입경로별 분포</div>
                <div class="mk-chart-wrap"><canvas id="chartInflow"></canvas></div>
            </div>
            {{-- 플랫폼 --}}
            <div>
                <div style="font-size:12px; font-weight:600; margin-bottom:10px; color:var(--text-muted);">플랫폼별 분포</div>
                @if(count($platformCounts))
                    <div class="mk-list">
                        @php $max = max($platformCounts); @endphp
                        @foreach($platformCounts as $platform => $cnt)
                            @php $pct = $platformTotal > 0 ? round($cnt / $platformTotal * 100) : 0; @endphp
                            <div class="mk-bar">
                                <div class="mk-bar-fill" style="width:{{ ($cnt / $max) * 100 }}%;"></div>
                                <span class="mk-bar-label">{{ $platform }}</span>
                                <span class="mk-bar-value">{{ $cnt }}명 <span style="color:var(--text-muted); font-weight:400;">({{ $pct }}%)</span></span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div style="padding:30px; text-align:center; color:var(--text-muted); font-size:12px;">데이터 없음</div>
                @endif
            </div>
        </div>

        <div class="mk-two-col" style="margin-top:20px;">
            {{-- 콘텐츠 유형 --}}
            <div>
                <div style="font-size:12px; font-weight:600; margin-bottom:10px; color:var(--text-muted);">콘텐츠 유형별 분포</div>
                @if(count($contentCounts))
                    <div class="mk-list">
                        @php $maxC = max($contentCounts); @endphp
                        @foreach($contentCounts as $content => $cnt)
                            <div class="mk-bar">
                                <div class="mk-bar-fill" style="width:{{ ($cnt / $maxC) * 100 }}%; background:rgba(138,180,200,0.3);"></div>
                                <span class="mk-bar-label">{{ $content }}</span>
                                <span class="mk-bar-value" style="color:#8ab4c8;">{{ $cnt }}명</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div style="padding:30px; text-align:center; color:var(--text-muted); font-size:12px;">데이터 없음</div>
                @endif
            </div>

            {{-- 의뢰자 유형 --}}
            <div>
                <div style="font-size:12px; font-weight:600; margin-bottom:10px; color:var(--text-muted);">의뢰자 유형</div>
                <div class="mk-list">
                    @foreach($typeL as $key => $label)
                        @php $cnt = $clientsByType[$key] ?? 0; $ratio = $newClients > 0 ? round(($cnt / $newClients) * 100) : 0; @endphp
                        <div class="mk-list-item">
                            <span class="mk-list-label">{{ $label }}</span>
                            <span class="mk-list-value">{{ $cnt }}명 ({{ $ratio }}%)</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- 섹션 1.5: 퍼널 분석 --}}
    <div class="mk-section">
        <div class="mk-section-title"><x-icon name="chart" :size="15"/> 퍼널 분석 <span style="font-size:11px; font-weight:400; color:var(--text-muted);">(기간 내 유입된 문의 코호트)</span></div>

        @php
            $stages = [
                ['label'=>'신규 문의', 'value'=>$funnelInquiry, 'color'=>'#c8b08a', 'icon'=>'💬'],
                ['label'=>'견적 발행', 'value'=>$funnelEstimateIssued, 'color'=>'#8ab4c8', 'icon'=>'📝'],
                ['label'=>'결제 완료', 'value'=>$funnelPaid, 'color'=>'#7ac87a', 'icon'=>'💰'],
                ['label'=>'세팅 완료', 'value'=>$funnelSettingDone, 'color'=>'#9b70c8', 'icon'=>'✅'],
            ];
            $maxFunnel = max(array_column($stages, 'value')) ?: 1;
        @endphp

        <div style="display:flex; flex-direction:column; gap:6px;">
            @foreach($stages as $i => $s)
                @php
                    $width = ($s['value'] / $maxFunnel) * 100;
                    $conv = null;
                    if ($i > 0) {
                        $prev = $stages[$i-1]['value'];
                        $conv = $prev > 0 ? round(($s['value'] / $prev) * 100, 1) : 0;
                    }
                @endphp
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="width:100px; font-size:12px; color:var(--text); font-weight:600;">{{ $s['icon'] }} {{ $s['label'] }}</div>
                    <div style="flex:1; background:var(--surface2); border-radius:6px; height:34px; position:relative; overflow:hidden;">
                        <div style="position:absolute; left:0; top:0; bottom:0; width:{{ $width }}%; background:{{ $s['color'] }}; opacity:0.3; transition:width 0.5s;"></div>
                        <div style="position:relative; z-index:1; display:flex; align-items:center; justify-content:space-between; padding:0 14px; height:100%; font-size:13px; font-weight:700;">
                            <span>{{ number_format($s['value']) }}건</span>
                            @if($conv !== null)
                                <span style="font-size:11px; color:{{ $conv >= 50 ? '#7ac87a' : ($conv >= 20 ? '#c8b08a' : '#c87a7a') }};">
                                    ↓ 전환율 {{ $conv }}%
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mk-grid" style="margin-top:20px;">
            <div class="mk-card">
                <div class="mk-label">문의→세팅 전환율</div>
                <div class="mk-value" style="color:var(--accent); font-size:24px;">{{ $funnelConversion['overall'] }}%</div>
                <div class="mk-sub">전체 유입 대비 완료</div>
            </div>
            <div class="mk-card">
                <div class="mk-label">평균 리드타임</div>
                <div class="mk-value" style="font-size:20px;">{{ $avgInquiryToComplete }}일</div>
                <div class="mk-sub">문의 → 세팅 완료까지</div>
            </div>
            <div class="mk-card">
                <div class="mk-label">결제→세팅 평균</div>
                <div class="mk-value" style="font-size:20px;">{{ $avgPaidToComplete }}일</div>
                <div class="mk-sub">결제 후 실제 세팅</div>
            </div>
        </div>
    </div>

    {{-- 섹션 1.6: 파이프라인 (현재 진행 중) --}}
    <div class="mk-section">
        <div class="mk-section-title"><x-icon name="gear" :size="15"/> 현재 파이프라인 <span style="font-size:11px; font-weight:400; color:var(--text-muted);">(기간 무관, 실시간 스냅샷)</span></div>
        <div class="mk-grid">
            <div class="mk-card">
                <div class="mk-label"><x-icon name="chat" :size="14"/> 상담 중</div>
                <div class="mk-value" style="color:#c8b08a;">{{ $pipeline['consulting'] }}</div>
                <div class="mk-sub">초기 상담 단계</div>
            </div>
            <div class="mk-card">
                <div class="mk-label"><x-icon name="note" :size="14"/> 견적 단계</div>
                <div class="mk-value" style="color:#8ab4c8;">{{ $pipeline['estimate'] }}</div>
                <div class="mk-sub">장비파악·제안·견적</div>
            </div>
            <div class="mk-card">
                <div class="mk-label"><x-icon name="money" :size="14"/> 결제 대기</div>
                <div class="mk-value" style="color:#e8894a;">{{ $pipeline['payment'] }}</div>
                <div class="mk-sub">결제/예약 단계</div>
            </div>
            <div class="mk-card">
                <div class="mk-label"><x-icon name="wrench" :size="14"/> 세팅 진행</div>
                <div class="mk-value" style="color:#7ac87a;">{{ $pipeline['visit'] }}</div>
                <div class="mk-sub">세팅·AS 진행 중</div>
            </div>
        </div>
    </div>

    {{-- 섹션 2: 프로젝트 지표 --}}
    <div class="mk-section">
        <div class="mk-section-title"><x-icon name="folder" :size="15"/> 프로젝트 지표</div>

        <div class="mk-grid" style="margin-bottom:20px;">
            <div class="mk-card">
                <div class="mk-label">신규 프로젝트</div>
                <div class="mk-value">{{ number_format($newProjects) }}</div>
            </div>
            <div class="mk-card">
                <div class="mk-label">세팅 완료</div>
                <div class="mk-value" style="color:#7ac87a;">{{ number_format($settingDone) }}</div>
                <div class="mk-sub">전환율 {{ $newProjects > 0 ? round(($settingDone / $newProjects) * 100, 1) : 0 }}%</div>
            </div>
            <div class="mk-card">
                <div class="mk-label">취소</div>
                <div class="mk-value" style="color:#c87a7a;">{{ number_format($cancelled) }}</div>
            </div>
        </div>

        {{-- 규모 × 작업유형 매트릭스 --}}
        <div style="font-size:12px; font-weight:600; margin-bottom:10px; color:var(--text-muted);">규모 × 작업유형 매트릭스</div>
        @php $workTypes = array_keys($workL); @endphp
        <div style="overflow-x:auto;">
            <table class="mk-matrix">
                <thead>
                    <tr>
                        <th style="width:100px;">규모 \\ 작업유형</th>
                        @foreach($workTypes as $wt)
                            <th>{{ $workL[$wt] }}</th>
                        @endforeach
                        <th>합계</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($scaleL as $scale => $scaleName)
                        @php $row = $scaleWorkMatrix[$scale] ?? []; $total = array_sum(is_array($row) ? $row : (array)$row); @endphp
                        @if($total > 0)
                        <tr>
                            <td style="font-weight:600; background:var(--surface2);">{{ $scaleName }}</td>
                            @foreach($workTypes as $wt)
                                @php $cnt = $row[$wt] ?? 0; @endphp
                                <td class="{{ $cnt > 0 ? 'cnt-cell' : 'cnt-zero' }}">{{ $cnt > 0 ? $cnt : '-' }}</td>
                            @endforeach
                            <td class="cnt-cell">{{ $total }}</td>
                        </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        @if(count($cancelReasons) > 0)
        <div style="margin-top:20px;">
            <div style="font-size:12px; font-weight:600; margin-bottom:10px; color:var(--text-muted);">취소 사유</div>
            <div class="mk-list">
                @foreach($cancelReasons as $reason => $cnt)
                    <div class="mk-list-item">
                        <span class="mk-list-label">{{ $reason }}</span>
                        <span class="mk-list-value" style="color:#c87a7a;">{{ $cnt }}건</span>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- 섹션 3: 매출 지표 --}}
    <div class="mk-section">
        <div class="mk-section-title"><x-icon name="money" :size="15"/> 매출 지표</div>

        <div class="mk-grid" style="margin-bottom:20px;">
            <div class="mk-card mk-clickable" onclick="goRevenuePage()" title="결제가 발생한 프로젝트 보기">
                <div class="mk-label">총 매출 <span style="font-size:10px; color:var(--accent);">상세 ↗</span></div>
                <div class="mk-value" style="color:var(--accent); font-size:20px;">{{ number_format($revenueTotal) }}원</div>
            </div>
            <div class="mk-card">
                <div class="mk-label">세팅비</div>
                <div class="mk-value" style="font-size:18px;">{{ number_format($revenueService) }}원</div>
            </div>
            <div class="mk-card">
                <div class="mk-label">장비판매</div>
                <div class="mk-value" style="font-size:18px;">{{ number_format($revenueProduct) }}원</div>
            </div>
        </div>

        @if(array_sum($revenueBreakdown) > 0)
        <div style="font-size:12px; font-weight:600; margin-bottom:10px; color:var(--text-muted);">세부 카테고리 매출 분해</div>
        <div class="mk-list" style="margin-bottom:20px;">
            @foreach($breakdownL as $key => $label)
                @php $val = $revenueBreakdown[$key] ?? 0; @endphp
                @if($val > 0)
                <div class="mk-list-item">
                    <span class="mk-list-label">{{ $label }}</span>
                    <span class="mk-list-value">{{ number_format($val) }}원</span>
                </div>
                @endif
            @endforeach
        </div>
        @endif

        {{-- 어떤 일(프로젝트 유형·작업 유형)에서 결제가 이루어졌는지 세분화 --}}
        @if(count($revenueByProjectType) || count($revenueByWorkType))
        <div class="mk-two-col">
            @if(count($revenueByProjectType))
            <div>
                <div style="font-size:12px; font-weight:600; margin-bottom:10px; color:var(--text-muted);">프로젝트 유형별 매출 <span style="font-weight:400;">· 하위 항목은 작업 유형</span></div>
                <div class="mk-list">
                    @foreach($revenueByProjectType as $label => $data)
                    <div class="mk-list-item">
                        <span class="mk-list-label" style="font-weight:700;">{{ $label }}</span>
                        <span class="mk-list-value">{{ number_format($data['total']) }}원 <span style="color:var(--text-muted); font-weight:400; font-size:11px;">({{ $revenueTotal > 0 ? round($data['total'] / $revenueTotal * 100) : 0 }}%)</span></span>
                    </div>
                    {{-- 유형 안에서 어떤 작업이 매출을 만들었는지 — 작업 유형이 미지정 하나뿐이면 생략 --}}
                    @if(count($data['works']) > 1 || !isset($data['works']['미지정']))
                        @foreach($data['works'] as $wLabel => $wVal)
                        <div class="mk-list-item" style="padding-left:24px; background:transparent;">
                            <span class="mk-list-label" style="color:var(--text-muted); font-size:12px;">└ {{ $wLabel }}</span>
                            <span class="mk-list-value" style="font-size:12px; color:var(--text-muted);">{{ number_format($wVal) }}원 <span style="font-weight:400; font-size:11px;">({{ $data['total'] > 0 ? round($wVal / $data['total'] * 100) : 0 }}%)</span></span>
                        </div>
                        @endforeach
                    @endif
                    @endforeach
                </div>
            </div>
            @endif
            @if(count($revenueByWorkType))
            <div>
                <div style="font-size:12px; font-weight:600; margin-bottom:10px; color:var(--text-muted);">작업 유형별 매출</div>
                <div class="mk-list">
                    @foreach($revenueByWorkType as $label => $val)
                    <div class="mk-list-item">
                        <span class="mk-list-label">{{ $label }}</span>
                        <span class="mk-list-value">{{ number_format($val) }}원 <span style="color:var(--text-muted); font-weight:400; font-size:11px;">({{ $revenueTotal > 0 ? round($val / $revenueTotal * 100) : 0 }}%)</span></span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endif
    </div>

    {{-- 섹션 4: 렌탈/방송룸 현황 --}}
    <div class="mk-two-col">
        <div class="mk-section">
            <div class="mk-section-title"><x-icon name="home" :size="15"/> 렌탈 현황</div>
            <div class="mk-list">
                <div class="mk-list-item">
                    <span class="mk-list-label">진행중 계약</span>
                    <span class="mk-list-value">{{ $rentalActive }}건</span>
                </div>
                <div class="mk-list-item">
                    <span class="mk-list-label">월 매출</span>
                    <span class="mk-list-value">{{ number_format($rentalMonthlyRevenue) }}원</span>
                </div>
                <div class="mk-list-item">
                    <span class="mk-list-label">기간 내 신규 계약</span>
                    <span class="mk-list-value">{{ $rentalNewInPeriod }}건</span>
                </div>
            </div>
        </div>

        <div class="mk-section">
            <div class="mk-section-title"><x-icon name="mic" :size="15"/> 방송룸 현황</div>
            <div class="mk-list">
                <div class="mk-list-item">
                    <span class="mk-list-label">진행중 월 계약</span>
                    <span class="mk-list-value">{{ $broadcastActive }}건</span>
                </div>
                <div class="mk-list-item">
                    <span class="mk-list-label">월 계약 매출</span>
                    <span class="mk-list-value">{{ number_format($broadcastMonthlyRevenue) }}원</span>
                </div>
                <div class="mk-list-item">
                    <span class="mk-list-label">기간 내 시간 대여</span>
                    <span class="mk-list-value">{{ $broadcastUsagesInPeriod }}회</span>
                </div>
                <div class="mk-list-item">
                    <span class="mk-list-label">시간 대여 매출</span>
                    <span class="mk-list-value">{{ number_format($broadcastUsageRevenue) }}원</span>
                </div>
            </div>
        </div>
    </div>

    {{-- 섹션 5: 월별 추이 --}}
    <div class="mk-section">
        <div class="mk-section-title"><x-icon name="chart" :size="15"/> 월별 추이 (최근 6개월)</div>
        <div class="mk-chart-wrap" style="height:280px;"><canvas id="chartMonthlyTrend"></canvas></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
const textColor = isDark ? '#a09890' : '#5a6070';
const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
Chart.defaults.color = textColor;
Chart.defaults.borderColor = gridColor;

// 유입경로 파이차트
@php
    $inflowLabelMap = ['search'=>'검색','referral'=>'지인 소개','sns'=>'SNS','ad'=>'광고','community'=>'커뮤니티','other'=>'기타'];
    $inflowLabelArr = [];
    foreach ($clientsByInflow as $k => $v) {
        $key = $k === null || $k === '' ? '미지정' : ($inflowLabelMap[$k] ?? $k);
        $inflowLabelArr[] = $key;
    }
@endphp
const inflowLabels = @json($inflowLabelArr);
const inflowData = @json(array_values($clientsByInflow->toArray()));
if (inflowData.length) {
    new Chart(document.getElementById('chartInflow'), {
        type: 'doughnut',
        data: {
            labels: inflowLabels,
            datasets: [{ data: inflowData, backgroundColor: ['#c8b08a','#8ab4c8','#7ac87a','#9b70c8','#e8894a','#c87a7a','#999'] }]
        },
        options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom', labels:{font:{size:11}, padding:8}}} }
    });
} else {
    document.getElementById('chartInflow').parentElement.innerHTML = '<div style="padding:40px; text-align:center; color:var(--text-muted); font-size:12px;">데이터 없음</div>';
}

// 월별 추이 라인차트
new Chart(document.getElementById('chartMonthlyTrend'), {
    type: 'line',
    data: {
        labels: @json(array_column($monthlyTrend, 'label')),
        datasets: [
            { label:'의뢰자', data:@json(array_column($monthlyTrend, 'clients')), borderColor:'#c8b08a', backgroundColor:'rgba(200,176,138,0.1)', fill:true, tension:0.3 },
            { label:'프로젝트', data:@json(array_column($monthlyTrend, 'projects')), borderColor:'#8ab4c8', backgroundColor:'rgba(138,180,200,0.1)', fill:true, tension:0.3 },
            { label:'상담', data:@json(array_column($monthlyTrend, 'consults')), borderColor:'#7ac87a', backgroundColor:'rgba(122,200,122,0.1)', fill:true, tension:0.3 },
            { label:'매출(원)', data:@json(array_column($monthlyTrend, 'revenue')), borderColor:'#9b70c8', backgroundColor:'transparent', tension:0.3, yAxisID:'y1', borderDash:[5,3] },
        ]
    },
    options: {
        responsive:true, maintainAspectRatio:false,
        plugins:{legend:{position:'top', labels:{font:{size:11}}}},
        scales:{
            y:{beginAtZero:true, title:{display:true, text:'건수'}},
            y1:{position:'right', beginAtZero:true, grid:{drawOnChartArea:false}, title:{display:true, text:'매출(원)'}, ticks:{callback:v=>v>=10000?(v/10000)+'만':v.toLocaleString()}}
        }
    }
});

// ── 총 매출 상세 페이지로 이동 (현재 기간 유지) ──
function goRevenuePage(){
    const from=document.getElementById('mkFrom').value, to=document.getElementById('mkTo').value;
    location.href=`/marketing-report/revenue?from=${from}&to=${to}`;
}

// ── 기간 프리셋 / 월 이동 ──
function mkPad(n){ return String(n).padStart(2,'0'); }
function mkYmd(d){ return d.getFullYear()+'-'+mkPad(d.getMonth()+1)+'-'+mkPad(d.getDate()); }
function mkSubmit(from, to){
    document.getElementById('mkFrom').value = from;
    document.getElementById('mkTo').value = to;
    document.getElementById('mkFilter').submit();
}
// 현재 from 기준 이전/다음 달로 이동 (해당 월 1일~말일)
function mkMonthNav(dir){
    const f = document.getElementById('mkFrom').value;
    const base = f ? new Date(f + 'T00:00:00') : new Date();
    const first = new Date(base.getFullYear(), base.getMonth() + dir, 1);
    const last = new Date(base.getFullYear(), base.getMonth() + dir + 1, 0);
    mkSubmit(mkYmd(first), mkYmd(last));
}
// 프리셋 드롭다운
function mkApplyPreset(key){
    if(!key) return;
    const now = new Date();
    const startOfDay = d => new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const addDays = (d,n) => { const x=new Date(d); x.setDate(x.getDate()+n); return x; };
    const today = startOfDay(now);
    const dow = today.getDay(); // 0=일
    const thisWeekSun = addDays(today, -dow);
    let from, to;
    switch(key){
        case 'today':     from=today; to=today; break;
        case 'yesterday': from=addDays(today,-1); to=addDays(today,-1); break;
        case 'thisweek':  from=thisWeekSun; to=today; break;
        case 'last7':     from=addDays(today,-6); to=today; break;
        case 'lastweek':  from=addDays(thisWeekSun,-7); to=addDays(thisWeekSun,-1); break;
        case 'last14':    from=addDays(today,-13); to=today; break;
        case 'thismonth': from=new Date(now.getFullYear(), now.getMonth(), 1); to=today; break;
        case 'last30':    from=addDays(today,-29); to=today; break;
        case 'lastmonth': from=new Date(now.getFullYear(), now.getMonth()-1, 1); to=new Date(now.getFullYear(), now.getMonth(), 0); break;
        case 'all':       from=new Date(2000,0,1); to=today; break;
        default: return;
    }
    mkSubmit(mkYmd(from), mkYmd(to));
}
</script>
@endpush
