<?php

declare(strict_types=1);

namespace App\Handler\API;

use Mezzio\Router\RouterInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ZonesHandlerFactory
{
    public function __invoke(ContainerInterface $container): RequestHandlerInterface
    {
        $router = $container->get(RouterInterface::class);

        return new ZonesHandler($router);
    }
}
