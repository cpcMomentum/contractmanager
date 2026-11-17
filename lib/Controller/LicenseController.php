<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Exception\WerkPlusApiException;
use OCA\ContractManager\Service\EntitlementService;
use OCA\ContractManager\Service\PermissionService;
use OCA\ContractManager\Service\WerkPlusClient;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;

// WerkPlus-Lizenz: nur Nextcloud-Admins, wie die uebrige Verwaltung von VertragsWerk.
class LicenseController extends Controller {

    public function __construct(
        IRequest $request,
        private readonly ?string $userId,
        private readonly PermissionService $permissionService,
        private readonly EntitlementService $entitlementService,
        private readonly IL10N $l10n,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    public function show(): DataResponse {
        if (($resp = $this->requireNextcloudAdmin()) !== null) {
            return $resp;
        }
        return new DataResponse($this->status());
    }

    #[NoAdminRequired]
    public function activate(#[\SensitiveParameter] string $key = ''): DataResponse {
        if (($resp = $this->requireNextcloudAdmin()) !== null) {
            return $resp;
        }
        try {
            $this->entitlementService->activate($key);
        } catch (\InvalidArgumentException $e) {
            return $this->invalidInput($e->getMessage());
        } catch (WerkPlusApiException $e) {
            return $this->apiError($e);
        }
        return new DataResponse($this->status());
    }

    // Der Schluessel steht nur in dieser Antwort; spaeter liefert der Status nur noch den Hinweis.
    #[NoAdminRequired]
    public function trial(): DataResponse {
        if (($resp = $this->requireNextcloudAdmin()) !== null) {
            return $resp;
        }
        try {
            $key = $this->entitlementService->startTrial();
        } catch (\InvalidArgumentException $e) {
            return $this->invalidInput($e->getMessage());
        } catch (WerkPlusApiException $e) {
            return $this->apiError($e);
        }
        return new DataResponse(['key' => $key, 'status' => $this->status()]);
    }

    #[NoAdminRequired]
    public function refresh(): DataResponse {
        if (($resp = $this->requireNextcloudAdmin()) !== null) {
            return $resp;
        }
        try {
            $this->entitlementService->refresh();
        } catch (\InvalidArgumentException $e) {
            return $this->invalidInput($e->getMessage());
        } catch (WerkPlusApiException $e) {
            return $this->apiError($e);
        }
        return new DataResponse($this->status());
    }

    #[NoAdminRequired]
    public function destroy(): DataResponse {
        if (($resp = $this->requireNextcloudAdmin()) !== null) {
            return $resp;
        }
        try {
            $this->entitlementService->removeKey();
        } catch (\InvalidArgumentException $e) {
            return $this->invalidInput($e->getMessage());
        }
        return new DataResponse($this->status());
    }

    /**
     * @return array<string, mixed>
     */
    private function status(): array {
        $status = $this->entitlementService->getStatus();
        $status['canManage'] = $this->userId !== null && $this->permissionService->isAdmin($this->userId);
        $lastError = $status['lastError'];
        $status['lastError'] = $lastError === null ? null : [
            'code' => $lastError['code'],
            'message' => $this->messageFor($lastError['code'], $lastError['serverMessage']),
            'at' => $lastError['at'] > 0 ? gmdate('Y-m-d\TH:i:s\Z', $lastError['at']) : null,
        ];
        return $status;
    }

    private function apiError(WerkPlusApiException $e): DataResponse {
        return new DataResponse([
            'error' => $this->messageFor($e->getErrorCode(), $e->getServerMessage()),
            'code' => $e->getErrorCode(),
            'status' => $this->status(),
        ], Http::STATUS_BAD_REQUEST);
    }

    private function invalidInput(string $field): DataResponse {
        $message = match ($field) {
            'keySet' => $this->l10n->t('Es ist bereits ein WerkPlus-Schlüssel hinterlegt. Ein Testzeitraum ist nur ohne Schlüssel möglich.'),
            'storeTooNew' => $this->l10n->t('Eine andere Werk-App hat die Lizenz in einem neueren Format gespeichert. Bitte aktualisiere VertragsWerk.'),
            default => $this->l10n->t('Bitte gib einen gültigen WerkPlus-Schlüssel ein.'),
        };
        return new DataResponse(['error' => $message, 'code' => 'INPUT_INVALID'], Http::STATUS_BAD_REQUEST);
    }

    // Verzweigt wird ueber den Code; die Servermeldung erscheint nur, wenn der Code unbekannt ist.
    private function messageFor(string $code, string $serverMessage): string {
        return match ($code) {
            'REQUEST_INVALID' => $this->l10n->t('Die Anfrage an WerkPlus war fehlerhaft. Bitte melde den Fehler dem Anbieter.'),
            'KEY_UNKNOWN' => $this->l10n->t('Dieser Schlüssel ist nicht bekannt. Bitte prüfe die Schreibweise.'),
            'KEY_REVOKED' => $this->l10n->t('Dieser Schlüssel wurde gesperrt. VertragsWerk arbeitet im freien Umfang weiter.'),
            'KEY_EXPIRED' => $this->l10n->t('Die Lizenz ist abgelaufen. VertragsWerk arbeitet im freien Umfang weiter.'),
            'INSTANCE_LIMIT_REACHED' => $this->l10n->t('Diese Lizenz ist einer anderen Nextcloud zugeordnet. Den Umzug kannst du im Kundenportal veranlassen.'),
            'TRIAL_ALREADY_USED' => $this->l10n->t('Für diese Nextcloud wurde bereits ein Testzeitraum genutzt. Für WerkPlus brauchst du einen Schlüssel.'),
            'TRIAL_UNAVAILABLE' => $this->l10n->t('Der Testzeitraum lässt sich gerade nicht in der App starten. Unter werkwolke.de/testen steht, wie du ihn trotzdem bekommst.'),
            'RATE_LIMITED' => $this->l10n->t('Zu viele Versuche. Bitte versuche es später erneut.'),
            WerkPlusClient::UNREACHABLE => $this->l10n->t('WerkPlus ist gerade nicht erreichbar. Bitte versuche es später erneut.'),
            EntitlementService::TOKEN_REJECTED => $this->l10n->t('Die Antwort von WerkPlus ließ sich nicht prüfen. Die Lizenz wurde nicht übernommen.'),
            'SERVER_ERROR', WerkPlusClient::INVALID_RESPONSE => $this->l10n->t('WerkPlus hat einen Fehler gemeldet. Bitte versuche es später erneut.'),
            default => $serverMessage !== '' ? $serverMessage : $this->l10n->t('WerkPlus hat einen Fehler gemeldet. Bitte versuche es später erneut.'),
        };
    }

    // Der Schluessel gilt fuer alle Werk-Apps dieser Nextcloud, deshalb aendern ihn nur Nextcloud-Admins.
    private function requireNextcloudAdmin(): ?DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => $this->l10n->t('Nicht angemeldet')], Http::STATUS_UNAUTHORIZED);
        }
        if (!$this->permissionService->isAdmin($this->userId)) {
            return new DataResponse(['error' => $this->l10n->t('Die WerkPlus-Lizenz gilt für alle Werk-Apps dieser Nextcloud und kann nur von Nextcloud-Admins geändert werden.')], Http::STATUS_FORBIDDEN);
        }
        return null;
    }
}
