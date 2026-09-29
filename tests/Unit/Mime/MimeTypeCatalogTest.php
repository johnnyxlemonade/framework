<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Mime;

use InvalidArgumentException;
use Lemonade\Framework\Mime\Exception\MimeTypeCatalogConflictException;
use Lemonade\Framework\Mime\MimeRisk;
use Lemonade\Framework\Mime\MimeType;
use Lemonade\Framework\Mime\MimeTypeCatalog;
use Lemonade\Framework\Mime\MimeTypeDefinition;
use PHPUnit\Framework\TestCase;

final class MimeTypeCatalogTest extends TestCase
{
    public function testNormalizesMimeTypesAndExtensions(): void
    {
        $mime = MimeType::fromString(' Text/Plain; charset=UTF-8 ');

        self::assertSame('text/plain', $mime->value());
        self::assertSame('jpg', MimeTypeCatalog::normalizeExtension('.JPG'));
    }

    public function testRejectsInvalidMimeType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MimeType::fromString('not-a-mime');
    }

    public function testIndexesAliasesRisksAndReverseExtensions(): void
    {
        $catalog = MimeTypeCatalog::default();

        self::assertSame('image/jpeg', $catalog->canonicalMimeForExtension('.JPE')?->value());
        self::assertSame(['jpg', 'jpeg', 'jpe'], $catalog->extensionsForMime('image/jpeg'));
        self::assertTrue($catalog->matches(
            'docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ));
        self::assertFalse($catalog->matches('docx', 'application/zip'));
        self::assertFalse($catalog->matches('pdf', 'application/octet-stream'));
        self::assertSame(MimeRisk::ActiveContent, $catalog->definitionForExtension('svg')?->risk());
        self::assertNull($catalog->definitionForExtension('unknown'));
    }

    public function testRejectsDuplicateNormalizedExtensions(): void
    {
        $this->expectException(MimeTypeCatalogConflictException::class);
        $this->expectExceptionMessage('Extension "sample"');

        new MimeTypeCatalog([
            $this->definition('application/example-one', ['sample']),
            $this->definition('application/example-two', ['Sample']),
        ]);
    }

    public function testRejectsDuplicateCanonicalMimeValues(): void
    {
        $this->expectException(MimeTypeCatalogConflictException::class);
        $this->expectExceptionMessage('Canonical MIME value "application/example"');

        new MimeTypeCatalog([
            $this->definition('application/example', ['one']),
            $this->definition('application/example', ['two']),
        ]);
    }

    public function testRejectsAliasCollidingWithAnotherCanonicalMimeValue(): void
    {
        $this->expectException(MimeTypeCatalogConflictException::class);
        $this->expectExceptionMessage('collides with an accepted alias');

        new MimeTypeCatalog([
            $this->definition('application/first', ['first'], ['application/second']),
            $this->definition('application/second', ['second']),
        ]);
    }

    public function testRejectsAliasesSharedByDifferentFamilies(): void
    {
        $this->expectException(MimeTypeCatalogConflictException::class);
        $this->expectExceptionMessage('Accepted alias "application/shared"');

        new MimeTypeCatalog([
            $this->definition('application/first', ['first'], ['application/shared']),
            $this->definition('application/second', ['second'], ['application/shared']),
        ]);
    }

    /**
     * @param list<string> $extensions
     * @param list<string> $aliases
     */
    private function definition(string $canonical, array $extensions, array $aliases = []): MimeTypeDefinition
    {
        return new MimeTypeDefinition(
            MimeType::fromString($canonical),
            $extensions,
            $aliases,
        );
    }
}
