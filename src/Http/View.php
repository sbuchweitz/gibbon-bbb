<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Http;

use Gibbon\Module\BigBlueButton\Support\Translator;

/**
 * Renders views/*.php. Templates get $v (variables), $e (escape) and $t (translate + escape).
 */
final class View
{
    public function __construct(private readonly string $templateDir, private readonly Translator $translator = new Translator())
    {
    }

    /** @param array<string, mixed> $vars */
    public function render(string $template, array $vars = []): string
    {
        $file = $this->templateDir.'/'.$template.'.php';
        if (preg_match('/^[a-z][a-z0-9-]*$/', $template) !== 1 || !is_file($file)) {
            throw new \InvalidArgumentException('Unknown view '.$template);
        }
        $e = static fn (mixed $value): string => self::e($value);
        $t = fn (string $text, array $params = []): string => self::e($this->translator->translate($text, $params));

        ob_start();
        try {
            (static function (string $__file, array $v, \Closure $e, \Closure $t): void {
                require $__file;
            })($file, $vars, $e, $t);

            return (string) ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
