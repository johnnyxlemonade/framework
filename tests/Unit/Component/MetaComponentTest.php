<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Component;

use Lemonade\Framework\Component\Meta\Config\MetaConfig;
use Lemonade\Framework\Component\Meta\MetaComponent;
use Lemonade\Framework\Component\Meta\MetaData;
use PHPUnit\Framework\TestCase;

final class MetaComponentTest extends TestCase
{
    public function testMakeAppliesTypedDefaults(): void
    {
        $component = new MetaComponent(new MetaConfig(
            websiteName: 'Lemonade',
            charset: 'UTF-8',
            viewport: 'width=device-width, initial-scale=1',
            rating: 'General',
            titleSeparator: ' | ',
        ));

        $meta = $component->make((new MetaData())->withTitleSeparator(' :: '));
        $html = $meta->toHtml();

        self::assertStringContainsString('charset="UTF-8"', $html);
        self::assertStringContainsString('content="width=device-width, initial-scale=1"', $html);
        self::assertStringContainsString('content="General"', $html);
        self::assertStringContainsString('content="Lemonade"', $html);
    }
}
