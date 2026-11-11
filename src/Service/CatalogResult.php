<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Service;

use Gibbon\Module\BigBlueButton\Recording\Recording;

final readonly class CatalogResult
{
    /**
     * @param list<Recording> $recordings
     * @param bool $failed BigBlueButton could not be reached; $recordings may be the last known list
     */
    public function __construct(public array $recordings, public bool $failed)
    {
    }

    public function find(string $recordID): ?Recording
    {
        foreach ($this->recordings as $recording) {
            if ($recording->recordID === $recordID) {
                return $recording;
            }
        }

        return null;
    }
}
