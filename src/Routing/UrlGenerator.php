<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

use InvalidArgumentException;

use Lemonade\Framework\Localization\LocaleResolverInterface;

use function array_key_exists;
use function str_contains;

final class UrlGenerator
{
    public function __construct(
        private readonly Router $router,
        private readonly ?LocaleResolverInterface $localeResolver = null,
    ) {}

    /**
     * @param array<string, scalar|null> $params
     */
    public function route(string $name, array $params = []): string
    {
        try {
            return $this->router->url($name, $params);
        } catch (InvalidArgumentException $exception) {
            if (
                !str_contains($exception->getMessage(), 'Missing route parameter "locale".')
                || array_key_exists('locale', $params)
                || $this->localeResolver === null
            ) {
                throw $exception;
            }

            $params['locale'] = $this->localeResolver->resolve();

            return $this->router->url($name, $params);
        }
    }
}
