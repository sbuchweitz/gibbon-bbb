<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Recording;

use Gibbon\Module\BigBlueButton\Bbb\BbbException;

/** Parses a BigBlueButton getRecordings API response. */
final class RecordingXmlParser
{
    /** @return list<Recording> */
    public function parse(string $xml): array
    {
        $root = $this->load($xml);
        $code = trim((string) $root->returncode);
        if ($code === 'FAILED') {
            throw new BbbException(trim((string) $root->message), trim((string) $root->messageKey));
        }
        if ($code !== 'SUCCESS') {
            throw $this->invalid();
        }
        if (trim((string) $root->messageKey) === 'noRecordings') {
            return [];
        }

        $out = [];
        foreach ($root->recordings->recording ?? [] as $node) {
            $recording = $this->recording($node);
            if ($recording !== null) {
                $out[] = $recording;
            }
        }

        return $out;
    }

    private function load(string $xml): \SimpleXMLElement
    {
        if (trim($xml) === '') {
            throw $this->invalid();
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $root = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            libxml_clear_errors();
        } finally {
            libxml_use_internal_errors($previous);
        }
        if ($root === false) {
            throw $this->invalid();
        }

        return $root;
    }

    private function invalid(): BbbException
    {
        return new BbbException('Invalid XML response from BigBlueButton', 'invalidXml');
    }

    private function recording(\SimpleXMLElement $node): ?Recording
    {
        $recordID = trim((string) $node->recordID);
        $meetingID = trim((string) $node->meetingID);
        if ($recordID === '' || $meetingID === '') {
            return null;
        }

        return new Recording(
            $recordID,
            $meetingID,
            trim((string) $node->name),
            trim((string) $node->published) === 'true',
            $this->millis((string) $node->startTime),
            $this->millis((string) $node->endTime),
            $this->metadata($node),
            $this->formats($node),
        );
    }

    private function millis(string $value): int
    {
        $value = trim($value);
        if (ctype_digit($value)) {
            return (int) $value;
        }

        return is_numeric($value) ? max(0, (int) (float) $value) : 0;
    }

    /** @return array<string,string> */
    private function metadata(\SimpleXMLElement $node): array
    {
        $out = [];
        foreach ($node->metadata->children() ?? [] as $name => $child) {
            $out[strtolower((string) $name)] = trim((string) $child);
        }

        return $out;
    }

    /** @return list<PlaybackFormat> */
    private function formats(\SimpleXMLElement $node): array
    {
        $out = [];
        foreach ($node->playback->format ?? [] as $format) {
            $type = strtolower(trim((string) $format->type));
            $url = trim((string) $format->url);
            if ($type !== '' && $url !== '') {
                $out[] = new PlaybackFormat($type, $url);
            }
        }

        return $out;
    }
}
