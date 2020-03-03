<?php

declare(strict_types=1);

namespace App\Handler;

use App\Test\Request;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Router\RouterInterface;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ZoneHandler implements RequestHandlerInterface
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
        $data = Request::get(
            $request,
            $this->router->generateUri('api.zones', ['nis5' => 21004])
        );

        return new HtmlResponse($this->template->render('app::zone', $data));
    }
}
