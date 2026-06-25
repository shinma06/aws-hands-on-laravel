<?php

namespace App\Http\Controllers;

use App\Models\DailyBedrockMessage;
use App\Services\BedrockService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class BedrockController extends Controller
{
    public function __construct(private readonly BedrockService $bedrockService) {}

    public function index(): Response
    {
        return Inertia::render('Bedrock/Index', [
            'dailyMessage' => DailyBedrockMessage::whereDate('date', today())
                ->first(['date', 'response', 'updated_at']),
        ]);
    }

    public function invoke(): JsonResponse
    {
        try {
            $response = $this->bedrockService->invokeFixedPrompt();

            DailyBedrockMessage::updateOrCreate(
                ['date' => today()],
                ['response' => $response],
            );

            return response()->json(['text' => $response]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
