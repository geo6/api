<?php

declare(strict_types=1);

namespace App\Handler;

use function get_class;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Mezzio\Router\RouterInterface;
use Mezzio\Template\TemplateRendererInterface;

class HomePageHandlerFactory
{
    public function __invoke(ContainerInterface $container): RequestHandlerInterface
    {
        $router = $container->get(RouterInterface::class);
        $template = $container->get(TemplateRendererInterface::class);

        return new HomePageHandler(get_class($container), $router, $template);
    }
}
