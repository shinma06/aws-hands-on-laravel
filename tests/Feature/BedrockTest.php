<?php

use App\Models\DailyBedrockMessage;
use App\Services\BedrockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('GET /bedrock は今日のメッセージなしでInertiaページを返す', function () {
    $this->get('/bedrock')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Bedrock/Index')
            ->where('dailyMessage', null));
});

test('GET /bedrock は今日のDBレコードをdailyMessageとして渡す', function () {
    $message = DailyBedrockMessage::factory()->forToday()->create();

    $this->get('/bedrock')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Bedrock/Index')
            ->where('dailyMessage.response', $message->response));
});

test('POST /bedrock/invoke はBedrock呼び出し結果をDBに保存しJSONで返す', function () {
    $this->mock(BedrockService::class, function ($mock) {
        $mock->shouldReceive('invokeFixedPrompt')
            ->once()
            ->andReturn('今日も一歩ずつ前進しましょう。');
    });

    $this->postJson('/bedrock/invoke')
        ->assertStatus(200)
        ->assertJson(['text' => '今日も一歩ずつ前進しましょう。']);

    expect(DailyBedrockMessage::whereDate('date', today())->first())
        ->response->toBe('今日も一歩ずつ前進しましょう。');
});

test('POST /bedrock/invoke はBedrockService失敗時に500を返す', function () {
    $this->mock(BedrockService::class, function ($mock) {
        $mock->shouldReceive('invokeFixedPrompt')
            ->once()
            ->andThrow(new RuntimeException('Bedrock connection failed'));
    });

    $this->postJson('/bedrock/invoke')
        ->assertStatus(500)
        ->assertJson(['error' => 'Bedrock connection failed']);
});
