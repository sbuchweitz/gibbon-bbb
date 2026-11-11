<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Database;

final class RecordingCacheGateway
{
    private const DATETIME = 'Y-m-d H:i:s';

    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function get(int $plannerEntryID): ?CachedPayload
    {
        $statement = $this->pdo->prepare('SELECT payload, timestampFetched FROM bigBlueButtonRecordingCache WHERE gibbonPlannerEntryID=:id');
        $statement->execute(['id' => $plannerEntryID]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $fetchedAt = \DateTimeImmutable::createFromFormat(self::DATETIME, (string) $row['timestampFetched'], new \DateTimeZone('UTC'));

        return $fetchedAt === false ? null : new CachedPayload((string) $row['payload'], $fetchedAt);
    }

    public function put(int $plannerEntryID, string $payload, \DateTimeImmutable $fetchedAt): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO bigBlueButtonRecordingCache (gibbonPlannerEntryID, payload, timestampFetched) VALUES (:id, :payload, :fetched)
             ON DUPLICATE KEY UPDATE payload=VALUES(payload), timestampFetched=VALUES(timestampFetched)'
        );
        $statement->execute([
            'id' => $plannerEntryID,
            'payload' => $payload,
            'fetched' => $fetchedAt->setTimezone(new \DateTimeZone('UTC'))->format(self::DATETIME),
        ]);
    }
}
