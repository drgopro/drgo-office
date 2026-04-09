<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            $model->logActivity('create', [], $model->getActivitySummary('create'));
        });

        static::updated(function ($model) {
            $changes = [];
            $jsonFields = ['gold_data', 'teal_data', 'special_opts', 'sched_event_opts', 'product_items', 'service_items', 'platforms', 'content_types', 'phones', 'items'];

            foreach ($model->getDirty() as $key => $newVal) {
                if (in_array($key, ['updated_at', 'created_at'])) {
                    continue;
                }
                $oldVal = $model->getOriginal($key);
                if (json_encode($oldVal) === json_encode($newVal)) {
                    continue;
                }

                // JSON 필드는 내부 diff로 분해
                if (in_array($key, $jsonFields)) {
                    $oldArr = is_array($oldVal) ? $oldVal : (is_string($oldVal) ? json_decode($oldVal, true) : []);
                    $newArr = is_array($newVal) ? $newVal : (is_string($newVal) ? json_decode($newVal, true) : []);
                    $oldArr = $oldArr ?: [];
                    $newArr = $newArr ?: [];
                    $parentLabel = self::fieldLabel($key);

                    // 단순 배열 (["유튜브","인스타"] 등) → 쉼표로 합쳐서 표시
                    $isSimpleArray = ! empty($oldArr) ? array_is_list($oldArr) : (! empty($newArr) ? array_is_list($newArr) : false);
                    if ($isSimpleArray) {
                        $changes[$parentLabel] = [
                            'old' => ! empty($oldArr) ? implode(', ', $oldArr) : '—',
                            'new' => ! empty($newArr) ? implode(', ', $newArr) : '—',
                        ];
                    } else {
                        // 연관 배열 (gold_data 등) → 키별 diff
                        $allKeys = array_unique(array_merge(array_keys($oldArr), array_keys($newArr)));
                        $hasInnerChange = false;
                        foreach ($allKeys as $subKey) {
                            if (is_int($subKey)) {
                                continue;
                            }
                            $ov = $oldArr[$subKey] ?? null;
                            $nv = $newArr[$subKey] ?? null;
                            if (json_encode($ov) !== json_encode($nv)) {
                                $subLabel = self::fieldLabel($subKey);
                                $changes[$parentLabel.' > '.$subLabel] = [
                                    'old' => self::formatValue($subKey, $ov),
                                    'new' => self::formatValue($subKey, $nv),
                                ];
                                $hasInnerChange = true;
                            }
                        }
                        if (! $hasInnerChange) {
                            $changes[$parentLabel] = ['old' => '(변경됨)', 'new' => '(변경됨)'];
                        }
                    }
                } else {
                    $label = self::fieldLabel($key);
                    $changes[$label] = ['old' => self::formatValue($key, $oldVal), 'new' => self::formatValue($key, $newVal)];
                }
            }
            if (! empty($changes)) {
                $model->logActivity('update', $changes, $model->getActivitySummary('update'));
            }
        });

        static::deleted(function ($model) {
            $model->logActivity('delete', [], $model->getActivitySummary('delete'));
        });
    }

    public function activityLogs()
    {
        return $this->morphMany(ActivityLog::class, 'loggable')->orderByDesc('created_at');
    }

    public function logActivity(string $action, array $changes = [], ?string $summary = null): void
    {
        ActivityLog::create([
            'loggable_type' => get_class($this),
            'loggable_id' => $this->getKey(),
            'user_id' => Auth::id(),
            'action' => $action,
            'changes' => $changes ?: null,
            'summary' => $summary,
        ]);
    }

    protected function getActivitySummary(string $action): string
    {
        $label = $this->getActivityLabel();
        $actions = ['create' => '생성', 'update' => '수정', 'delete' => '삭제'];

        return $label.' '.($actions[$action] ?? $action);
    }

    protected function getActivityLabel(): string
    {
        $map = [
            'App\\Models\\Client' => '의뢰자',
            'App\\Models\\Project' => '프로젝트',
            'App\\Models\\Estimate' => '견적서',
            'App\\Models\\Schedule' => '일정',
            'App\\Models\\Consultation' => '상담',
            'App\\Models\\Product' => '제품',
            'App\\Models\\ProductCategory' => '카테고리',
            'App\\Models\\PurchaseOrder' => '발주',
            'App\\Models\\StockMovement' => '입출고',
            'App\\Models\\ClientDocument' => '의뢰자 첨부파일',
            'App\\Models\\ProjectDocument' => '프로젝트 첨부파일',
            'App\\Models\\ScheduleAttachment' => '일정 첨부파일',
            'App\\Models\\ClientMemo' => '의뢰자 메모',
            'App\\Models\\ProjectMemo' => '프로젝트 메모',
        ];

        $type = get_class($this);
        $label = $map[$type] ?? class_basename($type);
        $name = $this->title ?? $this->name ?? $this->client_name ?? $this->file_name ?? "#{$this->getKey()}";

        return "[{$label}] {$name}";
    }

    protected static function fieldLabel(string $key): string
    {
        $labels = [
            // 공통
            'title' => '제목', 'name' => '이름', 'nickname' => '닉네임', 'phone' => '전화번호',
            'email' => '이메일', 'address' => '주소', 'address_detail' => '상세주소',
            'memo' => '메모', 'content' => '내용', 'description' => '설명', 'status' => '상태',
            'note' => '비고', 'is_active' => '활성', 'created_by' => '작성자',
            // 의뢰자
            'grade' => '등급', 'platforms' => '플랫폼', 'content_types' => '콘텐츠유형',
            'gender' => '성별', 'affiliation' => '소속', 'important_memo' => '중요메모',
            'assigned_user_id' => '담당자', 'last_contact_at' => '최근연락일',
            // 프로젝트
            'client_id' => '의뢰자', 'project_type' => '프로젝트유형', 'stage' => '진행단계',
            'budget' => '예산', 'deadline' => '마감일',
            // 일정
            'start_date' => '시작일', 'end_date' => '종료일', 'start_time' => '시작시간',
            'end_time' => '종료시간', 'is_all_day' => '종일', 'color' => '유형',
            'client_name' => '의뢰자명', 'location' => '장소', 'is_locked' => '잠금',
            'is_private' => '비공개', 'notif_minutes' => '알림',
            'gold_data' => '의뢰자정보', 'teal_data' => '원격정보',
            'special_opts' => '특수옵션', 'sched_opt' => '일정관련옵션',
            'sched_event_opts' => '일정옵션', 'sched_after_reason' => '이후사유',
            'sched_after_date' => '이후날짜',
            // 견적서
            'project_id' => '프로젝트', 'client_nickname' => '의뢰자닉네임',
            'client_phone' => '의뢰자전화', 'product_items' => '제품항목',
            'service_items' => '서비스항목', 'product_total' => '제품합계',
            'service_total' => '서비스합계', 'total_amount' => '총액',
            'validity_days' => '유효기간', 'issued_at' => '발행일',
            // 상담
            'consult_type' => '상담유형', 'consulted_at' => '상담일',
            'result' => '결과', 'is_important' => '중요',
            // 제품
            'sku' => 'SKU', 'category_id' => '카테고리', 'unit' => '단위',
            'purchase_price' => '매입가', 'sale_price' => '판매가',
            'safety_stock' => '안전재고', 'current_stock' => '현재재고',
            'show_in_estimate' => '견적서노출',
            // 입출고
            'product_id' => '제품', 'movement_type' => '유형', 'quantity' => '수량',
            'unit_price' => '단가', 'reference' => '참조',
            // 발주
            'supplier' => '거래처', 'items' => '항목', 'requested_by' => '요청자',
            'ordered_at' => '발주일', 'received_at' => '입고일',
            // 첨부파일
            'file_name' => '파일명', 'file_path' => '파일경로', 'file_size' => '파일크기',
            'mime_type' => '파일유형', 'attachment_type' => '첨부유형',
            // gold_data 내부 필드
            'nickname' => '닉네임', 'platform' => '플랫폼', 'career' => '경력',
            'source' => '유입경로', 'topic' => '방송주제', 'equipment' => '장비목록',
            'request_topic' => '의뢰주제', 'req_topic' => '의뢰주제',
            'req_detail' => '의뢰세부', 'special' => '특이사항',
            'specialReason' => '특수옵션사유', 'paid' => '결제여부',
            'estimate_amount' => '견적총액', 'order' => '주문제품',
            'delivery' => '배송완료', 'balance' => '잔금여부',
            'balance_amount' => '잔금금액', 'estimate_id' => '연결견적서',
            'client_id' => '의뢰자', 'project_id' => '프로젝트',
            'platform_etc' => '플랫폼(기타)', 'topic_etc' => '방송주제(기타)',
            'budget_etc' => '예산(직접입력)', 'source_ref' => '소개자',
            'req_topic_etc' => '의뢰주제(기타)',
            // teal_data 내부 필드
            'mode' => '모드', 'desc' => '설명',
        ];

        return $labels[$key] ?? $key;
    }

    protected static function formatValue(string $key, mixed $value): mixed
    {
        if (is_null($value)) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? '예' : '아니오';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        // 색상 코드 → 한글
        if ($key === 'color') {
            $colors = ['gold' => '방문의뢰', 'teal' => '원격/방송룸', 'blue' => '사내업무', 'red' => '휴가/개인', 'green' => '촬영/스튜디오', 'purple' => '미팅/내방'];

            return $colors[$value] ?? $value;
        }

        // 상태 코드 → 한글
        if ($key === 'status') {
            $statuses = ['created' => '작성중', 'editing' => '수정중', 'completed' => '완료', 'paid' => '결제완료', 'hold' => '보류', 'cancelled' => '취소'];

            return $statuses[$value] ?? $value;
        }

        // 단계 → 한글
        if ($key === 'stage') {
            $stages = ['consulting' => '상담', 'equipment' => '장비파악', 'proposal' => '일정제안', 'estimate' => '견적/계약', 'payment' => '결제/예약', 'visit' => '세팅', 'as' => 'AS', 'done' => '완료'];

            return $stages[$value] ?? $value;
        }

        return $value;
    }
}
