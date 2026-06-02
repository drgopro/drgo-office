<?php

namespace App\Models;

/**
 * 하위 호환 alias — 신규 코드는 ProjectFeedback 사용.
 * project_memos 테이블이 project_feedbacks로 이전된 후 동일 테이블을 가리키도록 table 지정.
 */
class ProjectMemo extends ProjectFeedback
{
    protected $table = 'project_feedbacks';
}
