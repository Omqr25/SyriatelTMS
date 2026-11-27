import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '../../components/ui/Button';
import Table from '../../components/ui/Table';
import Loading from '../../components/ui/Loading';
import Modal from '../../components/ui/Modal';
import { Field, Input } from '../../components/ui/Field';
import { getCertificates, getCertificateCandidates, issueCertificate, getCertificateFile } from '../../Api/certificateApi';
import styles from './CertificatesPage.module.css';
import CertificateTemplatesTab from './templates/CertificateTemplatesTab';

export default function CertificatesPage({ manage = false, section = 'both', embedded = false }) {
  const showIssuance = manage && section !== 'archive';
  const showArchive = section !== 'issuance';
  const { t } = useTranslation('certificates');
  const [certificates, setCertificates] = useState([]);
  const [candidates, setCandidates] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [actionError, setActionError] = useState('');
  const [busy, setBusy] = useState(false);
  const [upload, setUpload] = useState(null);
  const [preview, setPreview] = useState(null);
  const mounted = useRef(false);
  useEffect(() => { mounted.current = true; return () => { mounted.current = false; }; }, []);
  useEffect(() => () => { if (preview) URL.revokeObjectURL(preview.url); }, [preview]);

  async function message(err) {
    let data = err.response?.data;
    if (data instanceof Blob) {
      try { data = JSON.parse(await data.text()); } catch { data = null; }
    }
    return data?.errors ? Object.values(data.errors).flat().join(' ') : data?.message || t('failed');
  }

  async function load() {
    setLoading(true); setError('');
    try {
      const [items, eligible] = await Promise.all([showArchive ? getCertificates() : Promise.resolve([]), showIssuance ? getCertificateCandidates() : Promise.resolve([])]);
      if (mounted.current) { setCertificates(items); setCandidates(eligible); }
    } catch (err) { if (mounted.current) setError(await message(err)); }
    finally { if (mounted.current) setLoading(false); }
  }
  useEffect(() => { load(); }, [showArchive, showIssuance]);

  async function openFile(certificate, download) {
    setBusy(true); setActionError('');
    try {
      const blob = await getCertificateFile(certificate.id, download);
      if (!mounted.current) return;
      const url = URL.createObjectURL(blob);
      if (download) {
        const link = document.createElement('a'); link.href = url;
        link.download = `certificate-${certificate.id}.pdf`;
        document.body.appendChild(link); link.click(); link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
      } else setPreview({ url, certificate });
    } catch (err) { if (mounted.current) setActionError(await message(err)); }
    finally { if (mounted.current) setBusy(false); }
  }

  async function submit(event) {
    event.preventDefault();
    if (busy) return;
    const file = new FormData(event.currentTarget).get('file');
    setBusy(true); setActionError('');
    try { await issueCertificate(upload.id, file); setUpload(null); await load(); }
    catch (err) { setActionError(await message(err)); }
    finally { setBusy(false); }
  }

  const columns = [
    ...(manage ? [{ key: 'trainee_name', header: t('trainee') }] : []),
    { key: 'program_name', header: t('program') },
    { key: 'issue_date', header: t('issuedOn') },
    { key: 'actions', header: t('actions'), render: certificate => <div className={styles.actions}>
      <Button disabled={busy} onClick={() => openFile(certificate, false)}>{t('preview')}</Button>
      <Button disabled={busy} variant="soft" onClick={() => openFile(certificate, true)}>{t('download')}</Button>
    </div> },
  ];

  return <section>
    {!embedded && <h1>{t('title')}</h1>}
    {(!embedded || showIssuance) && <p className={styles.description}>{t(manage ? 'hrDescription' : 'description')}</p>}
    {loading ? <Loading /> : error ? <div role="alert">{error} <Button onClick={load}>{t('retry')}</Button></div> : <>
      {showIssuance && <>
        <h2>{t('completedTrainees')}</h2>
        <Table columns={[
          { key: 'name', header: t('trainee') }, { key: 'program_name', header: t('program') },
          { key: 'actions', header: t('actions'), render: candidate => candidate.issued ? t('issued') :
            <Button disabled={busy} onClick={() => { setActionError(''); setUpload(candidate); }}>{t('upload')}</Button> },
        ]} rows={candidates} emptyText={t('noCompleted')} />
      </>}
      {showArchive && <>
        {manage && <h2>{t('issuedCertificates')}</h2>}
        <Table columns={columns} rows={certificates} emptyText={t('empty')} />
      </>}
    </>}
    {actionError && !upload && <p role="alert">{actionError}</p>}
    {upload && <Modal title={`${t('upload')} — ${upload.name}`} onClose={() => { if (!busy) setUpload(null); }}>
      <form onSubmit={submit} className={styles.form}>
        <p>{t('pdfHint')}</p>
        <Field label={t('pdfFile')}><Input type="file" name="file" accept="application/pdf,.pdf" required disabled={busy} /></Field>
        {actionError && <p role="alert">{actionError}</p>}
        <Button type="submit" loading={busy}>{t('issue')}</Button>
      </form>
    </Modal>}
    {preview && <Modal title={t('preview')} onClose={() => setPreview(null)}>
      <p>{preview.certificate.program_name}</p>
      <iframe className={styles.preview} src={preview.url} title={t('preview')} />
      <p>{t('previewHint')}</p>
      <Button disabled={busy} onClick={() => openFile(preview.certificate, true)}>{t('download')}</Button>
    </Modal>}
  </section>;
}

const tabs = ['templates', 'issuance', 'archive'];

export function HrCertificatesPage() {
  const { t, i18n } = useTranslation('certificates');
  const [tab, setTab] = useState('templates');
  const guard = useRef(null);
  const controls = useRef({});
  function activate(next) {
    if (next === tab || (guard.current && !guard.current())) return false;
    setTab(next);
    return true;
  }
  function key(event) {
    const movement = i18n.dir() === 'rtl' ? -1 : 1;
    const current = tabs.indexOf(tab);
    const offset = { ArrowLeft: -movement, ArrowRight: movement }[event.key];
    const next = event.key === 'Home' ? tabs[0] : event.key === 'End' ? tabs.at(-1)
      : offset ? tabs[(current + offset + tabs.length) % tabs.length] : null;
    if (next) {
      event.preventDefault();
      if (activate(next)) controls.current[next]?.focus();
    }
  }
  return <section>
    <h1>{t('title')}</h1>
    <div role="tablist" aria-label={t('title')} onKeyDown={key} className={styles.tabs}>
      {tabs.map(id => <button key={id} ref={element => { controls.current[id] = element; }}
        type="button" role="tab" id={`certificates-tab-${id}`} aria-controls="certificates-panel"
        aria-selected={tab === id} tabIndex={tab === id ? 0 : -1}
        onClick={() => activate(id)}>{t(`tabs.${id}`)}</button>)}
    </div>
    <section id="certificates-panel" role="tabpanel" aria-labelledby={`certificates-tab-${tab}`}>
      {tab === 'templates' ? <CertificateTemplatesTab guard={guard} />
        : <CertificatesPage key={tab} manage section={tab} embedded />}
    </section>
  </section>;
}
