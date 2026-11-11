<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Unit\Domain;

use Gibbon\Module\BigBlueButton\Domain\MeetingIdFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MeetingIdFactory::class)]
final class MeetingIdFactoryTest extends TestCase
{
    public function testInstanceHashIsStablePerGuidAndDiffersBetweenInstallations(): void
    {
        $a = MeetingIdFactory::forGuid('vimqjcudv-gp-m0nu-hu8-b6rkkn0q3yi');

        self::assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $a->instanceHash);
        self::assertSame($a->instanceHash, MeetingIdFactory::forGuid('vimqjcudv-gp-m0nu-hu8-b6rkkn0q3yi')->instanceHash);
        self::assertNotSame($a->instanceHash, MeetingIdFactory::forGuid('another-install-guid')->instanceHash);
    }

    public function testNewMeetingIdsAreScopedToTheInstanceAndLesson(): void
    {
        $factory = new MeetingIdFactory('0123456789ab');
        $id = $factory->create(42);

        self::assertMatchesRegularExpression('/^gibbon-0123456789ab-42-[0-9a-f]{8}$/', $id);
        self::assertLessThanOrEqual(64, strlen($id));
        self::assertNotSame($id, $factory->create(42), 'random suffix avoids reuse after a database restore');
    }

    public function testLegacyIdsMatchVersionOne(): void
    {
        self::assertSame('planner42', MeetingIdFactory::legacy(42));
    }

    public function testRejectsMalformedInstanceHashes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MeetingIdFactory('not-a-hash');
    }

    public function testRejectsNonPositiveLessonIds(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new MeetingIdFactory('0123456789ab'))->create(0);
    }
}
