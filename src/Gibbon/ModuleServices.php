<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Gibbon;

use Gibbon\Module\BigBlueButton\Bbb\BbbApi;
use Gibbon\Module\BigBlueButton\Bbb\LibraryBbbApi;
use Gibbon\Module\BigBlueButton\Config\Settings;
use Gibbon\Module\BigBlueButton\Config\SettingsRepository;
use Gibbon\Module\BigBlueButton\Database\LessonGateway;
use Gibbon\Module\BigBlueButton\Database\MeetingGateway;
use Gibbon\Module\BigBlueButton\Database\RecordingCacheGateway;
use Gibbon\Module\BigBlueButton\Domain\Clock;
use Gibbon\Module\BigBlueButton\Domain\Lesson;
use Gibbon\Module\BigBlueButton\Domain\MeetingIdFactory;
use Gibbon\Module\BigBlueButton\Domain\SystemClock;
use Gibbon\Module\BigBlueButton\Recording\PlaybackUrlRewriter;
use Gibbon\Module\BigBlueButton\Recording\RecordingPresenter;
use Gibbon\Module\BigBlueButton\Service\LessonAccess;
use Gibbon\Module\BigBlueButton\Service\MeetingService;
use Gibbon\Module\BigBlueButton\Service\RecordingCatalog;

/**
 * Composition root: wires the module's services for one request.
 */
final class ModuleServices
{
    public readonly Settings $settings;
    public readonly LessonGateway $lessons;
    public readonly LessonAccess $access;
    public readonly MeetingIdFactory $ids;
    private readonly Clock $clock;
    private ?BbbApi $bbb;
    /** @var array<string, int>|null */
    private ?array $fieldIDs = null;

    public function __construct(private readonly \PDO $pdo, public readonly RequestContext $context, ?BbbApi $bbb = null, ?Clock $clock = null)
    {
        $this->settings = (new SettingsRepository($pdo))->load();
        $this->lessons = new LessonGateway($pdo);
        $this->access = new LessonAccess($this->lessons);
        $this->ids = MeetingIdFactory::forGuid($context->guid);
        $this->clock = $clock ?? new SystemClock($context->timezone);
        $this->bbb = $bbb;
    }

    public function meetings(): MeetingService
    {
        return new MeetingService(
            new MeetingGateway($this->pdo),
            $this->bbb(),
            $this->ids,
            $this->settings,
            $this->clock,
            $this->context->timezone,
            $this->context->moduleUrl('bbb_ended.php'),
            $this->context->serverName(),
        );
    }

    public function recordings(): RecordingCatalog
    {
        return new RecordingCatalog(new RecordingCacheGateway($this->pdo), $this->bbb(), $this->clock);
    }

    public function presenter(): RecordingPresenter
    {
        return new RecordingPresenter(new PlaybackUrlRewriter($this->settings->recordingHostOverride), $this->context->timezone, $this->context->moduleUrl('bbb_recording.php'));
    }

    public function now(): \DateTimeImmutable
    {
        return $this->clock->now();
    }

    public function hasVideoChat(Lesson $lesson): bool
    {
        $value = $this->fieldValue($lesson, LessonGateway::VIDEO_CHAT_FIELD);

        return $value !== null && in_array('Include', array_map('trim', explode(',', $value)), true);
    }

    public function isPresentationOnly(Lesson $lesson): bool
    {
        return in_array($this->fieldValue($lesson, LessonGateway::PRESENTATION_ONLY_FIELD), ['Y', 'Yes'], true);
    }

    private function fieldValue(Lesson $lesson, string $fieldName): ?string
    {
        $this->fieldIDs ??= $this->lessons->lessonCustomFieldIDs();
        $id = $this->fieldIDs[$fieldName] ?? null;

        return $id === null ? null : $lesson->fieldValue($id);
    }

    private function bbb(): BbbApi
    {
        if (!$this->settings->isConfigured()) {
            throw new \LogicException('BigBlueButton is not configured.');
        }

        return $this->bbb ??= LibraryBbbApi::connect($this->settings->serverUrl, $this->settings->secret);
    }
}
