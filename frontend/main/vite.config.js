import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join } from 'node:path';
import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import { firebaseConfig } from './src/firebase/config.js';
import { apiBaseUrl } from './src/Api/apiBaseUrl.js';

function firebaseWorkerAssets() {
  const require = createRequire(import.meta.url);
  const firebaseDir = dirname(require.resolve('firebase/package.json'));
  const assets = new Map();
  let base;
  return {
    name: 'firebase-worker-assets',
    configResolved(config) {
      base = config.base;
      assets.set('firebase-worker-config.js', `self.firebaseWorkerConfig = ${JSON.stringify(firebaseConfig(config.env))};\n`);
      for (const file of ['firebase-app-compat.js', 'firebase-messaging-compat.js']) {
        assets.set(file, readFileSync(join(firebaseDir, file), 'utf8').replace(/\/\/# sourceMappingURL=.*$/m, ''));
      }
    },
    configureServer(server) {
      for (const [fileName, source] of assets) {
        server.middlewares.use(base + fileName, (_request, response) => {
          response.setHeader('Content-Type', 'application/javascript');
          response.end(source);
        });
      }
    },
    generateBundle() {
      for (const [fileName, source] of assets) {
        this.emitFile({ type: 'asset', fileName, source });
      }
    },
  };
}

export default defineConfig(({ mode }) => {
  apiBaseUrl(loadEnv(mode, process.cwd()).VITE_API_URL);
  return { plugins: [react(), firebaseWorkerAssets()] };
});
