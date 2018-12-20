<?php

declare(strict_types=1);

namespace App\Token;

use Exception;
use Psr\Http\Message\ServerRequestInterface;

class Geo6 implements TokenInterface
{
    /** @var string */
    private $consumer;

    /** @var string */
    private $hostname;

    /** @var string */
    private $method;

    /** @var string */
    private $query;

    /** @var string */
    private $token;

    /** @var int */
    private $timestamp;

    /**
     * @param string $consumer
     * @param string $token
     * @param integer $timestamp
     * @param ServerRequestInterface $request
     */
    public function __construct(string $consumer, string $token, int $timestamp, ServerRequestInterface $request)
    {
        $this->consumer = $consumer;
        $this->token = $token;
        $this->timestamp = $timestamp;

        $this->hostname = ($request->getServerParams())['SERVER_NAME'] ?? '';
        $this->method = $request->getMethod();
        $this->query = self::getQuery($request->getUri()->getPath());
    }

    /**
     * @return string
     */
    public function getConsumer() : string
    {
        return $this->consumer;
    }

    /**
     * @return integer
     */
    public function getTimestamp() : int
    {
        return $this->timestamp;
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
     * @param string $secret
     *
     * @return bool
     */
    public function check(string $secret) : bool
    {
        $token = $this->generateToken($secret);

        return hash_equals($token, $this->token);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private static function getQuery(string $path) : string
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
