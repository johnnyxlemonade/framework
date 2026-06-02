<?php

declare(strict_types=1);

if (!function_exists('service')) {
    /**
     * Removed global service resolver.
     *
     * Global helpers no longer resolve services from the container. Use
     * constructor DI, ControllerServices, $helpers, or $requestHelpers instead.
     *
     * @deprecated removed; use explicit DI, ControllerServices, $helpers, or $requestHelpers.
     *
     * @param class-string|non-empty-string $id
     */
    function service(string $id, mixed $default = null): mixed
    {
        throw new LogicException(sprintf(
            'The global service() helper has been removed. Requested service "%s" must be resolved through constructor DI, ControllerServices, $helpers, or $requestHelpers.',
            $id,
        ));
    }
}
