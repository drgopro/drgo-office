<?php

/*
|--------------------------------------------------------------------------
| CRM 개편안 (2026.06.18 회의 반영) — 신규 분류/파이프라인 단일 소스
|--------------------------------------------------------------------------
| 데모(/crm-demo)에서 이 정의를 그대로 사용한다. 운영 적용 시에도 이 config를
| 기준으로 마이그레이션/UI를 구성한다. 검증 단계이므로 값은 자유롭게 조정 가능.
|
| 'billing'=true 단계 = 결제(청구) 단계 → '청구' 체크 생성 대상.
*/

return [

    // 0) 대분류 태그 (고정) — 운영 프로젝트 태그의 대분류. 소분류는 project_subtags 테이블에서 관리.
    'major_tags' => ['이사세팅', '스튜디오세팅', '처음세팅', '세팅개선'],

    // 1) 의뢰자 유형 (4종)
    'requester_types' => [
        'personal' => ['label' => '개인',    'desc' => '개인 단위 의뢰자'],
        'studio' => ['label' => '스튜디오', 'desc' => '스튜디오 운영 및 관련 의뢰자'],
        'enter' => ['label' => '엔터',    'desc' => '엔터·에이전시 소속 (MCN 아님)'],
        'company' => ['label' => '기업',    'desc' => '광고대행사·게임사 등 기업 단위'],
    ],

    // 2) 프로젝트 유형 9종 → 작업 유형(종속) + 진행 단계 파이프라인 + 담당 부서
    //    department: sales(물류·세일즈) / counsel(상담) / remote(원격)
    'project_types' => [
        'inquiry' => [
            'label' => '문의', 'department' => 'counsel',
            'work_types' => ['단순'],
            'pipeline' => [
                ['key' => 'consult', 'label' => '상담'],
                ['key' => 'done',    'label' => '완료'],
            ],
        ],
        'remote' => [
            'label' => '원격', 'department' => 'remote',
            'work_types' => ['문제해결', 'AS'],
            'pipeline' => [
                ['key' => 'consult',  'label' => '상담'],
                ['key' => 'diagnose', 'label' => '진단'],
                ['key' => 'propose',  'label' => '일정제안'],
                ['key' => 'pay',      'label' => '견적/결제', 'billing' => true],
                ['key' => 'process',  'label' => '원격처리'],
                ['key' => 'done',     'label' => '완료'],
            ],
        ],
        'visit' => [
            'label' => '방문', 'department' => 'counsel',
            'work_types' => ['일반', '재방문', '급행', '사전답사'],
            'pipeline' => [
                ['key' => 'consult',  'label' => '상담'],
                ['key' => 'propose',  'label' => '일정제안'],
                ['key' => 'estimate', 'label' => '견적/계약', 'billing' => true],
                ['key' => 'pay',      'label' => '결제/예약', 'billing' => true],
                ['key' => 'setup',    'label' => '방문세팅'],
                ['key' => 'done',     'label' => '완료'],
            ],
        ],
        'onsite' => [
            'label' => '내방', 'department' => 'counsel',
            'work_types' => ['야외방송 세팅(1차)', '교육·컨설팅'],
            'pipeline' => [
                ['key' => 'consult', 'label' => '상담'],
                ['key' => 'propose', 'label' => '일정제안'],
                ['key' => 'pay',     'label' => '결제', 'billing' => true],
                ['key' => 'visit',   'label' => '내방'],
                ['key' => 'done',    'label' => '완료'],
            ],
        ],
        'filming' => [
            'label' => '촬영', 'department' => 'counsel',
            'work_types' => ['중계', '야외'],
            'pipeline' => [
                ['key' => 'consult',  'label' => '상담'],
                ['key' => 'propose',  'label' => '일정제안'],
                ['key' => 'survey',   'label' => '사전답사'],
                ['key' => 'estimate', 'label' => '견적/계약', 'billing' => true],
                ['key' => 'pay',      'label' => '결제', 'billing' => true],
                ['key' => 'filming',  'label' => '촬영'],
                ['key' => 'done',     'label' => '완료'],
            ],
        ],
        'production' => [
            'label' => '제작', 'department' => 'counsel',
            'work_types' => ['디자인', '영상', '음향'],
            'pipeline' => [
                ['key' => 'consult',  'label' => '상담'],
                ['key' => 'estimate', 'label' => '견적/계약', 'billing' => true],
                ['key' => 'pay',      'label' => '결제', 'billing' => true],
                ['key' => 'make',     'label' => '제작/컨펌'],
                ['key' => 'deliver',  'label' => '납품'],
                ['key' => 'done',     'label' => '완료'],
            ],
        ],
        'rental' => [
            'label' => '렌탈', 'department' => 'sales',
            'work_types' => ['렌탈', '장비(단품)'],
            'pipeline' => [
                ['key' => 'consult',  'label' => '상담'],
                ['key' => 'propose',  'label' => '일정제안'],
                ['key' => 'estimate', 'label' => '견적', 'billing' => true],
                ['key' => 'pay',      'label' => '결제/계약', 'billing' => true],
            ],
        ],
        'broadcast_room' => [
            'label' => '방송룸', 'department' => 'sales',
            'work_types' => ['시간대여', '일대여', '월대여'],
            'pipeline' => [
                ['key' => 'consult',  'label' => '상담'],
                ['key' => 'estimate', 'label' => '일정제안/견적', 'billing' => true],
                ['key' => 'pay',      'label' => '결제', 'billing' => true],
                ['key' => 'use',      'label' => '이용'],
                ['key' => 'done',     'label' => '완료'],
            ],
        ],
        'goods' => [
            'label' => '상품', 'department' => 'sales',
            'work_types' => ['쇼핑몰'],
            'pipeline' => [
                ['key' => 'consult', 'label' => '상담'],
                ['key' => 'process', 'label' => '처리'],
                ['key' => 'done',    'label' => '완료'],
            ],
        ],
    ],

    // 5) 취소 사유 (정형 옵션 + 기타 주관식)
    'cancel_reasons' => [
        '연락 두절',      // ※ 며칠 무응답 기준 논의 필요
        '의뢰자 사정으로 취소',
        '일정이 맞지 않음',
        '비용 문제',
        '기타',
    ],

    // 6) 담당 부서 라벨
    'departments' => [
        'sales' => '물류·세일즈',
        'counsel' => '상담',
        'remote' => '원격',
    ],
];
