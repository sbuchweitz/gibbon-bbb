<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Recording;

/** A BigBlueButton recording. Times are epoch milliseconds. */
final readonly class Recording
{
    /**
     * @param array<string,string> $metadata
     * @param list<PlaybackFormat> $formats
     */
    public function __construct(
        public string $recordID,
        public string $meetingID,
        public string $name,
        public bool $published,
        public int $startTime,
        public int $endTime,
        public array $metadata,
        public array $formats,
    ) {
    }

    /** @param list<PlaybackFormat> $formats */
    public function withFormats(array $formats): self
    {
        return new self(
            $this->recordID,
            $this->meetingID,
            $this->name,
            $this->published,
            $this->startTime,
            $this->endTime,
            $this->metadata,
            $formats,
        );
    }

    public function format(string $type): ?PlaybackFormat
    {
        foreach ($this->formats as $format) {
            if ($format->type === $type) {
                return $format;
            }
        }

        return null;
    }

    public function meta(string $key): ?string
    {
        return $this->metadata[$key] ?? null;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'recordID' => $this->recordID,
            'meetingID' => $this->meetingID,
            'name' => $this->name,
            'published' => $this->published,
            'startTime' => $this->startTime,
            'endTime' => $this->endTime,
            'metadata' => $this->metadata,
            'formats' => array_map(
                static fn (PlaybackFormat $f): array => ['type' => $f->type, 'url' => $f->url],
                $this->formats
            ),
        ];
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            self::string($data, 'recordID'),
            self::string($data, 'meetingID'),
            self::string($data, 'name'),
            self::bool($data, 'published'),
            self::int($data, 'startTime'),
            self::int($data, 'endTime'),
            self::metadata($data['metadata'] ?? null),
            self::formats($data['formats'] ?? null),
        );
    }

    /** @param array<mixed> $data */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value)) {
            throw new \InvalidArgumentException("Recording field '$key' must be a string");
        }

        return $value;
    }

    /** @param array<mixed> $data */
    private static function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;
        if (!is_bool($value)) {
            throw new \InvalidArgumentException("Recording field '$key' must be a bool");
        }

        return $value;
    }

    /** @param array<mixed> $data */
    private static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value)) {
            throw new \InvalidArgumentException("Recording field '$key' must be an int");
        }

        return $value;
    }

    /** @return array<string,string> */
    private static function metadata(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("Recording field 'metadata' must be an array");
        }
        $out = [];
        foreach ($value as $key => $item) {
            if (!is_string($item)) {
                throw new \InvalidArgumentException('Recording metadata values must be strings');
            }
            // PHP turns numeric string keys into ints.
            $out[(string) $key] = $item;
        }

        return $out;
    }

    /** @return list<PlaybackFormat> */
    private static function formats(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("Recording field 'formats' must be an array");
        }
        $out = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Recording format must be an array');
            }
            $out[] = new PlaybackFormat(self::string($item, 'type'), self::string($item, 'url'));
        }

        return $out;
    }
}
