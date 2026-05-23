<?php

namespace App\Domain\Auth;

use Illuminate\Http\Request;

final readonly class HelperSignatureVerifier
{
    /** @param array<int, string> $trustedHelperIds */
    public function __construct(
        private string $sharedSecret,
        private array $trustedHelperIds,
        private int $toleranceSeconds,
    ) {}

    /** @return array{ok: true}|array{ok: false, code: string, message: string} */
    public function verify(Request $request): array
    {
        if ($this->sharedSecret === '') {
            return $this->rejected('helper_signature_not_configured', 'Helper sync signing is not configured on the central server.');
        }

        $helperId = (string) $request->headers->get('X-DCR-Helper-Id', '');
        $timestamp = (string) $request->headers->get('X-DCR-Timestamp', '');
        $signature = (string) $request->headers->get('X-DCR-Signature', '');

        if ($helperId === '' || $timestamp === '' || $signature === '') {
            return $this->rejected('missing_helper_signature', 'Helper sync requests must include helper id, timestamp and signature headers.');
        }

        if (! in_array($helperId, $this->trustedHelperIds, true)) {
            return $this->rejected('unknown_helper', 'The helper id is missing or not trusted by this central server.');
        }

        $requestTime = strtotime($timestamp);
        if ($requestTime === false) {
            return $this->rejected('invalid_helper_timestamp', 'The helper signature timestamp is invalid.');
        }

        if (abs(time() - $requestTime) > $this->toleranceSeconds) {
            return $this->rejected('stale_helper_signature', 'The helper signature timestamp is outside the accepted clock tolerance.');
        }

        $expectedSignature = hash_hmac('sha256', $this->signaturePayload($request, $timestamp), $this->sharedSecret);

        if (! hash_equals($expectedSignature, $signature)) {
            return $this->rejected('invalid_helper_signature', 'The helper signature does not match the request.');
        }

        return ['ok' => true];
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

    /** @return array{ok: false, code: string, message: string} */
    private function rejected(string $code, string $message): array
    {
        return [
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ];
    }
}
