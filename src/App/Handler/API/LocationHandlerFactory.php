<?php

declare(strict_types=1);

namespace App\Handler\API;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Mezzio\Router\RouterInterface;

class LocationHandlerFactory
{
    public function __invoke(ContainerInterface $container): RequestHandlerInterface
    {
        $router = $container->get(RouterInterface::class);

        return new LocationHandler($router);
    }
}
