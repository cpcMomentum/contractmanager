<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\ContractManager\Service;

/**
 * Adressen auf werkwolke.de nach app-links-v1. Einzige Stelle in der App, die Oberflaeche bekommt sie per Initial State.
 * Nur der Parameter `app`, nie Kennung, Schluessel oder Version.
 */
final class WerkPlusLinks {

    public const BUY = 'https://werkwolke.de/preis?app=vertragswerk';
    public const FEATURES = 'https://werkwolke.de/funktionen#vertragswerk';
    public const TRIAL = 'https://werkwolke.de/testen?app=vertragswerk';

    /** @return array{buy: string, features: string, trial: string} */
    public static function all(): array {
        return ['buy' => self::BUY, 'features' => self::FEATURES, 'trial' => self::TRIAL];
    }
}
