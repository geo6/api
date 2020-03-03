<?php

declare(strict_types=1);

namespace AppTest\Handler\API;

use App\Handler\API\PingHandler;
use Laminas\Diactoros\Response\JsonResponse;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class PingHandlerTest extends TestCase
{
    public function testResponse()
    {
        $pingHandler = new PingHandler();
        $response = $pingHandler->handle(
            $this->prophesize(ServerRequestInterface::class)->reveal()
        );

        $json = json_decode((string) $response->getBody());

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertTrue(isset($json->now));
        $this->assertTrue(isset($json->token) || is_null($json->token));
        $this->assertTrue(isset($json->ip) || is_null($json->ip));
        $this->assertTrue(isset($json->referer) || is_null($json->referer));
        $this->assertTrue(isset($json->{'user-agent'}) || is_null($json->{'user-agent'}));
    }
}
