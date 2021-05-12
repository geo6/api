<?php
/**
 * Script for clearing the configuration cache.
 *
 * Can also be invoked as `composer clear-config-cache`.
 *
 * @see       https://github.com/mezzio/mezzio-skeleton for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/mezzio/mezzio-skeleton/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

chdir(__DIR__.'/../');

require 'vendor/autoload.php';

$config = include 'config/config.php';

if (!isset($config['router']['fastroute']['cache_file'])) {
    echo 'No configuration cache path found'.PHP_EOL;
    exit(0);
}

if (!file_exists($config['router']['fastroute']['cache_file'])) {
    printf(
        "Configured config cache file '%s' not found%s",
        $config['router']['fastroute']['cache_file'],
        PHP_EOL
    );
    exit(0);
}

if (false === unlink($config['router']['fastroute']['cache_file'])) {
    printf(
        "Error removing config cache file '%s'%s",
        $config['router']['fastroute']['cache_file'],
        PHP_EOL
    );
    exit(1);
}

printf(
    "Removed configured config cache file '%s'%s",
    $config['router']['fastroute']['cache_file'],
    PHP_EOL
);
exit(0);
