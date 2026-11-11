<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Config;

final readonly class SettingsValidationResult
{
    /**
     * @param array<string, string> $values setting name => value to store (the secret only when changed)
     * @param list<string> $errors untranslated, user-facing messages
     */
    public function __construct(public array $values, public array $errors)
    {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
