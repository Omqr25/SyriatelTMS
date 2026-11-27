import { useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import Button from '../../components/ui/Button';
import Table from '../../components/ui/Table';
import Loading from '../../components/ui/Loading';
import { getCertificates } from '../../Api/certificateApi';
import styles from './CertificatesPage.module.css';
import CertificateArchiveTab from './CertificateArchiveTab';
import { CertificateFileProvider, CertificateFileButtons } from './CertificateFileActions';
import CertificateIssuanceTab from './CertificateIssuanceTab';
import CertificateNameForm from './CertificateNameForm';
import CertificateTemplatesTab from './templates/CertificateTemplatesTab';

export default function CertificatesPage() {
  const { t } = useTranslation('certificates');
  const [certificates, setCertificates] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const mounted = useRef(false);
  useEffect(() => { mounted.current = true; return () => { mounted.current = false; }; }, []);

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
      const items = await getCertificates();
      if (mounted.current) setCertificates(items);
    } catch (err) { if (mounted.current) setError(await message(err)); }
    finally { if (mounted.current) setLoading(false); }
  }
  useEffect(() => { load(); }, []);

  const columns = [
    { key: 'program_name', header: t('program') },
    { key: 'issue_date', header: t('issuedOn') },
    { key: 'actions', header: t('actions'), render: certificate => <CertificateFileButtons certificate={certificate} /> },
  ];

  return <CertificateFileProvider><section>
    <h1>{t('title')}</h1>
    <p className={styles.description}>{t('description')}</p>
    <CertificateNameForm />
    {loading ? <Loading /> : error ? <div role="alert">{error} <Button onClick={load}>{t('retry')}</Button></div> :
      <Table columns={columns} rows={certificates} emptyText={t('empty')} />}
  </section></CertificateFileProvider>;
}

const tabs = ['templates', 'issuance', 'archive'];

export function HrCertificatesPage() {
  const { t, i18n } = useTranslation('certificates');
  const [params, setParams] = useSearchParams();
  const tab = tabs.includes(params.get('tab')) ? params.get('tab') : 'templates';
  const guard = useRef(null);
  const controls = useRef({});
  function activate(next) {
    if (next === tab || (guard.current && !guard.current())) return false;
    setParams(current => { current.set('tab', next); return current; }, { replace: true });
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
        : tab === 'issuance' ? <CertificateIssuanceTab /> : <CertificateArchiveTab />}
    </section>
  </section>;
}
