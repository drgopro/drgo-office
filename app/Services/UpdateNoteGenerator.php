<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Process;

/**
 * 배포 커밋(git log)을 일자별·기능별로 묶어 위키 '업데이트' 게시물 초안 HTML을 생성.
 * 초안은 임시저장 글로 만들어지므로 관리자가 검토·수정 후 발행한다.
 */
class UpdateNoteGenerator
{
    /** 커밋 scope 첫 세그먼트 → 표시 섹션 (선언 순서 = 문서 내 표시 순서) */
    private const SECTIONS = [
        'calendar' => '📅 캘린더',
        'shipments' => '📦 배송',
        'todos' => '✅ 할 일',
        'project' => '📁 프로젝트',
        'projects' => '📁 프로젝트',
        'clients' => '👥 의뢰자',
        'documents' => '📄 문서·계약',
        'quotes' => '📄 문서·계약',
        'contracts' => '📄 문서·계약',
        'marketing' => '📊 통계·마케팅',
        'stats' => '📊 통계·마케팅',
        'wiki' => '📖 위키',
        'feedback' => '💬 피드백',
        'attachments' => '📎 첨부파일',
        'admin' => '🔧 시스템',
        'ops' => '🔧 시스템',
        'smoke' => '🔧 시스템',
        'data' => '🔧 시스템',
        'layout' => '🔧 시스템',
    ];

    private const OTHER_SECTION = '🔹 기타';

    /**
     * 한국어 커밋 제목 키워드 → 섹션 (conventional 형식이 아닌 커밋의 폴백 분류).
     * 위에서부터 첫 매칭이 우선 — 캘린더 UI 작업이 프로젝트/첨부 등을 함께 언급하는 경우가 많아 캘린더를 최상위로.
     */
    private const KEYWORD_SECTIONS = [
        // 내부 정비성 키워드 먼저 — '카페24 .htaccess 백업(견적서 프록시)'처럼 기능 키워드를 함께 언급해도 내부로
        '배포' => '🛠 내부 정비 (사용 화면 변화 없음)',
        '스크립트' => '🛠 내부 정비 (사용 화면 변화 없음)',
        'npm' => '🛠 내부 정비 (사용 화면 변화 없음)',
        '.htaccess' => '🛠 내부 정비 (사용 화면 변화 없음)',
        '백업' => '🛠 내부 정비 (사용 화면 변화 없음)',
        // 연차는 캘린더보다 먼저 — '연차 관리 + 캘린더 연동' 같은 제목은 연차로
        '연차' => '🌴 연차',
        '휴가' => '🌴 연차',
        '캘린더' => '📅 캘린더',
        '일정' => '📅 캘린더',
        '배송' => '📦 배송',
        '주문' => '📦 배송',
        '할 일' => '✅ 할 일',
        '의뢰자' => '👥 의뢰자',
        '상담' => '👥 의뢰자',
        '프로젝트' => '📁 프로젝트',
        '견적' => '📄 문서·계약',
        '계약' => '📄 문서·계약',
        '문서' => '📄 문서·계약',
        '통계' => '📊 통계·마케팅',
        '마케팅' => '📊 통계·마케팅',
        '매출' => '📊 통계·마케팅',
        '엑셀' => '📊 통계·마케팅',
        '위키' => '📖 위키',
        '피드백' => '💬 피드백',
        '첨부' => '📎 첨부파일',
        '검색' => '🔧 시스템',
        '사이드바' => '🔧 시스템',
        '전화번호' => '🔧 시스템',
    ];

    private const INTERNAL_SECTION = '🛠 내부 정비 (사용 화면 변화 없음)';

    /** 커밋 type → 항목 라벨. 여기 없는 type(refactor/test/chore 등)은 내부 정비로 분류 */
    private const TYPE_LABELS = ['feat' => '신규', 'fix' => '수정', 'style' => '개선', 'perf' => '개선'];

    private const WEEKDAYS_KO = ['일', '월', '화', '수', '목', '금', '토'];

    /**
     * 기간 내 배포 커밋 목록 (머지 커밋 제외, git log 순서 = 최신순).
     *
     * @return array<int, array{date: string, subject: string}>
     */
    public function commitsBetween(string $from, string $to): array
    {
        $result = Process::path(base_path())->run([
            'git', 'log', '--no-merges',
            '--date=format:%Y-%m-%d', '--pretty=format:%cd%x09%s',
            "--since={$from} 00:00:00", "--until={$to} 23:59:59",
        ]);

        if (! $result->successful()) {
            return [];
        }

        return collect(explode("\n", trim($result->output())))
            ->filter(fn (string $line) => str_contains($line, "\t"))
            ->map(function (string $line) {
                [$date, $subject] = explode("\t", $line, 2);

                return ['date' => $date, 'subject' => trim($subject)];
            })
            ->values()
            ->all();
    }

    /**
     * 커밋들을 일자별(최신일 우선)·섹션별 HTML로 변환 (위키 에디터 호환: h2/h3/ul/strong).
     *
     * @param  array<int, array{date: string, subject: string}>  $commits
     */
    public function buildHtml(array $commits): string
    {
        $html = '';
        foreach (collect($commits)->groupBy('date')->sortKeysDesc() as $date => $dayCommits) {
            $html .= '<h2>'.$this->dateHeading($date).'</h2>';
            $html .= $this->daySectionsHtml($dayCommits->all());
        }

        return $html;
    }

    /** 초안 제목 — 하루면 "업데이트 노트 7/30 (목)", 기간이면 "업데이트 노트 7/28 ~ 7/30" */
    public function title(string $from, string $to): string
    {
        if ($from === $to) {
            return '업데이트 노트 '.$this->dateHeading($from);
        }

        return '업데이트 노트 '.Carbon::parse($from)->format('n/j').' ~ '.Carbon::parse($to)->format('n/j');
    }

    /** "7/30 (목)" 형식의 날짜 제목 */
    public function dateHeading(string $date): string
    {
        $day = Carbon::parse($date);

        return $day->format('n/j').' ('.self::WEEKDAYS_KO[$day->dayOfWeek].')';
    }

    /**
     * 하루치 커밋을 섹션별 소제목 + 목록으로 변환.
     *
     * @param  array<int, array{date: string, subject: string}>  $commits
     */
    private function daySectionsHtml(array $commits): string
    {
        $sections = [];
        foreach ($commits as $commit) {
            [$section, $item] = $this->classify($commit['subject']);
            $sections[$section][] = $item;
        }

        $order = array_values(array_unique([
            ...array_values(self::SECTIONS),
            ...array_diff(array_values(self::KEYWORD_SECTIONS), [self::INTERNAL_SECTION, self::OTHER_SECTION]),
            self::OTHER_SECTION,
            self::INTERNAL_SECTION,
        ]));
        uksort($sections, fn (string $a, string $b) => array_search($a, $order, true) <=> array_search($b, $order, true));

        $html = '';
        foreach ($sections as $section => $items) {
            $html .= '<h3>'.$section.'</h3><ul>';
            // git log는 최신순 → 문서에는 작업한 순서대로
            foreach (array_reverse($items) as $item) {
                $html .= '<li>'.$item.'</li>';
            }
            $html .= '</ul>';
        }

        return $html;
    }

    /**
     * 커밋 제목 한 줄을 [섹션, 항목 HTML]로 분류.
     * "feat(calendar): ..." 형식이 아니면 기타, feat/fix/style/perf 외 type은 내부 정비.
     *
     * @return array{0: string, 1: string}
     */
    private function classify(string $subject): array
    {
        if (! preg_match('/^([a-z]+)(?:\(([^)]*)\))?!?:\s*(.+)$/u', $subject, $matches)) {
            // 한국어 제목 커밋 — 키워드로 섹션 분류 (첫 매칭 우선)
            foreach (self::KEYWORD_SECTIONS as $keyword => $section) {
                if (mb_stripos($subject, (string) $keyword) !== false) {
                    return [$section, e($subject)];
                }
            }

            return [self::OTHER_SECTION, e($subject)];
        }

        [, $type, $scope, $text] = $matches;
        $text = e($text);

        if (! isset(self::TYPE_LABELS[$type])) {
            return [self::INTERNAL_SECTION, $text];
        }

        $firstScope = strtolower(explode('/', $scope)[0] ?? '');
        $section = self::SECTIONS[$firstScope] ?? self::OTHER_SECTION;

        return [$section, '<strong>['.self::TYPE_LABELS[$type].']</strong> '.$text];
    }
}
