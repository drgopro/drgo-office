<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Services\ChannelTalkClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('marketing:consent-digest {--date= : 집계 기준일 (기본 어제, YYYY-MM-DD)}')]
#[Description('전날 마케팅 활용 동의/거부로 변경된 프로젝트 목록을 채널톡 아웃바운드 방으로 발송 (매일 아침)')]
class SendMarketingConsentDigest extends Command
{
    public function handle(ChannelTalkClient $channelTalk): int
    {
        if (! $channelTalk->isConfigured()) {
            $this->warn('채널톡 연동 정보가 없어 건너뜁니다 (.env CHANNELTALK_*)');

            return self::SUCCESS;
        }

        $date = $this->option('date') ? now()->parse($this->option('date')) : now()->subDay();

        // 기준일에 동의/거부로 확정된 프로젝트만 — 미확인(null)으로 되돌린 건은 제외
        $projects = Project::with('client:id,name,nickname')
            ->whereNotNull('marketing_consent')
            ->whereBetween('marketing_consent_updated_at', [
                $date->copy()->startOfDay(), $date->copy()->endOfDay(),
            ])
            ->orderByDesc('marketing_consent')->orderBy('marketing_consent_updated_at')
            ->get(['id', 'name', 'client_id', 'manual_client_name', 'marketing_consent', 'marketing_consent_updated_at']);

        if ($projects->isEmpty()) {
            $this->info("{$date->format('m/d')} 마케팅 활용 동의 변경 없음 — 발송 생략");

            return self::SUCCESS;
        }

        $weekday = ['일', '월', '화', '수', '목', '금', '토'][$date->dayOfWeek];
        $lines = ["[마케팅 활용] {$date->format('m/d')} ({$weekday}) 동의/거부 변경 {$projects->count()}건\n"];

        foreach ([['consent' => true, 'label' => '동의'], ['consent' => false, 'label' => '거부']] as $section) {
            ['consent' => $consent, 'label' => $label] = $section;
            $group = $projects->where('marketing_consent', $consent);
            if ($group->isEmpty()) {
                continue;
            }
            $lines[] = "▶ {$label} {$group->count()}건";
            foreach ($group as $p) {
                $clientName = $p->client
                    ? ($p->client->nickname ?: $p->client->name ?: '의뢰자 미상')
                    : ($p->manual_client_name ?: '의뢰자 미상');
                $lines[] = "• {$p->name} — {$clientName}";
            }
        }

        // 담당자 멘션 — 관리 > 프로젝트 > 마케팅 알림에서 지정 (비우면 멘션 없음)
        $managerIds = json_decode((string) Setting::get('marketing_alert_managers', '[]'), true);
        $mentions = User::whereIn('id', is_array($managerIds) ? $managerIds : [])
            ->get()
            ->map(fn (User $u) => $channelTalk->managerMention($u->email, $u->display_name))
            ->implode(' ');
        if ($mentions !== '') {
            $lines[] = "\n담당자: ".$mentions;
        }

        // 톡방 — 관리 설정값, 비우면 기본 팀챗 그룹(.env, 아웃바운드)
        $group = trim((string) Setting::get('marketing_alert_group', ''));

        $result = $channelTalk->sendGroupMessage(implode("\n", $lines), $group !== '' ? $group : null);
        if (! $result['ok']) {
            $this->error($result['error']);

            return self::FAILURE;
        }

        $this->info("발송 완료 — {$projects->count()}건");

        return self::SUCCESS;
    }
}
