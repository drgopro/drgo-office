@extends(config('view.tab_mode') ? 'layouts.tab-content' : 'layouts.app')

@section('title', ($client ? '의뢰자 수정' : '의뢰자 등록') . ' - 닥터고블린 오피스')

@push('styles')
<style>
    .page-wrap { padding:24px; max-width:720px; }
    .page-header { display:flex; align-items:center; gap:12px; margin-bottom:24px; }
    .page-title { font-size:18px; font-weight:700; }
    .back-btn { color:var(--text-muted); text-decoration:none; font-size:13px; }
    .back-btn:hover { color:var(--text); }
    .form-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:24px; margin-bottom:16px; }
    .form-section-title { font-size:13px; font-weight:600; color:var(--accent); margin-bottom:16px; letter-spacing:0.05em; }
    .field-group { margin-bottom:16px; }
    .field-label { font-size:11px; color:var(--text-muted); margin-bottom:6px; letter-spacing:0.05em; }
    .field-input { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:10px 14px; color:var(--text); font-size:13px; outline:none; }
    .field-input:focus { border-color:var(--accent); }
    .field-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .field-select { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:10px 14px; color:var(--text); font-size:13px; outline:none; cursor:pointer; }
    .field-textarea { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:10px 14px; color:var(--text); font-size:13px; outline:none; resize:vertical; }
    .field-textarea:focus { border-color:var(--accent); }

    /* 체크박스 그룹 */
    .checkbox-group { display:flex; flex-wrap:wrap; gap:8px; }
    .checkbox-item { display:flex; align-items:center; gap:5px; }
    .checkbox-item input { accent-color:var(--accent); width:14px; height:14px; cursor:pointer; }
    .checkbox-item label { font-size:13px; cursor:pointer; }

    /* 라디오 그룹 */
    .radio-group { display:flex; gap:12px; flex-wrap:wrap; }
    .radio-item { display:flex; align-items:center; gap:5px; }
    .radio-item input { accent-color:var(--accent); width:14px; height:14px; cursor:pointer; }
    .radio-item label { font-size:13px; cursor:pointer; }

    .error-msg { font-size:11px; color:var(--red); margin-top:4px; }
    .form-actions { display:flex; gap:10px; justify-content:flex-end; }
    .btn-primary { background:var(--accent); color:var(--accent-text); border:none; padding:10px 24px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
    .btn-cancel { background:none; border:1px solid var(--border); color:var(--text-muted); padding:10px 24px; border-radius:8px; font-size:13px; cursor:pointer; text-decoration:none; display:inline-block; }
    .btn-danger { background:none; border:1px solid var(--red); color:var(--red); padding:10px 24px; border-radius:8px; font-size:13px; cursor:pointer; }
    [data-theme="light"] .btn-primary { color:#fff; }
</style>
@endpush

@section('content')
<div class="page-wrap">
    <div class="page-header">
        <a href="{{ route('clients.index') }}" class="back-btn">← 목록</a>
        <div class="page-title">{{ $client ? '의뢰자 수정' : '의뢰자 등록' }}</div>
    </div>

    <form method="POST" action="{{ $client ? route('clients.update', $client) : route('clients.store') }}" id="clientForm">
        @csrf
        @if($client) @method('PUT') @endif
        <input type="hidden" name="force_duplicate" id="forceDuplicate" value="0">{{-- 중복 팝업 '추가등록' 시 1 --}}

        <!-- 기본 정보 -->
        <div class="form-card">
            <div class="form-section-title">기본 정보</div>

            <div class="field-row">
                <div class="field-group">
                    <div class="field-label">닉네임 (스트리머명) *</div>
                    <input class="field-input" type="text" name="nickname" value="{{ old('nickname', $client?->nickname) }}" required>
                    @error('nickname')<div class="error-msg">{{ $message }}</div>@enderror
                </div>
                <div class="field-group">
                    <div class="field-label">이름</div>
                    <input class="field-input" type="text" name="name" value="{{ old('name', $client?->name) }}">
                    @error('name')<div class="error-msg">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="field-row">
                <div class="field-group">
                    <div class="field-label">연락처</div>
                    <input class="field-input" type="text" name="phone" value="{{ old('phone', $client?->phone) }}" placeholder="010-0000-0000">
                    @error('phone')<div class="error-msg">{{ $message }}</div>@enderror
                </div>
                <div class="field-group">
                    <div class="field-label">소속</div>
                    <input class="field-input" type="text" name="affiliation" value="{{ old('affiliation', $client?->affiliation) }}" placeholder="#엔터테인 8기">
                </div>
            </div>

            <div class="field-group">
                <div class="field-label">주소</div>
                <div style="display:flex; gap:8px;">
                    <input class="field-input" type="text" name="address" id="address" value="{{ old('address', $client?->address) }}" placeholder="우편번호 검색 버튼을 눌러주세요" readonly style="flex:1; cursor:pointer;" onclick="searchAddress()">
                    <button type="button" onclick="searchAddress()" style="background:var(--accent); color:var(--accent-text); border:none; padding:0 16px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap;">주소 검색</button>
                </div>
            </div>
            <div class="field-group">
                <div class="field-label">상세주소</div>
                <input class="field-input" type="text" name="address_detail" id="address_detail" value="{{ old('address_detail', $client?->address_detail) }}" placeholder="동/호수 직접 입력">
            </div>
        </div>

        <!-- 분류 -->
        <div class="form-card">
            <div class="form-section-title">분류 정보</div>

            <div class="field-row">
                <div class="field-group">
                    <div class="field-label">등급 *</div>
                    <select class="field-select" name="grade">
                        <option value="normal" {{ old('grade', $client?->grade) === 'normal' ? 'selected' : '' }}>일반</option>
                        <option value="vip" {{ old('grade', $client?->grade) === 'vip' ? 'selected' : '' }}>VIP</option>
                        <option value="rental" {{ old('grade', $client?->grade) === 'rental' ? 'selected' : '' }}>렌탈</option>
                    </select>
                </div>
                <div class="field-group">
                    <div class="field-label">성별</div>
                    <div class="radio-group" style="margin-top:8px;">
                        <div class="radio-item">
                            <input type="radio" name="gender" id="g_female" value="female" {{ old('gender', $client?->gender) === 'female' ? 'checked' : '' }}>
                            <label for="g_female">여성</label>
                        </div>
                        <div class="radio-item">
                            <input type="radio" name="gender" id="g_male" value="male" {{ old('gender', $client?->gender) === 'male' ? 'checked' : '' }}>
                            <label for="g_male">남성</label>
                        </div>
                        <div class="radio-item">
                            <input type="radio" name="gender" id="g_other" value="other" {{ old('gender', $client?->gender) === 'other' ? 'checked' : '' }}>
                            <label for="g_other">기타</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="field-group">
                <div class="field-label">플랫폼</div>
                <div class="checkbox-group">
                    @foreach(['유튜브','트위치','치지직','아프리카','팬더티비','기타'] as $platform)
                    <div class="checkbox-item">
                        <input type="checkbox" name="platforms[]" id="p_{{ $loop->index }}" value="{{ $platform }}"
                            {{ in_array($platform, old('platforms', $client?->platforms ?? [])) ? 'checked' : '' }}
                            @if($platform === '기타') onchange="toggleFormEtc(this, 'platformEtcWrap')" @endif>
                        <label for="p_{{ $loop->index }}">{{ $platform }}</label>
                    </div>
                    @endforeach
                </div>
                @php $platformEtcOn = in_array('기타', old('platforms', $client?->platforms ?? [])); @endphp
                <div id="platformEtcWrap" style="margin-top:8px; display:{{ $platformEtcOn ? 'block' : 'none' }};">
                    <input class="field-input" type="text" name="platform_etc" value="{{ old('platform_etc', $client?->platform_etc) }}" placeholder="기타 플랫폼 직접 입력">
                </div>
            </div>

            <div class="field-group">
                <div class="field-label">콘텐츠 유형</div>
                <div class="checkbox-group">
                    @foreach(['게임','소통','노래','먹방','스포츠','기타'] as $type)
                    <div class="checkbox-item">
                        <input type="checkbox" name="content_types[]" id="ct_{{ $loop->index }}" value="{{ $type }}"
                            {{ in_array($type, old('content_types', $client?->content_types ?? [])) ? 'checked' : '' }}
                            @if($type === '기타') onchange="toggleFormEtc(this, 'topicEtcWrap')" @endif>
                        <label for="ct_{{ $loop->index }}">{{ $type }}</label>
                    </div>
                    @endforeach
                </div>
                @php $topicEtcOn = in_array('기타', old('content_types', $client?->content_types ?? [])); @endphp
                <div id="topicEtcWrap" style="margin-top:8px; display:{{ $topicEtcOn ? 'block' : 'none' }};">
                    <input class="field-input" type="text" name="topic_etc" value="{{ old('topic_etc', $client?->topic_etc) }}" placeholder="기타 방송 주제 직접 입력">
                </div>
            </div>
        </div>

        <!-- 메모 -->
        <div class="form-card">
            <div class="form-section-title">메모</div>
            <div class="field-group">
                <div class="field-label">중요 메모</div>
                <textarea class="field-textarea" name="important_memo" rows="2" placeholder="중요한 사항을 입력하세요">{{ old('important_memo', $client?->important_memo) }}</textarea>
            </div>
            <div class="field-group">
                <div class="field-label">일반 메모</div>
                <textarea class="field-textarea" name="memo" rows="3" placeholder="기타 메모">{{ old('memo', $client?->memo) }}</textarea>
            </div>
        </div>

        <div class="form-actions">
            @if($client)
                <button type="button" class="btn-danger" onclick="if(confirm('삭제할까요?')) document.getElementById('deleteForm').submit()">삭제</button>
            @endif
            <a href="{{ route('clients.index') }}" class="btn-cancel">취소</a>
            <button type="submit" class="btn-primary">{{ $client ? '수정' : '등록' }}</button>
        </div>
    </form>

    @if($client)
    <form id="deleteForm" method="POST" action="{{ route('clients.destroy', $client) }}" style="display:none;">
        @csrf @method('DELETE')
    </form>
    @endif
</div>
@endsection

@push('scripts')
<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
<script>
function searchAddress() {
    new daum.Postcode({
        oncomplete: function(data) {
            let addr = data.userSelectedType === 'R' ? data.roadAddress : data.jibunAddress; // 선택 유형 반영
            document.getElementById('address').value = addr;
            document.getElementById('address_detail').focus();
        }
    }).open();
}
// '기타' 체크 시 수기입력 칸 토글 (플랫폼/콘텐츠 유형 공용) — 해제하면 값도 비움
function toggleFormEtc(cb, wrapId) {
    const wrap = document.getElementById(wrapId);
    if (!wrap) return;
    wrap.style.display = cb.checked ? 'block' : 'none';
    if (!cb.checked) { const inp = wrap.querySelector('input'); if (inp) inp.value = ''; }
}

// ── 전화번호 중복 방지 — 신규 등록 제출 전 확인, 중복이면 '추가등록'/'등록 취소' 팝업 ──
const isEditingClient = @json((bool) $client);
function fdEsc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function showDupClientDialog(existing, onConfirm) {
    document.getElementById('dupClientDialog')?.remove();
    const label = fdEsc(existing.nickname || existing.name || '이름 없음')
        + (existing.nickname && existing.name && existing.nickname !== existing.name ? ` (${fdEsc(existing.name)})` : '');
    const ov = document.createElement('div');
    ov.id = 'dupClientDialog';
    ov.style.cssText = 'position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:10050; display:flex; align-items:center; justify-content:center; padding:20px;';
    ov.innerHTML = `
        <div style="background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:22px 24px; max-width:380px; width:100%; box-shadow:0 12px 40px rgba(0,0,0,0.25);">
            <div style="font-size:15px; font-weight:800; margin-bottom:10px;">이미 등록된 의뢰자입니다</div>
            <div style="font-size:13px; line-height:1.7; background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:10px 14px; margin-bottom:12px;">
                <div style="font-weight:700;">${label}</div>
                <div style="color:var(--text-muted);">${fdEsc(existing.phone || '')}</div>
            </div>
            <div style="font-size:12.5px; color:var(--text-muted); margin-bottom:16px;">같은 전화번호의 의뢰자가 이미 있습니다. 그래도 새 의뢰자로 추가 등록하시겠습니까?</div>
            <div style="display:flex; gap:8px; justify-content:flex-end;">
                <button type="button" id="dupCancelBtn" style="background:none; border:1px solid var(--border); color:var(--text); padding:8px 16px; border-radius:8px; font-size:13px; cursor:pointer;">등록 취소</button>
                <button type="button" id="dupConfirmBtn" style="background:var(--accent); border:none; color:var(--accent-text, #fff); padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer;">추가등록</button>
            </div>
        </div>`;
    document.body.appendChild(ov);
    ov.querySelector('#dupCancelBtn').onclick = () => ov.remove();
    ov.querySelector('#dupConfirmBtn').onclick = () => { ov.remove(); onConfirm(); };
    ov.onclick = e => { if (e.target === ov) ov.remove(); };
}
document.getElementById('clientForm').addEventListener('submit', async function (e) {
    if (isEditingClient) return; // 수정은 검사 대상 아님
    if (document.getElementById('forceDuplicate').value === '1') return; // '추가등록' 확정 제출
    const phone = (this.querySelector('[name="phone"]')?.value || '').trim();
    if (phone.replace(/\D/g, '').length < 7) return; // 번호 없으면 그대로 등록
    e.preventDefault();
    try {
        const res = await fetch(`/api/clients/check-phone?phone=${encodeURIComponent(phone)}`, { headers: { 'Accept': 'application/json' } });
        const d = res.ok ? await res.json() : { duplicate: false };
        if (d.duplicate && d.existing) {
            showDupClientDialog(d.existing, () => {
                document.getElementById('forceDuplicate').value = '1';
                this.submit();
            });
            return;
        }
    } catch (err) { /* 확인 실패 시 서버 폴백 검증에 맡기고 그대로 제출 */ }
    this.submit();
});
</script>
@endpush
