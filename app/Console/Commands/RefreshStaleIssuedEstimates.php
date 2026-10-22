<?php

namespace App\Console\Commands;

use App\Models\Estimate;
use App\Services\ChannelTalkNotifier;
use App\Services\PayAppClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 발행 24시간 가격 보장 — 발행완료 후 24시간이 지나도록 미결제인 견적서를 재갱신한다.
 * 현재 가격(기간 할인 반영/종료)으로 다시 계산해서,
 * - 금액이 그대로면 아무것도 바꾸지 않고
 * - 금액이 달라지면 견적서 금액을 갱신하고, 대기 중인 페이앱 결제요청을 새 금액으로
 *   재생성한 뒤 채널톡으로 알린다. (수동 특가/할인·price_fixed·대체 항목은 기존 보호 유지)
 */
class RefreshStaleIssuedEstimates extends Command
{
    /** 발행 후 가격 보장 시간 */
    public const GUARANTEE_HOURS = 24;

    protected $signature = 'estimates:refresh-stale-issued';

    protected $description = '발행 24시간 경과 미결제 견적서를 현재 가격으로 재갱신 (페이앱 결제요청 재생성 포함)';

    public function handle(PayAppClient $payapp, ChannelTalkNotifier $notifier): int
    {
        $cutoff = now()->subHours(self::GUARANTEE_HOURS);
        $stale = Estimate::where('status', 'issued')
            ->whereNull('payapp_paid_at')
            ->where(fn ($q) => $q->where('issued_at', '<=', $cutoff)
                ->orWhere(fn ($q2) => $q2->whereNull('issued_at')->where('updated_at', '<=', $cutoff)))
            ->get();

        $refreshed = 0;
        foreach ($stale as $estimate) {
            try {
                $refreshed += $this->refresh($estimate, $payapp, $notifier) ? 1 : 0;
            } catch (\Throwable $e) {
                report($e);
                $this->error("견적서 #{$estimate->id} 재갱신 실패: {$e->getMessage()}");
            }
        }

        $this->info('발행 '.self::GUARANTEE_HOURS."시간 경과 {$stale->count()}건 점검, 금액 변경 {$refreshed}건 재갱신");

        return self::SUCCESS;
    }

    private function refresh(Estimate $estimate, PayAppClient $payapp, ChannelTalkNotifier $notifier): bool
    {
        $oldTotal = (int) $estimate->total_amount;

        DB::beginTransaction();
        $estimate->syncSnapshotPrices(ignoreLock: true);
        $newTotal = (int) $estimate->fresh()->total_amount;

        if ($newTotal === $oldTotal) {
            DB::commit(); // 품목 백필 등은 유지 — 금액이 같으면 결제요청도 그대로

            return false;
        }

        // 대기 중인 페이앱 결제요청 — 기존 요청 철회 후 새 금액으로 재생성.
        // 철회 실패(이미 결제됐을 가능성 등)면 가격 변경까지 되돌리고 직원 판단에 맡긴다
        $linkNote = '페이앱 결제요청 없음 — 의뢰자 링크 금액만 갱신됨';
        if ($estimate->payapp_mul_no && (int) $estimate->payapp_state === 1) {
            $cancel = $payapp->cancelRequest($estimate);
            if (! $cancel['ok']) {
                DB::rollBack();
                Log::warning("견적서 #{$estimate->id} 재갱신 보류 — 결제요청 철회 실패: ".($cancel['error'] ?? ''));
                $this->notify($notifier, $estimate, $oldTotal, $newTotal,
                    '결제요청 철회 실패로 금액을 바꾸지 않았습니다. 결제 여부 확인 후 수동 처리해주세요.');

                return false;
            }
            $request = $payapp->requestPayment($estimate->fresh());
            if ($request['ok']) {
                $estimate->forceFill([
                    'payapp_mul_no' => $request['mul_no'],
                    'payapp_payurl' => $request['payurl'],
                    'payapp_state' => 1,
                    'payapp_requested_at' => now(),
                    'payapp_paid_at' => null,
                ])->save();
                $linkNote = '페이앱 결제요청을 새 금액으로 재생성했습니다 (의뢰자 링크 주소는 그대로)';
            } else {
                $estimate->forceFill([
                    'payapp_mul_no' => null,
                    'payapp_payurl' => null,
                    'payapp_state' => null,
                ])->save();
                $linkNote = '기존 결제요청은 철회했지만 재생성에 실패했습니다 — 빌더에서 결제요청을 다시 발행해주세요. ('.($request['error'] ?? '').')';
            }
        }
        DB::commit();

        $this->notify($notifier, $estimate, $oldTotal, $newTotal, $linkNote);

        return true;
    }

    private function notify(ChannelTalkNotifier $notifier, Estimate $estimate, int $oldTotal, int $newTotal, string $note): void
    {
        $notifier->estimateReissued($estimate, $oldTotal, $newTotal, $note);
    }
}
