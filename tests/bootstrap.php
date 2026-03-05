<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap für ContractManager Tests
 *
 * Für Unit-Tests reicht der Composer-Autoloader (alle NC-Klassen werden gemockt).
 * Für Integration-Tests wird die volle Nextcloud-Umgebung benötigt.
 */

// Composer Autoloader laden
require_once __DIR__ . '/../vendor/autoload.php';

// Nextcloud-Umgebung nur laden wenn verfügbar und gewünscht
$nextcloudPath = getenv('NEXTCLOUD_PATH') ?: '/var/www/html';
$loadNextcloud = getenv('LOAD_NEXTCLOUD') ?: false;

if ($loadNextcloud && file_exists($nextcloudPath . '/lib/base.php')) {
	require_once $nextcloudPath . '/lib/base.php';
	\OC::$loader->addValidRoot(__DIR__ . '/..');
	\OC_App::loadApp('contractmanager');
}
