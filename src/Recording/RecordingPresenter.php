<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Recording;

/**
 * Turns a lesson's recordings into button view models: one recording per row, one button per playback format.
 */
final class RecordingPresenter
{
    /** Not "recordID": Gibbon v25 strips everything but letters and digits from URL parameters ending in "ID". */
    public const VIEWER_RECORDING_PARAMETER = 'recording';

    private const LABELS = ['presentation' => 'Presentation', 'video' => 'Video', 'transcript' => 'Transcript', 'summary' => 'Summary'];

    public function __construct(
        private readonly PlaybackUrlRewriter $rewriter,
        private readonly \DateTimeZone $timezone,
        private readonly string $viewerBaseUrl,
        private readonly FormatOrder $order = new FormatOrder(),
        private readonly DownloadUrlResolver $downloads = new DownloadUrlResolver(),
    ) {
    }

    /**
     * @param list<Recording> $recordings
     * @param int|null $childID forwarded so parents keep access on the viewer page
     * @return list<RecordingView>
     */
    public function present(array $recordings, int $plannerEntryID, bool $presentationOnly, ?int $childID): array
    {
        usort($recordings, static fn (Recording $a, Recording $b): int => $a->startTime <=> $b->startTime);
        $views = [];
        foreach ($recordings as $recording) {
            $links = $this->links($recording, $plannerEntryID, $presentationOnly, $childID);
            if ($links !== []) {
                $views[] = new RecordingView(
                    recordID: $recording->recordID,
                    number: count($views) + 1,
                    startedAt: (new \DateTimeImmutable('@'.intdiv($recording->startTime, 1000)))->setTimezone($this->timezone),
                    durationMinutes: max(0, (int) round(($recording->endTime - $recording->startTime) / 60_000)),
                    links: $links,
                );
            }
        }

        return $views;
    }

    /** @return list<FormatLink> */
    private function links(Recording $recording, int $plannerEntryID, bool $presentationOnly, ?int $childID): array
    {
        $formats = $this->order->sort($recording->formats);
        if ($presentationOnly) {
            $formats = $this->order->presentationOnly($formats);
        }

        $links = [];
        foreach ($formats as $format) {
            $url = $this->rewriter->rewrite($format->url);
            if ($url === null) {
                continue;
            }
            $viewer = $this->viewerUrl($plannerEntryID, $recording->recordID, $format->type, $childID);
            $download = $this->downloads->downloadUrl($format->withUrl($url));
            $links[] = new FormatLink($format->type, self::label($format->type), $download ?? $viewer, $viewer, $url, $download !== null);
        }

        return $links;
    }

    private function viewerUrl(int $plannerEntryID, string $recordID, string $type, ?int $childID): string
    {
        $query = ['gibbonPlannerEntryID' => $plannerEntryID, self::VIEWER_RECORDING_PARAMETER => $recordID, 'format' => $type];
        if ($childID !== null && $childID > 0) {
            $query['search'] = $childID;
        }

        return $this->viewerBaseUrl.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? ucwords(str_replace(['-', '_'], ' ', $type));
    }
}
