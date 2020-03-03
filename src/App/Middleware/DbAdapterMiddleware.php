<?php

declare(strict_types=1);

namespace App\Middleware;

use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Db\Adapter\Adapter;

class DbAdapterMiddleware implements MiddlewareInterface
{
    public const DBADAPTER_ATTRIBUTE = 'adapter';

    /** @var array */
    private $postgresql;

    public function __construct(array $postgresql)
    {
        $this->postgresql = $postgresql;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $adapter = new Adapter(
            array_merge([
                'driver'         => 'Pdo_Pgsql',
                'driver_options' => [
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ],
            ], $this->postgresql)
        );

        return $handler->handle($request->withAttribute(self::DBADAPTER_ATTRIBUTE, $adapter));
    }
}
