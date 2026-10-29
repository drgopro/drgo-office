<?php

namespace Tests\Unit;

use App\Services\AppVersion;
use PHPUnit\Framework\TestCase;

/** 배포 버전 식별 — 스탬프 우선, .git(HEAD ref·detached·packed-refs·gitdir 파일), .git 없는 환경의 폴백, 불투명 해시 */
class AppVersionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/appver-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/.git/refs/heads', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->dir);
        parent::tearDown();
    }

    private function version(): AppVersion
    {
        return new AppVersion($this->dir, 'test-salt');
    }

    public function test_reads_branch_ref_commit_from_loose_ref_file(): void
    {
        file_put_contents($this->dir.'/.git/HEAD', "ref: refs/heads/main\n");
        file_put_contents($this->dir.'/.git/refs/heads/main', "0123456789abcdef0123456789abcdef01234567\n");

        $this->assertSame('git:0123456789ab', $this->version()->raw());
    }

    public function test_falls_back_to_packed_refs_when_loose_ref_missing(): void
    {
        file_put_contents($this->dir.'/.git/HEAD', "ref: refs/heads/main\n");
        file_put_contents($this->dir.'/.git/packed-refs', "# pack-refs with: peeled fully-peeled sorted \n"
            ."aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa refs/heads/other\n"
            ."fedcba9876543210fedcba9876543210fedcba98 refs/heads/main\n");

        $this->assertSame('git:fedcba987654', $this->version()->raw());
    }

    public function test_reads_detached_head_hash_directly(): void
    {
        file_put_contents($this->dir.'/.git/HEAD', "abcdef1234567890abcdef1234567890abcdef12\n");

        $this->assertSame('git:abcdef123456', $this->version()->raw());
    }

    public function test_supports_gitdir_pointer_file_of_a_worktree(): void
    {
        $this->rmdir($this->dir.'/.git');
        mkdir($this->dir.'/repo.git/worktrees/wt', 0777, true);
        mkdir($this->dir.'/repo.git/refs/heads', 0777, true);
        file_put_contents($this->dir.'/.git', 'gitdir: '.$this->dir."/repo.git/worktrees/wt\n");
        file_put_contents($this->dir.'/repo.git/worktrees/wt/HEAD', "ref: refs/heads/main\n");
        file_put_contents($this->dir.'/repo.git/worktrees/wt/commondir', "../..\n");
        file_put_contents($this->dir.'/repo.git/refs/heads/main', "1111111111222222222233333333334444444444\n");

        $this->assertSame('git:111111111122', $this->version()->raw());
    }

    public function test_deploy_stamp_takes_priority_over_git(): void
    {
        file_put_contents($this->dir.'/.git/HEAD', "abcdef1234567890abcdef1234567890abcdef12\n");
        mkdir($this->dir.'/storage/app', 0777, true);
        file_put_contents($this->dir.'/'.AppVersion::STAMP_RELATIVE_PATH, "abcdef123456@20261029180000\n");

        $this->assertSame('stamp:abcdef123456@20261029180000', $this->version()->raw());
    }

    public function test_without_git_uses_composer_lock_mtime_and_stays_stable(): void
    {
        $this->rmdir($this->dir.'/.git');
        file_put_contents($this->dir.'/composer.lock', '{}');
        touch($this->dir.'/composer.lock', 1_700_000_000);

        $this->assertSame('lock:1700000000', $this->version()->raw());
        $this->assertSame($this->version()->current(), $this->version()->current());
    }

    public function test_without_any_source_still_returns_non_empty_version(): void
    {
        $this->rmdir($this->dir.'/.git');

        $this->assertSame('lock:0', $this->version()->raw());
    }

    public function test_public_version_is_opaque_salted_hash_of_raw_value(): void
    {
        file_put_contents($this->dir.'/.git/HEAD', "abcdef1234567890abcdef1234567890abcdef12\n");

        $version = $this->version()->current();

        $this->assertSame(substr(sha1('test-salt|git:abcdef123456'), 0, 12), $version);
        $this->assertStringNotContainsString('abcdef123456', $version); // 원천 커밋 해시가 그대로 노출되지 않음
        $this->assertNotSame($version, (new AppVersion($this->dir, 'other-salt'))->current());
    }

    private function rmdir(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path.'/'.$entry;
            is_dir($full) ? $this->rmdir($full) : unlink($full);
        }
        rmdir($path);
    }
}
