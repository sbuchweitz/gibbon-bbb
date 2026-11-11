<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Service;

use Gibbon\Module\BigBlueButton\Bbb\BbbApi;
use Gibbon\Module\BigBlueButton\Bbb\BbbException;
use Gibbon\Module\BigBlueButton\Database\RecordingCacheGateway;
use Gibbon\Module\BigBlueButton\Domain\Clock;
use Gibbon\Module\BigBlueButton\Domain\Lesson;
use Gibbon\Module\BigBlueButton\Recording\CacheTtlPolicy;
use Gibbon\Module\BigBlueButton\Recording\Recording;
use Gibbon\Module\BigBlueButton\Recording\RecordingFilter;

/**
 * A lesson's recordings, cached per lesson so viewing lessons doesn't call BigBlueButton every time.
 * When BigBlueButton is unreachable the last known list is served (and the failure cached briefly).
 */
final class RecordingCatalog
{
    /** A forced refresh (unknown recordID on the viewer page) is honoured at most this often per lesson. */
    public const MIN_FORCED_REFRESH_SECONDS = 30;

    public function __construct(
        private readonly RecordingCacheGateway $cache,
        private readonly BbbApi $bbb,
        private readonly Clock $clock,
        private readonly CacheTtlPolicy $ttl = new CacheTtlPolicy(),
        private readonly RecordingFilter $filter = new RecordingFilter(),
    ) {
    }

    /** @param list<string> $meetingIDs */
    public function forLesson(Lesson $lesson, array $meetingIDs, string $instanceHash, \DateTimeZone $timezone, bool $forceRefresh = false): CatalogResult
    {
        if ($meetingIDs === []) {
            return new CatalogResult([], false);
        }
        $now = $this->clock->now();
        $cached = $this->readCache($lesson->id, $meetingIDs);
        $lessonEnd = $lesson->window($timezone)->lessonEnd;

        if ($cached !== null) {
            $ttl = $forceRefresh ? self::MIN_FORCED_REFRESH_SECONDS : $this->ttl->ttlSeconds($cached['result']->recordings !== [], $cached['result']->failed, $lessonEnd, $now);
            if ($cached['fetchedAt']->modify(sprintf('+%d seconds', $ttl)) > $now) {
                return $cached['result'];
            }
        }

        try {
            $result = new CatalogResult($this->filter->forLesson($this->bbb->recordings($meetingIDs), $meetingIDs, $instanceHash), false);
        } catch (BbbException) {
            $result = new CatalogResult($cached['result']->recordings ?? [], true);
        }
        $this->writeCache($lesson->id, $meetingIDs, $result, $now);

        return $result;
    }

    /**
     * @param list<string> $meetingIDs
     * @return array{result: CatalogResult, fetchedAt: \DateTimeImmutable}|null
     */
    private function readCache(int $plannerEntryID, array $meetingIDs): ?array
    {
        $entry = $this->cache->get($plannerEntryID);
        $data = $entry === null ? null : json_decode($entry->payload, true);
        if (!is_array($data) || ($data['ids'] ?? null) !== $meetingIDs || !is_array($data['recordings'] ?? null)) {
            return null; // missing, corrupt, or the lesson's meeting IDs changed
        }
        try {
            $recordings = array_map(static fn (mixed $item): Recording => Recording::fromArray(is_array($item) ? $item : []), array_values($data['recordings']));
        } catch (\InvalidArgumentException) {
            return null;
        }

        return ['result' => new CatalogResult($recordings, ($data['failed'] ?? false) === true), 'fetchedAt' => $entry->fetchedAt];
    }

    /** @param list<string> $meetingIDs */
    private function writeCache(int $plannerEntryID, array $meetingIDs, CatalogResult $result, \DateTimeImmutable $now): void
    {
        $payload = json_encode([
            'ids' => $meetingIDs,
            'failed' => $result->failed,
            'recordings' => array_map(static fn (Recording $r): array => $r->toArray(), $result->recordings),
        ], JSON_THROW_ON_ERROR);
        $this->cache->put($plannerEntryID, $payload, $now);
    }
}
