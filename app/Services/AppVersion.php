<?php

namespace App\Services;

/**
 * 현재 배포된 코드 버전 식별자 — 셸(탭 레이아웃)이 1분마다 비교해 새 배포가 있으면 새로고침 안내 띠를 띄운다.
 *
 * 원천 우선순위:
 *  1. 배포 스크립트 마지막 단계(deploy:stamp)가 남긴 storage/app/deploy-version — 배포가 끝난 시점에만 바뀜
 *  2. .git/HEAD 커밋 해시 (스탬프가 없는 환경 — git pull 직후부터 바뀌므로 배포 중간에 안내될 수 있음)
 *  3. composer.lock 수정 시각
 * 외부에는 원천 값을 그대로 내보내지 않고 APP_KEY로 섞은 불투명 해시만 노출한다.
 * 프로세스 캐시는 두지 않는다 — FPM reload 뒤에 스탬프가 써지므로 캐시하면 새 배포를 영구히 놓친다.
 */
class AppVersion
{
    public const STAMP_RELATIVE_PATH = 'storage/app/deploy-version';

    public function __construct(
        private readonly ?string $basePath = null,
        private readonly ?string $salt = null,
    ) {}

    /** 클라이언트에 노출하는 불투명 버전 (원천이 바뀌면 함께 바뀜) */
    public function current(): string
    {
        $salt = $this->salt ?? (string) config('app.key');

        return substr(sha1($salt.'|'.$this->raw()), 0, 12);
    }

    /** 원천 식별자 — 스탬프 → git 커밋 → composer.lock mtime */
    public function raw(): string
    {
        $stamp = $this->readFile($this->stampPath());
        if ($stamp !== null && $stamp !== '') {
            return 'stamp:'.$stamp;
        }

        $commit = $this->gitCommit();
        if ($commit !== null) {
            return 'git:'.$commit;
        }

        return $this->fallback();
    }

    public function stampPath(): string
    {
        return $this->base().'/'.self::STAMP_RELATIVE_PATH;
    }

    /** 현재 체크아웃된 커밋 해시 앞 12자리 (.git 디렉터리 또는 gitdir 파일, 브랜치 ref·detached·packed-refs) */
    public function gitCommit(): ?string
    {
        $gitDir = $this->gitDir();
        if ($gitDir === null) {
            return null;
        }

        $head = $this->readFile($gitDir.'/HEAD');
        if ($head === null) {
            return null;
        }

        if (preg_match('/^[0-9a-f]{7,64}$/i', $head)) {
            return substr($head, 0, 12); // detached HEAD
        }

        if (! preg_match('/^ref:\s*(\S+)$/', $head, $m)) {
            return null;
        }
        $ref = $m[1];

        $direct = $this->readFile($gitDir.'/'.$ref);
        if ($direct !== null && preg_match('/^[0-9a-f]{7,64}$/i', $direct)) {
            return substr($direct, 0, 12);
        }

        // worktree는 refs가 공통 저장소(commondir)에 있다
        $common = $this->readFile($gitDir.'/commondir');
        $refsRoot = $common !== null ? $this->resolvePath($gitDir, $common) : $gitDir;
        if ($refsRoot !== $gitDir) {
            $shared = $this->readFile($refsRoot.'/'.$ref);
            if ($shared !== null && preg_match('/^[0-9a-f]{7,64}$/i', $shared)) {
                return substr($shared, 0, 12);
            }
        }

        $packed = $this->readFile($refsRoot.'/packed-refs');
        if ($packed !== null && preg_match('/^([0-9a-f]{7,64})\s+'.preg_quote($ref, '/').'$/mi', $packed, $pm)) {
            return substr($pm[1], 0, 12);
        }

        return null;
    }

    private function base(): string
    {
        return rtrim($this->basePath ?? base_path(), '/');
    }

    /** .git이 디렉터리면 그대로, "gitdir: …" 파일이면 가리키는 디렉터리 */
    private function gitDir(): ?string
    {
        $dotGit = $this->base().'/.git';
        if (is_dir($dotGit)) {
            return $dotGit;
        }

        $pointer = $this->readFile($dotGit);
        if ($pointer !== null && preg_match('/^gitdir:\s*(.+)$/', $pointer, $m)) {
            $dir = $this->resolvePath($this->base(), trim($m[1]));

            return is_dir($dir) ? $dir : null;
        }

        return null;
    }

    private function resolvePath(string $from, string $path): string
    {
        $path = rtrim($path, '/');

        return str_starts_with($path, '/') ? $path : $from.'/'.$path;
    }

    /** 스탬프도 .git도 없는 환경 — 의존성 잠금 파일 수정 시각 */
    private function fallback(): string
    {
        $lock = $this->base().'/composer.lock';
        $mtime = is_file($lock) ? @filemtime($lock) : false;

        return 'lock:'.($mtime ?: '0');
    }

    private function readFile(string $path): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }
        $content = @file_get_contents($path);

        return $content === false ? null : trim($content);
    }
}
