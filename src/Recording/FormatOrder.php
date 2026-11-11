<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Recording;

/** Display ordering and filtering of playback formats. */
final class FormatOrder
{
    private const KNOWN = ['presentation', 'video', 'transcript', 'summary'];

    /**
     * @param list<PlaybackFormat> $formats
     * @return list<PlaybackFormat>
     */
    public function sort(array $formats): array
    {
        $byType = [];
        foreach ($formats as $format) {
            $byType[$format->type] ??= $format;
        }
        $unique = array_values($byType);
        usort($unique, fn (PlaybackFormat $a, PlaybackFormat $b): int => $this->rank($a) <=> $this->rank($b)
            ?: strcmp($a->type, $b->type));

        return $unique;
    }

    /**
     * @param list<PlaybackFormat> $formats
     * @return list<PlaybackFormat>
     */
    public function presentationOnly(array $formats): array
    {
        return array_values(array_filter(
            $formats,
            static fn (PlaybackFormat $f): bool => str_contains($f->type, 'presentation')
        ));
    }

    private function rank(PlaybackFormat $format): int
    {
        $index = array_search($format->type, self::KNOWN, true);

        return $index === false ? count(self::KNOWN) : $index;
    }
}
