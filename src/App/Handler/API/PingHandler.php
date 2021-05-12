<?php

declare(strict_types=1);

namespace App\Handler\API;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use function time;

class PingHandler extends AbstractHandler
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $server = $request->getServerParams();

        $json = [
            'now'        => time(),
            'ip'         => $server['REMOTE_ADDR'] ?? null,
            'referer'    => $server['HTTP_REFERER'] ?? null,
            'user-agent' => $server['HTTP_USER_AGENT'] ?? null,
        ];

        return self::response($request, $json);
    }
}
