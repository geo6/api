<?php

declare(strict_types=1);

namespace App\Handler;

use App\Test\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Router\RouterInterface;
use Mezzio\Template\TemplateRendererInterface;

class LocationHandler implements RequestHandlerInterface
{
    /** @var string */
    private $containerName;

    /** @var RouterInterface */
    private $router;

    /** @var TemplateRendererInterface */
    private $template;

    public function __construct(
        string $containerName,
        RouterInterface $router,
        TemplateRendererInterface $template
    ) {
        $this->containerName = $containerName;
        $this->router = $router;
        $this->template = $template;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $data = [
            'latlng' => Request::get(
                $request,
                $this->router->generateUri('api.latlng', ['latitude' => 50.89794, 'longitude' => 4.36302])
            ),
            'xy' => Request::get(
                $request,
                $this->router->generateUri('api.xy', ['x' => 149597, 'y' => 176400])
            ),
        ];

        return new HtmlResponse($this->template->render('app::location', $data));
    }
}
