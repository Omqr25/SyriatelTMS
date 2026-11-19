import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { firebaseConfig } from './src/firebase/config.js';

function firebaseWorkerConfig() {
  const fileName = 'firebase-worker-config.js';
  let source;
  let base;
  return {
    name: 'firebase-worker-config',
    configResolved(config) {
      base = config.base;
      source = `self.firebaseWorkerConfig = ${JSON.stringify(firebaseConfig(config.env))};\n`;
    },
    configureServer(server) {
      server.middlewares.use(base + fileName, (_request, response) => {
        response.setHeader('Content-Type', 'application/javascript');
        response.end(source);
      });
    },
    generateBundle() {
      this.emitFile({ type: 'asset', fileName, source });
    },
  };
}

export default defineConfig({
  plugins: [react(), firebaseWorkerConfig()],
});
