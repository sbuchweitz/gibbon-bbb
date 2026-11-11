<?php

declare(strict_types=1);

/*
 * bigbluebutton-api-php 3.0 calls mb_trim() unqualified inside its own namespace,
 * but mb_trim() only exists from PHP 8.4. PHP resolves unqualified function calls
 * in the current namespace first, so this fallback keeps the library working on 8.2/8.3.
 */

namespace BigBlueButton;

if (!\function_exists('mb_trim') && !\function_exists(__NAMESPACE__.'\\mb_trim')) {
    function mb_trim(string $string, ?string $characters = null, ?string $encoding = null): string
    {
        if ($characters === null) {
            return \preg_replace('/^[\s\x{0}\x{0B}]+|[\s\x{0}\x{0B}]+$/u', '', $string) ?? \trim($string);
        }

        $quoted = \preg_quote($characters, '/');

        return \preg_replace('/^['.$quoted.']+|['.$quoted.']+$/u', '', $string) ?? \trim($string, $characters);
    }
}
