<?php

declare(strict_types=1);

namespace OCA\ContractManager\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
    public const APP_ID = 'contractmanager';

    public function __construct() {
        parent::__construct(self::APP_ID);

        // Load composer autoloader for third-party dependencies (smalot/pdfparser)
        $autoloader = __DIR__ . '/../../vendor/autoload.php';
        if (file_exists($autoloader)) {
            require_once $autoloader;
        }
    }

    public function register(IRegistrationContext $context): void {
        // Reminders are sent via Talk and E-Mail, no Nextcloud notifications
    }

    public function boot(IBootContext $context): void {
        // Boot logic
    }
}
