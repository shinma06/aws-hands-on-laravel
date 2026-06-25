<?php

use App\Services\BedrockService;

test('GET /bedrock returns Inertia page', function () {
    $response = $this->get('/bedrock');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Bedrock/Index'));
});

test('POST /bedrock/invoke returns text response', function () {
    $this->mock(BedrockService::class, function ($mock) {
        $mock->shouldReceive('invokeFixedPrompt')
            ->once()
            ->andReturn('今日も一歩ずつ前進しましょう。');
    });

    $response = $this->postJson('/bedrock/invoke');

    $response->assertStatus(200)
        ->assertJson(['text' => '今日も一歩ずつ前進しましょう。']);
});

test('POST /bedrock/invoke returns 500 on BedrockService failure', function () {
    $this->mock(BedrockService::class, function ($mock) {
        $mock->shouldReceive('invokeFixedPrompt')
            ->once()
            ->andThrow(new RuntimeException('Bedrock connection failed'));
    });

    $response = $this->postJson('/bedrock/invoke');

    $response->assertStatus(500)
        ->assertJson(['error' => 'Bedrock connection failed']);
});
