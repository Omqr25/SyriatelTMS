import { getCertificateAssets, getCertificateFont } from '../../../Api/certificateApi';

let assets;
const faces = new Map();

export function certificateAssets() {
  assets ??= getCertificateAssets().catch(error => { assets = undefined; throw error; });
  return assets;
}

export async function loadCertificateFonts(families, metrics) {
  await Promise.all(families.flatMap(family => Object.entries(metrics[family]).map(([style, face]) => {
    const alias = certificateFont(family, style, face.sha256);
    if (!faces.has(alias)) {
      faces.set(alias, getCertificateFont(family, style).then(bytes =>
        new FontFace(alias, bytes).load()).then(font => document.fonts.add(font))
        .catch(error => { faces.delete(alias); throw error; }));
    }
    return faces.get(alias);
  })));
}

export const certificateFont = (family, style, hash) => `certificate-${family}-${style}-${hash.slice(0, 8)}`;

export async function backgroundImage(blob, mime, signal) {
  if (mime !== 'application/pdf') return blob;
  const [{ GlobalWorkerOptions, getDocument }, { default: worker }] = await Promise.all([
    import('pdfjs-dist'),
    import('pdfjs-dist/build/pdf.worker.min.mjs?url'),
  ]);
  GlobalWorkerOptions.workerSrc = worker;
  const task = getDocument({
    data: new Uint8Array(await blob.arrayBuffer()), isEvalSupported: false,
    useSystemFonts: true, enableXfa: false, disableFontFace: false, useWorkerFetch: false,
    maxImageSize: 8000000,
  });
  const abort = () => { void task.destroy().catch(() => {}); };
  signal.addEventListener('abort', abort, { once: true });
  try {
    if (signal.aborted) throw new DOMException('Aborted', 'AbortError');
    const document = await task.promise;
    const page = await document.getPage(1);
    const unit = page.getViewport({ scale: 1 });
    const scale = Math.min(3, 2600 / Math.max(unit.width, unit.height));
    const viewport = page.getViewport({ scale });
    const canvas = window.document.createElement('canvas');
    canvas.width = Math.ceil(viewport.width);
    canvas.height = Math.ceil(viewport.height);
    await page.render({ canvas, viewport, annotationMode: 0 }).promise;
    return await new Promise((resolve, reject) => {
      canvas.toBlob(image => image ? resolve(image) : reject(new Error('Background preview failed')), 'image/png');
    });
  } finally {
    signal.removeEventListener('abort', abort);
    await task.destroy();
  }
}
