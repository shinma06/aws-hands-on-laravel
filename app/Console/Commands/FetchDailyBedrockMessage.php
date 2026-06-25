<?php

namespace App\Console\Commands;

use App\Models\DailyBedrockMessage;
use App\Services\BedrockService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('bedrock:fetch-daily-message')]
#[Description('Bedrockにリクエストを送り、今日のメッセージをDBに保存する')]
class FetchDailyBedrockMessage extends Command
{
    public function __construct(private readonly BedrockService $bedrockService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $response = $this->bedrockService->invokeFixedPrompt();

            DailyBedrockMessage::updateOrCreate(
                ['date' => today()],
                ['response' => $response],
            );

            // 90日より古いレコードを削除
            DailyBedrockMessage::where('date', '<', today()->subDays(90))->delete();

            $this->info('今日のメッセージを保存しました。');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Bedrock呼び出しに失敗しました: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
