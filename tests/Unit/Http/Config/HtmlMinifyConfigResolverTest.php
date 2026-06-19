<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Config;

use Lemonade\Framework\Http\Config\HtmlMinifyConfigDefinition;
use Lemonade\Framework\Http\Config\HtmlMinifyConfigResolver;
use PHPUnit\Framework\TestCase;

final class HtmlMinifyConfigResolverTest extends TestCase
{
    public function testDefaultDefinitionResolvesDisabledRuntimeConfig(): void
    {
        $config = (new HtmlMinifyConfigResolver())->resolve(
            HtmlMinifyConfigDefinition::create()->disabled(),
        );

        self::assertFalse($config->enabled);
    }

    public function testApplicationOverrideEnablesConfig(): void
    {
        $config = (new HtmlMinifyConfigResolver())->resolve(
            HtmlMinifyConfigDefinition::create()->disabled(),
            HtmlMinifyConfigDefinition::create()->enabled(),
        );

        self::assertTrue($config->enabled);
    }

    public function testLastDefinitionWinsForEnabledFlag(): void
    {
        $config = (new HtmlMinifyConfigResolver())->resolve(
            HtmlMinifyConfigDefinition::create()->enabled(),
            HtmlMinifyConfigDefinition::create()->disabled(),
        );

        self::assertFalse($config->enabled);
    }
}
