<?php

declare(strict_types=1);

namespace App\Token;

use Jose\Component\Checker;
use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Checker\ClaimCheckerManager;
use Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\Converter\JsonConverter;
use Jose\Component\Core\Converter\StandardConverter;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWS;
use Jose\Component\Signature\Algorithm;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;

/**
 * @see https://jwt.io/
 * @see https://web-token.spomky-labs.com/
 */
class JWT implements TokenInterface
{
    /** @var JWS */
    private $jws;

    /** @var int */
    private $issuedAt;

    /** @var string */
    private $issuer;

    /** @var string */
    private $subject;

    /**
     * @param string $token
     */
    public function __construct(string $token)
    {
        $serializerManager = self::getSerializeManager();

        $this->jws = $serializerManager->unserialize($token);

        $this->checkHeader();
        $this->checkClaims();
    }

    /**
     * @return string
     */
    public function getConsumer() : string
    {
        return $this->subject;
    }

    /**
     * @return int
     */
    public function getTimestamp() : int
    {
        return $this->issuedAt;
    }

    /**
     * @return JsonConverter
     */
    private static function getJsonConverter() : JsonConverter
    {
        return new StandardConverter();
    }

    /**
     * @return JWSSerializerManager
     */
    private static function getSerializeManager() : JWSSerializerManager
    {
        $jsonConverter = self::getJsonConverter();

        return JWSSerializerManager::create([
            new CompactSerializer($jsonConverter),
        ]);
    }

    /**
     * @return HeaderCheckerManager
     */
    private static function getHeaderCheckerManager() : HeaderCheckerManager
    {
        return HeaderCheckerManager::create(
            [
                new AlgorithmChecker([
                    'HS256',
                    'HS384',
                    'HS512',
                ]),
            ],
            [
                new JWSTokenSupport(),
            ]
        );
    }

    /**
     * @param string $secret
     *
     * @return JWK
     */
    private function generateToken(string $secret) : JWK
    {
        return JWK::create([
            'kty' => 'oct',
            'k'   => $secret,
            'use' => 'sig',
        ]);
    }

    /**
     * @param string $secret
     *
     * @return bool
     */
    public function check(string $secret) : bool
    {
        $jwk = $this->generateToken($secret);

        $algorithmManager = AlgorithmManager::create([
            new Algorithm\HS256(),
            new Algorithm\HS384(),
            new Algorithm\HS512(),
        ]);

        $jwsVerifier = new JWSVerifier(
            $algorithmManager
        );

        return $jwsVerifier->verifyWithKey($this->jws, $jwk, 0);
    }

    /**
     * @return void
     */
    private function checkHeader() : void
    {
        $headerCheckerManager = self::getHeaderCheckerManager();
        $headerCheckerManager->check($this->jws, 0, ['alg']);
    }

    /**
     * @return void
     */
    private function checkClaims() : void
    {
        $jsonConverter = self::getJsonConverter();

        $claims = $jsonConverter->decode($this->jws->getPayload());

        $claimCheckerManager = ClaimCheckerManager::create(
            [
                new Checker\IssuedAtChecker(5 * 60),
                // new Checker\AudienceChecker('GEO-6 API'),
            ]
        );

        $claimCheckerManager->check($claims, ['iat', 'iss', 'sub']);

        $this->issuedAt = $claims['iat'];
        $this->issuer = $claims['iss'];
        $this->subject = $claims['sub'];
    }
}
