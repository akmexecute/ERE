<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Payment\Actions\ProcessWebhookEventAction;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly ProcessWebhookEventAction $processWebhookAction
    ) {}

    /**
     * Handle incoming provider webhooks with HMAC validation and deduplication.
     */
    public function handle(Request $request, string $provider): JsonResponse
    {
        $rawBody = $request->getContent();
        $headers = $request->headers->all();
        $payload = $request->all();

        // Convert array of single-item headers to flat strings
        $flatHeaders = array_map(fn($v) => is_array($v) ? ($v[0] ?? '') : $v, $headers);

        $signature = $request->header('x-signature')
            ?? $request->header('x-chapa-signature')
            ?? $request->header('signature')
            ?? $request->input('signature');

        try {
            $result = $this->processWebhookAction->execute(
                $provider,
                $flatHeaders,
                $rawBody,
                $payload,
                $signature ? (string) $signature : null
            );

            return response()->json([
                'received' => true,
                'provider' => $provider,
                'result' => $result,
            ], 200);
        } catch (DomainException $e) {
            Log::warning("Payment webhook processing failed for [{$provider}]: {$e->getMessage()}");

            return response()->json([
                'received' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}

