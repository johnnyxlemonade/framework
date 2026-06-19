<?php

declare(strict_types=1);

use Lemonade\Framework\Api\Config\ApiConfigDefinition;

return ApiConfigDefinition::create()
    ->enabled()
    ->prefix('/api')
    ->staticBearerDisabled()
    ->staticBearerScopes(['api:admin'])
    ->frameworkEnabled()
    ->healthEnabled()
    ->healthRoute('/framework/health')
    ->healthAccess('public')
    ->openApiEnabled()
    ->openApiRoute('/framework/openapi.json')
    ->openApiAccess('protected')
    ->openApiScopes(['openapi:read'])
    ->docsDisabled()
    ->docsRoute('/framework/docs')
    ->docsAccess('protected')
    ->docsScopes(['openapi:read']);
