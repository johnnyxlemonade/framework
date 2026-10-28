<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Http;

/**
 * Identifies actual HTTP execution boundaries for an explicitly active profiler.
 *
 * A missing completion checkpoint means the corresponding operation did not complete.
 * These identifiers do not allocate or retain a timeline in the framework.
 */
enum HttpCheckpoint: string
{
    case RequestStarted = 'request_started';
    case ConfigurationLoaded = 'configuration_loaded';
    case BootstrapStarted = 'bootstrap_started';
    case ProvidersRegistered = 'providers_registered';
    case ProvidersBooted = 'providers_booted';
    case RoutesRegistered = 'routes_registered';
    case RouteMatchStarted = 'route_match_started';
    case RouteMatched = 'route_matched';
    case ControllerResolutionStarted = 'controller_resolution_started';
    case ControllerResolved = 'controller_resolved';
    case ControllerActionStarted = 'controller_action_started';
    case ControllerActionFinished = 'controller_action_finished';
    case ResponseCreated = 'response_created';
}
