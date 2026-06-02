<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\View;

use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Security\Csrf\CsrfViewHelper;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Support\BaseUrlResolver;
use Lemonade\Framework\View\ViewHelpers;
use PHPUnit\Framework\TestCase;

final class ViewHelpersTest extends TestCase
{
    public function testAssetBuildsUrlFromBaseUrlResolver(): void
    {
        $helpers = $this->helpers();

        self::assertSame('https://example.test/assets/app.css', $helpers->asset('assets/app.css'));
    }

    public function testUrlAndLocalizedUrlUseUrlGenerator(): void
    {
        $router = new Router();
        $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('posts.show', '/posts/{id}', 'PostController@show');
        });
        $helpers = $this->helpers(router: $router);

        self::assertSame('/posts/15', $helpers->url('posts.show', ['id' => 15]));
        self::assertSame('/cs/posts/15', $helpers->localizedUrl('posts.show', ['id' => 15], 'cs'));
    }

    public function testCsrfHelpersDelegateToCsrfViewHelper(): void
    {
        $helpers = $this->helpers();

        $token = $helpers->csrfToken('login');
        self::assertNotSame('', $token);
        self::assertStringContainsString('name="LEMONADE_CSRF"', $helpers->csrfField('login'));
        self::assertStringContainsString($token, $helpers->csrfField('login'));
    }

    public function testLanguageHelpersDelegateToTranslator(): void
    {
        $translator = new ViewHelpersTranslatorStub(locale: 'cs');
        $helpers = $this->helpers(translator: $translator);

        self::assertSame('Ahoj John', $helpers->lang('messages.hello', ['name' => 'John']));
        self::assertSame('cs', $helpers->currentLocale());
        self::assertSame(['hello' => 'Ahoj {name}'], $helpers->langGroup('messages'));
        self::assertSame(['messages' => ['hello' => 'Ahoj {name}']], $helpers->langAll());
    }

    public function testCurrentLocaleFallsBackToConfig(): void
    {
        $helpers = $this->helpers(config: new Config([
            'app' => ['base_url' => 'https://example.test'],
            'localization' => ['default_locale' => 'sk'],
        ]));

        self::assertSame('sk', $helpers->currentLocale());
    }

    private function helpers(
        ?Router $router = null,
        ?TranslatorInterface $translator = null,
        ?Config $config = null,
    ): ViewHelpers {
        $config ??= new Config([
            'app' => ['base_url' => 'https://example.test'],
            'localization' => ['default_locale' => 'en'],
        ]);
        $router ??= new Router();
        $router->getNamed('home', '/', 'HomeController@index');

        $session = new ViewHelpersSessionStub();
        $csrf = new CsrfViewHelper(new CsrfTokenManager($session));

        return new ViewHelpers(
            baseUrl: new BaseUrlResolver($config),
            urlGenerator: new UrlGenerator($router, null, new ViewHelpersLocaleUrlStrategyStub()),
            csrf: $csrf,
            translator: $translator ?? new ViewHelpersTranslatorStub(),
            config: $config,
        );
    }
}

final class ViewHelpersSessionStub implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function start(): void {}

    public function started(): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        unset($deleteOldSession);
    }
}

final class ViewHelpersTranslatorStub implements TranslatorInterface
{
    public function __construct(
        private readonly ?string $locale = null,
    ) {}

    public function setLocale(?string $locale): self
    {
        unset($locale);

        return $this;
    }

    public function locale(): ?string
    {
        return $this->locale;
    }

    public function get(string $key, array $replacements = [], ?string $locale = null): string
    {
        unset($locale);

        if ($key !== 'messages.hello') {
            return $key;
        }

        $name = $replacements['name'] ?? '';

        return 'Ahoj ' . (string) $name;
    }

    public function group(string $group, ?string $locale = null): array
    {
        unset($locale);

        return $group === 'messages' ? ['hello' => 'Ahoj {name}'] : [];
    }

    public function all(?string $locale = null): array
    {
        unset($locale);

        return ['messages' => ['hello' => 'Ahoj {name}']];
    }
}

final class ViewHelpersLocaleUrlStrategyStub implements \Lemonade\Framework\Routing\LocaleUrlStrategyInterface
{
    public function enabled(): bool
    {
        return true;
    }

    public function localizedRouteName(string $baseRouteName): string
    {
        return 'localized.' . $baseRouteName;
    }

    public function shouldUseLocalizedRoute(string $locale): bool
    {
        return strtolower($locale) !== 'en';
    }

    public function localeParameter(): string
    {
        return 'locale';
    }
}
