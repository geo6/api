<?php

declare(strict_types=1);

namespace App\Middleware;

use ArrayObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Expressive\Router\RouteResult;

class TokenMiddleware implements MiddlewareInterface
{
    public const TOKEN_ATTRIBUTE = 'token';

    /** @var array */
    private $access = [];

    /** @var bool */
    private $debug = false;

    /** @var string */
    private $consumer;

    /** @var string */
    private $hostname;

    /** @var string */
    private $method;

    /** @var string */
    private $query;

    /** @var string */
    private $referer;

    /** @var string */
    private $token;

    /** @var int */
    private $timestamp;

    /** @var bool */
    private $valid = false;

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

        if ($route->getMatchedRouteName() === 'api.ping') {
            $this->debug = true;
        }

        $this->getTokenFromHeader($request);

        $server = $request->getServerParams();

        $this->hostname = 'localhost'/*$server['SERVER_NAME'] ?? ''*/;
        $this->ip = $server['REMOTE_ADDR'] ?? '';
        $this->method = $request->getMethod();

        $this->query = $this->getQuery($request->getUri()->getPath());

        $this->valid = $this->checkToken();

        if ($this->debug === false && $this->valid === false) {
            return new EmptyResponse(403);
        }

        $data = new ArrayObject([
            'debug'     => $this->debug,
            'consumer'  => $this->consumer,
            'referer'   => $this->referer,
            'timestamp' => $this->timestamp,
            'query'     => $this->query,
            'valid'     => $this->valid,
        ], ArrayObject::ARRAY_AS_PROPS);

        return $handler->handle($request->withAttribute(self::TOKEN_ATTRIBUTE, $data));
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return void
     */
    private function getTokenFromHeader(ServerRequestInterface $request) : void
    {
        $this->referer = $request->getHeaderLine('Referer');

        $this->consumer = $request->getHeaderLine('X-Geo6-Consumer');
        $this->token = $request->getHeaderLine('X-Geo6-Token');
        $this->timestamp = $request->getHeaderLine('X-Geo6-Timestamp');
    }

    /**
     * @param string $secret
     *
     * @return string
     */
    private function generateToken(string $secret) : string
    {
        $token = $this->consumer.'__';
        $token .= $this->timestamp.'__';
        $token .= $this->hostname.'__';
        $token .= $this->method.'__';
        $token .= $this->query;

        return crypt($token, '$6$'.$secret.'$');
    }

    /**
     * @return bool
     */
    private function checkToken() : bool
    {
        if (empty($this->consumer) || !in_array($this->consumer, array_keys($this->access))) {
            return false;
        }

        $access = $this->access[$this->consumer];

        if (isset($access['referer']) && !in_array($this->referer, $access['referer'])) {
            return false;
        }
        if (isset($access['ip']) && !in_array($this->ip, $access['ip'])) {
            return false;
        }

        if (empty($this->timestamp) || $this->timestamp > (time() + (5 * 60)) || $this->timestamp < (time() - (5 * 60))) {
            return false;
        }

        $token = $this->generateToken($access['secret']);

        return hash_equals($token, $this->token);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function getQuery(string $path) : string
    {
        if (preg_match('/^(\/geocode\/[a-z]+)/i', $path, $matches) === 1) {
            return $matches[1];
        } elseif (preg_match('/^(\/(?:xy|latlng))/i', $path, $matches) === 1) {
            return $matches[1];
        } elseif (preg_match('/^(\/zones)/i', $path, $matches) === 1) {
            return $matches[1];
        }

        return $path;
    }
}
