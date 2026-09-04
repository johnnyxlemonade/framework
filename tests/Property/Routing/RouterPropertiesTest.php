<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Property\Routing;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueOptions;
use Eris\Generator\GeneratedValueSingle;
use Eris\Random\RandomRange;
use Eris\TestTrait;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;
use Lemonade\Framework\Routing\RouteMatch;
use Lemonade\Framework\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class RouterPropertiesTest extends TestCase
{
    use TestTrait;

    private const PROPERTY_CASES = 200;

    public function testRouteParameterRoundTripForSimpleSegment(): void
    {
        $firstFailure = null;

        $this
            ->limitTo(self::PROPERTY_CASES)
            ->forAll(RoutePropertyGenerators::roundTripSegment())
            ->then(function (string $segment) use (&$firstFailure): void {
                $router = new Router();
                $router->getNamed('items.show', '/items/{value}', 'ItemController@show');

                $url = $router->url('items.show', ['value' => $segment]);
                $match = $router->match(new ServerRequest('GET', $url));
                $actual = $match->params()['value'] ?? null;

                if ($actual !== $segment) {
                    $firstFailure ??= [
                        'segment' => $segment,
                        'url' => $url,
                        'actual' => $actual,
                    ];

                    self::fail($this->formatFailureMessage(
                        invariant: 'Route parameter round-trip preserves the original simple route parameter value.',
                        firstFailure: $firstFailure,
                        currentFailure: [
                            'segment' => $segment,
                            'url' => $url,
                            'actual' => $actual,
                        ],
                    ));
                }

                self::assertSame('App\\Controllers\\ItemController', $match->controller());
                self::assertSame('show', $match->action());
            });
    }

    public function testWildcardRouteParameterRoundTrip(): void
    {
        $firstFailure = null;

        $this
            ->limitTo(self::PROPERTY_CASES)
            ->forAll(RoutePropertyGenerators::wildcardRoundTripValue())
            ->then(function (string $value) use (&$firstFailure): void {
                $router = new Router();
                $router->getNamed('items.show', '/items/{value:any}', 'ItemController@show');

                $url = $router->url('items.show', ['value' => $value]);
                $match = $router->match(new ServerRequest('GET', $url));
                $actual = $match->params()['value'] ?? null;

                if ($actual !== $value) {
                    $firstFailure ??= [
                        'value' => $value,
                        'url' => $url,
                        'actual' => $actual,
                    ];

                    self::fail($this->formatFailureMessage(
                        invariant: 'Wildcard route parameter round-trip preserves the original logical path value.',
                        firstFailure: $firstFailure,
                        currentFailure: [
                            'value' => $value,
                            'url' => $url,
                            'actual' => $actual,
                        ],
                    ));
                }

                self::assertSame('App\\Controllers\\ItemController', $match->controller());
                self::assertSame('show', $match->action());
            });
    }

    public function testStaticRouteIsolation(): void
    {
        $firstFailure = null;

        $this
            ->limitTo(self::PROPERTY_CASES)
            ->forAll(
                RoutePropertyGenerators::staticRoutePath(),
                RoutePropertyGenerators::staticRoutePath(),
            )
            ->when(static fn(string $registeredPath, string $otherPath): bool => $registeredPath !== $otherPath)
            ->then(function (string $registeredPath, string $otherPath) use (&$firstFailure): void {
                $router = new Router();
                $router->get($registeredPath, 'CatalogController@index');

                try {
                    $match = $router->match(new ServerRequest('GET', $registeredPath));
                } catch (RouteNotFoundException $exception) {
                    $firstFailure ??= [
                        'registered_path' => $registeredPath,
                        'other_path' => $otherPath,
                        'registered_path_error' => $exception->getMessage(),
                    ];

                    self::fail($this->formatFailureMessage(
                        invariant: 'A static route must match its own registered path.',
                        firstFailure: $firstFailure,
                        currentFailure: [
                            'registered_path' => $registeredPath,
                            'other_path' => $otherPath,
                            'registered_path_error' => $exception->getMessage(),
                        ],
                    ));
                }

                self::assertSame('App\\Controllers\\CatalogController', $match->controller());
                self::assertSame('index', $match->action());
                self::assertSame([], $match->params());

                try {
                    $otherMatch = $router->match(new ServerRequest('GET', $otherPath));
                } catch (RouteNotFoundException) {
                    return;
                }

                $firstFailure ??= [
                    'registered_path' => $registeredPath,
                    'other_path' => $otherPath,
                    'other_match' => $this->normalizeMatch($otherMatch),
                ];

                self::fail($this->formatFailureMessage(
                    invariant: 'A static route must not match a different generated static path.',
                    firstFailure: $firstFailure,
                    currentFailure: [
                        'registered_path' => $registeredPath,
                        'other_path' => $otherPath,
                        'other_match' => $this->normalizeMatch($otherMatch),
                    ],
                ));
            });
    }

    public function testHeadResolvesToSameHandlerAsGetForRegisteredGetRoutes(): void
    {
        $firstFailure = null;

        $this
            ->limitTo(self::PROPERTY_CASES)
            ->forAll(RoutePropertyGenerators::headInvariantCase())
            ->then(function (GetRouteCase $case) use (&$firstFailure): void {
                $router = new Router();
                $router->get($case->routePath, 'ArticleController@show');

                try {
                    $getMatch = $router->match(new ServerRequest('GET', $case->requestPath));
                    $headMatch = $router->match(new ServerRequest('HEAD', $case->requestPath));
                } catch (RouteNotFoundException $exception) {
                    $firstFailure ??= [
                        'case' => $case->toArray(),
                        'route_resolution_error' => $exception->getMessage(),
                    ];

                    self::fail($this->formatFailureMessage(
                        invariant: 'A registered GET route must resolve for both GET and HEAD requests to the generated request path.',
                        firstFailure: $firstFailure,
                        currentFailure: [
                            'case' => $case->toArray(),
                            'route_resolution_error' => $exception->getMessage(),
                        ],
                    ));
                }

                $normalizedGet = $this->normalizeMatch($getMatch);
                $normalizedHead = $this->normalizeMatch($headMatch);

                if ($normalizedHead !== $normalizedGet) {
                    $firstFailure ??= [
                        'case' => $case->toArray(),
                        'get' => $normalizedGet,
                        'head' => $normalizedHead,
                    ];

                    self::fail($this->formatFailureMessage(
                        invariant: 'HEAD must resolve to the same handler and params as GET for the same registered GET route.',
                        firstFailure: $firstFailure,
                        currentFailure: [
                            'case' => $case->toArray(),
                            'get' => $normalizedGet,
                            'head' => $normalizedHead,
                        ],
                    ));
                }

                self::assertSame($normalizedGet, $normalizedHead);
            });
    }

    /**
     * @return array{controller:string, action:string, params:array<string, string>}
     */
    private function normalizeMatch(RouteMatch $match): array
    {
        return [
            'controller' => $match->controller(),
            'action' => $match->action(),
            'params' => $match->params(),
        ];
    }

    /**
     * @param array<string, mixed> $firstFailure
     * @param array<string, mixed> $currentFailure
     */
    private function formatFailureMessage(string $invariant, array $firstFailure, array $currentFailure): string
    {
        return $invariant
            . "\nFirst failing case: " . json_encode($firstFailure, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . "\nCurrent failing case: " . json_encode($currentFailure, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

final class RoutePropertyGenerators
{
    public static function roundTripSegment(): StringDomainGenerator
    {
        return new StringDomainGenerator(
            name: 'route-segment',
            characters: ['a', '0', '-', '_', '.', '~', '@', ' ', '/', "\u{010D}", '%', '+', "\u{00DF}", "\u{4F60}", "\u{1F642}"],
            shrinkTargets: ['/', '@', ' ', "\u{010D}", '%', 'a', '0', '-', '_', '.', '~'],
            minLength: 1,
            maxLength: 8,
            validator: static fn(string $value): bool => $value !== '',
        );
    }

    public static function wildcardRoundTripValue(): SlashSeparatedValueGenerator
    {
        return new SlashSeparatedValueGenerator(
            new StringDomainGenerator(
                name: 'wildcard-route-segment',
                characters: ['a', '0', '-', '_', '.', '~', '@', ' ', "\u{010D}", '%', '+', "\u{00DF}", "\u{4F60}", "\u{1F642}"],
                shrinkTargets: ['@', ' ', "\u{010D}", '%', 'a', '0', '-', '_', '.', '~'],
                minLength: 1,
                maxLength: 8,
                validator: static fn(string $value): bool => $value !== '' && !str_contains($value, '/'),
            ),
            minSegments: 1,
            maxSegments: 4,
        );
    }

    /**
     * Valid static route path domain derived from the documented public API:
     * slash-delimited literal route definitions with non-empty literal segments.
     * Excludes placeholder syntax and percent-encoding syntax in route definitions.
     */
    public static function staticRoutePath(): StaticRoutePathGenerator
    {
        return new StaticRoutePathGenerator(
            new StringDomainGenerator(
                name: 'static-route-segment',
                characters: ['a', '0', '-', '_', '.', '~', '@', ' ', "\u{010D}", "\u{00DF}", "\u{4F60}", "\u{1F642}"],
                shrinkTargets: ["\u{010D}", ' ', '@', 'a', '0', '-', '_', '.', '~'],
                minLength: 1,
                maxLength: 10,
                validator: static fn(string $value): bool => self::isValidLiteralStaticSegment($value),
            ),
            minSegments: 1,
            maxSegments: 3,
        );
    }

    /**
     * HEAD invariant domain intentionally uses unreserved ASCII-only request paths
     * so this property isolates HEAD fallback semantics from path representation bugs.
     */
    public static function headInvariantCase(): GetRouteCaseGenerator
    {
        return new GetRouteCaseGenerator(
            staticPathGenerator: new StaticRoutePathGenerator(
                new StringDomainGenerator(
                    name: 'safe-static-segment',
                    characters: ['a', 'b', 'c', 'x', 'y', 'z', '0', '1', '2', '-', '_', '.', '~'],
                    shrinkTargets: ['a', '0', '-', '_', '.', '~'],
                    minLength: 1,
                    maxLength: 8,
                    validator: static fn(string $value): bool => $value !== '.' && $value !== '..',
                ),
                minSegments: 1,
                maxSegments: 3,
            ),
            parameterGenerator: new StringDomainGenerator(
                name: 'safe-parameter-segment',
                characters: ['a', 'b', 'c', 'x', 'y', 'z', '0', '1', '2', '-', '_', '.', '~'],
                shrinkTargets: ['a', '0', '-', '_', '.', '~'],
                minLength: 1,
                maxLength: 8,
                validator: static fn(string $value): bool => !str_contains($value, '/'),
            ),
        );
    }

    private static function isValidLiteralStaticSegment(string $value): bool
    {
        if ($value === '' || $value === '.' || $value === '..') {
            return false;
        }

        return !str_contains($value, '/')
            && !str_contains($value, '{')
            && !str_contains($value, '}')
            && !str_contains($value, '%');
    }
}

final class GetRouteCase
{
    public function __construct(
        public readonly string $routePath,
        public readonly string $requestPath,
        public readonly string $kind,
        public readonly ?string $expectedParam = null,
    ) {}

    /**
     * @return array{route_path:string, request_path:string, kind:string, expected_param:?string}
     */
    public function toArray(): array
    {
        return [
            'route_path' => $this->routePath,
            'request_path' => $this->requestPath,
            'kind' => $this->kind,
            'expected_param' => $this->expectedParam,
        ];
    }
}

/**
 * @implements ErisGenerator<string>
 */
final class StringDomainGenerator implements ErisGenerator
{
    /**
     * @param list<string> $characters
     * @param list<string> $shrinkTargets
     * @param callable(string):bool $validator
     */
    public function __construct(
        private readonly string $name,
        private readonly array $characters,
        private readonly array $shrinkTargets,
        private readonly int $minLength,
        private readonly int $maxLength,
        callable $validator,
    ) {
        $this->validator = $validator(...);
    }

    /**
     * @var \Closure(string):bool
     */
    private readonly \Closure $validator;

    /**
     * @var array<string, int>
     */
    private array $characterRanks = [];

    /**
     * @param int $size
     * @return GeneratedValueSingle<string>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $length = $rand->rand($this->minLength, $this->maxLength);
            $value = '';

            for ($index = 0; $index < $length; $index++) {
                $value .= $this->characters[$rand->rand(0, count($this->characters) - 1)];
            }

            if (($this->validator)($value)) {
                return GeneratedValueSingle::fromJustValue($value, $this->name);
            }
        }

        throw new \RuntimeException(sprintf('Unable to generate valid value for domain "%s".', $this->name));
    }

    /**
     * @param GeneratedValue<string> $element
     * @return GeneratedValue<string>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        $value = $element->unbox();
        if (!is_string($value)) {
            throw new \LogicException(sprintf('Expected string value for "%s" shrink.', $this->name));
        }

        $characters = self::splitChars($value);
        /** @var list<GeneratedValueSingle<string>> $candidates */
        $candidates = [];

        if (count($characters) > 1) {
            foreach (self::preferredSingleCharacterCandidates($characters, $this->shrinkTargets) as $character) {
                $this->addCandidateIfSmaller($candidates, $character, $value);
            }

            foreach (array_keys($characters) as $index) {
                $shorter = $characters;
                unset($shorter[$index]);
                $shorterValue = implode('', array_values($shorter));

                $this->addCandidateIfSmaller($candidates, $shorterValue, $value);
            }
        }

        foreach ($this->shrinkTargets as $target) {
            $this->addCandidateIfSmaller($candidates, $target, $value);
        }

        foreach ($characters as $index => $character) {
            foreach ($this->shrinkTargets as $target) {
                if ($target === $character) {
                    continue;
                }

                $replaced = $characters;
                $replaced[$index] = $target;
                $replacedValue = implode('', $replaced);

                $this->addCandidateIfSmaller($candidates, $replacedValue, $value);
            }
        }

        return self::optionsOrSelf($candidates, $value, $this->name);
    }

    /**
     * @return list<string>
     */
    private static function splitChars(string $value): array
    {
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($characters)) {
            throw new \LogicException('Unable to split generated Unicode string.');
        }

        $result = [];

        foreach ($characters as $character) {
            if (!is_string($character)) {
                throw new \LogicException('Expected Unicode split to produce string characters.');
            }

            $result[] = $character;
        }

        return $result;
    }

    /**
     * @param list<string> $characters
     * @param list<string> $shrinkTargets
     * @return list<string>
     */
    private static function preferredSingleCharacterCandidates(array $characters, array $shrinkTargets): array
    {
        $preferred = [];
        $seen = [];

        foreach ($shrinkTargets as $target) {
            if (in_array($target, $characters, true) && !isset($seen[$target])) {
                $preferred[] = $target;
                $seen[$target] = true;
            }
        }

        foreach ($characters as $character) {
            if (!isset($seen[$character])) {
                $preferred[] = $character;
                $seen[$character] = true;
            }
        }

        return $preferred;
    }

    /**
     * @param list<GeneratedValueSingle<string>> $candidates
     */
    private function addCandidateIfSmaller(array &$candidates, string $candidate, string $current): void
    {
        if ($candidate === '' || !(($this->validator)($candidate)) || !$this->isStrictlySmaller($candidate, $current)) {
            return;
        }

        $candidates[] = self::single($candidate, $this->name);
    }

    private function isStrictlySmaller(string $candidate, string $current): bool
    {
        if ($candidate === $current) {
            return false;
        }

        $candidateCharacters = self::splitChars($candidate);
        $currentCharacters = self::splitChars($current);

        if (count($candidateCharacters) !== count($currentCharacters)) {
            return count($candidateCharacters) < count($currentCharacters);
        }

        $candidateRanks = array_map($this->characterRank(...), $candidateCharacters);
        $currentRanks = array_map($this->characterRank(...), $currentCharacters);

        foreach ($candidateRanks as $index => $rank) {
            $currentRank = $currentRanks[$index];

            if ($rank === $currentRank) {
                continue;
            }

            return $rank < $currentRank;
        }

        return strcmp($candidate, $current) < 0;
    }

    private function characterRank(string $character): int
    {
        if ($this->characterRanks === []) {
            $rank = 0;

            foreach (array_values(array_unique(array_merge($this->shrinkTargets, $this->characters))) as $candidate) {
                $this->characterRanks[$candidate] = $rank;
                $rank++;
            }
        }

        return $this->characterRanks[$character] ?? (count($this->characterRanks) + mb_ord($character, 'UTF-8'));
    }

    /**
     * @return GeneratedValueSingle<string>
     */
    private static function single(string $value, string $name): GeneratedValueSingle
    {
        return GeneratedValueSingle::fromJustValue($value, $name);
    }

    /**
     * @param list<GeneratedValueSingle<string>> $candidates
     * @return GeneratedValue<string>
     */
    private static function optionsOrSelf(array $candidates, string $current, string $name): GeneratedValue
    {
        $unique = [];
        $seen = [];

        foreach ($candidates as $candidate) {
            $candidateValue = $candidate->unbox();

            if (!is_string($candidateValue) || $candidateValue === $current || isset($seen[$candidateValue])) {
                continue;
            }

            $seen[$candidateValue] = true;
            $unique[] = $candidate;
        }

        if ($unique === []) {
            return GeneratedValueSingle::fromJustValue($current, $name);
        }

        return new GeneratedValueOptions($unique);
    }
}

/**
 * @implements ErisGenerator<string>
 */
final class StaticRoutePathGenerator implements ErisGenerator
{
    public function __construct(
        private readonly StringDomainGenerator $segmentGenerator,
        private readonly int $minSegments,
        private readonly int $maxSegments,
    ) {}

    /**
     * @param int $size
     * @return GeneratedValueSingle<string>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        $count = $rand->rand($this->minSegments, $this->maxSegments);
        $segments = [];

        for ($index = 0; $index < $count; $index++) {
            $segment = $this->segmentGenerator->__invoke($size, $rand)->unbox();

            if (!is_string($segment)) {
                throw new \LogicException('Expected static route segment generator to produce string values.');
            }

            $segments[] = $segment;
        }

        return GeneratedValueSingle::fromValueAndInput(
            self::buildPath($segments),
            $segments,
            'static-route-path',
        );
    }

    /**
     * @param GeneratedValue<string> $element
     * @return GeneratedValue<string>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        $segments = self::normalizeSegmentList($element->input(), 'Expected static route path input to be segment list.');
        $candidates = [];

        if (count($segments) > 1) {
            foreach (array_keys($segments) as $index) {
                $shorter = $segments;
                unset($shorter[$index]);
                $shorter = array_values($shorter);

                if ($shorter !== []) {
                    $candidates[] = GeneratedValueSingle::fromValueAndInput(
                        self::buildPath($shorter),
                        $shorter,
                        'static-route-path',
                    );
                }
            }
        }

        foreach ($segments as $index => $segment) {
            foreach (self::generatedValueSingles(
                $this->segmentGenerator->shrink(GeneratedValueSingle::fromJustValue($segment, 'static-route-segment'))
            ) as $shrunkSegment) {
                $candidateSegment = $shrunkSegment->unbox();

                if (!is_string($candidateSegment)) {
                    continue;
                }

                $replaced = $segments;
                $replaced[$index] = $candidateSegment;
                $replaced = array_values($replaced);

                $candidates[] = GeneratedValueSingle::fromValueAndInput(
                    self::buildPath($replaced),
                    $replaced,
                    'static-route-path',
                );
            }
        }

        return self::optionsOrSelf($candidates, self::buildPath($segments));
    }

    /**
     * @param list<string> $segments
     */
    private static function buildPath(array $segments): string
    {
        return '/' . implode('/', $segments);
    }

    /**
     * @return list<string>
     */
    private static function splitPathSegments(string $path): array
    {
        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            static fn(string $segment): bool => $segment !== '',
        ));

        return $segments;
    }

    /**
     * @param list<GeneratedValueSingle<string>> $candidates
     * @return GeneratedValue<string>
     */
    private static function optionsOrSelf(array $candidates, string $current): GeneratedValue
    {
        $unique = [];
        $seen = [];

        foreach ($candidates as $candidate) {
            $candidateValue = $candidate->unbox();

            if (!is_string($candidateValue) || $candidateValue === $current || isset($seen[$candidateValue])) {
                continue;
            }

            $seen[$candidateValue] = true;
            $unique[] = $candidate;
        }

        if ($unique === []) {
            return GeneratedValueSingle::fromValueAndInput(
                $current,
                self::splitPathSegments($current),
                'static-route-path',
            );
        }

        return new GeneratedValueOptions($unique);
    }

    /**
     * @param mixed $input
     * @return list<string>
     */
    private static function normalizeSegmentList(mixed $input, string $message): array
    {
        if (!is_array($input)) {
            throw new \LogicException($message);
        }

        $segments = [];

        foreach ($input as $segment) {
            if (!is_string($segment)) {
                throw new \LogicException($message);
            }

            $segments[] = $segment;
        }

        return $segments;
    }

    /**
     * @template T
     * @param GeneratedValue<T> $value
     * @return list<GeneratedValueSingle<T>>
     */
    public static function generatedValueSingles(GeneratedValue $value): array
    {
        if ($value instanceof GeneratedValueSingle) {
            return [$value];
        }

        $generatedSingles = [];

        foreach ($value as $candidate) {
            $generatedSingles[] = $candidate;
        }

        return $generatedSingles;
    }
}

/**
 * @implements ErisGenerator<string>
 */
final class SlashSeparatedValueGenerator implements ErisGenerator
{
    public function __construct(
        private readonly StringDomainGenerator $segmentGenerator,
        private readonly int $minSegments,
        private readonly int $maxSegments,
    ) {}

    /**
     * @param int $size
     * @return GeneratedValueSingle<string>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        $count = $rand->rand($this->minSegments, $this->maxSegments);
        $segments = [];

        for ($index = 0; $index < $count; $index++) {
            $segment = $this->segmentGenerator->__invoke($size, $rand)->unbox();

            if (!is_string($segment)) {
                throw new \LogicException('Expected wildcard route segment generator to produce string values.');
            }

            $segments[] = $segment;
        }

        return GeneratedValueSingle::fromValueAndInput(
            self::buildValue($segments),
            $segments,
            'slash-separated-value',
        );
    }

    /**
     * @param GeneratedValue<string> $element
     * @return GeneratedValue<string>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        $segments = self::normalizeSegmentList($element->input(), 'Expected slash-separated value input to be segment list.');
        $candidates = [];

        if (count($segments) > 1) {
            foreach (array_keys($segments) as $index) {
                $shorter = $segments;
                unset($shorter[$index]);
                $shorter = array_values($shorter);

                if ($shorter !== []) {
                    $candidates[] = GeneratedValueSingle::fromValueAndInput(
                        self::buildValue($shorter),
                        $shorter,
                        'slash-separated-value',
                    );
                }
            }
        }

        foreach ($segments as $index => $segment) {
            foreach (StaticRoutePathGenerator::generatedValueSingles(
                $this->segmentGenerator->shrink(GeneratedValueSingle::fromJustValue($segment, 'wildcard-route-segment'))
            ) as $shrunkSegment) {
                $candidateSegment = $shrunkSegment->unbox();

                if (!is_string($candidateSegment)) {
                    continue;
                }

                $replaced = $segments;
                $replaced[$index] = $candidateSegment;
                $replaced = array_values($replaced);

                $candidates[] = GeneratedValueSingle::fromValueAndInput(
                    self::buildValue($replaced),
                    $replaced,
                    'slash-separated-value',
                );
            }
        }

        return self::optionsOrSelf($candidates, self::buildValue($segments));
    }

    /**
     * @param list<string> $segments
     */
    private static function buildValue(array $segments): string
    {
        return implode('/', $segments);
    }

    /**
     * @param list<GeneratedValueSingle<string>> $candidates
     * @return GeneratedValue<string>
     */
    private static function optionsOrSelf(array $candidates, string $current): GeneratedValue
    {
        $unique = [];
        $seen = [];

        foreach ($candidates as $candidate) {
            $candidateValue = $candidate->unbox();

            if (!is_string($candidateValue) || $candidateValue === $current || isset($seen[$candidateValue])) {
                continue;
            }

            $seen[$candidateValue] = true;
            $unique[] = $candidate;
        }

        if ($unique === []) {
            return GeneratedValueSingle::fromValueAndInput(
                $current,
                self::splitSegments($current),
                'slash-separated-value',
            );
        }

        return new GeneratedValueOptions($unique);
    }

    /**
     * @return list<string>
     */
    private static function splitSegments(string $value): array
    {
        $segments = array_values(array_filter(
            explode('/', $value),
            static fn(string $segment): bool => $segment !== '',
        ));

        return $segments;
    }

    /**
     * @param mixed $input
     * @return list<string>
     */
    private static function normalizeSegmentList(mixed $input, string $message): array
    {
        if (!is_array($input)) {
            throw new \LogicException($message);
        }

        $segments = [];

        foreach ($input as $segment) {
            if (!is_string($segment)) {
                throw new \LogicException($message);
            }

            $segments[] = $segment;
        }

        return $segments;
    }
}

/**
 * @implements ErisGenerator<GetRouteCase>
 */
final class GetRouteCaseGenerator implements ErisGenerator
{
    public function __construct(
        private readonly StaticRoutePathGenerator $staticPathGenerator,
        private readonly StringDomainGenerator $parameterGenerator,
    ) {}

    /**
     * @param int $size
     * @return GeneratedValueSingle<GetRouteCase>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        $kind = $rand->rand(0, 1) === 0 ? 'static' : 'parameterized';

        if ($kind === 'static') {
            $path = $this->staticPathGenerator->__invoke($size, $rand)->unbox();

            if (!is_string($path)) {
                throw new \LogicException('Expected static GET/HEAD case path to be string.');
            }

            $case = new GetRouteCase(
                routePath: $path,
                requestPath: $path,
                kind: 'static',
            );

            return GeneratedValueSingle::fromValueAndInput($case, $path, 'get-head-case');
        }

        $segment = $this->parameterGenerator->__invoke($size, $rand)->unbox();
        if (!is_string($segment)) {
            throw new \LogicException('Expected parameter GET/HEAD case segment to be string.');
        }

        $case = new GetRouteCase(
            routePath: '/articles/{slug}',
            requestPath: '/articles/' . $segment,
            kind: 'parameterized',
            expectedParam: $segment,
        );

        return GeneratedValueSingle::fromValueAndInput($case, $segment, 'get-head-case');
    }

    /**
     * @param GeneratedValue<GetRouteCase> $element
     * @return GeneratedValue<GetRouteCase>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        $case = $element->unbox();
        if (!$case instanceof GetRouteCase) {
            throw new \LogicException('Expected GetRouteCase during GET/HEAD case shrink.');
        }

        if ($case->kind === 'static') {
            $path = $element->input();
            if (!is_string($path)) {
                throw new \LogicException('Expected string static path input during GET/HEAD shrink.');
            }

            $options = [];
            foreach (StaticRoutePathGenerator::generatedValueSingles(
                $this->staticPathGenerator->shrink(GeneratedValueSingle::fromValueAndInput($path, self::splitPathSegments($path), 'static-route-path'))
            ) as $shrunkPath) {
                $shrunkValue = $shrunkPath->unbox();
                if (!is_string($shrunkValue)) {
                    continue;
                }

                $options[] = GeneratedValueSingle::fromValueAndInput(
                    new GetRouteCase(
                        routePath: $shrunkValue,
                        requestPath: $shrunkValue,
                        kind: 'static',
                    ),
                    $shrunkValue,
                    'get-head-case',
                );
            }

            return self::optionsOrSelf($options, $case);
        }

        $segment = $element->input();
        if (!is_string($segment)) {
            throw new \LogicException('Expected parameter segment input during GET/HEAD shrink.');
        }

        $options = [];
        foreach (StaticRoutePathGenerator::generatedValueSingles(
            $this->parameterGenerator->shrink(GeneratedValueSingle::fromJustValue($segment, 'safe-parameter-segment'))
        ) as $shrunkSegment) {
            $shrunkValue = $shrunkSegment->unbox();
            if (!is_string($shrunkValue)) {
                continue;
            }

            $options[] = GeneratedValueSingle::fromValueAndInput(
                new GetRouteCase(
                    routePath: '/articles/{slug}',
                    requestPath: '/articles/' . $shrunkValue,
                    kind: 'parameterized',
                    expectedParam: $shrunkValue,
                ),
                $shrunkValue,
                'get-head-case',
            );
        }

        return self::optionsOrSelf($options, $case);
    }

    /**
     * @param list<GeneratedValueSingle<GetRouteCase>> $candidates
     * @return GeneratedValue<GetRouteCase>
     */
    private static function optionsOrSelf(array $candidates, GetRouteCase $current): GeneratedValue
    {
        $unique = [];
        $seen = [];
        $currentKey = json_encode($current->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($currentKey)) {
            throw new \LogicException('Unable to encode current GET/HEAD case for uniqueness comparison.');
        }

        foreach ($candidates as $candidate) {
            $candidateValue = $candidate->unbox();

            if (!$candidateValue instanceof GetRouteCase) {
                continue;
            }

            $key = json_encode($candidateValue->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (!is_string($key) || $key === $currentKey || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $candidate;
        }

        if ($unique === []) {
            return GeneratedValueSingle::fromValueAndInput(
                $current,
                $current->kind === 'static' ? $current->requestPath : $current->expectedParam,
                'get-head-case',
            );
        }

        return new GeneratedValueOptions($unique);
    }

    /**
     * @return list<string>
     */
    private static function splitPathSegments(string $path): array
    {
        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            static fn(string $segment): bool => $segment !== '',
        ));

        return $segments;
    }
}
