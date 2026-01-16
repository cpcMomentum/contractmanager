<?php

declare(strict_types=1);

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {
    public function __construct(
        IRequest $request
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        Util::addScript(Application::APP_ID, 'contractmanager-main');
        Util::addStyle(Application::APP_ID, 'main');

        return new TemplateResponse(Application::APP_ID, 'main');
    }
}
