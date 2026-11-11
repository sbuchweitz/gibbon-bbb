<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Gibbon;

use Gibbon\Contracts\Services\Session;
use Gibbon\Module\BigBlueButton\Controller\LessonHookController;
use Gibbon\Module\BigBlueButton\Http\Response;
use Gibbon\Module\BigBlueButton\Http\View;
use Gibbon\Module\BigBlueButton\Support\Log;

/**
 * The only place that reads Gibbon's globals (session, PDO, guid) and core functions.
 * Entry files call into here after bootstrap.php has confirmed the environment.
 */
final class GibbonEnvironment
{
    public const SETTINGS_ADDRESS = '/modules/BigBlueButton/bbb_settings.php';
    private const PLANNER_ADDRESS = '/modules/Planner/planner_view_full.php';

    public static function view(): View
    {
        return new View(dirname(__DIR__, 2).'/views');
    }

    public static function context(Session $session, \PDO $pdo, string $guid): RequestContext
    {
        $highestAction = \getHighestGroupedAction($guid, self::PLANNER_ADDRESS, $pdo);
        $name = trim((string) $session->get('preferredName').' '.(string) $session->get('surname'));

        return new RequestContext(
            personID: (int) $session->get('gibbonPersonID'),
            displayName: $name !== '' ? $name : (string) $session->get('username'),
            highestAction: is_string($highestAction) ? $highestAction : '',
            absoluteURL: (string) $session->get('absoluteURL'),
            guid: $guid,
            timezone: new \DateTimeZone(date_default_timezone_get()),
            canManageSettings: \isActionAccessible($guid, $pdo, self::SETTINGS_ADDRESS),
        );
    }

    /**
     * Lesson page hook. Never throws: a failure here must not break the lesson page.
     */
    public static function renderHook(Session $session, \PDO $pdo, string $guid, int $plannerEntryID, string $hookName, mixed $search): string
    {
        try {
            $services = new ModuleServices($pdo, self::context($session, $pdo, $guid));

            return (new LessonHookController($services, self::view()))->render($plannerEntryID, $hookName, RequestContext::childID($search));
        } catch (\Throwable $e) {
            Log::error('lesson hook for lesson '.$plannerEntryID, $e);

            return '';
        }
    }

    /**
     * Runs a standalone module page (new-tab pages) after checking sign-in and the page's own permission.
     *
     * @param callable(ModuleServices, View): Response $handler
     */
    public static function runPage(Session $session, \PDO $pdo, string $guid, string $address, callable $handler): void
    {
        $view = self::view();
        $assetBase = rtrim((string) $session->get('absoluteURL'), '/').'/modules/BigBlueButton/';
        $message = static fn (string $text, int $status, ?array $link = null): Response => Response::page(
            $view->render('message', ['title' => 'BigBlueButton', 'message' => $text, 'assetBase' => $assetBase, 'link' => $link]),
            $status,
        );

        if (!$session->has('username')) {
            $message('Please sign in to Gibbon, then open the class again from the lesson page.', 401, ['url' => (string) $session->get('absoluteURL'), 'label' => 'Sign in'])->send();

            return;
        }
        if (!\isActionAccessible($guid, $pdo, $address)) {
            $message('Your request failed because you do not have access to this action.', 403)->send();

            return;
        }

        try {
            $handler(new ModuleServices($pdo, self::context($session, $pdo, $guid)), $view)->send();
        } catch (\Throwable $e) {
            Log::error('page '.$address, $e);
            $message('Something went wrong. Please try again, or ask your Gibbon administrator to check the server log.', 500)->send();
        }
    }
}
