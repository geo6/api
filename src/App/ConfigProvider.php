<?php

declare(strict_types=1);

namespace App;

/**
 * The configuration provider for the App module.
 *
 * @see https://docs.zendframework.com/zend-component-installer/
 */
class ConfigProvider
{
    /**
     * Returns the configuration array.
     *
     * To add a bit of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     */
    public function __invoke() : array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'templates'    => $this->getTemplates(),
        ];
    }

    /**
     * Returns the container dependencies.
     */
    public function getDependencies() : array
    {
        return [
            'invokables' => [
                Handler\API\Address\ExpandHandler::class   => Handler\API\Address\ExpandHandler::class,
                Handler\API\Address\ParseHandler::class    => Handler\API\Address\ParseHandler::class,
                Handler\API\Geocode\DatabaseHandler::class => Handler\API\Geocode\DatabaseHandler::class,
                Handler\API\Geocode\POIHandler::class      => Handler\API\Geocode\POIHandler::class,
                Handler\API\Geocode\StreetHandler::class   => Handler\API\Geocode\StreetHandler::class,
                Handler\API\Geocode\ZoneHandler::class     => Handler\API\Geocode\ZoneHandler::class,
                Handler\API\PingHandler::class             => Handler\API\PingHandler::class,
            ],
            'factories'  => [
                Handler\HomePageHandler::class => Handler\HomePageHandlerFactory::class,
                Handler\GeocodeHandler::class  => Handler\GeocodeHandlerFactory::class,
                Handler\LocationHandler::class => Handler\LocationHandlerFactory::class,
                Handler\ZoneHandler::class     => Handler\ZoneHandlerFactory::class,

                Handler\API\LocationHandler::class => Handler\API\LocationHandlerFactory::class,
                Handler\API\ZonesHandler::class    => Handler\API\ZonesHandlerFactory::class,

                Middleware\DbAdapterMiddleware::class => Middleware\DbAdapterMiddlewareFactory::class,
                Middleware\TokenMiddleware::class     => Middleware\TokenMiddlewareFactory::class,
            ],
        ];
    }

    /**
     * Returns the templates configuration.
     */
    public function getTemplates() : array
    {
        return [
            'paths' => [
                'app'    => ['templates/app'],
                'error'  => ['templates/error'],
                'layout' => ['templates/layout'],
            ],
        ];
    }
}
