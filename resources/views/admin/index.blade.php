@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

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
    .btn-save:hover { opacity:0.85; }
    .save-msg { font-size:12px; color:var(--green); margin-left:10px; display:none; }

    /* 셀렉트, 인라인 폼 */
    .inline-select { background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:5px 8px; color:var(--text); font-size:12px; outline:none; }
    .inline-select:focus { border-color:var(--accent); }
    .btn-sm { background:var(--accent); color:#1a1207; border:none; padding:5px 12px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; }
    .btn-sm:hover { opacity:0.85; }
    .btn-danger { background:var(--red); color:#fff; border:none; padding:5px 12px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; }
    .btn-danger:hover { opacity:0.85; }
    .btn-add { background:none; border:1px solid var(--accent); color:var(--accent); padding:7px 14px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; margin-bottom:12px; }
    .btn-add:hover { background:var(--accent); color:#1a1207; }

    /* 팀 카드 */
    .team-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:12px; }
    .team-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
    .team-name-input { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:15px; font-weight:700; outline:none; max-width:220px; }
    .team-name-input:focus { border-color:var(--accent); }
    .team-count { font-size:11px; color:var(--text-muted); margin-left:8px; }

    /* 권한 토글 */
    .perm-section { margin-bottom:14px; }
    .perm-section:last-child { margin-bottom:0; }
    .perm-section-title { font-size:11px; font-weight:600; color:var(--text-muted); margin-bottom:8px; letter-spacing:0.05em; }
    .perm-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(170px, 1fr)); gap:10px; }
    .perm-toggle { display:flex; align-items:center; gap:8px; cursor:pointer; user-select:none; }
    .perm-toggle input[type="checkbox"] { display:none; }
    .perm-switch { position:relative; width:34px; height:18px; background:var(--border); border-radius:9px; transition:background 0.2s; flex-shrink:0; }
    .perm-switch::after { content:''; position:absolute; top:2px; left:2px; width:14px; height:14px; background:var(--text-muted); border-radius:50%; transition:all 0.2s; }
    .perm-toggle input:checked + .perm-switch { background:var(--accent); }
    .perm-toggle input:checked + .perm-switch::after { left:18px; background:#fff; }
    .perm-label { font-size:12px; color:var(--text-muted); transition:color 0.15s; }
    .perm-toggle input:checked ~ .perm-label { color:var(--text); font-weight:600; }

    /* 활성 토글 */
    .toggle-active { cursor:pointer; font-size:12px; }
    .toggle-active.on { color:var(--green); }
    .toggle-active.off { color:var(--red); }
    [data-theme="light"] .tab-btn.active { color:#fff; }
    [data-theme="light"] .pagination-wrap nav span[aria-current] { color:#fff; }
    [data-theme="light"] .btn-save { color:#fff; }
    [data-theme="light"] .btn-sm { color:#fff; }
    [data-theme="light"] .btn-add:hover { color:#fff; }
</style>
@endpush

@section('content')
<div class="page-wrap">
    <div class="page-title">관리</div>

    <div class="tab-bar" id="adminTabBar">
        <button class="tab-btn active" data-tab="logs">로그인 기록</button>
        <button class="tab-btn" data-tab="users">사용자 관리</button>
        <button class="tab-btn" data-tab="teams">팀 관리</button>
        <button class="tab-btn" data-tab="seller">판매처 설정</button>
    </div>

    {{-- 로그인 기록 --}}
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

    {{-- 사용자 관리 --}}
    <div class="tab-panel" id="panel-users">
        <button class="btn-add" onclick="toggleNewUserForm()">+ 사용자 추가</button>
        <div id="newUserForm" style="display:none; margin-bottom:16px;" class="settings-form">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="field-group">
                    <div class="field-label">아이디 (로그인용)</div>
                    <input class="field-input" id="newUsername" placeholder="username">
                </div>
                <div class="field-group">
                    <div class="field-label">표시 이름</div>
                    <input class="field-input" id="newDisplayName" placeholder="홍길동">
                </div>
                <div class="field-group">
                    <div class="field-label">비밀번호</div>
                    <input class="field-input" id="newPassword" type="password" placeholder="8자 이상">
                </div>
                <div class="field-group">
                    <div class="field-label">역할</div>
                    <select class="field-input" id="newRole" onchange="document.getElementById('newTeamId').disabled=this.value!=='member'">
                        <option value="member">member</option>
                        <option value="admin">admin</option>
                        <option value="guest">guest</option>
                    </select>
                </div>
                <div class="field-group">
                    <div class="field-label">팀</div>
                    <select class="field-input" id="newTeamId"><option value="">없음</option></select>
                </div>
            </div>
            <div style="display:flex; gap:8px; margin-top:12px;">
                <button class="btn-save" onclick="createUser()">생성</button>
                <button class="btn-danger" onclick="document.getElementById('newUserForm').style.display='none'">취소</button>
            </div>
        </div>
        <div class="data-card">
            <table class="data-table">
                <thead>
                    <tr><th>이름</th><th>아이디</th><th>역할</th><th>팀</th><th>활성</th><th></th></tr>
                </thead>
                <tbody id="usersBody">
                    <tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted);">로딩 중...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- 팀 관리 --}}
    <div class="tab-panel" id="panel-teams">
        <button class="btn-add" onclick="showNewTeamForm()">+ 팀 추가</button>
        <div id="newTeamForm" style="display:none;" class="team-card">
            <div class="team-header">
                <input class="team-name-input" id="newTeamName" placeholder="팀 이름">
                <div style="display:flex; gap:6px;">
                    <button class="btn-sm" onclick="createTeam()">저장</button>
                    <button class="btn-danger" onclick="document.getElementById('newTeamForm').style.display='none'">취소</button>
                </div>
            </div>
            <div id="newTeamPerms"></div>
        </div>
        <div id="teamsContainer"></div>
    </div>

    {{-- 판매처 설정 --}}
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
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const currentRole = @json(Auth::user()->role);
const PERM_GROUPS = [
    { title: '캘린더', perms: [{ key: 'calendar.view', label: '조회' }, { key: 'calendar.edit', label: '편집' }] },
    { title: '의뢰자', perms: [{ key: 'clients.view', label: '조회' }, { key: 'clients.edit', label: '편집' }] },
    { title: '프로젝트', perms: [{ key: 'projects.view', label: '조회' }, { key: 'projects.edit', label: '편집' }] },
    { title: '재고', perms: [{ key: 'inventory.view', label: '조회' }, { key: 'inventory.edit', label: '편집' }] },
    { title: '견적서', perms: [{ key: 'estimates.view', label: '조회' }, { key: 'estimates.edit', label: '편집' }] },
    { title: '문서', perms: [{ key: 'documents.edit', label: '편집' }] },
];
const ALL_PERMS = PERM_GROUPS.flatMap(g => g.perms);

function renderPermToggles(containerId, activePerms = []) {
    return PERM_GROUPS.map(g => `
        <div class="perm-section">
            <div class="perm-section-title">${g.title}</div>
            <div class="perm-grid">
                ${g.perms.map(p => {
                    const checked = activePerms.includes(p.key) ? 'checked' : '';
                    return `<label class="perm-toggle">
                        <input type="checkbox" value="${p.key}" ${checked}>
                        <span class="perm-switch"></span>
                        <span class="perm-label">${p.label}</span>
                    </label>`;
                }).join('')}
            </div>
        </div>
    `).join('');
}

let teamsList = [];

// ── 탭 전환 ──
document.querySelectorAll('#adminTabBar .tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        document.querySelectorAll('#adminTabBar .tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('active', p.id === 'panel-' + tab));
        if (tab === 'users') loadUsers();
        if (tab === 'teams') loadTeams();
    });
});

// ── 사용자 관리 ──
async function loadUsers() {
    const [usersRes, teamsRes] = await Promise.all([
        fetch('/api/admin/users', { headers: { 'Accept': 'application/json' } }),
        fetch('/api/admin/teams', { headers: { 'Accept': 'application/json' } }),
    ]);
    const users = await usersRes.json();
    teamsList = await teamsRes.json();

    const tbody = document.getElementById('usersBody');
    if (!users.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted);">사용자가 없습니다.</td></tr>';
        return;
    }

    tbody.innerHTML = users.map(u => {
        const roles = ['master', 'admin', 'member', 'guest'];
        const roleOpts = roles.map(r => {
            const disabled = (r === 'master' && currentRole !== 'master') ? 'disabled' : '';
            return `<option value="${r}" ${u.role === r ? 'selected' : ''} ${disabled}>${r}</option>`;
        }).join('');

        const teamOpts = ['<option value="">없음</option>'].concat(
            teamsList.map(t => `<option value="${t.id}" ${u.team_id === t.id ? 'selected' : ''}>${t.name}</option>`)
        ).join('');

        const activeClass = u.is_active ? 'on' : 'off';
        const activeText = u.is_active ? '활성' : '비활성';

        return `<tr data-uid="${u.id}">
            <td>${u.display_name}</td>
            <td class="text-muted">${u.username}</td>
            <td><select class="inline-select ur" onchange="toggleTeamSelect(this)">${roleOpts}</select></td>
            <td><select class="inline-select ut" ${u.role !== 'member' ? 'disabled' : ''}>${teamOpts}</select></td>
            <td><span class="toggle-active ${activeClass}" onclick="toggleActive(this)">${activeText}</span></td>
            <td><button class="btn-sm" onclick="saveUser(${u.id}, this)">저장</button></td>
        </tr>`;
    }).join('');
}

function toggleTeamSelect(sel) {
    const row = sel.closest('tr');
    const teamSel = row.querySelector('.ut');
    teamSel.disabled = sel.value !== 'member';
    if (sel.value !== 'member') teamSel.value = '';
}

function toggleActive(span) {
    const isOn = span.classList.contains('on');
    span.classList.toggle('on', !isOn);
    span.classList.toggle('off', isOn);
    span.textContent = isOn ? '비활성' : '활성';
}

function toggleNewUserForm() {
    const form = document.getElementById('newUserForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    // 팀 목록 채우기
    const sel = document.getElementById('newTeamId');
    sel.innerHTML = '<option value="">없음</option>' + teamsList.map(t => `<option value="${t.id}">${t.name}</option>`).join('');
}

async function createUser() {
    const username = document.getElementById('newUsername').value.trim();
    const display_name = document.getElementById('newDisplayName').value.trim();
    const password = document.getElementById('newPassword').value;
    const role = document.getElementById('newRole').value;
    const team_id = document.getElementById('newTeamId').value || null;

    if (!username || !display_name || !password) return alert('아이디, 이름, 비밀번호를 입력하세요.');
    if (password.length < 8) return alert('비밀번호는 8자 이상이어야 합니다.');

    const res = await fetch('/api/admin/users', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ username, display_name, password, role, team_id }),
    });

    if (res.ok) {
        document.getElementById('newUserForm').style.display = 'none';
        document.getElementById('newUsername').value = '';
        document.getElementById('newDisplayName').value = '';
        document.getElementById('newPassword').value = '';
        loadUsers();
    } else {
        const err = await res.json();
        alert(err.message || Object.values(err.errors || {}).flat().join('\n') || '생성 실패');
    }
}

async function saveUser(id, btn) {
    const row = btn.closest('tr');
    const role = row.querySelector('.ur').value;
    const team_id = row.querySelector('.ut').value || null;
    const is_active = row.querySelector('.toggle-active').classList.contains('on');

    const res = await fetch(`/api/admin/users/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ role, team_id, is_active }),
    });

    if (res.ok) {
        btn.textContent = '완료';
        setTimeout(() => btn.textContent = '저장', 1500);
    } else {
        const err = await res.json();
        alert(err.message || '저장 실패');
    }
}

// ── 팀 관리 ──
async function loadTeams() {
    const res = await fetch('/api/admin/teams', { headers: { 'Accept': 'application/json' } });
    teamsList = await res.json();
    renderTeams();
}

function renderTeams() {
    const container = document.getElementById('teamsContainer');
    if (!teamsList.length) {
        container.innerHTML = '<div style="text-align:center; padding:40px; color:var(--text-muted);">등록된 팀이 없습니다.</div>';
        return;
    }

    container.innerHTML = teamsList.map(t => `<div class="team-card" data-tid="${t.id}">
        <div class="team-header">
            <div style="display:flex; align-items:center;">
                <input class="team-name-input tn" value="${t.name}">
                <span class="team-count">${t.users_count || 0}명</span>
            </div>
            <div style="display:flex; gap:6px;">
                <button class="btn-sm" onclick="saveTeam(${t.id}, this)">저장</button>
                <button class="btn-danger" onclick="deleteTeam(${t.id})">삭제</button>
            </div>
        </div>
        ${renderPermToggles('', t.permissions || [])}
    </div>`).join('');
}

function showNewTeamForm() {
    const form = document.getElementById('newTeamForm');
    form.style.display = 'block';
    document.getElementById('newTeamName').value = '';
    document.getElementById('newTeamPerms').innerHTML = renderPermToggles('newTeamPerms', []);
}

async function createTeam() {
    const name = document.getElementById('newTeamName').value.trim();
    if (!name) return alert('팀 이름을 입력하세요.');

    const perms = [...document.querySelectorAll('#newTeamPerms input:checked')].map(c => c.value);

    const res = await fetch('/api/admin/teams', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ name, permissions: perms }),
    });

    if (res.ok) {
        document.getElementById('newTeamForm').style.display = 'none';
        loadTeams();
    } else {
        const err = await res.json();
        alert(err.message || '생성 실패');
    }
}

async function saveTeam(id, btn) {
    const card = btn.closest('.team-card');
    const name = card.querySelector('.tn').value.trim();
    const perms = [...card.querySelectorAll('input[type="checkbox"]:checked')].map(c => c.value);

    const res = await fetch(`/api/admin/teams/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ name, permissions: perms }),
    });

    if (res.ok) {
        btn.textContent = '완료';
        setTimeout(() => btn.textContent = '저장', 1500);
    } else {
        alert('저장 실패');
    }
}

async function deleteTeam(id) {
    if (!confirm('이 팀을 삭제하시겠습니까? 소속 사용자의 팀이 해제됩니다.')) return;

    await fetch(`/api/admin/teams/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    });
    loadTeams();
}

// ── 판매처 설정 ──
async function saveSellerSettings() {
    const body = {
        seller_name: document.getElementById('sellerName').value,
        seller_biz_no: document.getElementById('sellerBizNo').value,
        seller_address: document.getElementById('sellerAddress').value,
        seller_biz_type: document.getElementById('sellerBizType').value,
        seller_biz_item: document.getElementById('sellerBizItem').value,
        seller_phone: document.getElementById('sellerPhone').value,
    };
    await fetch('/api/settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(body)
    });
    const msg = document.getElementById('sellerSaveMsg');
    msg.style.display = 'inline';
    setTimeout(() => msg.style.display = 'none', 2000);
}
</script>
@endpush
