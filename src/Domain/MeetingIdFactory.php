<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Domain;

/**
 * BigBlueButton meeting IDs that are unique per Gibbon installation.
 * v1.x used "planner<id>", which collides when several Gibbon instances share a BBB server.
 */
final readonly class MeetingIdFactory
{
    public function __construct(public string $instanceHash)
    {
        if (preg_match('/^[0-9a-f]{12}$/', $instanceHash) !== 1) {
            throw new \InvalidArgumentException('The instance hash must be 12 lowercase hex characters.');
        }
    }

    /** @param string $guid Gibbon's installation GUID ($guid / config.php) */
    public static function forGuid(string $guid): self
    {
        return new self(substr(hash('sha256', 'gibbon-bbb|'.$guid), 0, 12));
    }

    /** A new ID for a lesson's first meeting; stored in bigBlueButtonMeeting and reused from then on. */
    public function create(int $plannerEntryID): string
    {
        self::assertValidLesson($plannerEntryID);

        return sprintf('gibbon-%s-%d-%s', $this->instanceHash, $plannerEntryID, bin2hex(random_bytes(4)));
    }

    /** The ID v1.x used, so recordings made before the upgrade stay visible. */
    public static function legacy(int $plannerEntryID): string
    {
        self::assertValidLesson($plannerEntryID);

        return 'planner'.$plannerEntryID;
    }

    private static function assertValidLesson(int $plannerEntryID): void
    {
        if ($plannerEntryID <= 0) {
            throw new \InvalidArgumentException('Lesson IDs must be positive.');
        }
    }
}
