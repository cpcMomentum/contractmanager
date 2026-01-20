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
    }

    public function register(IRegistrationContext $context): void {
        // Reminders are sent via Talk and E-Mail, no Nextcloud notifications
    }

    public function boot(IBootContext $context): void {
        // Boot logic
    }
}
