#!/usr/bin/env node
/**
 * Prueft, dass jeder uebersetzbare String aus src/ auch in den l10n-Dateien steht.
 *
 * Hintergrund (#334): 41 Strings waren ueber Monate nie eingetragen und fielen
 * damit in JEDER Sprache auf den deutschen Quelltext zurueck. Aufgefallen ist es
 * nur zufaellig, weil auf Deutsch entwickelt wird und Nextcloud bei fehlendem
 * Eintrag genau diesen Quelltext anzeigt - die Luecke ist im Alltag unsichtbar.
 *
 * Der Pflichtschritt "bei l10n-Aenderung Sprache umschalten" konnte das nicht
 * auffangen: Wer den Eintrag vergisst, aendert keine l10n-Datei, also greift der
 * Ausloeser nicht. Die Pruefung haengt sonst an genau dem Artefakt, das fehlt.
 * Deshalb hier mechanisch statt als Merksatz.
 *
 * Geprueft wird zweierlei:
 *   1. Jeder t('contractmanager', '...')-String hat einen Eintrag in de.json
 *   2. Alle vier l10n-Dateien tragen denselben Schluesselsatz
 */
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join, extname } from 'node:path'

const APP_ID = 'contractmanager'
const SRC = 'src'
const SOURCE_EXT = new Set(['.vue', '.js', '.ts'])

/** Sammelt rekursiv alle Quelldateien, Testdateien ausgenommen. */
function sourceFiles(dir) {
	const out = []
	for (const entry of readdirSync(dir)) {
		const path = join(dir, entry)
		if (statSync(path).isDirectory()) {
			out.push(...sourceFiles(path))
		} else if (SOURCE_EXT.has(extname(entry)) && !entry.includes('.test.')) {
			out.push(path)
		}
	}
	return out
}

/**
 * Einfach-gequotete Strings koennen \' enthalten ("Er sagt \'hallo\'"), deshalb
 * matcht das Muster escapte Zeichen mit und macht sie danach rueckgaengig.
 */
const CALL = new RegExp(`\\bt\\(\\s*'${APP_ID}'\\s*,\\s*'((?:[^'\\\\]|\\\\.)*)'`, 'g')

function usedStrings() {
	const found = new Map()
	for (const file of sourceFiles(SRC)) {
		const code = readFileSync(file, 'utf8')
		for (const match of code.matchAll(CALL)) {
			const text = match[1].replace(/\\'/g, "'").replace(/\\\\/g, '\\')
			if (!found.has(text)) {
				found.set(text, file)
			}
		}
	}
	return found
}

function jsonKeys(path) {
	return new Set(Object.keys(JSON.parse(readFileSync(path, 'utf8')).translations))
}

/** Die .js-Varianten sind kein JSON, deshalb ueber dieselbe Schluessel-Syntax gelesen. */
function jsKeys(path) {
	const code = readFileSync(path, 'utf8')
	const keys = new Set()
	for (const match of code.matchAll(/^\s*"((?:[^"\\]|\\.)*)"\s*:/gm)) {
		keys.add(match[1].replace(/\\"/g, '"').replace(/\\\\/g, '\\'))
	}
	return keys
}

const used = usedStrings()
const files = {
	'l10n/de.json': jsonKeys('l10n/de.json'),
	'l10n/en.json': jsonKeys('l10n/en.json'),
	'l10n/de.js': jsKeys('l10n/de.js'),
	'l10n/en.js': jsKeys('l10n/en.js'),
}

const problems = []

const missing = [...used].filter(([text]) => !files['l10n/de.json'].has(text))
if (missing.length > 0) {
	problems.push(`${missing.length} übersetzbare Strings ohne l10n-Eintrag:`)
	for (const [text, file] of missing.sort((a, b) => a[1].localeCompare(b[1]))) {
		problems.push(`  ${file}: ${JSON.stringify(text)}`)
	}
}

const reference = files['l10n/de.json']
for (const [name, keys] of Object.entries(files)) {
	if (name === 'l10n/de.json') {
		continue
	}
	const absent = [...reference].filter(k => !keys.has(k))
	const extra = [...keys].filter(k => !reference.has(k))
	if (absent.length > 0) {
		problems.push(`${name}: ${absent.length} Schlüssel fehlen, u. a. ${JSON.stringify(absent[0])}`)
	}
	if (extra.length > 0) {
		problems.push(`${name}: ${extra.length} Schlüssel zu viel, u. a. ${JSON.stringify(extra[0])}`)
	}
}

if (problems.length > 0) {
	console.error('l10n-Prüfung fehlgeschlagen\n')
	console.error(problems.join('\n'))
	console.error('\nJeder sichtbare String gehört in alle vier Dateien:')
	console.error('  l10n/de.js  l10n/de.json  l10n/en.js  l10n/en.json')
	console.error('Deutsche Werte identisch zum Quelltext, englische übersetzt.')
	process.exit(1)
}

console.log(`l10n ok: ${used.size} Strings, ${reference.size} Einträge, vier Dateien deckungsgleich`)
