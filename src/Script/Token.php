<?php

declare(strict_types=1);

namespace Script;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use ErrorException;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\Converter\StandardConverter;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\HS512;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use PDO;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Sql;
use Zend\Filter\FilterChain;
use Zend\Filter\StringToLower;
use Zend\Filter\Word\CamelCaseToDash;
use Zend\I18n\Filter\Alnum;

class Token
{
    /** @var array */
    private $config;

    /** @var Composer */
    private $composer;

    /** @var string Path to this file. */
    private $installerSource;

    /** @var IOInterface */
    private $io;

    /** @var string */
    private $projectRoot;

    public function __construct(IOInterface $io, Composer $composer)
    {
        $this->io = $io;
        $this->composer = $composer;

        // Get composer.json location
        $composerFile = Factory::getComposerFile();

        // Calculate project root from composer.json, if necessary
        $this->projectRoot = realpath(dirname($composerFile)) ?? '';
        $this->projectRoot = rtrim($this->projectRoot, '/\\') . '/';

        // Parse the composer.json
        // $this->parseComposerDefinition($composer, $composerFile);

        $this->config = require $this->projectRoot . 'config/autoload/local.php';

        // Source path for this file
        $this->installerSource = realpath(__DIR__) . '/';
    }

    public static function generate(Event $event): void
    {
        $installer = new self($event->getIO(), $event->getComposer());

        $id = $installer->requestId();
        $access = $installer->config['access'][$id];

        $installer->io->write(sprintf("\n<info>Token:\n%s</info>", self::getJWT($id, $access['secret'])));
    }

    private function requestId(): string
    {
        $query = [
            sprintf(
                "\n  <question>%s</question>\n",
                'For which id do you want to generate a token?',
            ),
        ];

        $ids = array_keys($this->config['access']);
        foreach ($ids as $i => $id) {
            $query[] = sprintf("  - [<comment>%d</comment>] %s\n", $i + 1, $id);
        }

        while (true) {
            $answer = $this->io->ask(implode($query));
            $i = intval($answer) - 1;

            if (!isset($ids[$i]) || !isset($this->config['access'][$ids[$i]])) {
                $this->io->write('<error>Invalid answer</error>');
            } else {
                return $ids[$i];
            }
        }
    }

    private static function getJWT(string $clientId, string $privateKey): string
    {
        $algorithmManager = AlgorithmManager::create([
            new HS512(),
        ]);

        $jwk = JWK::create([
            'kty' => 'oct',
            'k'   => $privateKey,
            'use' => 'sig',
        ]);

        $jsonConverter = new StandardConverter();

        $payload = $jsonConverter->encode([
            'aud' => 'GEO-6 API',
            'iat' => time(),
            'iss' => 'api-composer',
            'sub' => $clientId,
        ]);

        $jwsBuilder = new JWSBuilder(
            $jsonConverter,
            $algorithmManager
        );

        $jws = $jwsBuilder
            ->create()
            ->withPayload($payload)
            ->addSignature($jwk, ['alg' => 'HS512', 'typ' => 'JWT'])
            ->build();

        return (new CompactSerializer($jsonConverter))->serialize($jws);
    }
}
