<?php

/**
 * drgo.pro 게시판 새 글/답변/댓글 피드 — 오피스(office.drgo.pro)가 주기 폴링해 채널톡 알림.
 *
 * 설치: 이 파일을 그누보드 웹 루트(estimate-view-proxy.php 옆)에 업로드하고
 *       아래 DRGO_FEED_TOKEN 값을 오피스 서버 .env의 DRGO_BOARD_FEED_TOKEN과 동일하게 교체.
 *
 * 사용: /board-feed.php?token=...&board=free&since=1234&limit=50
 *  - since 이후(wr_id 초과)의 글·답변글·댓글을 오래된 순으로 반환
 *  - since=0 이면 items 없이 현재 max_id만 반환 (오피스 첫 실행 초기화용 — 과거 글 알림 폭탄 방지)
 *  - 비밀글은 secret=true로 표시 (제목은 그대로 반환하되 노출 여부는 오피스가 결정)
 */
define('DRGO_FEED_TOKEN', 'CHANGE_ME_TOKEN');

header('Content-Type: application/json; charset=UTF-8');

if (DRGO_FEED_TOKEN === 'CHANGE_ME_TOKEN'
    || ! isset($_GET['token'])
    || ! hash_equals(DRGO_FEED_TOKEN, (string) $_GET['token'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'denied']);
    exit;
}

include_once './_common.php'; // 그누보드 부트스트랩 (DB 연결, $g5)

$board = preg_replace('/[^0-9a-zA-Z_]/', '', isset($_GET['board']) ? $_GET['board'] : 'free');
$since = isset($_GET['since']) ? (int) $_GET['since'] : 0;
$limit = min(100, max(1, isset($_GET['limit']) ? (int) $_GET['limit'] : 50));
$table = $g5['write_prefix'].$board;

// 게시판 존재 확인
$chk = sql_fetch("SELECT COUNT(*) AS cnt FROM {$g5['board_table']} WHERE bo_table = '".sql_escape_string($board)."'");
if (! $chk || ! $chk['cnt']) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'unknown board']);
    exit;
}

$maxRow = sql_fetch("SELECT MAX(wr_id) AS max_id FROM {$table}");
$maxId = $maxRow && $maxRow['max_id'] ? (int) $maxRow['max_id'] : 0;

$items = [];
if ($since > 0) {
    $result = sql_query("SELECT wr_id, wr_num, wr_reply, wr_parent, wr_is_comment, wr_subject, wr_name, wr_datetime, wr_option
        FROM {$table} WHERE wr_id > {$since} ORDER BY wr_id ASC LIMIT {$limit}");

    $rows = [];
    $parentIds = [];
    while ($row = sql_fetch_array($result)) {
        $rows[] = $row;
        if ((int) $row['wr_is_comment'] === 1) {
            $parentIds[(int) $row['wr_parent']] = true;
        }
    }

    // 댓글의 원글 제목 조회
    $parentSubjects = [];
    if ($parentIds) {
        $ids = implode(',', array_map('intval', array_keys($parentIds)));
        $pres = sql_query("SELECT wr_id, wr_subject, wr_option FROM {$table} WHERE wr_id IN ({$ids})");
        while ($p = sql_fetch_array($pres)) {
            $parentSubjects[(int) $p['wr_id']] = [
                'subject' => $p['wr_subject'],
                'secret' => strpos((string) $p['wr_option'], 'secret') !== false,
            ];
        }
    }

    foreach ($rows as $row) {
        $isComment = (int) $row['wr_is_comment'] === 1;
        $parentId = (int) $row['wr_parent'];
        $parent = $isComment && isset($parentSubjects[$parentId]) ? $parentSubjects[$parentId] : null;
        $items[] = [
            'id' => (int) $row['wr_id'],
            'type' => $isComment ? 'comment' : ($row['wr_reply'] !== '' ? 'reply' : 'post'),
            'subject' => $isComment ? '' : (string) $row['wr_subject'],
            'name' => (string) $row['wr_name'],
            'datetime' => (string) $row['wr_datetime'],
            'secret' => strpos((string) $row['wr_option'], 'secret') !== false || ($parent && $parent['secret']),
            'parent_id' => $isComment ? $parentId : null,
            'parent_subject' => $parent ? (string) $parent['subject'] : null,
        ];
    }
}

echo json_encode(['ok' => true, 'board' => $board, 'max_id' => $maxId, 'items' => $items]);
