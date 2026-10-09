<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Component;

use Lemonade\Framework\Component\Meta\MetaComponent;
use Lemonade\Framework\Component\Meta\Sections\AbstractMetaEntity;
use Lemonade\Framework\Component\Meta\Sections\Dc;
use Lemonade\Framework\Component\Meta\Sections\Facebook;
use Lemonade\Framework\Component\Meta\Sections\Meta;
use Lemonade\Framework\Component\Meta\Sections\Twitter;
use Lemonade\Framework\Component\Meta\Tag\AbstractTag;
use Lemonade\Framework\Component\Meta\Tag\AlternateLinkTag;
use Lemonade\Framework\Component\Meta\Tag\CharsetTag;
use Lemonade\Framework\Component\Meta\Tag\DcTag;
use Lemonade\Framework\Component\Meta\Tag\LinkTag;
use Lemonade\Framework\Component\Meta\Tag\MetaTag;
use Lemonade\Framework\Component\Meta\Tag\OpenGraphTag;
use Lemonade\Framework\Component\Meta\Tag\TitleTag;
use Lemonade\Framework\Component\Meta\Tag\TwitterTag;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MetaReadonlyTest extends TestCase
{
    public function testImmutableMetaTypesAreReadonly(): void
    {
        $classes = [
            MetaComponent::class,
            AbstractMetaEntity::class,
            Dc::class,
            Facebook::class,
            Meta::class,
            Twitter::class,
            AbstractTag::class,
            AlternateLinkTag::class,
            CharsetTag::class,
            DcTag::class,
            LinkTag::class,
            MetaTag::class,
            OpenGraphTag::class,
            TitleTag::class,
            TwitterTag::class,
        ];

        foreach ($classes as $class) {
            self::assertTrue(
                (new ReflectionClass($class))->isReadOnly(),
                sprintf('%s must remain readonly.', $class),
            );
        }
    }
}
