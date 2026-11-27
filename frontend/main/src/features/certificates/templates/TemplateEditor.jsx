import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '../../../components/ui/Button';
import Loading from '../../../components/ui/Loading';
import Modal from '../../../components/ui/Modal';
import { Field, Input, Select } from '../../../components/ui/Field';
import { getCertificateBackground, getCertificateProof, saveCertificateTemplate, uploadCertificateBackground } from '../../../Api/certificateApi';
import CertificateCanvas from './CertificateCanvas';
import TemplateInspector from './TemplateInspector';
import { backgroundImage, certificateAssets, loadCertificateFonts } from './previewAssets';
import { templateError } from './templateError';
import styles from './Templates.module.css';

const initialState = template => ({
  name: template?.name ?? '',
  configuration: template?.configuration ?? { page: null, boxes: [] },
  background: template ? { version: template.version_id, mime: template.mime } : null,
});

function newBox(page) {
  return {
    x: page.width * .08, y: page.height * .12, w: page.width * .55, h: Math.min(38, page.height * .7),
    font: 'sans', size: 16, min: 10, fit: false, leading: 1.32, align: 'left', color: '#242933', paragraphs: [[]],
  };
}

function resized(boxes, oldPage, newPage) {
  if (!oldPage) return boxes;
  const sx = newPage.width / oldPage.width, sy = newPage.height / oldPage.height;
  return boxes.map(box => ({
    ...box, x: Math.min(box.x * sx, newPage.width - 1), y: Math.min(box.y * sy, newPage.height - 1),
    w: Math.max(1, box.w * sx), h: Math.max(1, box.h * sy),
  }));
}

export default function TemplateEditor({ template, mode, onClose, onSaved, guard }) {
  const { t } = useTranslation('certificates', { keyPrefix: 'templates' });
  const [baseline, setBaseline] = useState(() => initialState(template));
  const [history, setHistory] = useState(() => ({ past: [], present: baseline, future: [] }));
  const { present: draft } = history;
  const { configuration: { page, boxes }, background } = draft;
  const [version, setVersion] = useState(template?.version_id);
  const [id, setId] = useState(template?.id);
  const [selected, setSelected] = useState(null);
  const [assets, setAssets] = useState(null);
  const [fontsReady, setFontsReady] = useState(false);
  const [backgroundUrl, setBackgroundUrl] = useState(null);
  const [loadingImage, setLoadingImage] = useState(false);
  const [zoom, setZoom] = useState(100);
  const [sample, setSample] = useState('short');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [proof, setProof] = useState(null);
  const editable = mode !== 'preview';
  const dirty = editable && JSON.stringify(draft) !== JSON.stringify(baseline);
  const families = [...new Set(boxes.map(box => box.font))].sort().join(',');
  const box = boxes[selected];

  useEffect(() => {
    let active = true;
    certificateAssets().then(value => { if (active) setAssets(value); })
      .catch(() => { if (active) setError(t('previewFailed')); });
    return () => { active = false; };
  }, [t]);

  useEffect(() => {
    let active = true;
    setFontsReady(false);
    if (assets) loadCertificateFonts(families ? families.split(',') : [], assets.fonts)
      .then(() => { if (active) setFontsReady(true); })
      .catch(() => { if (active) setError(t('previewFailed')); });
    return () => { active = false; };
  }, [assets, families, t]);

  useEffect(() => {
    setBackgroundUrl(null);
    if (!background) return;
    const abort = new AbortController();
    let objectUrl;
    setLoadingImage(true);
    getCertificateBackground(background, abort.signal)
      .then(blob => backgroundImage(blob, background.mime, abort.signal))
      .then(image => {
        if (abort.signal.aborted) return;
        objectUrl = URL.createObjectURL(image);
        setBackgroundUrl(objectUrl);
      })
      .catch(async err => { if (!abort.signal.aborted) setError(await templateError(err, t('previewFailed'))); })
      .finally(() => { if (!abort.signal.aborted) setLoadingImage(false); });
    return () => { abort.abort(); if (objectUrl) URL.revokeObjectURL(objectUrl); };
  }, [background, t]);

  useEffect(() => {
    const allow = () => !busy && (!dirty || window.confirm(t('discard')));
    guard.current = allow;
    if (!dirty && !busy) return () => { guard.current = null; };

    const unload = event => { event.preventDefault(); event.returnValue = ''; };
    const links = event => {
      const link = event.target.closest?.('a[href]');
      if (link && !link.target && link.href !== location.href && !allow()) {
        event.preventDefault();
        event.stopPropagation();
      }
    };
    window.addEventListener('beforeunload', unload);
    document.addEventListener('click', links, true);
    return () => {
      guard.current = null;
      window.removeEventListener('beforeunload', unload);
      document.removeEventListener('click', links, true);
    };
  }, [busy, dirty, guard, t]);

  useEffect(() => () => { if (proof) URL.revokeObjectURL(proof); }, [proof]);

  function change(transform) {
    setNotice('');
    setHistory(current => {
      const next = transform(current.present);
      if (JSON.stringify(next) === JSON.stringify(current.present)) return current;
      return { past: [...current.past.slice(-79), current.present], present: next, future: [] };
    });
  }

  function step(direction) {
    setNotice('');
    setHistory(current => direction === 'undo'
      ? current.past.length ? { past: current.past.slice(0, -1), present: current.past.at(-1), future: [current.present, ...current.future] } : current
      : current.future.length ? { past: [...current.past, current.present], present: current.future[0], future: current.future.slice(1) } : current);
  }

  function transformBoxes(callback) {
    change(current => ({ ...current, configuration: { ...current.configuration, boxes: callback(current.configuration.boxes) } }));
  }

  function move(index, patch) {
    transformBoxes(previous => previous.map((item, i) => i === index ? { ...item, ...patch } : item));
  }

  function payload() {
    return {
      configuration: draft.configuration,
      ...(background?.token ? { background_token: background.token } : { version_id: background?.version }),
    };
  }

  async function run(fn) {
    setBusy(true); setError('');
    try { await fn(); }
    catch (err) { setError(await templateError(err, t('requestFailed'))); }
    finally { setBusy(false); }
  }

  async function upload(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    await run(async () => {
      const source = await uploadCertificateBackground(file);
      change(current => ({
        ...current, background: { token: source.token, mime: source.mime },
        configuration: { page: source.page, boxes: resized(current.configuration.boxes, current.configuration.page, source.page) },
      }));
    });
  }

  async function save(event) {
    event.preventDefault();
    await run(async () => {
      const saved = await saveCertificateTemplate(id, { ...payload(), name: draft.name, ...(id && { base_version: version }) });
      const state = initialState(saved);
      setId(saved.id); setVersion(saved.version_id);
      setBaseline(state);
      setHistory({ past: [], present: state, future: [] });
      setNotice(t('saved', { version: saved.version }));
      onSaved();
    });
  }

  return <section className={styles.editor} onKeyDown={event => {
    if (!editable || !(event.ctrlKey || event.metaKey) || event.key.toLowerCase() !== 'z') return;
    event.preventDefault();
    step(event.shiftKey ? 'redo' : 'undo');
  }}>
    <div className={styles.toolbar}>
      <h2>{t(editable ? (id ? 'edit' : 'create') : 'preview')}</h2>
      <Button variant="soft" onClick={() => { if (!guard.current || guard.current()) onClose(); }}>{t('back')}</Button>
    </div>
    {error && <p role="alert" className={styles.warning}>{error}</p>}
    {notice && <p role="status">{notice}</p>}
    <form id="certificate-template-form" onSubmit={save}>
      <div className={styles.toolbar}>
        <Field label={t('name')}><Input required maxLength={255} value={draft.name} disabled={!editable || busy}
          onChange={event => change(current => ({ ...current, name: event.target.value }))} /></Field>
        {editable && <Field label={t('background')}><Input type="file" accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/jpeg,image/png" onChange={upload} disabled={busy} /></Field>}
        {editable && <Button type="submit" disabled={!dirty || !page || busy || !draft.name.trim()} loading={busy}>{t('save')}</Button>}
      </div>
    </form>
    {page ? <>
      <div className={styles.toolbar}>
        <div className={styles.actions}>
          {editable && <>
            <Button size="sm" variant="soft" disabled={busy || boxes.length >= 30} onClick={() => {
              setSelected(boxes.length); transformBoxes(previous => [...previous, newBox(page)]);
            }}>{t('addBox')}</Button>
            <Button size="sm" variant="soft" disabled={!history.past.length || busy} onClick={() => step('undo')}>{t('undo')}</Button>
            <Button size="sm" variant="soft" disabled={!history.future.length || busy} onClick={() => step('redo')}>{t('redo')}</Button>
          </>}
          <Field label={t('zoom')}><Select aria-label={t('zoom')} value={zoom} onChange={event => setZoom(Number(event.target.value))}>
            {[50, 75, 100, 125, 150, 200].map(value => <option key={value} value={value}>{value}%</option>)}
          </Select></Field>
          <Field label={t('sample')}><Select aria-label={t('sample')} value={sample} onChange={event => setSample(event.target.value)}>
            <option value="short">{t('shortSample')}</option><option value="long">{t('longSample')}</option>
          </Select></Field>
          <Button size="sm" variant="soft" disabled={busy || !assets} onClick={() => run(async () => {
            setProof(URL.createObjectURL(await getCertificateProof({ ...payload(), sample })));
          })}>{t('proof')}</Button>
        </div>
        <span dir="ltr" className={styles.muted}>{page.width.toFixed(1)} × {page.height.toFixed(1)} mm</span>
      </div>
      <div className={editable ? styles.workspace : styles.readOnly}>
        <div>
          {assets && fontsReady && !loadingImage && <CertificateCanvas page={page} boxes={boxes} selected={selected}
            select={setSelected} move={move} background={backgroundUrl} assets={assets} sample={sample} zoom={zoom} editable={editable && !busy} t={t} />}
          {loadingImage && <Loading />}
        </div>
        {editable && assets && <aside aria-label={t('properties')}>
          <TemplateInspector key={selected} box={box} page={page} change={patch => move(selected, patch)} placeholders={assets.placeholders} t={t}
            remove={() => { transformBoxes(previous => previous.filter((_, i) => i !== selected)); setSelected(null); }} />
        </aside>}
      </div>
    </> : <p className={styles.muted}>{t('backgroundHint')}</p>}
    {proof && <Modal title={t('proof')} onClose={() => setProof(null)}>
      <iframe src={proof} title={t('proof')} className={styles.proof} />
      <a href={proof} download="certificate-proof.pdf">{t('downloadProof')}</a>
    </Modal>}
  </section>;
}
