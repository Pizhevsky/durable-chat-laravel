<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyHelperSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('durable-chat.helper_shared_secret');
        $helperId = (string) $request->headers->get('X-DCR-Helper-Id', '');
        $timestamp = (string) $request->headers->get('X-DCR-Timestamp', '');
        $signature = (string) $request->headers->get('X-DCR-Signature', '');
        $trustedHelperIds = config('durable-chat.trusted_helper_ids', []);

        if ($secret === '') {
            return $this->unauthorized('helper_signature_not_configured', 'Helper sync signing is not configured on the central server.');
        }

        if ($helperId === '' || $timestamp === '' || $signature === '') {
            return $this->unauthorized('missing_helper_signature', 'Helper sync requests must include helper id, timestamp and signature headers.');
        }

        if (! in_array($helperId, $trustedHelperIds, true)) {
            return $this->unauthorized('unknown_helper', 'The helper id is missing or not trusted by this central server.');
        }

        $requestTime = strtotime($timestamp);
        if ($requestTime === false) {
            return $this->unauthorized('invalid_helper_timestamp', 'The helper signature timestamp is invalid.');
        }

        $tolerance = (int) config('durable-chat.helper_signature_tolerance_seconds', 300);
        if (abs(time() - $requestTime) > $tolerance) {
            return $this->unauthorized('stale_helper_signature', 'The helper signature timestamp is outside the accepted clock tolerance.');
        }

        $expectedSignature = hash_hmac(
            'sha256',
            $this->signaturePayload($request, $timestamp),
            $secret,
        );

        if (! hash_equals($expectedSignature, $signature)) {
            return $this->unauthorized('invalid_helper_signature', 'The helper signature does not match the request.');
        }

        return $next($request);
    }

    private function signaturePayload(Request $request, string $timestamp): string
    {
        $method = strtoupper($request->getMethod());
        $body = in_array($method, ['GET', 'HEAD'], true) ? '' : $request->getContent();

        return implode("\n", [
            $timestamp,
            $method,
            $request->getRequestUri(),
            $body,
        ]);
    }

    private function unauthorized(string $code, string $message): JsonResponse
    {
        return response()->json([
            'error' => $message,
            'code' => $code,
        ], 401);
    }
}
