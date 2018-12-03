<?php

declare(strict_types=1);

use App\Handler\API\Geocode\AddressHandler;
use App\Handler\API\Geocode\POIHandler;
use App\Handler\API\Geocode\DatabaseHandler;
use App\Handler\API\Geocode\ZoneHandler;
use App\Handler\API\Geocode\StreetHandler;
use App\Handler\API\LocationHandler;
use App\Handler\API\MapHandler;
use App\Handler\API\ZonesHandler;
use App\Handler\PingHandler;
use App\Middleware\TokenMiddleware;
use Psr\Container\ContainerInterface;
use Zend\Expressive\Application;
use Zend\Expressive\MiddlewareFactory;

/*
 * Setup routes with a single request method:
 *
 * $app->get('/', App\Handler\HomePageHandler::class, 'home');
 * $app->post('/album', App\Handler\AlbumCreateHandler::class, 'album.create');
 * $app->put('/album/:id', App\Handler\AlbumUpdateHandler::class, 'album.put');
 * $app->patch('/album/:id', App\Handler\AlbumUpdateHandler::class, 'album.patch');
 * $app->delete('/album/:id', App\Handler\AlbumDeleteHandler::class, 'album.delete');
 *
 * Or with multiple request methods:
 *
 * $app->route('/contact', App\Handler\ContactHandler::class, ['GET', 'POST', ...], 'contact');
 *
 * Or handling all request methods:
 *
 * $app->route('/contact', App\Handler\ContactHandler::class)->setName('contact');
 *
 * or:
 *
 * $app->route(
 *     '/contact',
 *     App\Handler\ContactHandler::class,
 *     Zend\Expressive\Router\Route::HTTP_METHOD_ANY,
 *     'contact'
 * );
 */
return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container) : void {
    $app->get('/', App\Handler\HomePageHandler::class, 'home');

    $app->get('/test/geocode', App\Handler\GeocodeHandler::class, 'test.geocode');
    $app->get('/test/latlng', App\Handler\LatLngHandler::class, 'test.latlng');
    $app->get('/test/zone', App\Handler\ZoneHandler::class, 'test.zone');

    $app->get('/ping', [TokenMiddleware::class, PingHandler::class], 'api.ping');

    $app->get('/geocode/getDatabaseList', [TokenMiddleware::class, DatabaseHandler::class], 'api.geocode.database');

    $app->get('/geocode/getZoneList/{locality}', [TokenMiddleware::class, ZoneHandler::class], 'api.geocode.zone');

    $app->get('/geocode/getStreetList/{source:urbis|crab|picc}/{locality}/{postalcode}/{street}', [TokenMiddleware::class, StreetHandler::class], 'api.geocode.source.street.3');
    $app->get('/geocode/getStreetList/{source:urbis|crab|picc}/{locality}/{street}', [TokenMiddleware::class, StreetHandler::class], 'api.geocode.source.street.2');
    $app->get('/geocode/getStreetList/{source:urbis|crab|picc}/{street}', [TokenMiddleware::class, StreetHandler::class], 'api.geocode.source.street.1');
    $app->get('/geocode/getStreetList/{locality}/{postalcode}/{street}', [TokenMiddleware::class, StreetHandler::class], 'api.geocode.street.3');
    $app->get('/geocode/getStreetList/{locality}/{street}', [TokenMiddleware::class, StreetHandler::class], 'api.geocode.street.2');
    $app->get('/geocode/getStreetList/{street}', [TokenMiddleware::class, StreetHandler::class], 'api.geocode.street.1');

    $app->get('/geocode/getAddressList/{source:urbis|crab|picc}/{locality}/{postalcode}/{street}/[{number}]', [TokenMiddleware::class, AddressHandler::class], 'api.geocode.source.address.4');
    $app->get('/geocode/getAddressList/{source:urbis|crab|picc}/{locality}/{street}/[{number}]', [TokenMiddleware::class, AddressHandler::class], 'api.geocode.source.address.3');
    $app->get('/geocode/getAddressList/{source:urbis|crab|picc}/{street}/[{number}]', [TokenMiddleware::class, AddressHandler::class], 'api.geocode.source.address.2');
    $app->get('/geocode/getAddressList/{locality}/{postalcode}/{street}/[{number}]', [TokenMiddleware::class, AddressHandler::class], 'api.geocode.address.4');
    $app->get('/geocode/getAddressList/{locality}/{street}/[{number}]', [TokenMiddleware::class, AddressHandler::class], 'api.geocode.address.3');
    $app->get('/geocode/getAddressList/{street}/[{number}]', [TokenMiddleware::class, AddressHandler::class], 'api.geocode.address.2');

    $app->get('/geocode/getPOIList/{source}/{poi}', [TokenMiddleware::class, POIHandler::class], 'api.geocode.source.poi');
    $app->get('/geocode/getPOIList/{poi}', [TokenMiddleware::class, POIHandler::class], 'api.geocode.poi');
    // Backward compatibilty
    $app->get('/geocode/getPOI/{source}/{poi}', [TokenMiddleware::class, POIHandler::class], 'api.geocode.source.poi.old');
    $app->get('/geocode/getPOI/{poi}', [TokenMiddleware::class, POIHandler::class], 'api.geocode.poi.old');

    $app->get('/xy/{x:[0-9.]+}/{y:[0-9.]+}', [TokenMiddleware::class, LocationHandler::class], 'api.xy');
    $app->get('/latlng/{latitude:[\-0-9.]+}/{longitude:[\-0-9.]+}', [TokenMiddleware::class, LocationHandler::class], 'api.latlng');
    // Backward compatibilty
    $app->get('/xy/{x:[0-9.]+},{y:[0-9.]+}', [TokenMiddleware::class, LocationHandler::class], 'api.xy.old');
    $app->get('/latlng/{latitude:[\-0-9.]+},{longitude:[\-0-9.]+}', [TokenMiddleware::class, LocationHandler::class], 'api.latlng.old');

    $app->get('/zones/{nis5:[0-9]{5}}', [TokenMiddleware::class, ZonesHandler::class], 'api.zones');

    $app->get('/zones/map/{key}/{slug}.png', MapHandler::class, 'api.zones.maps');
};
