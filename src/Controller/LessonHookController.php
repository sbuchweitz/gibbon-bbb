<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Controller;

use Gibbon\Module\BigBlueButton\Config\JoinMode;
use Gibbon\Module\BigBlueButton\Domain\Lesson;
use Gibbon\Module\BigBlueButton\Domain\LiveState;
use Gibbon\Module\BigBlueButton\Gibbon\ModuleServices;
use Gibbon\Module\BigBlueButton\Http\View;

/**
 * Renders the module's sections on the lesson page (core's "Lesson Planner" hooks). Never calls BigBlueButton
 * for the live section; the recordings section uses the cached catalog.
 */
final class LessonHookController
{
    public const LIVE_HOOK = 'View live sessions';
    public const RECORDED_HOOK = 'View recorded sessions';

    public function __construct(private readonly ModuleServices $services, private readonly View $view)
    {
    }

    public function render(int $plannerEntryID, string $hookName, ?int $childID): string
    {
        $lesson = $plannerEntryID > 0 ? $this->services->lessons->lesson($plannerEntryID) : null;
        if ($lesson === null || !$this->services->settings->enabled) {
            return '';
        }
        $notice = $hookName === self::LIVE_HOOK ? $this->adminNotice() : '';
        if (!$this->services->settings->isConfigured()) {
            return $notice;
        }

        return $notice.match ($hookName) {
            self::LIVE_HOOK => $this->live($lesson, $childID),
            self::RECORDED_HOOK => $this->recordings($lesson, $childID),
            default => '',
        };
    }

    /** Configuration problems, shown on lesson pages to people who can fix them. */
    private function adminNotice(): string
    {
        $settings = $this->services->settings;
        $message = match (true) {
            !$this->services->context->canManageSettings => null,
            !$settings->isConfigured() => 'BigBlueButton is enabled, but the server URL or shared secret is missing.',
            $settings->secretRotationRequired => 'After upgrading to version 2.0, replace the BigBlueButton shared secret: change it on the BigBlueButton server (bbb-conf --setsecret) and enter the new one in the settings.',
            default => null,
        };

        return $message === null ? '' : $this->view->render('notice', ['message' => $message, 'settingsUrl' => $this->settingsUrl()]);
    }

    private function live(Lesson $lesson, ?int $childID): string
    {
        if (!$this->services->hasVideoChat($lesson)) {
            return '';
        }
        $window = $lesson->window($this->services->context->timezone);
        $state = $window->state($this->services->now());
        if ($state === LiveState::Closed) {
            return '';
        }
        $page = $this->services->settings->joinMode === JoinMode::Direct ? 'bbb_join.php' : 'bbb_live.php';

        return $this->view->render('lesson-live', [
            'open' => $state === LiveState::Open,
            'opensAt' => $window->opensAt(),
            'sameDay' => $window->opensAt()->format('Y-m-d') === $this->services->now()->format('Y-m-d'),
            'joinUrl' => $this->services->context->moduleUrl($page).'?'.self::query($lesson->id, $childID),
            'assetBase' => $this->services->context->moduleUrl(),
        ]);
    }

    private function recordings(Lesson $lesson, ?int $childID): string
    {
        $meetings = $this->services->meetings();
        $meetingIDs = $meetings->recordingMeetingIDs($lesson, $this->services->hasVideoChat($lesson));
        if ($meetingIDs === []) {
            return '';
        }
        $result = $this->services->recordings()->forLesson($lesson, $meetingIDs, $this->services->ids->instanceHash, $this->services->context->timezone);
        $views = $this->services->presenter()->present($result->recordings, $lesson->id, $this->services->isPresentationOnly($lesson), $childID);
        // The "no recordings yet" note only makes sense for a lesson that actually held a meeting.
        $heldAndOver = $meetings->hasMeeting($lesson) && $lesson->window($this->services->context->timezone)->lessonEnd < $this->services->now();
        if ($views === [] && !$heldAndOver) {
            return '';
        }

        return $this->view->render('lesson-recordings', [
            'recordings' => $views,
            'failed' => $result->failed,
            'assetBase' => $this->services->context->moduleUrl(),
        ]);
    }

    private function settingsUrl(): string
    {
        return rtrim($this->services->context->absoluteURL, '/').'/index.php?q=/modules/BigBlueButton/bbb_settings.php';
    }

    public static function query(int $plannerEntryID, ?int $childID): string
    {
        $params = ['gibbonPlannerEntryID' => $plannerEntryID];
        if ($childID !== null) {
            $params['search'] = $childID;
        }

        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
