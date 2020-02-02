<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Container\ContainerInterface;

class TokenMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): TokenMiddleware
    {
        $config = $container->get('config');

        $access = $config['access'] ?? [];
        $debug = isset($config['debug']) && $config['debug'] === true;

        return new TokenMiddleware($access, $debug);
    }
}
