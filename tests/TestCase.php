<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected function helperPostJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR);

        return $this
            ->withHeaders(array_merge($this->helperSignatureHeaders('POST', $uri, $body), $headers))
            ->postJson($uri, $data);
    }

    protected function helperGetJson(string $uri, array $headers = []): TestResponse
    {
        return $this
            ->withHeaders(array_merge($this->helperSignatureHeaders('GET', $uri, ''), $headers))
            ->getJson($uri);
    }

    /** @return array<string, string> */
    private function helperSignatureHeaders(string $method, string $uri, string $body): array
    {
        $timestamp = gmdate('c');
        $secret = (string) config('durable-chat.helper_shared_secret', 'local-dev-helper-secret');
        $signature = hash_hmac('sha256', implode("\n", [
            $timestamp,
            strtoupper($method),
            $uri,
            $body,
        ]), $secret);

        return [
            'X-DCR-Helper-Id' => 'helper-demo',
            'X-DCR-Timestamp' => $timestamp,
            'X-DCR-Signature' => $signature,
        ];
    }
}
