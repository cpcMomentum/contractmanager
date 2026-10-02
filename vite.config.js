/// <reference types="vitest/config" />
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { readFileSync } from 'fs'
import { resolve } from 'path'

// `appName` und `appVersion` sind globale Bezeichner, die @nextcloud/vue liest
// (dist/chunks/appName.mjs) — ohne Ersetzung protokolliert die Bibliothek einen
// Fehler. Beide standen hier bis 09/2026 als Literal: der Name in Grossbuchstaben
// und die Version fest auf '0.5.4' (die App war da bei 1.8.0).
//
// Der Name muss die APP-ID sein, nicht ein Anzeigename: `useLocalizedAppName()`
// sucht damit in der App-Liste (`apps.find(({ id }) => id === appName)`) und fand
// mit 'CONTRACTMANAGER' nie etwas.
//
// Die Version kommt aus `appinfo/info.xml`, der fuehrenden Quelle beim Release
// (der Release-Skill bumpt sie). package.json wird hier zwar ebenfalls gepflegt,
// aber so gibt es nur eine Wahrheit statt zweier, die auseinanderlaufen koennen.
// NcAppSettingsDialog zeigt beides als "<Name> <Version>" an.
const appName = JSON.parse(readFileSync(resolve(__dirname, 'package.json'), 'utf8')).name
const appVersion = readFileSync(resolve(__dirname, 'appinfo/info.xml'), 'utf8')
  .match(/<version>([^<]+)<\/version>/)?.[1]
if (!appVersion) {
  throw new Error('vite.config.js: <version> nicht in appinfo/info.xml gefunden')
}

export default defineConfig(({ mode }) => ({
  plugins: [vue()],
  test: {
    environment: 'happy-dom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    include: ['src/**/*.{test,spec}.{ts,js}'],
  },
  define: {
    // Vue, Pinia und Vue Router werden als esm-bundler-Builds eingebunden. Die
    // setzen voraus, dass der Bundler process.env.NODE_ENV ersetzt, und liefern
    // bewusst keine eigene Definition mit. Im Library-Modus nimmt Vite das nicht
    // automatisch vor, und ein pauschales 'process.env': {} liess NODE_ENV als
    // undefined durchlaufen — womit Vue im Entwicklungspfad blieb und Warnungen,
    // Prop-Typpruefungen und Hydration-Diagnosen im Produktionsbundle landeten.
    'process.env.NODE_ENV': JSON.stringify(mode === 'production' ? 'production' : 'development'),
    // Auffangnetz fuer andere process.env.*-Zugriffe; greift erst nach der
    // spezifischeren Ersetzung oben, da Vite laengere Schluessel zuerst anwendet.
    'process.env': {},
    '__VUE_OPTIONS_API__': true,
    '__VUE_PROD_DEVTOOLS__': false,
    '__VUE_PROD_HYDRATION_MISMATCH_DETAILS__': false,
    appName: JSON.stringify(appName),
    appVersion: JSON.stringify(appVersion),
  },
  resolve: {
    alias: {
      vue: resolve(__dirname, 'node_modules/vue/dist/vue.esm-bundler.js'),
      '@': resolve(__dirname, 'src'),
    },
    dedupe: ['vue'],
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    lib: {
      entry: resolve(__dirname, 'src/main.ts'),
      name: 'contractmanager',
      formats: ['iife'],
      fileName: () => 'js/contractmanager-main.js',
    },
    rollupOptions: {
      output: {
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'css/contractmanager-main.css'
          }
          return 'css/[name][extname]'
        },
      },
    },
  },
}))
