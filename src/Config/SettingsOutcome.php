<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Config;

final readonly class SettingsOutcome
{
    /**
     * @param string $returnCode Gibbon return code for the redirect (success0, warning1, error3)
     * @param list<string> $errors untranslated validation messages
     * @param string|null $message untranslated connection test result
     */
    public function __construct(public string $returnCode, public array $errors = [], public ?string $message = null)
    {
    }
}
