<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Mime;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Framework;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Mime\Exception\MimeTypeCatalogConflictException;
use Lemonade\Framework\Mime\MimeRisk;
use Lemonade\Framework\Mime\MimeServiceProvider;
use Lemonade\Framework\Mime\MimeType;
use Lemonade\Framework\Mime\MimeTypeCatalog;
use Lemonade\Framework\Mime\MimeTypeDefinition;
use Lemonade\Framework\Mime\MimeTypeDefinitionProviderInterface;
use LogicException;
use PHPUnit\Framework\TestCase;

final class MimeServiceProviderTest extends TestCase
{
    public function testTaggedApplicationProviderContributesDefinitionAfterComposition(): void
    {
        $framework = $this->framework();
        $definition = $this->definition('application/vnd.acme.document', ['acme']);

        $framework->register(
            new MimeServiceProvider(),
            new MimeDefinitionsApplicationProvider([
                'mime.acme' => new StaticMimeDefinitionProvider([$definition]),
            ]),
        );
        $framework->bootProviders();

        $catalog = $framework->container()->get(MimeTypeCatalog::class);

        self::assertInstanceOf(MimeTypeCatalog::class, $catalog);
        self::assertSame(
            'application/vnd.acme.document',
            $catalog->canonicalMimeForExtension('acme')?->value(),
        );
    }

    public function testTaggedProvidersAreResolvedInDeclarationOrder(): void
    {
        $log = new MimeProviderCallLog();
        $framework = $this->framework();

        $framework->register(
            new MimeServiceProvider(),
            new MimeDefinitionsApplicationProvider([
                'mime.first' => new RecordingMimeDefinitionProvider(
                    $log,
                    'first',
                    [$this->definition('application/vnd.acme.first', ['acme-first'])],
                ),
                'mime.second' => new RecordingMimeDefinitionProvider(
                    $log,
                    'second',
                    [$this->definition('application/vnd.acme.second', ['acme-second'])],
                ),
            ]),
        );
        $framework->bootProviders();

        self::assertSame(['first', 'second'], $log->events);
    }

    public function testFrameworkDefinitionsPrecedeTaggedApplicationContributions(): void
    {
        $framework = $this->framework();
        $definition = $this->definition('application/vnd.acme.pdf', ['pdf']);

        $framework->register(
            new MimeServiceProvider(),
            new MimeDefinitionsApplicationProvider([
                'mime.conflict' => new StaticMimeDefinitionProvider([$definition]),
            ]),
        );

        $this->expectException(MimeTypeCatalogConflictException::class);
        $this->expectExceptionMessage('Extension "pdf"');
        $framework->bootProviders();
    }

    public function testCatalogFailsFastBeforeProviderCompositionIsFrozen(): void
    {
        $framework = $this->framework();

        $framework->register(new MimeServiceProvider());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('before provider composition is frozen');
        $framework->container()->get(MimeTypeCatalog::class);
    }

    public function testCatalogIsCreatedOnceAndImmutableAfterBootstrap(): void
    {
        $framework = $this->framework();

        $framework->register(new MimeServiceProvider());
        $framework->bootProviders();

        $first = $framework->container()->get(MimeTypeCatalog::class);
        $second = $framework->container()->get(MimeTypeCatalog::class);
        $reflection = new \ReflectionClass(MimeTypeCatalog::class);

        self::assertInstanceOf(MimeTypeCatalog::class, $first);
        self::assertSame($first, $second);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
    }

    private function framework(): Framework
    {
        return new Framework(
            new \Lemonade\Framework\Container\Container(),
            new ApplicationContext(
                Environment::Testing,
                new Path(__DIR__),
                DebugMode::disabled(),
            ),
        );
    }

    /**
     * @param list<string> $extensions
     */
    private function definition(string $canonical, array $extensions): MimeTypeDefinition
    {
        return new MimeTypeDefinition(
            MimeType::fromString($canonical),
            $extensions,
            risk: MimeRisk::Container,
        );
    }
}

final readonly class MimeDefinitionsApplicationProvider implements ServiceProviderInterface
{
    /**
     * @param array<non-empty-string, MimeTypeDefinitionProviderInterface> $providers
     */
    public function __construct(
        private array $providers,
    ) {
    }

    public function register(ContainerBuilderInterface $container): void
    {
        foreach ($this->providers as $serviceId => $definitions) {
            $container->singletonTagged(
                $serviceId,
                $definitions,
                MimeTypeDefinitionProviderInterface::class,
            );
        }
    }
}

final readonly class StaticMimeDefinitionProvider implements MimeTypeDefinitionProviderInterface
{
    /**
     * @param list<MimeTypeDefinition> $definitions
     */
    public function __construct(private array $definitions)
    {
    }

    public function definitions(): iterable
    {
        return $this->definitions;
    }
}

final readonly class RecordingMimeDefinitionProvider implements MimeTypeDefinitionProviderInterface
{
    /**
     * @param list<MimeTypeDefinition> $definitions
     */
    public function __construct(
        private MimeProviderCallLog $log,
        private string $event,
        private array $definitions,
    ) {
    }

    public function definitions(): iterable
    {
        $this->log->events[] = $this->event;

        return $this->definitions;
    }
}

final class MimeProviderCallLog
{
    /**
     * @var list<string>
     */
    public array $events = [];
}
