<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap für ContractManager Tests
 *
 * Lädt die Nextcloud-Umgebung und Autoloader
 */

// Nextcloud Server-Pfad (im Docker-Container)
$nextcloudPath = getenv('NEXTCLOUD_PATH') ?: '/var/www/html';

// Nextcloud Autoloader laden
require_once $nextcloudPath . '/lib/base.php';

// PHPUnit Autoloader (falls nicht über Composer geladen)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// App-Namespace registrieren
\OC::$loader->addValidRoot(__DIR__ . '/..');

// App laden
\OC_App::loadApp('contractmanager');
