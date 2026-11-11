<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Http;

/**
 * Headers for the module's standalone pages. Join URLs are personal and short-lived, so pages are never cached.
 */
final class SecurityHeaders
{
    /**
     * @param list<string> $frameOrigins
     * @return array<string, string>
     */
    public static function forPage(array $frameOrigins): array
    {
        // https: lets load-balanced BBB setups (e.g. Scalelite) redirect the frame to a node host.
        $frameSrc = implode(' ', array_unique(["'self'", 'https:', ...$frameOrigins]));

        return [
            'Cache-Control' => 'no-store',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'Content-Security-Policy' => "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; "
                ."frame-src {$frameSrc}; frame-ancestors 'self'; base-uri 'none'; form-action 'self'",
        ];
    }
}
