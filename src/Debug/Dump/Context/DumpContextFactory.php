<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump\Context;

final class DumpContextFactory
{
    /**
     * @param list<string> $ignoredFunctions
     */
    public function __construct(
        private readonly array $ignoredFunctions = ['dump', 'dd', '_Vd', 'dumper'],
    ) {}

    public function create(): DumpContext
    {
        return new DumpContext(
            sourceLocation: $this->resolveSourceLocation(),
            cli: PHP_SAPI === 'cli',
            sapi: PHP_SAPI,
        );
    }

    private function resolveSourceLocation(): DumpSourceLocation
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 16);

        foreach ($trace as $frame) {
            $function = $frame['function'];

            if (in_array($function, $this->ignoredFunctions, true)) {
                continue;
            }

            return $this->sourceLocationFromFrame($frame);
        }

        return $this->sourceLocationFromFrame($trace[0] ?? []);
    }

    /**
     * @param array{
     *     function?: string,
     *     line?: int,
     *     file?: string,
     *     class?: class-string,
     *     type?: '->'|'::',
     *     args?: list<mixed>,
     *     object?: object
     * } $frame
     */
    private function sourceLocationFromFrame(array $frame): DumpSourceLocation
    {
        return new DumpSourceLocation(
            file: $frame['file'] ?? 'DEBUG',
            line: $frame['line'] ?? null,
        );
    }
}
