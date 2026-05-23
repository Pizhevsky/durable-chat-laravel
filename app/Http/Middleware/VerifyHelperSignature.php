<?php

namespace App\Http\Middleware;

use App\Domain\Auth\HelperSignatureVerifier;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyHelperSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $verifier = new HelperSignatureVerifier(
            (string) config('durable-chat.helper_shared_secret'),
            config('durable-chat.trusted_helper_ids', []),
            (int) config('durable-chat.helper_signature_tolerance_seconds', 300),
        );

        $result = $verifier->verify($request);
        if ($result['ok']) {
            return $next($request);
        }

        return $this->unauthorized($result['code'], $result['message']);
    }

    private function unauthorized(string $code, string $message): JsonResponse
    {
        return response()->json([
            'error' => $message,
            'code' => $code,
        ], 401);
    }
}
