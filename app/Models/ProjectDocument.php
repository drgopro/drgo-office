<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ProjectDocument extends Model
{
    use LogsActivity;

    /** 업로드 폼 분류 목록 — note는 "분류 - 메모" 형태로 저장됨 (표시 순서 기준) */
    public const CATEGORIES = ['방 사진', '레퍼런스', '사진/이미지', '계약서', '견적서', '현금영수증', '사업자등록증', '방문 보고서', '기타'];

    protected $fillable = [
        'project_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'note',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * note 앞부분에서 분류를 추출한다. 목록에 없는 값(자유 메모 등)은 '기타'로 취급.
     */
    public function category(): string
    {
        $note = trim((string) $this->note);
        if (str_starts_with($note, '방문 보고서')) {
            return '방문 보고서'; // 보고서 에디터 인라인 업로드 ("방문 보고서 · 이미지" 형태)
        }
        $head = trim(explode(' - ', $note, 2)[0]);

        return in_array($head, self::CATEGORIES, true) ? $head : '기타';
    }

    /**
     * note에서 분류를 뗀 나머지 메모.
     */
    public function noteBody(): string
    {
        $parts = explode(' - ', trim((string) $this->note), 2);

        return count($parts) === 2 && in_array(trim($parts[0]), self::CATEGORIES, true) ? trim($parts[1]) : '';
    }
}
