<?php

namespace App\Services;

use Aws\BedrockRuntime\BedrockRuntimeClient;

class BedrockService
{
    private BedrockRuntimeClient $client;

    private const MODEL_ID = 'us.anthropic.claude-haiku-4-5-20251001-v1:0';

    private const ANTHROPIC_VERSION = 'bedrock-2023-05-31';

    /** 固定プロンプト */
    private const PROMPT = '今日の一言を日本語で短く教えてください。50文字以内でお願いします。';

    public function __construct()
    {
        $this->client = new BedrockRuntimeClient([
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'version' => 'latest',
        ]);
    }

    /**
     * 固定プロンプトをClaude 3 Haikuに送信し、レスポンステキストを返す。
     */
    public function invokeFixedPrompt(): string
    {
        $result = $this->client->invokeModel([
            'modelId' => self::MODEL_ID,
            'contentType' => 'application/json',
            'accept' => 'application/json',
            'body' => json_encode([
                'anthropic_version' => self::ANTHROPIC_VERSION,
                'max_tokens' => 256,
                'messages' => [[
                    'role' => 'user',
                    'content' => self::PROMPT,
                ]],
            ]),
        ]);

        /** @var array{content: array<int, array{text: string}>} $body */
        $body = json_decode((string) $result['body'], true);

        return $body['content'][0]['text'] ?? '';
    }
}
