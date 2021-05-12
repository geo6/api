<?php

declare(strict_types=1);

namespace App\Handler\API;

use App\Middleware\QuotaMiddleware;
use App\Middleware\TokenMiddleware;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

abstract class AbstractHandler implements RequestHandlerInterface
{
    protected static function response(ServerRequestInterface $request, array $data, int $status = 200): JsonResponse
    {
      $token = $request->getAttribute(TokenMiddleware::TOKEN_ATTRIBUTE);
      $quota = $request->getAttribute(QuotaMiddleware::QUOTA_ATTRIBUTE);

      if ($token['debug'] === true) {
          $data['token'] = $token;
      }
      if ($quota['debug'] === true) {
          $data['quota'] = $quota;
      }

      return new JsonResponse($data, $status);
    }
}