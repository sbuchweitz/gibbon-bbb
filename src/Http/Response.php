<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Http;

final readonly class Response
{
    /** @param array<string, string> $headers */
    public function __construct(public int $status, public array $headers, public string $body)
    {
    }

    /** @param list<string> $frameOrigins origins the page may embed in an iframe */
    public static function page(string $html, int $status = 200, array $frameOrigins = []): self
    {
        return new self($status, SecurityHeaders::forPage($frameOrigins) + ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    /** @param array<string, scalar|null> $data */
    public static function json(array $data, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff'], (string) json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
    }

    public static function redirect(string $url): self
    {
        return new self(302, ['Location' => $url, 'Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer'], '');
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name.': '.str_replace(["\r", "\n"], '', $value));
        }
        echo $this->body;
    }
}
