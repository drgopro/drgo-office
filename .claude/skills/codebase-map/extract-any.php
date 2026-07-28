<?php

// 범용: 지정 URL 페이지를 렌더링해 인라인 <script>를 추출 (usage: php extract-any.php <url-path> <out-dir>)
chdir('C:/Users/gidoh/Herd/drgo-office');
require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();

try {
    $user = App\Models\User::where('role', 'master')->first() ?? App\Models\User::first();
    Auth::setUser($user);

    $path = $argv[1] ?? '/';
    $outDir = $argv[2] ?? __DIR__.'/scripts-any';

    // 프로젝트 상세는 데이터 필요
    if ($path === '__project_show__') {
        $client = App\Models\Client::create(['nickname' => '검증용', 'grade' => 'normal']);
        $project = App\Models\Project::create([
            'client_id' => $client->id, 'name' => '검증 프로젝트', 'project_type' => 'visit', 'stage' => 'consulting',
        ]);
        $path = "/projects/{$project->id}";
    }

    $request = Illuminate\Http\Request::create($path, 'GET');
    $request->setUserResolver(fn () => $user);
    $app->instance('request', $request);

    $httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $httpKernel->handle($request);
    $html = $response->getContent();

    @mkdir($outDir, 0777, true);
    array_map('unlink', glob($outDir.'/*.js') ?: []);
    array_map('unlink', glob($outDir.'/*.mjs') ?: []);
    preg_match_all('/<script(?![^>]*src=)([^>]*)>(.*?)<\/script>/si', $html, $m, PREG_SET_ORDER);
    $i = 0;
    foreach ($m as $s) {
        if (str_contains($s[1], 'importmap') || str_contains($s[1], 'application/json') || trim($s[2]) === '') {
            continue;
        }
        $ext = str_contains($s[1], 'module') ? 'mjs' : 'js';
        file_put_contents($outDir.'/s-'.($i++).'.'.$ext, $s[2]);
    }
    echo "status=".$response->getStatusCode()." extracted {$i} scripts, html=".strlen($html)." bytes\n";
} finally {
    DB::rollBack();
}
