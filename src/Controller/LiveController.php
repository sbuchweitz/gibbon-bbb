<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Controller;

use Gibbon\Module\BigBlueButton\Bbb\BbbException;
use Gibbon\Module\BigBlueButton\Config\JoinMode;
use Gibbon\Module\BigBlueButton\Domain\JoinState;
use Gibbon\Module\BigBlueButton\Gibbon\ModuleServices;
use Gibbon\Module\BigBlueButton\Http\Response;
use Gibbon\Module\BigBlueButton\Http\View;
use Gibbon\Module\BigBlueButton\Recording\PlaybackUrlRewriter;
use Gibbon\Module\BigBlueButton\Service\JoinDecision;
use Gibbon\Module\BigBlueButton\Service\LessonAccessResult;
use Gibbon\Module\BigBlueButton\Service\Participant;
use Gibbon\Module\BigBlueButton\Service\Viewer;
use Gibbon\Module\BigBlueButton\Support\Log;

/**
 * The page "Join Class (New Tab)" opens: bbb_live.php embeds the class, bbb_join.php redirects to it.
 * Both show a waiting page (polling bbb_live_status.php) until a moderator has started the meeting.
 */
final class LiveController
{
    public function __construct(private readonly ModuleServices $services, private readonly View $view)
    {
    }

    public function page(int $plannerEntryID, ?int $childID, JoinMode $mode): Response
    {
        $access = $this->checkAccess($plannerEntryID, $childID);
        if ($access instanceof Response) {
            return $access;
        }
        $lesson = $access->lesson;
        assert($lesson !== null);
        $query = LessonHookController::query($lesson->id, $childID);

        try {
            $decision = $this->services->meetings()->join($lesson, new Participant($this->services->context->personID, $this->services->context->displayName, $access->moderator));
        } catch (BbbException $e) {
            Log::error('join lesson '.$lesson->id, $e, $this->services->settings->secret);

            return $this->message('BigBlueButton cannot be reached right now. Please try again in a moment.', 503, $lesson->name);
        }

        return match ($decision->state) {
            JoinState::Ready => $mode === JoinMode::Direct ? Response::redirect((string) $decision->url) : $this->embedded($decision, $lesson->name, $lesson->classLabel(), $query),
            JoinState::Waiting, JoinState::Upcoming => $this->waiting($decision, $lesson->name, $lesson->classLabel(), $query),
            JoinState::Closed => $this->message('This class has ended. Any recordings will appear on the lesson page once they have been processed.', 200, $lesson->name),
        };
    }

    public function status(int $plannerEntryID, ?int $childID): Response
    {
        $access = $this->checkAccess($plannerEntryID, $childID);
        if ($access instanceof Response) {
            return Response::json(['state' => 'denied'], $access->status);
        }
        assert($access->lesson !== null);
        try {
            return Response::json(['state' => $this->services->meetings()->status($access->lesson, $access->moderator)->value]);
        } catch (BbbException $e) {
            Log::error('status lesson '.$plannerEntryID, $e, $this->services->settings->secret);

            return Response::json(['state' => 'error'], 503);
        }
    }

    private function checkAccess(int $plannerEntryID, ?int $childID): LessonAccessResult|Response
    {
        $context = $this->services->context;
        $viewer = new Viewer($context->personID, $context->highestAction);
        $access = $this->services->access->check($plannerEntryID, $viewer, $childID, $this->services->now()->format('Y-m-d'));
        if (!$access->allowed || $access->lesson === null) {
            return $this->message('The selected record does not exist, or you do not have access to it.', 403);
        }
        if (!$this->services->settings->isConfigured()) {
            return $this->message('Video classes are not set up on this Gibbon site yet.', 503, $access->lesson->name);
        }
        if (!$this->services->hasVideoChat($access->lesson)) {
            return $this->message('This lesson does not have a video class.', 404, $access->lesson->name);
        }

        return $access;
    }

    private function embedded(JoinDecision $decision, string $lessonName, string $classLabel, string $query): Response
    {
        $url = (string) $decision->url;
        $html = $this->view->render('live', [
            'title' => $lessonName,
            'subtitle' => $classLabel,
            'joinUrl' => $url,
            'directUrl' => $this->services->context->moduleUrl('bbb_join.php').'?'.$query,
            'assetBase' => $this->services->context->moduleUrl(),
        ]);

        return Response::page($html, 200, array_values(array_filter([PlaybackUrlRewriter::origin($url), PlaybackUrlRewriter::origin($this->services->settings->serverUrl)])));
    }

    private function waiting(JoinDecision $decision, string $lessonName, string $classLabel, string $query): Response
    {
        return Response::page($this->view->render('waiting', [
            'title' => $lessonName,
            'subtitle' => $classLabel,
            'upcoming' => $decision->state === JoinState::Upcoming,
            'opensAt' => $decision->opensAt,
            'statusUrl' => $this->services->context->moduleUrl('bbb_live_status.php').'?'.$query,
            'assetBase' => $this->services->context->moduleUrl(),
        ]));
    }

    private function message(string $message, int $status, string $title = 'BigBlueButton'): Response
    {
        return Response::page($this->view->render('message', ['title' => $title, 'message' => $message, 'assetBase' => $this->services->context->moduleUrl()]), $status);
    }
}
