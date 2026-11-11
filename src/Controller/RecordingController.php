<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Controller;

use Gibbon\Module\BigBlueButton\Bbb\BbbException;
use Gibbon\Module\BigBlueButton\Gibbon\ModuleServices;
use Gibbon\Module\BigBlueButton\Http\Response;
use Gibbon\Module\BigBlueButton\Http\View;
use Gibbon\Module\BigBlueButton\Recording\PlaybackUrlRewriter;
use Gibbon\Module\BigBlueButton\Service\Viewer;
use Gibbon\Module\BigBlueButton\Support\Log;

/**
 * bbb_recording.php: one recording format in an iframe. The playback URL is looked up server-side from the
 * lesson's own recordings; nothing in the request is used as a URL.
 */
final class RecordingController
{
    public const RECORD_ID_PATTERN = '/^[0-9a-f]{40}-\d{13}$/';
    public const FORMAT_PATTERN = '/^[a-z][a-z0-9_-]{0,31}$/';

    public function __construct(private readonly ModuleServices $services, private readonly View $view)
    {
    }

    public function page(int $plannerEntryID, ?int $childID, string $recordID, string $format): Response
    {
        if (preg_match(self::RECORD_ID_PATTERN, $recordID) !== 1 || preg_match(self::FORMAT_PATTERN, $format) !== 1) {
            return $this->message('This recording could not be found.', 404);
        }
        $context = $this->services->context;
        $access = $this->services->access->check($plannerEntryID, new Viewer($context->personID, $context->highestAction), $childID, $this->services->now()->format('Y-m-d'));
        $lesson = $access->lesson;
        if (!$access->allowed || $lesson === null) {
            return $this->message('The selected record does not exist, or you do not have access to it.', 403);
        }
        if (!$this->services->settings->isConfigured()) {
            return $this->message('Video classes are not set up on this Gibbon site yet.', 503);
        }

        try {
            $meetingIDs = $this->services->meetings()->recordingMeetingIDs($lesson, $this->services->hasVideoChat($lesson));
            $catalog = $this->services->recordings();
            $load = fn (bool $refresh) => $catalog->forLesson($lesson, $meetingIDs, $this->services->ids->instanceHash, $context->timezone, $refresh);
            $result = $load(false);
            $recordings = $result->find($recordID) === null ? $load(true)->recordings : $result->recordings;
        } catch (BbbException $e) {
            Log::error('recording for lesson '.$lesson->id, $e, $this->services->settings->secret);

            return $this->message('BigBlueButton cannot be reached right now. Please try again in a moment.', 503);
        }

        // Present the whole list so the recording keeps the number it has on the lesson page.
        $views = $this->services->presenter()->present($recordings, $lesson->id, $this->services->isPresentationOnly($lesson), $childID);
        $view = current(array_filter($views, static fn ($candidate) => $candidate->recordID === $recordID)) ?: null;
        $current = $view === null ? null : (current(array_filter($view->links, static fn ($link) => $link->type === $format)) ?: null);
        if ($view === null || $current === null) {
            return $this->message('This recording could not be found.', 404);
        }

        $html = $this->view->render('recording', [
            'title' => $lesson->name,
            'subtitle' => $lesson->classLabel(),
            'recording' => $view,
            'current' => $current,
            'assetBase' => $context->moduleUrl(),
        ]);

        $origin = PlaybackUrlRewriter::origin($current->playbackUrl);

        return Response::page($html, 200, $origin === null ? [] : [$origin]);
    }

    private function message(string $message, int $status): Response
    {
        return Response::page($this->view->render('message', ['title' => 'BigBlueButton', 'message' => $message, 'assetBase' => $this->services->context->moduleUrl()]), $status);
    }
}
