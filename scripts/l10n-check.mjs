#!/usr/bin/env node
/**
 * l10n-check.mjs — Konsistenz-Waechter fuer die Uebersetzungskataloge.
 *
 * Herkunft: 1:1 aus worktime (worktime#259, Lehre aus 0.12.0-Review #394),
 * angepasst sind nur APP_ID und die Sprachliste.
 *
 * Hintergrund hier (contractmanager#340):
 * Der `t('contractmanager', '…')`-Lookup ist byte-genau. Die Kataloge in l10n/
 * werden von Hand gepflegt → sie driften: fehlende Keys, tote Keys, typografische
 * Mismatches (`…` vs `...`), .js und .json laufen auseinander. Folge: Nutzer
 * sehen statt der Uebersetzung den deutschen Quelltext.
 *
 * Der Vorgaenger `check-l10n.mjs` (#334) sah nur `src/`. Dadurch blieben 29
 * Backend-Strings unbemerkt, die seit 03/2026 englisch ausgeliefert wurden,
 * waehrend die Pruefung gruen meldete. Dieser Waechter leitet die Wahrheit aus
 * dem gesamten CODE ab — `src/` UND `lib/`, `templates/`, `appinfo/` — und
 * prueft die 4 Kataloge dagegen.
 *
 *   node scripts/l10n-check.mjs          → pruefen (Exit 1 bei struktureller Drift)
 *   node scripts/l10n-check.mjs --fix    → Kataloge aus dem Code regenerieren
 *
 * Bewusst KEINE neuen Dependencies (laeuft mit dem ohnehin vorhandenen Node).
 * Deutsch bleibt Quellsprache. Plural (`n()`) wird aktuell nicht verwendet.
 */

import { readFileSync, writeFileSync, readdirSync, statSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join, relative } from 'node:path'
import { runInNewContext } from 'node:vm'

const APP_ID = 'contractmanager'
const SOURCE_LANG = 'de'
const LANGS = ['de', 'en']

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..')
const L10N_DIR = join(ROOT, 'l10n')

// Quellen der uebersetzbaren Strings: Frontend (Vue/JS) UND Backend (PHP).
// NC-Kataloge decken beide Seiten ab — der Notifier/Service nutzt $l->t(...).
const FRONTEND_DIRS = ['src']
const BACKEND_DIRS = ['lib', 'templates', 'appinfo']

const FIX = process.argv.includes('--fix')

// ANSI
const red = (s) => `\x1b[31m${s}\x1b[0m`
const green = (s) => `\x1b[32m${s}\x1b[0m`
const yellow = (s) => `\x1b[33m${s}\x1b[0m`
const dim = (s) => `\x1b[2m${s}\x1b[0m`

// --- 1. Kanonische Keys aus dem Quellcode -----------------------------------

/** Rekursiv Dateien mit passender Endung unter dir einsammeln. */
function filesUnder(dir, re) {
	const out = []
	let entries
	try { entries = readdirSync(dir) } catch { return out } // Verzeichnis fehlt → leer
	for (const name of entries) {
		const p = join(dir, name)
		if (statSync(p).isDirectory()) out.push(...filesUnder(p, re))
		else if (re.test(name)) out.push(p)
	}
	return out
}

// Frontend: t('worktime', '<literal>') — single-quoted, erstes Argument.
const T_FRONTEND = /\bt\(\s*'contractmanager'\s*,\s*'((?:[^'\\]|\\.)*)'/g
// Backend (PHP): $l->t('…') oder ->t("…"); Whitespace/Newlines toleriert (mehrzeilige Aufrufe).
const T_BACKEND_SQ = /->t\(\s*'((?:[^'\\]|\\.)*)'/g
const T_BACKEND_DQ = /->t\(\s*"((?:[^"\\]|\\.)*)"/g

/** Escapes eines single-quoted PHP/JS-Literals aufloesen (nur \' bzw. \" und \\). */
function unescapeSingle(lit, quote) {
	return lit.replace(new RegExp(`\\\\([${quote}\\\\])`, 'g'), '$1')
}

function extractCanonicalKeys() {
	const keys = new Set()
	// Frontend
	for (const base of FRONTEND_DIRS) {
		for (const file of filesUnder(join(ROOT, base), /\.(js|vue)$/)) {
			const code = readFileSync(file, 'utf8')
			for (const m of code.matchAll(T_FRONTEND)) keys.add(unescapeSingle(m[1], "'"))
		}
	}
	// Backend (PHP)
	for (const base of BACKEND_DIRS) {
		for (const file of filesUnder(join(ROOT, base), /\.php$/)) {
			const code = readFileSync(file, 'utf8')
			for (const m of code.matchAll(T_BACKEND_SQ)) keys.add(unescapeSingle(m[1], "'"))
			for (const m of code.matchAll(T_BACKEND_DQ)) keys.add(unescapeSingle(m[1], '"'))
		}
	}
	return keys
}

// --- 2. Kataloge laden ------------------------------------------------------

/** l10n/<lang>.js → { map, plural }. Per kontrolliertem Eval (eigene Datei). */
function loadJs(lang) {
	const code = readFileSync(join(L10N_DIR, `${lang}.js`), 'utf8')
	let captured = null
	const sandbox = {
		OC: { L10N: { register(app, map, plural) { captured = { map, plural } } } },
	}
	runInNewContext(code, sandbox, { filename: `${lang}.js` })
	if (!captured) throw new Error(`${lang}.js hat OC.L10N.register nicht aufgerufen`)
	return captured
}

/** l10n/<lang>.json → { translations, pluralForm }. */
function loadJson(lang) {
	const data = JSON.parse(readFileSync(join(L10N_DIR, `${lang}.json`), 'utf8'))
	return { translations: data.translations ?? {}, pluralForm: data.pluralForm ?? '' }
}

// --- 3. Kataloge schreiben (Format byte-genau wie Bestand) ------------------

function writeJs(lang, map, plural) {
	const entries = Object.entries(map)
		.map(([k, v]) => `    ${JSON.stringify(k)} : ${JSON.stringify(v)}`)
		.join(',\n')
	const body = `OC.L10N.register(\n    "${APP_ID}",\n    {\n${entries}\n},\n"${plural}");\n`
	writeFileSync(join(L10N_DIR, `${lang}.js`), body)
}

function writeJson(lang, translations, pluralForm) {
	const body = JSON.stringify({ translations, pluralForm }, null, '\t') + '\n'
	writeFileSync(join(L10N_DIR, `${lang}.json`), body)
}

// --- 4. Pruefen -------------------------------------------------------------

const diff = (a, b) => [...a].filter((x) => !b.has(x)) // Elemente in a, nicht in b

function main() {
	const canonical = extractCanonicalKeys()
	const problems = [] // blockierend
	const info = [] // nicht blockierend

	const catalogs = {}
	for (const lang of LANGS) {
		catalogs[lang] = { js: loadJs(lang), json: loadJson(lang) }
	}

	for (const lang of LANGS) {
		const { js, json } = catalogs[lang]
		const jsKeys = new Set(Object.keys(js.map))
		const jsonKeys = new Set(Object.keys(json.translations))

		// (a) .js vs .json: identische Keys
		const onlyJs = diff(jsKeys, jsonKeys)
		const onlyJson = diff(jsonKeys, jsKeys)
		if (onlyJs.length || onlyJson.length) {
			problems.push(`${lang}: .js und .json haben unterschiedliche Keys `
				+ `(nur in .js: ${onlyJs.length}, nur in .json: ${onlyJson.length})`)
		}
		// (b) .js vs .json: identische Werte (faengt typografische Drift)
		const valMismatch = [...jsKeys].filter((k) => jsonKeys.has(k) && js.map[k] !== json.translations[k])
		if (valMismatch.length) {
			problems.push(`${lang}: ${valMismatch.length} Wert(e) weichen zwischen .js und .json ab `
				+ `(z.B. ${JSON.stringify(valMismatch[0])})`)
		}

		// (c) Key-Set vs Code (kanonisch)
		const missing = diff(canonical, jsonKeys) // im Code, nicht im Katalog
		const orphan = diff(jsonKeys, canonical) // im Katalog, nicht im Code (tot)
		if (missing.length) {
			const label = lang === SOURCE_LANG ? 'fehlt im Quellkatalog' : 'fehlende Uebersetzung'
			problems.push(`${lang}: ${missing.length} Key(s) ${label} `
				+ `(z.B. ${JSON.stringify(missing[0])})`)
		}
		if (orphan.length) {
			problems.push(`${lang}: ${orphan.length} tote(r) Key(s) (im Katalog, nicht im Code) `
				+ `(z.B. ${JSON.stringify(orphan[0])})`)
		}

		// (d) Info: noch unuebersetzt (Wert == deutscher Quelltext == Key)
		if (lang !== SOURCE_LANG) {
			const untranslated = [...jsonKeys].filter((k) => canonical.has(k) && json.translations[k] === k)
			if (untranslated.length) {
				info.push(`${lang}: ${untranslated.length} Eintrag/Eintraege noch unuebersetzt (== deutscher Quelltext)`)
			}
		}
	}

	// --- Ausgabe / Fix ---
	console.log(dim(`Quelle: ${canonical.size} eindeutige Keys aus Quellcode (src/, lib/, templates/, appinfo/)`))

	if (FIX) {
		applyFix(canonical, catalogs)
		return
	}

	for (const i of info) console.log(yellow('  ⚐ ' + i))

	if (problems.length === 0) {
		console.log(green('✓ l10n-Kataloge konsistent — keine Drift.'))
		process.exit(0)
	}

	console.log(red(`\n✗ ${problems.length} Konsistenz-Problem(e):`))
	for (const p of problems) console.log(red('  • ' + p))
	console.log(dim('\n  Beheben:  npm run l10n:fix   (regeneriert die Kataloge aus dem Code)'))
	console.log(dim('  Danach Uebersetzungen fuer neue Keys in l10n/{en,cs}.{js,json} ergaenzen.'))
	process.exit(1)
}

// --- 5. Fix: Kataloge aus Code regenerieren, vorhandene Werte erhalten -------

function applyFix(canonical, catalogs) {
	const canonicalList = [...canonical]
	let changed = 0

	for (const lang of LANGS) {
		const { js, json } = catalogs[lang]
		// Bestehende Werte (json als Referenz) + Reihenfolge erhalten, neue Keys hinten anhaengen.
		const existing = json.translations
		const existingOrder = Object.keys(existing).filter((k) => canonical.has(k))
		const newKeys = canonicalList.filter((k) => !(k in existing))
		const orderedKeys = [...existingOrder, ...newKeys]

		const map = {}
		for (const k of orderedKeys) {
			if (lang === SOURCE_LANG) map[k] = k // Quellsprache: Wert == Key
			else map[k] = k in existing ? existing[k] : k // sonst: Bestand behalten, sonst DE-Fallback
		}

		const before = JSON.stringify(existing)
		const after = JSON.stringify(map)
		if (before !== after || Object.keys(js.map).length !== orderedKeys.length) changed++

		writeJs(lang, map, js.plural || json.pluralForm)
		writeJson(lang, map, json.pluralForm || js.plural)

		const added = newKeys.length
		const removed = Object.keys(existing).filter((k) => !canonical.has(k)).length
		console.log(`  ${lang}: ${green(`+${added}`)} neu, ${red(`-${removed}`)} tot, ${orderedKeys.length} gesamt`)
	}

	console.log(changed
		? green(`\n✓ Kataloge regeneriert. Bitte neue Keys in en/cs uebersetzen und committen.`)
		: green(`\n✓ Bereits konsistent — nichts zu tun.`))
}

main()
