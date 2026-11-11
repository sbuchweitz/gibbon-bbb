<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Client for the mock BBB server's /__mock control routes (tests/harness/mock-bbb).
 */
final readonly class MockBbb
{
    public function __construct(private string $baseUrl, private string $secret)
    {
    }

    public static function fromEnvironment(): self
    {
        $base = rtrim((string) getenv('BBB_MOCK_URL'), '/');
        if ($base === '') {
            TestCase::markTestSkipped('BBB_MOCK_URL not set (run via the harness "tests" service)');
        }
        $secret = (string) (getenv('BBB_SECRET') ?: 'mock-secret-0123456789');
        $mock = new self($base, $secret);
        if (!$mock->isUp()) {
            TestCase::markTestSkipped("Mock BBB not reachable at {$base} (start tests/harness/compose.yml)");
        }

        return $mock;
    }

    public function apiUrl(): string
    {
        return $this->baseUrl.'/bigbluebutton/';
    }

    public function secret(): string
    {
        return $this->secret;
    }

    public function reset(): void
    {
        $this->request('POST', '/__mock/reset');
    }

    /** @param array<string, mixed> $config */
    public function configure(array $config): void
    {
        $this->request('POST', '/__mock/config', $config);
    }

    /**
     * @param list<string> $formats
     * @return array<string, mixed> the stored recording
     */
    public function seedRecording(string $meetingID, array $formats, array $extra = []): array
    {
        return $this->request('POST', '/__mock/recordings', ['meetingID' => $meetingID, 'formats' => $formats] + $extra);
    }

    /** @return array<string, mixed> */
    public function state(): array
    {
        return $this->request('GET', '/__mock/state');
    }

    /** @return array{call: string, params: array<string, string>} */
    public function lastRequest(string $call): array
    {
        $matches = array_values(array_filter($this->state()['requests'], static fn (array $r): bool => $r['call'] === $call));
        TestCase::assertNotEmpty($matches, "No {$call} request was made");

        return end($matches);
    }

    /** Requests a join URL without following redirects; returns the Location header. */
    public function followJoin(string $joinUrl): string
    {
        $context = stream_context_create(['http' => ['follow_location' => 0, 'ignore_errors' => true, 'timeout' => 5]]);
        file_get_contents($joinUrl, false, $context);
        foreach ($http_response_header ?? [] as $header) {
            if (stripos($header, 'location:') === 0) {
                return trim(substr($header, 9));
            }
        }

        return '';
    }

    private function isUp(): bool
    {
        $context = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);

        return @file_get_contents($this->baseUrl.'/__mock/health', false, $context) !== false;
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => "content-type: application/json\r\n",
            'content' => $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR),
            'timeout' => 5,
        ]]);
        $response = file_get_contents($this->baseUrl.$path, false, $context);
        TestCase::assertIsString($response, "Mock BBB {$method} {$path} failed");

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }
}
