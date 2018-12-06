<?php

declare(strict_types=1);

namespace App\Handler;

use App\Test\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

class GeocodeHandler implements RequestHandlerInterface
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

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $data = [
            'database' => Request::get(
                $request,
                $this->router->generateUri('api.geocode.database')
            ),
            'poi' => Request::get(
                $request,
                $this->router->generateUri('api.geocode.poi', ['source' => 'urbis', 'poi' => 'Manneken Pis'])
            ),
            'zone' => Request::get(
                $request,
                $this->router->generateUri('api.geocode.zone', ['locality' => 'Bruxelles'])
            ),
            'street' => Request::get(
                $request,
                $this->router->generateUri('api.geocode.street.source.3', ['source' => 'urbis', 'locality' => 'Bruxelles', 'postalcode' => '1020', 'street' => 'araucaria'])
            ),
            'address' => Request::get(
                $request,
                $this->router->generateUri('api.geocode.address.source.4', ['source' => 'urbis', 'locality' => 'Bruxelles', 'postalcode' => '1020', 'street' => 'araucaria', 'number' => '147'])
            ),
        ];

        return new HtmlResponse($this->template->render('app::geocode', $data));
    }
}
