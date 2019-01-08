<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Token\Geo6 as Geo6Token;
use App\Token\JWT;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Metadata\Metadata;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Expressive\Router\RouteResult;

class TokenMiddleware implements MiddlewareInterface
{
    public const TOKEN_ATTRIBUTE = 'token';

    /** @var array */
    private $access = [];

    /** @var bool */
    private $debug = false;

    /** @var string */
    private $ip;

    /** @var string */
    private $referer;

    /**
     * @param array $access
     * @param bool  $debug
     */
    public function __construct(array $access, bool $debug)
    {
        $this->access = $access;
        $this->debug = $debug;
    }

    /**
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $route = $request->getAttribute(RouteResult::class);

        $authorization = $request->getHeaderLine('Authorization');
        if (strlen($authorization) > 0 && preg_match('/^Bearer (.+)$/', $authorization, $matches)) {
            $token = new JWT($matches[1]);
        } else {
            $consumer = $request->getHeaderLine('X-Geo6-Consumer');
            $token = $request->getHeaderLine('X-Geo6-Token');
            $timestamp = intval($request->getHeaderLine('X-Geo6-Timestamp'));

            $token = new Geo6Token($consumer, $token, $timestamp, $request);
        }

        $this->referer = $request->getHeaderLine('Referer');
        $this->ip = ($request->getServerParams())['REMOTE_ADDR'] ?? '';

        $timestamp = $token->getTimestamp();
        $consumer = $token->getConsumer();

        try {
            if ($timestamp < (time() - (5 * 60))) {
                throw new Exception(
                    sprintf('Expired token. Token timestamp is "%s".', date('c', $timestamp))
                );
            }
            if ($timestamp > (time() + (5 * 60))) {
                throw new Exception(
                    sprintf('Invalid timestamp. Token timestamp is "%s".', date('c', $timestamp))
                );
            }

            if (strlen($consumer) === 0 || !in_array($consumer, array_keys($this->access), true)) {
                throw new Exception(
                    sprintf('Invalid consumer "%s".', $consumer)
                );
            }

            $access = $this->access[$consumer];

            if (isset($access['referer']) && !in_array(parse_url($this->referer, PHP_URL_HOST), $access['referer'], true)) {
                throw new Exception(
                    sprintf('Unauthorized referer "%s".', $this->referer)
                );
            }
            if (isset($access['ip']) && !in_array($this->ip, $access['ip'], true)) {
                throw new Exception(
                    sprintf('Unauthorized ip "%s".', $this->ip)
                );
            }

            if ($token->check($access['secret']) !== true) {
                throw new Exception('Invalid token!');
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        if ($this->debug === false && $this->ip !== $_SERVER['SERVER_ADDR'] && $route->getMatchedRouteName() !== 'api.ping' && isset($error)) {
            return new JsonResponse([
                'error' => $error,
            ], 403);
        }

        $adapter = $request->getAttribute(DbAdapterMiddleware::DBADAPTER_ATTRIBUTE);

        if ($this->debug === true) {
            $this->access[$consumer]['database'] = [
                'address' => [
                    // 'picc',
                    'icar',
                ],
                'poi' => [],
            ];
        }

        $data = [
            'debug'     => $this->debug,
            'consumer'  => $consumer,
            'database'  => $this->getDatabases($adapter, $this->access[$consumer]['database'] ?? []),
            'referer'   => $this->referer,
            'timestamp' => $timestamp,
            'error'     => $error ?? null,
        ];

        return $handler->handle($request->withAttribute(self::TOKEN_ATTRIBUTE, $data));
    }

    /**
     * Get list of databases that the user have the right to access.
     *
     * @param Adapter $adapter
     * @param array   $config
     *
     * @return array
     */
    private static function getDatabases(Adapter $adapter, array $config) : array
    {
        $address = [
            'crab',
            'urbis',
        ];
        $poi = [
            'urbis',
        ];

        if (isset($config['address'])) {
            $address = array_merge($address, $config['address']);
            $address = array_unique($address);
            sort($address);
        }

        foreach ($address as $i => $a) {
            if (!in_array($a, ['crab', 'icar', 'picc', 'urbis'])) {
                unset($address[$i]);
                $address = array_values($address);
            }
        }

        if (isset($config['poi'])) {
            $poi = array_merge($poi, $config['poi']);
            $poi = array_unique($poi);
            sort($poi);
        }

        $metadata = new Metadata($adapter);
        $sources_poi = $metadata->getTableNames('poi');

        foreach ($poi as $i => $p) {
            if (!in_array($p, $sources_poi)) {
                unset($poi[$i]);
                $poi = array_values($poi);
            }
        }

        return [
            'address' => $address,
            'poi'     => $poi,
        ];
    }
}
