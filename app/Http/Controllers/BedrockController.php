<?php

namespace App\Http\Controllers;

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
        return Inertia::render('Bedrock/Index');
    }

    public function invoke(): JsonResponse
    {
        try {
            $text = $this->bedrockService->invokeFixedPrompt();

            return response()->json(['text' => $text]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
