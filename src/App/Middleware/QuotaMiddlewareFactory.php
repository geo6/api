<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Container\ContainerInterface;

class QuotaMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): QuotaMiddleware
    {
        $config = $container->get('config');

        $access = $config['access'] ?? [];
        $debug = isset($config['debug']) && $config['debug'] === true;

        return new QuotaMiddleware($access, $debug);
    }
}
