<?php

declare(strict_types=1);

use Lemonade\Framework\Http\Config\ForwardedHeaderProfile;
use Lemonade\Framework\Http\Config\HttpServerConfigDefinition;

return HttpServerConfigDefinition::create()
    ->trustedProxies([])
    ->forwardedHeaderProfile(ForwardedHeaderProfile::None)
    ->trustForwardedPrefix(false)
    ->allowedHosts([]);
