<?php

use App\Console\Commands\FetchDailyBedrockMessage;
use App\Models\DailyBedrockMessage;
use App\Services\BedrockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('コマンド実行で今日のメッセージがDBに保存される', function () {
    $this->mock(BedrockService::class, function ($mock) {
        $mock->shouldReceive('invokeFixedPrompt')
            ->once()
            ->andReturn('今日も一歩前進しましょう。');
    });

    $this->artisan(FetchDailyBedrockMessage::class)
        ->assertSuccessful();

    expect(DailyBedrockMessage::whereDate('date', today())->first())
        ->response->toBe('今日も一歩前進しましょう。');
});

test('同じ日に再実行すると既存レコードが上書きされる', function () {
    DailyBedrockMessage::factory()->forToday()->create(['response' => '古いメッセージ']);

    $this->mock(BedrockService::class, function ($mock) {
        $mock->shouldReceive('invokeFixedPrompt')
            ->once()
            ->andReturn('新しいメッセージ');
    });

    $this->artisan(FetchDailyBedrockMessage::class)->assertSuccessful();

    expect(DailyBedrockMessage::count())->toBe(1)
        ->and(DailyBedrockMessage::first()->response)->toBe('新しいメッセージ');
});

test('91日以上前のレコードが削除され90日前は残る', function () {
    $old = DailyBedrockMessage::factory()->create(['date' => today()->subDays(91)]);
    $boundary = DailyBedrockMessage::factory()->create(['date' => today()->subDays(90)]);
    $recent = DailyBedrockMessage::factory()->create(['date' => today()->subDays(89)]);

    $this->mock(BedrockService::class, fn ($mock) => $mock
        ->shouldReceive('invokeFixedPrompt')->once()->andReturn('メッセージ'));

    $this->artisan(FetchDailyBedrockMessage::class)->assertSuccessful();

    // 91日前は削除、90日前・89日前は残る（"より古い" = 91日以上）
    expect(DailyBedrockMessage::find($old->id))->toBeNull()
        ->and(DailyBedrockMessage::find($boundary->id))->not->toBeNull()
        ->and(DailyBedrockMessage::find($recent->id))->not->toBeNull();
});

test('Bedrock呼び出し失敗時はコマンドがFAILUREを返す', function () {
    $this->mock(BedrockService::class, function ($mock) {
        $mock->shouldReceive('invokeFixedPrompt')
            ->once()
            ->andThrow(new RuntimeException('接続エラー'));
    });

    $this->artisan(FetchDailyBedrockMessage::class)->assertFailed();

    expect(DailyBedrockMessage::count())->toBe(0);
});
