<?php

declare(strict_types=1);

use App\Handler\API\Address\ExpandHandler;
use App\Handler\API\Address\ParseHandler;
use App\Handler\API\Geocode\AddressHandler;
use App\Handler\API\Geocode\DatabaseHandler;
use App\Handler\API\Geocode\POIHandler;
use App\Handler\API\Geocode\StreetHandler;
use App\Handler\API\Geocode\ZoneHandler;
use App\Handler\API\LocationHandler;
use App\Handler\API\MapHandler;
use App\Handler\API\PingHandler;
use App\Handler\API\ZonesHandler;
use App\Middleware\DbAdapterMiddleware;
use App\Middleware\QuotaMiddleware;
use App\Middleware\TokenMiddleware;
use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;

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
 *     Mezzio\Router\Route::HTTP_METHOD_ANY,
 *     'contact'
 * );
 */
return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    $app->get('/', App\Handler\HomePageHandler::class, 'home');

    $app->get('/test/geocode', App\Handler\GeocodeHandler::class, 'test.geocode');
    $app->get('/test/location', App\Handler\LocationHandler::class, 'test.location');
    $app->get('/test/zone', App\Handler\ZoneHandler::class, 'test.zone');

    $app->get('/ping', PingHandler::class, 'api.ping');

    $app->get('/address/expand/{address}', ExpandHandler::class, 'api.address.expand');
    $app->get('/address/parse/{address}', ParseHandler::class, 'api.address.parse');

    $app->get('/geocode/getDatabaseList', DatabaseHandler::class, 'api.geocode.database');

    $app->get('/geocode/getZoneList/{nis5:[0-9]{5}}', ZoneHandler::class, 'api.geocode.zone.nis5');
    $app->get('/geocode/getZoneList/{postalcode:[0-9]{4}}', ZoneHandler::class, 'api.geocode.zone.postalcode');
    $app->get('/geocode/getZoneList/{locality}', ZoneHandler::class, 'api.geocode.zone');

    $app->get('/geocode/getStreetList/{source:urbis|crab|icar}/{nis5:[0-9]{5}}/{postalcode:[0-9]{4}}/[{street}]', StreetHandler::class, 'api.geocode.street.source.3.nis5');
    $app->get('/geocode/getStreetList/{source:urbis|crab|icar}/{locality}/{postalcode:[0-9]{4}}/[{street}]', StreetHandler::class, 'api.geocode.street.source.3');
    $app->get('/geocode/getStreetList/{source:urbis|crab|icar}/{nis5:[0-9]{5}}/[{street}]', StreetHandler::class, 'api.geocode.street.source.2.nis5');
    $app->get('/geocode/getStreetList/{source:urbis|crab|icar}/{postalcode:[0-9]{4}}/[{street}]', StreetHandler::class, 'api.geocode.street.source.2.postalcode');
    $app->get('/geocode/getStreetList/{source:urbis|crab|icar}/{locality}/[{street}]', StreetHandler::class, 'api.geocode.street.source.2');
    $app->get('/geocode/getStreetList/{source:urbis|crab|icar}/{street}', StreetHandler::class, 'api.geocode.street.source');

    $app->get('/geocode/getStreetList/{nis5:[0-9]{5}}/{postalcode:[0-9]{4}}/[{street}]', StreetHandler::class, 'api.geocode.street.3.nis5');
    $app->get('/geocode/getStreetList/{locality}/{postalcode:[0-9]{4}}/[{street}]', StreetHandler::class, 'api.geocode.street.3');
    $app->get('/geocode/getStreetList/{nis5:[0-9]{5}}/[{street}]', StreetHandler::class, 'api.geocode.street.2.nis5');
    $app->get('/geocode/getStreetList/{postalcode:[0-9]{4}}/[{street}]', StreetHandler::class, 'api.geocode.street.2.postalcode');
    $app->get('/geocode/getStreetList/{locality}/[{street}]', StreetHandler::class, 'api.geocode.street.2');
    $app->get('/geocode/getStreetList/{street}', StreetHandler::class, 'api.geocode.street');

    $app->get('/geocode/getAddressList/{source:urbis|crab|icar}/{nis5:[0-9]{5}}/{postalcode:[0-9]{4}}/[{street}/[{number}]]', AddressHandler::class, 'api.geocode.address.source.4.nis5');
    $app->get('/geocode/getAddressList/{source:urbis|crab|icar}/{locality}/{postalcode:[0-9]{4}}/[{street}/[{number}]]', AddressHandler::class, 'api.geocode.address.source.4');
    $app->get('/geocode/getAddressList/{source:urbis|crab|icar}/{nis5:[0-9]{5}}/[{street}/[{number}]]', AddressHandler::class, 'api.geocode.address.source.3.nis5');
    $app->get('/geocode/getAddressList/{source:urbis|crab|icar}/{postalcode:[0-9]{4}}/[{street}/[{number}]]', AddressHandler::class, 'api.geocode.address.source.3.postalcode');
    $app->get('/geocode/getAddressList/{source:urbis|crab|icar}/{locality}/{street}/[{number}]', AddressHandler::class, 'api.geocode.address.source.3');
    $app->get('/geocode/getAddressList/{source:urbis|crab|icar}/{street}/[{number}]', AddressHandler::class, 'api.geocode.address.source.2');
    $app->get('/geocode/getAddressList/{source:urbis|crab|icar}/{address}', AddressHandler::class, 'api.geocode.address.source.1');

    $app->get('/geocode/getAddressList/{nis5:[0-9]{5}}/{postalcode:[0-9]{4}}/[{street}/[{number}]]', AddressHandler::class, 'api.geocode.address.4.nis5');
    $app->get('/geocode/getAddressList/{locality}/{postalcode:[0-9]{4}}/[{street}/[{number}]]', AddressHandler::class, 'api.geocode.address.4');
    $app->get('/geocode/getAddressList/{nis5:[0-9]{5}}/[{street}/[{number}]]', AddressHandler::class, 'api.geocode.address.3.nis5');
    $app->get('/geocode/getAddressList/{postalcode:[0-9]{4}}/[{street}/[{number}]]', AddressHandler::class, 'api.geocode.address.3.postalcode');
    $app->get('/geocode/getAddressList/{locality}/{street}/[{number}]', AddressHandler::class, 'api.geocode.address.3');
    $app->get('/geocode/getAddressList/{street}/[{number}]', AddressHandler::class, 'api.geocode.address.2');
    $app->get('/geocode/getAddressList/{address}', AddressHandler::class, 'api.geocode.address.1');

    $app->get('/geocode/getPOIList/{source}/{poi}', POIHandler::class, 'api.geocode.poi.source');
    $app->get('/geocode/getPOIList/{poi}', POIHandler::class, 'api.geocode.poi');
    // Backward compatibilty
    $app->get('/geocode/getPOI/{source}/{poi}', POIHandler::class, 'api.geocode.poi.source.old');
    $app->get('/geocode/getPOI/{poi}', POIHandler::class, 'api.geocode.poi.old');

    $app->get('/xy/{x:[0-9.]+}/{y:[0-9.]+}', LocationHandler::class, 'api.xy');
    $app->get('/latlng/{latitude:[\-0-9.]+}/{longitude:[\-0-9.]+}', LocationHandler::class, 'api.latlng');
    // Backward compatibilty
    $app->get('/xy/{x:[0-9.]+},{y:[0-9.]+}', LocationHandler::class, 'api.xy.old');
    $app->get('/latlng/{latitude:[\-0-9.]+},{longitude:[\-0-9.]+}', LocationHandler::class, 'api.latlng.old');

    $app->get('/zones/{nis5:[0-9]{5}}', [DbAdapterMiddleware::class, TokenMiddleware::class, QuotaMiddleware::class, ZonesHandler::class], 'api.zones');

    $app->get('/zones/map/{key}/{slug}.png', MapHandler::class, 'api.zones.maps.png');
    $app->get('/zones/map/{key}/{slug}', MapHandler::class, 'api.zones.maps');
};
