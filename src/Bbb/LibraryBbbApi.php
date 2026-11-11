<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Bbb;

use BigBlueButton\BigBlueButton;
use BigBlueButton\Enum\Role;
use BigBlueButton\Parameters\CreateMeetingParameters;
use BigBlueButton\Parameters\GetMeetingInfoParameters;
use BigBlueButton\Parameters\GetRecordingsParameters;
use BigBlueButton\Parameters\JoinMeetingParameters;
use BigBlueButton\Responses\BaseResponse;
use Gibbon\Module\BigBlueButton\Recording\RecordingXmlParser;

/**
 * BbbApi backed by the bundled bigbluebutton/bigbluebutton-api-php library.
 */
final class LibraryBbbApi implements BbbApi
{
    public const CONNECT_TIMEOUT_SECONDS = 3;
    /** The library only sets a connect timeout; without a total timeout a stalled server hangs lesson pages. */
    public const TOTAL_TIMEOUT_SECONDS = 8;

    public function __construct(
        private readonly BigBlueButton $client,
        private readonly string $secret,
        private readonly RecordingXmlParser $parser = new RecordingXmlParser(),
    ) {
    }

    public static function connect(string $serverUrl, string $secret): self
    {
        $client = new BigBlueButton($serverUrl, $secret, ['curl' => [
            CURLOPT_TIMEOUT => self::TOTAL_TIMEOUT_SECONDS,
            // The library follows redirects; keep them to http(s) and a short chain.
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_MAXREDIRS => 3,
        ]]);
        $client->setTimeOut(self::CONNECT_TIMEOUT_SECONDS);

        return new self($client, $secret);
    }

    public static function createParameters(MeetingSpec $spec): CreateMeetingParameters
    {
        $params = (new CreateMeetingParameters($spec->meetingID, $spec->name))
            ->setRecord(true)
            ->setAutoStartRecording(false)
            ->setAllowStartStopRecording(true)
            ->setDuration($spec->durationMinutes)
            ->setLogoutUrl($spec->logoutUrl);
        if ($spec->allowRequestsWithoutSession) {
            $params->setAllowRequestsWithoutSession(true);
        }
        foreach ($spec->metadata as $key => $value) {
            $params->addMeta($key, $value);
        }

        return $params;
    }

    public static function joinParameters(JoinSpec $spec): JoinMeetingParameters
    {
        return (new JoinMeetingParameters($spec->meetingID, $spec->fullName, $spec->moderator ? Role::MODERATOR : Role::VIEWER))
            ->setUserId($spec->userID)
            ->setRedirect(true);
    }

    public function createMeeting(MeetingSpec $spec): void
    {
        $response = $this->call(fn () => $this->client->createMeeting(self::createParameters($spec)));
        $this->assertSuccess($response);
    }

    public function meetingExists(string $meetingID): bool
    {
        $response = $this->call(fn () => $this->client->getMeetingInfo(new GetMeetingInfoParameters($meetingID)));
        if ($response->failed() && $response->getMessageKey() === 'notFound') {
            return false;
        }
        $this->assertSuccess($response);

        return true;
    }

    public function joinUrl(JoinSpec $spec): string
    {
        return $this->call(fn () => $this->client->getJoinMeetingURL(self::joinParameters($spec)));
    }

    public function recordings(array $meetingIDs): array
    {
        if ($meetingIDs === []) {
            return [];
        }
        $params = new GetRecordingsParameters();
        $params->setMeetingId(implode(',', $meetingIDs));
        $response = $this->call(fn () => $this->client->getRecordings($params));

        return $this->parser->parse((string) $response->getRawXml()->asXML());
    }

    public function testConnection(): string
    {
        $response = $this->call(fn () => $this->client->getMeetings());
        $this->assertSuccess($response);
        $version = $this->call(fn () => $this->client->getApiVersion());

        return $version->getVersion() ?: 'unknown';
    }

    /**
     * @template T
     * @param \Closure(): T $request
     * @return T
     */
    private function call(\Closure $request): mixed
    {
        // The library parses responses with SimpleXML and emits warnings on bad/empty bodies; turn them into exceptions.
        $previousLibxml = libxml_use_internal_errors(true);
        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, 0, $severity);
        });
        try {
            return $request();
        } catch (BbbException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new BbbException(UrlRedactor::redact($e->getMessage(), $this->secret), 'requestFailed');
        } finally {
            restore_error_handler();
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxml);
        }
    }

    private function assertSuccess(BaseResponse $response): void
    {
        if (!$response->success()) {
            $message = $response->getMessage() ?: 'BigBlueButton returned '.($response->getReturnCode() ?: 'no return code');
            throw new BbbException(UrlRedactor::redact($message, $this->secret), $response->getMessageKey());
        }
    }
}
