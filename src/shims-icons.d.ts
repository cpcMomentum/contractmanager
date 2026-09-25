/**
 * Typen fuer die Symbol-Komponenten aus `vue-material-design-icons`.
 *
 * Das Paket liefert seine Deklarationen als `Folder.d.vue.ts` aus, und diesen
 * Pfad findet TypeScript unter der „exports"-Karte des Pakets nicht (TS7016).
 * Betroffen sind nur Bloecke mit `lang="ts"`; die aelteren Ansichten der App
 * importieren dieselben Symbole aus JavaScript heraus und liefen deshalb bisher
 * nicht in den Fehler. Diese Deklaration schliesst die Luecke fuer alle.
 */
declare module 'vue-material-design-icons/*.vue' {
	import type { DefineComponent } from 'vue'

	const component: DefineComponent<{
		size?: number
		fillColor?: string
		title?: string
	}>
	export default component
}
