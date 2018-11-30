<?php

declare(strict_types=1);

namespace App\Middleware;

use Exception;
use Psr\Container\ContainerInterface;

class DbAdapterMiddlewareFactory
{
    public function __invoke(ContainerInterface $container) : DbAdapterMiddleware
    {
        $config = $container->get('config');

        if (!isset($config['postgresql'])) {
            throw new Exception(sprintf(
                'Cannot create %s; could not locate PostgreSQL parameters in application configuration.',
                self::class
            ));
        }

        return new DbAdapterMiddleware($config['postgresql']);
    }
}
