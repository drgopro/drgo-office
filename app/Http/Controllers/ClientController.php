<?php

namespace App\Http\Controllers;

use App\Models\ChannelTalkUser;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientMemo;
use App\Models\Project;
use App\Models\ProjectFieldDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    // 목록
    public function index(Request $request)
    {
        $query = Client::with('assignedUser')
            ->where('status', '!=', 'blacklist');

        // 검색
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // 등급 필터
        if ($grade = $request->query('grade')) {
            $query->where('grade', $grade);
        }

        $clients = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('clients.index', compact('clients'));
    }

    // 상세
    public function show(Client $client)
    {
        $client->load('assignedUser', 'projects', 'documents', 'estimates.creator');

        return view('clients.show', compact('client'));
    }

    // 등록 폼
    public function create()
    {
        return view('clients.form', ['client' => null]);
    }

    // 저장
    /**
     * 전화번호 기준 중복 의뢰자 탐색 — 하이픈/공백 차이를 무시하고 숫자만 비교.
     * 등록 화면의 중복 경고 팝업('추가등록'/'등록 취소')용.
     */
    private function findDuplicateByPhone(?string $phone, ?int $excludeId = null): ?Client
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) < 7) {
            return null; // 짧은 번호(내선 등)는 오탐이 많아 검사 제외
        }

        // 끝 4자리 LIKE로 후보를 좁힌 뒤 숫자만 비교 (한국 번호 포맷은 끝 4자리가 항상 붙어 있음)
        return Client::whereNotNull('phone')
            ->where('phone', 'like', '%'.substr($digits, -4).'%')
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('id')
            ->get(['id', 'name', 'nickname', 'phone'])
            ->first(fn ($c) => preg_replace('/\D+/', '', (string) $c->phone) === $digits);
    }

    /**
     * 채널톡 고객 검색 (로컬 미러) — 의뢰자 등록의 '채널톡 연동' 버튼용.
     * 이름/전화(숫자만 비교)/이메일 부분 일치, 최근 갱신순 10건.
     */
    public function channeltalkUsers(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }
        $digits = preg_replace('/\D+/', '', $q);

        $rows = ChannelTalkUser::query()
            ->where(function ($query) use ($q, $digits) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
                if (strlen($digits) >= 4) {
                    $query->orWhere('mobile_digits', 'like', "%{$digits}%");
                }
            })
            ->orderByDesc('ct_updated_at')
            ->limit(10)
            ->get(['ct_id', 'name', 'mobile', 'mobile_digits', 'email', 'tags', 'profile']);

        // 이미 연동된 의뢰자 표시 — 중복 등록 여부 판단 도움
        $linked = Client::whereIn('channeltalk_user_id', $rows->pluck('ct_id'))
            ->get(['id', 'nickname', 'name', 'channeltalk_user_id'])->keyBy('channeltalk_user_id');

        return response()->json($rows->map(fn ($u) => [
            'ct_id' => $u->ct_id,
            'name' => $u->name,
            'mobile' => $u->mobile,
            'mobile_digits' => $u->mobile_digits,
            'email' => $u->email,
            'tags' => $u->tags ?? [],
            ...$this->mapChanneltalkProfile($u->profile ?? []),
            'linked_client' => $linked->has($u->ct_id) ? [
                'id' => $linked[$u->ct_id]->id,
                'label' => $linked[$u->ct_id]->nickname ?: $linked[$u->ct_id]->name,
            ] : null,
        ])->values());
    }

    /**
     * 채널톡 커스텀 프로필 키 → 의뢰자 필드 매핑.
     * profile.name=닉네임 / truename=이름 / platform=방송 플랫폼(list) / content=방송주제(list)
     * / history=경력 / note=중요메모 / chname=방송국 주소 / address=주소.
     * 오피스 선택지에 없는 플랫폼·주제는 '기타' + 직접입력 텍스트로 넘긴다.
     *
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function mapChanneltalkProfile(array $profile): array
    {
        $toList = fn ($v) => collect(is_array($v) ? $v : (trim((string) $v) !== '' ? preg_split('/[,\/]+/', (string) $v) : []))
            ->map(fn ($x) => trim((string) $x))->filter()->values();

        // 방송 플랫폼 — 오피스 명칭으로 정규화 (아프리카→SOOP 등), 미지원은 기타 텍스트
        $platMap = ['SOOP' => 'SOOP', 'soop' => 'SOOP', '아프리카' => 'SOOP', '아프리카TV' => 'SOOP',
            '유튜브' => '유튜브', 'YouTube' => '유튜브', '치지직' => '치지직', '틱톡' => '틱톡', '팬더티비' => '팬더티비', '팬더' => '팬더티비'];
        $platforms = [];
        $platformEtc = [];
        foreach ($toList($profile['platform'] ?? null) as $p) {
            if (isset($platMap[$p])) {
                $platforms[] = $platMap[$p];
            } elseif ($p !== '기타') {
                $platformEtc[] = $p;
            }
        }
        if ($platformEtc) {
            $platforms[] = '기타';
        }

        // 방송주제 — 오피스 선택지 외는 기타 텍스트
        $knownTopics = ['소통', '게임', '노래', '먹방', '야외', '버추얼', '코인', '주식', '미정'];
        $topics = [];
        $topicEtc = [];
        foreach ($toList($profile['content'] ?? null) as $t) {
            if (in_array($t, $knownTopics, true)) {
                $topics[] = $t;
            } elseif ($t !== '기타') {
                $topicEtc[] = $t;
            }
        }
        if ($topicEtc) {
            $topics[] = '기타';
        }

        // 경력 — 오피스 선택지(처음/초보/경력)로 근사 매핑
        $history = trim((string) ($profile['history'] ?? ''));
        $career = str_contains($history, '경력') ? '경력'
            : (str_contains($history, '초보') ? '초보'
            : (str_contains($history, '처음') || str_contains($history, '신규') ? '처음' : ''));

        return [
            'truename' => trim((string) ($profile['truename'] ?? '')),
            'platforms' => array_values(array_unique($platforms)),
            'platform_etc' => implode(', ', $platformEtc),
            'content_types' => array_values(array_unique($topics)),
            'topic_etc' => implode(', ', $topicEtc),
            'career' => $career,
            'career_raw' => $history,
            'broadcast_id' => trim((string) ($profile['chname'] ?? '')),
            'address' => trim((string) ($profile['address'] ?? '')),
            'important_memo' => trim((string) ($profile['note'] ?? '')),
        ];
    }

    /** 등록 폼의 사전 중복 확인 — 같은 번호의 기존 의뢰자 정보 반환 */
    public function checkPhone(Request $request)
    {
        $dup = $this->findDuplicateByPhone((string) $request->query('phone', ''),
            $request->query('exclude_id') ? (int) $request->query('exclude_id') : null);

        return response()->json($dup ? [
            'duplicate' => true,
            'existing' => ['id' => $dup->id, 'name' => $dup->name, 'nickname' => $dup->nickname, 'phone' => $dup->phone],
        ] : ['duplicate' => false]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'nickname' => 'required|string|max:100',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:300',
            'address_detail' => 'nullable|string|max:200',
            'extra_addresses' => 'nullable|array|max:3', // 주소 2~4 (주소 1은 address가 메인)
            'extra_addresses.*.address' => 'nullable|string|max:300',
            'extra_addresses.*.address_detail' => 'nullable|string|max:200',
            'grade' => 'required|in:normal,vip,rental',
            'platforms' => 'nullable|array',
            'platform_etc' => 'nullable|string|max:100',
            'content_types' => 'nullable|array',
            'topic_etc' => 'nullable|string|max:100',
            'broadcast_id' => 'nullable|string|max:100',
            'career' => 'nullable|string|in:처음,초보,경력',
            'inflow_source' => 'nullable|string|in:search,referral,sns,ad,community,other',
            'client_type' => 'nullable|string|in:personal,enterprise,studio',
            'custom_data' => 'nullable|array',
            'gender' => 'nullable|in:male,female,other',
            'affiliation' => 'nullable|string|max:200',
            'important_memo' => 'nullable|string',
            'memo' => 'nullable|string',
            'personality' => 'nullable|string|max:500',
            'budget_style' => 'nullable|string|max:500',
            'force_duplicate' => 'nullable|boolean', // 중복 경고 팝업에서 '추가등록' 선택
        ]);
        unset($validated['force_duplicate']);

        // 동일 전화번호 중복 방지 — 프런트 팝업이 1차 방어, 서버는 우회 제출 대비 폴백
        if (! $request->boolean('force_duplicate')
            && ($dup = $this->findDuplicateByPhone($validated['phone'] ?? null))) {
            $label = ($dup->nickname ?: $dup->name).($dup->nickname && $dup->name ? " ({$dup->name})" : '');

            return back()->withInput()->withErrors([
                'phone' => "동일한 전화번호로 등록된 의뢰자가 있습니다: {$label} · {$dup->phone}",
            ]);
        }

        $validated['assigned_user_id'] = Auth::id();
        $validated['status'] = 'active';

        $client = Client::create($validated);

        return redirect()->route('clients.show', $client)->with('success', '의뢰자가 등록되었습니다.');
    }

    // 수정 폼
    public function edit(Client $client)
    {
        return view('clients.form', compact('client'));
    }

    // 업데이트
    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'nickname' => 'required|string|max:100',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:300',
            'address_detail' => 'nullable|string|max:200',
            'extra_addresses' => 'nullable|array|max:3', // 주소 2~4 (주소 1은 address가 메인)
            'extra_addresses.*.address' => 'nullable|string|max:300',
            'extra_addresses.*.address_detail' => 'nullable|string|max:200',
            'grade' => 'required|in:normal,vip,rental',
            'platforms' => 'nullable|array',
            'platform_etc' => 'nullable|string|max:100',
            'content_types' => 'nullable|array',
            'topic_etc' => 'nullable|string|max:100',
            'broadcast_id' => 'nullable|string|max:100',
            'career' => 'nullable|string|in:처음,초보,경력',
            'inflow_source' => 'nullable|string|in:search,referral,sns,ad,community,other',
            'client_type' => 'nullable|string|in:personal,enterprise,studio',
            'custom_data' => 'nullable|array',
            'gender' => 'nullable|in:male,female,other',
            'affiliation' => 'nullable|string|max:200',
            'important_memo' => 'nullable|string',
            'memo' => 'nullable|string',
            'personality' => 'nullable|string|max:500',
            'budget_style' => 'nullable|string|max:500',
        ]);

        $client->update($validated);

        return redirect()->route('clients.show', $client)->with('success', '수정되었습니다.');
    }

    // JSON 상세 API (탭 내 로드)
    /** 연락처·주소 열람 권한 (팀 관리 > 의뢰자 > 연락처·주소 조회, admin 이상 항상 허용) */
    private function canViewPii(): bool
    {
        return Auth::user()?->hasPermission('clients.pii') ?? false;
    }

    public function detail(Client $client)
    {
        $client->load('assignedUser', 'projects.consultations', 'projects.documents', 'documents', 'memos.user', 'estimates.creator', 'contacts');
        $pii = $this->canViewPii();

        // 장비 정보 연동 — 프로젝트별 장비를 각각 계산해 제공.
        // (한 의뢰자가 여러 장소에 세팅하는 경우 캘린더는 일정에 연결된 프로젝트의 장비를 쓴다)
        $sortedProjects = $client->projects->sortByDesc('created_at');
        $eqFields = $sortedProjects->isNotEmpty()
            ? ProjectFieldDefinition::where('section', 'equipment')
                ->where('is_active', true)
                ->orderByDesc('priority')
                ->orderBy('subsection')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['key', 'label', 'type', 'subsection', 'priority', 'options'])
            : collect();

        $equipmentOf = function (Project $project) use ($eqFields, $sortedProjects): ?array {
            $custom = $project->custom_data ?? [];
            // 프로젝트 전용 장비 항목 (custom_data.__equip_items) — 전역 정의 뒤에 이어서
            $localDefs = collect($custom['__equip_items'] ?? [])
                ->filter(fn ($i) => is_array($i) && ! empty($i['key']) && ! empty($i['label']))
                ->map(fn ($i) => (object) [
                    'key' => $i['key'],
                    'label' => $i['label'],
                    'type' => $i['type'] ?? 'text',
                    'subsection' => $i['subsection'] ?? null,
                    'priority' => 0,
                    'options' => $i['options'] ?? null,
                ]);
            $values = [];
            foreach ($eqFields->concat($localDefs) as $f) {
                $v = $custom[$f->key] ?? null;
                // false = 토글 '없음' — 미입력과 동일하게 표시하지 않음 ({value:false, qty:…} 수량형 포함)
                if ($v === null || $v === '' || $v === false || (is_array($v) && empty($v))
                    || (is_array($v) && array_key_exists('value', $v) && in_array($v['value'], [null, '', false], true))) {
                    continue;
                }
                $values[] = [
                    'key' => $f->key,
                    'label' => $f->label,
                    'type' => $f->type,
                    'subsection' => $f->subsection,
                    'priority' => $f->priority,
                    'value' => $v,
                ];
            }
            if (empty($values)) {
                return null;
            }

            return [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'created_at' => $project->created_at->format('Y.m.d'),
                // 최신 프로젝트가 아닌 과거 프로젝트에서 가져온 경우 표시용
                'is_latest' => $project->is($sortedProjects->first()),
                'fields' => $values,
            ];
        };

        // 폴백 표시용 — 최신 프로젝트 우선, 비어 있으면 장비 정보가 적힌 가장 최근 프로젝트
        $projectEquipments = $sortedProjects->mapWithKeys(fn (Project $p) => [$p->id => $equipmentOf($p)]);
        $lastProjectEquipment = $projectEquipments->filter()->first();

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'nickname' => $client->nickname,
            'phone' => $pii ? $client->phone : null,
            'address' => $pii ? $client->address : null,
            'address_detail' => $pii ? $client->address_detail : null,
            'extra_addresses' => $pii ? ($client->extra_addresses ?? []) : [],
            'can_view_pii' => $pii,
            'grade' => $client->grade,
            'platforms' => $client->platforms ?? [],
            'platform_etc' => $client->platform_etc,
            'content_types' => $client->content_types ?? [],
            'topic_etc' => $client->topic_etc,
            'broadcast_id' => $client->broadcast_id,
            'career' => $client->career,
            'inflow_source' => $client->inflow_source,
            'client_type' => $client->client_type,
            'custom_data' => $client->custom_data ?? new \stdClass,
            'last_project_equipment' => $lastProjectEquipment,
            'gender' => $client->gender,
            'affiliation' => $client->affiliation,
            'important_memo' => $client->important_memo,
            'memo' => $client->memo,
            'personality' => $client->personality,
            'budget_style' => $client->budget_style,
            'status' => $client->status,
            'assigned_user' => $client->assignedUser?->display_name,
            'created_at' => $client->created_at->format('Y.m.d'),
            'projects' => $client->projects->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->project_type,
                'stage' => $p->stage,
                'stage_label' => $p->stageLabel(),
                'tags' => $p->tags ?? ['major' => [], 'minor' => []],
                'address' => $pii ? $p->address : null, // 세팅 장소 — 캘린더 장소 연동용
                'address_detail' => $pii ? $p->address_detail : null,
                'created_at' => $p->created_at->format('Y.m.d'),
                'consultations_count' => $p->consultations->count(),
                'equipment' => $projectEquipments[$p->id] ?? null, // 프로젝트별 장비 — 캘린더가 연결 프로젝트 기준으로 표시
                // 프로젝트 첨부 문서 — 캘린더가 연결 프로젝트의 파일을 읽기 전용으로 표시
                'documents' => $p->documents->sortByDesc('created_at')->values()->map(fn ($d) => [
                    'id' => $d->id,
                    'file_name' => $d->file_name,
                    'mime_type' => $d->mime_type,
                    'category' => $d->category(), // 방 사진/레퍼런스 등 — 캘린더 분류 표시용
                    'note' => $d->noteBody(),
                    'view_url' => route('project-documents.serve', $d),
                    'thumb_url' => str_starts_with((string) $d->mime_type, 'image/') ? route('project-documents.thumb', $d) : null,
                    'download_url' => route('project-documents.download', $d),
                    'created_at' => $d->created_at->format('Y.m.d'),
                ]),
            ]),
            'documents' => $client->documents->map(fn ($d) => [
                'id' => $d->id,
                'file_name' => $d->file_name,
                'mime_type' => $d->mime_type,
                'file_size' => $d->file_size,
                'note' => $d->note,
                'view_url' => route('documents.serve', $d),
                'thumb_url' => str_starts_with((string) $d->mime_type, 'image/') ? route('documents.thumb', $d).'?v=2' : null,
                'download_url' => route('documents.download', $d),
                'created_at' => $d->created_at->format('Y.m.d H:i:s'),
            ]),
            'contacts' => $client->contacts->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $pii ? $c->phone : null,
                'relation' => $c->relation,
                'memo' => $c->memo,
            ]),
            'memos' => $client->memos->map(fn ($m) => [
                'id' => $m->id,
                'content' => $m->content,
                'user_name' => $m->user?->display_name ?? '알 수 없음',
                'created_at' => $m->created_at->format('Y.m.d H:i'),
            ]),
            'estimates' => $client->estimates->map(fn ($e) => [
                'id' => $e->id,
                'no' => $e->display_no,
                'status' => $e->status,
                'total_amount' => $e->total_amount,
                'created_at' => $e->created_at->format('Y.m.d'),
                'creator_name' => $e->creator?->display_name ?? $e->creator?->name,
                'print_url' => route('estimates.print', $e),
                'edit_url' => route('estimates.edit', $e),
            ]),
        ]);
    }

    // JSON 업데이트 API
    public function updateJson(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'nickname' => 'required|string|max:100',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:300',
            'address_detail' => 'nullable|string|max:200',
            'extra_addresses' => 'nullable|array|max:3', // 주소 2~4 (주소 1은 address가 메인)
            'extra_addresses.*.address' => 'nullable|string|max:300',
            'extra_addresses.*.address_detail' => 'nullable|string|max:200',
            'grade' => 'required|in:normal,vip,rental',
            'platforms' => 'nullable|array',
            'platform_etc' => 'nullable|string|max:100',
            'content_types' => 'nullable|array',
            'topic_etc' => 'nullable|string|max:100',
            'broadcast_id' => 'nullable|string|max:100',
            'career' => 'nullable|string|in:처음,초보,경력',
            'inflow_source' => 'nullable|string|in:search,referral,sns,ad,community,other',
            'client_type' => 'nullable|string|in:personal,enterprise,studio',
            'custom_data' => 'nullable|array',
            'gender' => 'nullable|in:male,female,other',
            'affiliation' => 'nullable|string|max:200',
            'important_memo' => 'nullable|string',
            'memo' => 'nullable|string',
            'personality' => 'nullable|string|max:500',
            'budget_style' => 'nullable|string|max:500',
        ]);
        // 추가 주소 정리 — 주소가 빈 행 제거, 최대 3개, 없으면 null
        if (array_key_exists('extra_addresses', $validated)) {
            $validated['extra_addresses'] = collect($validated['extra_addresses'] ?? [])
                ->map(fn ($a) => ['address' => trim((string) ($a['address'] ?? '')), 'address_detail' => trim((string) ($a['address_detail'] ?? ''))])
                ->filter(fn ($a) => $a['address'] !== '')
                ->slice(0, 3)->values()->all() ?: null;
        }

        // 연락처·주소 열람 권한이 없으면 화면에 빈 값으로 보이므로,
        // 저장 시 기존 값이 빈 값으로 덮어써지지 않게 해당 필드는 제외
        if (! $this->canViewPii()) {
            unset($validated['phone'], $validated['address'], $validated['address_detail'], $validated['extra_addresses']);
        }

        $client->update($validated);

        return response()->json(['message' => '저장되었습니다.']);
    }

    // JSON 생성 API
    public function storeJson(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'nickname' => 'required|string|max:100',
            'phone' => 'nullable|string|max:30',
            'grade' => 'required|in:normal,vip,rental',
            'inflow_source' => 'nullable|string|in:search,referral,sns,ad,community,other',
            'client_type' => 'nullable|string|in:personal,enterprise,studio',
            'platforms' => 'nullable|array',
            'platform_etc' => 'nullable|string|max:100',
            'content_types' => 'nullable|array',
            'topic_etc' => 'nullable|string|max:100',
            'broadcast_id' => 'nullable|string|max:100',
            'career' => 'nullable|string|in:처음,초보,경력',
            'personality' => 'nullable|string|max:500',
            'budget_style' => 'nullable|string|max:500',
            // 등록 폼 리디자인에서 추가된 기본 정보 필드
            'gender' => 'nullable|in:male,female,other',
            'affiliation' => 'nullable|string|max:200',
            'address' => 'nullable|string|max:300',
            'address_detail' => 'nullable|string|max:200',
            'extra_addresses' => 'nullable|array|max:3', // 주소 2~4 (주소 1은 address가 메인)
            'extra_addresses.*.address' => 'nullable|string|max:300',
            'extra_addresses.*.address_detail' => 'nullable|string|max:200',
            'important_memo' => 'nullable|string',
            'memo' => 'nullable|string',
            'channeltalk_user_id' => 'nullable|string|max:60', // 채널톡 연동 버튼으로 불러온 고객 ID
            'force_duplicate' => 'nullable|boolean', // 중복 경고 팝업에서 '추가등록' 선택
        ]);
        unset($validated['force_duplicate']);

        // 동일 전화번호 중복 방지 — 409로 기존 의뢰자 정보를 돌려주면 프런트가 팝업 표시
        if (! $request->boolean('force_duplicate')
            && ($dup = $this->findDuplicateByPhone($validated['phone'] ?? null))) {
            return response()->json([
                'duplicate' => true,
                'message' => '동일한 전화번호로 등록된 의뢰자가 있습니다.',
                'existing' => ['id' => $dup->id, 'name' => $dup->name, 'nickname' => $dup->nickname, 'phone' => $dup->phone],
            ], 409);
        }
        // 추가 주소 정리 — 주소가 빈 행 제거, 최대 3개, 없으면 null
        if (array_key_exists('extra_addresses', $validated)) {
            $validated['extra_addresses'] = collect($validated['extra_addresses'] ?? [])
                ->map(fn ($a) => ['address' => trim((string) ($a['address'] ?? '')), 'address_detail' => trim((string) ($a['address_detail'] ?? ''))])
                ->filter(fn ($a) => $a['address'] !== '')
                ->slice(0, 3)->values()->all() ?: null;
        }

        $validated['assigned_user_id'] = Auth::id();
        $validated['status'] = 'active';

        $client = Client::create($validated);

        return response()->json(['id' => $client->id, 'message' => '등록되었습니다.'], 201);
    }

    // JSON 목록 API (서버사이드 페이지네이션)
    public function listJson(Request $request)
    {
        $query = Client::where('status', '!=', 'blacklist');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    // 관계자(매니저 등) 이름/연락처로도 의뢰자 검색
                    ->orWhereHas('contacts', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($grade = $request->query('grade')) {
            $query->where('grade', $grade);
        }

        // 플랫폼 필터 — platforms JSON 배열에 해당 플랫폼이 포함된 의뢰자만
        if ($platform = $request->query('platform')) {
            $query->whereJsonContains('platforms', $platform);
        }

        $perPage = (int) ($request->query('per_page', 20));
        $perPage = max(1, min($perPage, 100));

        $paginated = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['id', 'name', 'nickname', 'phone', 'grade', 'status', 'platforms']);

        $pii = $this->canViewPii();

        return response()->json([
            'data' => collect($paginated->items())->map(function ($c) use ($pii) {
                $arr = $c->toArray();
                if (! $pii) {
                    $arr['phone'] = null;
                }

                return $arr;
            })->all(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
        ]);
    }

    // 관계자(매니저 등) 추가 — 의뢰자당 최대 10명
    public function storeContact(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:30',
            'relation' => 'nullable|string|max:50',
            'memo' => 'nullable|string|max:500',
        ]);

        if ($client->contacts()->count() >= ClientContact::MAX_PER_CLIENT) {
            return response()->json(['message' => '관계자는 의뢰자당 최대 '.ClientContact::MAX_PER_CLIENT.'명까지 등록할 수 있습니다.'], 422);
        }

        $contact = $client->contacts()->create($validated);

        return response()->json($contact, 201);
    }

    public function updateContact(Request $request, ClientContact $contact)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:30',
            'relation' => 'nullable|string|max:50',
            'memo' => 'nullable|string|max:500',
        ]);

        // 연락처 열람 권한이 없으면 빈 값으로 기존 연락처를 지우지 않게 제외
        if (! $this->canViewPii()) {
            unset($validated['phone']);
        }

        $contact->update($validated);

        return response()->json($contact);
    }

    public function destroyContact(ClientContact $contact)
    {
        $contact->delete();

        return response()->json(['message' => '삭제되었습니다.']);
    }

    // 메모 추가
    public function storeMemo(Request $request, Client $client)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $memo = $client->memos()->create([
            'user_id' => Auth::id(),
            'content' => $validated['content'],
        ]);

        $memo->load('user');

        return response()->json([
            'id' => $memo->id,
            'content' => $memo->content,
            'user_name' => $memo->user?->display_name,
            'created_at' => $memo->created_at->format('Y.m.d H:i'),
        ], 201);
    }

    // 메모 삭제
    public function destroyMemo(ClientMemo $memo)
    {
        $memo->delete();

        return response()->json(['message' => '삭제되었습니다.']);
    }

    // 검색 API (견적서 등에서 사용)
    public function search(Request $request)
    {
        $q = $request->query('q', '');
        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $clients = Client::where('status', '!=', 'blacklist')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('nickname', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhereHas('contacts', function ($cq) use ($q) {
                        $cq->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%");
                    });
            })
            ->with(['contacts' => function ($cq) use ($q) {
                $cq->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%");
            }])
            ->limit(10)
            ->get(['id', 'name', 'nickname', 'phone']);

        // 관계자로 매칭된 경우 어떤 관계자로 걸렸는지 병기 (예: 김실장 (실장))
        $pii = $this->canViewPii();

        return response()->json($clients->map(function ($c) use ($pii) {
            $matched = $c->contacts->first();
            $arr = $c->only(['id', 'name', 'nickname', 'phone']);
            if (! $pii) {
                $arr['phone'] = null;
            }
            $arr['matched_contact'] = $matched
                ? trim($matched->name.($matched->relation ? " ({$matched->relation})" : ''))
                : null;
            unset($c->contacts);

            return $arr;
        }));
    }

    // 삭제
    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()->route('clients.index')->with('success', '삭제되었습니다.');
    }
}
