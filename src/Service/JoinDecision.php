<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Service;

use Gibbon\Module\BigBlueButton\Domain\JoinState;

final readonly class JoinDecision
{
    private function __construct(
        public JoinState $state,
        public ?string $url = null,
        public ?\DateTimeImmutable $opensAt = null,
    ) {
    }

    public static function ready(string $url): self
    {
        return new self(JoinState::Ready, $url);
    }

    public static function waiting(): self
    {
        return new self(JoinState::Waiting);
    }

    public static function upcoming(\DateTimeImmutable $opensAt): self
    {
        return new self(JoinState::Upcoming, null, $opensAt);
    }

    public static function closed(): self
    {
        return new self(JoinState::Closed);
    }
}
