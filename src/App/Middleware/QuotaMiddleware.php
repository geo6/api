<?php

declare(strict_types=1);

namespace App\Middleware;

use Exception;
use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class QuotaMiddleware implements MiddlewareInterface
{
    public const QUOTA_ATTRIBUTE = 'quota';

    private const DIRECTORY = 'data/cache/quota';

    /**
     * @param array $access
     * @param bool  $debug
     */
    public function __construct(array $access, bool $debug)
    {
        $this->access = $access;
        $this->debug = $debug;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var RouteResult */
        $route = $request->getAttribute(RouteResult::class);

        $token = $request->getAttribute(TokenMiddleware::TOKEN_ATTRIBUTE);

        try {
            $_route = explode('.', $route->getMatchedRouteName());
            $action = $_route[1] ?? '';
            if ($action === 'xy' || $action == 'latlng') {
                $action = 'location';
            }

            $limit = isset(
                $this->access[$token['consumer']],
                $this->access[$token['consumer']]['limit'],
                $this->access[$token['consumer']]['limit'][$action]
            ) ? $this->access[$token['consumer']]['limit'][$action] : INF;

            if (!file_exists(self::DIRECTORY) || !is_dir(self::DIRECTORY)) {
                mkdir(self::DIRECTORY, 0777, true);
            }

            $quota = self::getQuota($token['consumer']);

            $quota[$action] = isset($quota[$action]) ? $quota[$action] + 1 : 1;

            if (strlen($token['consumer']) > 0) {
                file_put_contents(
                    sprintf('%s/%s.json', self::DIRECTORY, $token['consumer']),
                    json_encode($quota, JSON_PRETTY_PRINT)
                );
            }

            if ($quota[$action] > $limit) {
                throw new Exception(sprintf('Quota exceeded for action "%s".', $action));
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        if ($this->debug === false && $this->ip !== $_SERVER['SERVER_ADDR'] && $route->getMatchedRouteName() !== 'api.ping' && isset($error)) {
            return new JsonResponse([
                'error' => sprintf('Quota exceeded for action "%s".', $action)
            ], 429);
        }

        $quota['debug'] = $this->debug;
        $quota['error'] = $error ?? null;

        return $handler->handle($request->withAttribute(self::QUOTA_ATTRIBUTE, $quota));
    }

    private static function getQuota(string $consumer): array
    {
        $path = sprintf('%s/%s.json', self::DIRECTORY, $consumer);

        if (file_exists($path)) {
            $count = json_decode(file_get_contents($path), true);

            $time = filemtime($path);
            if (date('Y-m-d', $time) !== date('Y-m-d')) {
                unlink($path);
                unset($count);
            }
        }

        return $count ?? [];
    }
}
