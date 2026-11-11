<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Unit\Recording;

use Gibbon\Module\BigBlueButton\Bbb\BbbException;
use Gibbon\Module\BigBlueButton\Recording\RecordingXmlParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecordingXmlParser::class)]
final class RecordingXmlParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/bbb/' . $name);
        self::assertIsString($xml);

        return $xml;
    }

    public function testParsesMultiple(): void
    {
        $list = (new RecordingXmlParser())->parse($this->fixture('getRecordings-multiple.xml'));

        self::assertCount(2, $list);
        $r = $list[0];
        self::assertSame('183f0bf3a0982a127bdb8161e0c44eb696b3e75c-1530718721124', $r->recordID);
        self::assertSame('gibbon-abc123def456-42-7', $r->meetingID);
        self::assertSame('Year 10 Maths - Lesson 7', $r->name);
        self::assertTrue($r->published);
        self::assertSame(1530718721124, $r->startTime);
        self::assertSame(1530722321876, $r->endTime);
        self::assertSame('Gibbon', $r->meta('bbb-origin'));
        self::assertSame('abc123def456', $r->meta('gibbon-instance'));
        self::assertSame('42', $r->meta('gibbon-planner-entry'));
        self::assertSame('Year 10 Maths - Lesson 7', $r->meta('meetingname'));
        self::assertSame(['presentation', 'video', 'transcript', 'summary'], array_map(static fn ($f) => $f->type, $r->formats));
        self::assertSame(
            'https://bbb.example.net/presentation/' . $r->recordID . '/summary/summary.html',
            $r->format('summary')?->url
        );
        self::assertSame('2f5a1c9e7b3d4a6c8e0f1b2d3c4a5b6c7d8e9f01-1530722400456', $list[1]->recordID);
    }

    public function testNoRecordings(): void
    {
        self::assertSame([], (new RecordingXmlParser())->parse($this->fixture('getRecordings-none.xml')));
    }

    public function testEmptyRecordingsElementWithoutMessageKey(): void
    {
        $xml = '<response><returncode>SUCCESS</returncode><recordings/></response>';
        self::assertSame([], (new RecordingXmlParser())->parse($xml));
    }

    public function testFailed(): void
    {
        try {
            (new RecordingXmlParser())->parse($this->fixture('getRecordings-failed.xml'));
            self::fail('Expected exception');
        } catch (BbbException $e) {
            self::assertSame('checksumError', $e->messageKey());
            self::assertSame('You did not pass the checksum security check', $e->getMessage());
        }
    }

    public function testEmptyMetadataAndFloatTimes(): void
    {
        $list = (new RecordingXmlParser())->parse($this->fixture('getRecordings-empty-metadata.xml'));

        self::assertCount(1, $list);
        self::assertSame([], $list[0]->metadata);
        self::assertFalse($list[0]->published);
        self::assertSame(1530718721124, $list[0]->startTime);
        self::assertSame(1530722321876, $list[0]->endTime);
    }

    /** @return array<string, array{0: string}> */
    public static function invalid(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ["  \n"],
            'garbage' => ['not xml'],
            'truncated' => ['<response><returncode>SUCCESS'],
            'wrong root without returncode' => ['<foo/>'],
        ];
    }

    #[DataProvider('invalid')]
    public function testInvalidXml(string $xml): void
    {
        try {
            (new RecordingXmlParser())->parse($xml);
            self::fail('Expected exception');
        } catch (BbbException $e) {
            self::assertSame('invalidXml', $e->messageKey());
            self::assertSame('Invalid XML response from BigBlueButton', $e->getMessage());
        }
    }

    public function testRestoresLibxmlErrorSetting(): void
    {
        $prev = libxml_use_internal_errors(false);
        try {
            (new RecordingXmlParser())->parse('<response><returncode>SUCCESS</returncode><recordings/></response>');
            self::assertFalse(libxml_use_internal_errors());
            try {
                (new RecordingXmlParser())->parse('garbage');
            } catch (BbbException) {
            }
            self::assertFalse(libxml_use_internal_errors());
            self::assertSame([], libxml_get_errors());
        } finally {
            libxml_use_internal_errors($prev);
        }
    }

    public function testExternalEntitiesAreNotExpanded(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'xxe');
        self::assertIsString($file);
        file_put_contents($file, 'SECRET');
        $xml = '<!DOCTYPE r [<!ENTITY x SYSTEM "file://' . $file . '">]>'
            . '<response><returncode>SUCCESS</returncode><recordings><recording><recordID>a</recordID>'
            . '<meetingID>m</meetingID><name>&x;</name><published>true</published><startTime>1</startTime>'
            . '<endTime>2</endTime></recording></recordings></response>';
        try {
            try {
                $list = (new RecordingXmlParser())->parse($xml);
                self::assertStringNotContainsString('SECRET', $list[0]->name ?? '');
            } catch (BbbException) {
                self::addToAssertionCount(1);
            }
        } finally {
            unlink($file);
        }
    }

    public function testSkipsIncompleteRecordingsAndFormats(): void
    {
        $xml = <<<'XML'
<response><returncode>SUCCESS</returncode><recordings>
<recording><recordID></recordID><meetingID>m</meetingID><name>a</name><published>true</published><startTime>1</startTime><endTime>2</endTime></recording>
<recording><recordID>r</recordID><meetingID> </meetingID><name>a</name><published>true</published><startTime>1</startTime><endTime>2</endTime></recording>
<recording><recordID>ok</recordID><meetingID>m</meetingID><name>a</name><published>TRUE</published><startTime>x</startTime><endTime></endTime>
<metadata><Foo> Bar </Foo></metadata>
<playback>
<format><type> Video </type><url> https://x.test/v </url></format>
<format><type></type><url>https://x.test/a</url></format>
<format><type>summary</type><url> </url></format>
</playback></recording>
</recordings></response>
XML;
        $list = (new RecordingXmlParser())->parse($xml);

        self::assertCount(1, $list);
        self::assertSame('ok', $list[0]->recordID);
        self::assertFalse($list[0]->published);
        self::assertSame(0, $list[0]->startTime);
        self::assertSame(0, $list[0]->endTime);
        self::assertSame(['foo' => 'Bar'], $list[0]->metadata);
        self::assertCount(1, $list[0]->formats);
        self::assertSame('video', $list[0]->formats[0]->type);
        self::assertSame('https://x.test/v', $list[0]->formats[0]->url);
    }
}
