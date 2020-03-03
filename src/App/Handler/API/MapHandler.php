<?php

declare(strict_types=1);

namespace App\Handler\API;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Stream;

class MapHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $key = $request->getAttribute('key');
        $slug = $request->getAttribute('slug');

        $file = 'data/maps/'.$key.'/'.$slug.'.png';

        if (!file_exists($file)) {
            return new EmptyResponse(404);
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return new EmptyResponse(500);
        }
        $gzcontent = gzencode($content);
        if ($gzcontent === false) {
            return new EmptyResponse(500);
        }

        $body = new Stream('php://temp', 'wb+');
        $body->write($gzcontent);
        $body->rewind();

        return new Response($body, 200, [
            'Content-Encoding' => 'gzip',
            'Content-Length'   => (string) strlen($gzcontent),
            'Content-Type'     => 'image/png',
        ]);
    }
}
