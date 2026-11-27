import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router-dom';
import Button from '../../components/ui/Button';
import { Field, Input } from '../../components/ui/Field';
import Loading from '../../components/ui/Loading';
import Pagination from '../../components/ui/Pagination';
import Table from '../../components/ui/Table';
import {
  downloadCertificatePackage, getArchiveCertificates, getCertificateArchive, getCertificatePackages,
  requestCertificatePackage, retryCertificatePackage,
} from '../../Api/certificateApi';
import { CertificateFileButtons, CertificateFileProvider } from './CertificateFileActions';
import useCertificateSelection from './useCertificateSelection';
import styles from './CertificatesPage.module.css';

const packagingActive = item => ['queued', 'processing'].includes(item.status);
const errorMessage = (error, fallback) => {
  const details = error.response?.data;
  return details?.errors ? Object.values(details.errors).flat().join(' ') : details?.message || fallback;
};

export default function CertificateArchiveTab() {
  const { t } = useTranslation('certificates');
  const [params, setParams] = useSearchParams();
  const program = params.get('archiveProgram');
  function open(id) {
    setParams(current => {
      if (id) current.set('archiveProgram', id); else current.delete('archiveProgram');
      return current;
    }, { replace: true });
  }

  if (program) {
    return <section className={styles.issuance}>
      <div><Button variant="soft" onClick={() => open(null)}>{t('archive.back')}</Button></div>
      <ArchiveProgram program={program} key={program} />
    </section>;
  }

  return <ArchivePrograms open={open} />;
}

function ArchivePrograms({ open }) {
  const { t } = useTranslation('certificates');
  const [programs, setPrograms] = useState(null);
  const [error, setError] = useState('');
  const [reload, setReload] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    getCertificateArchive(controller.signal).then(result => {
      if (!controller.signal.aborted) { setPrograms(result); setError(''); }
    }).catch(error => {
      if (!controller.signal.aborted) setError(errorMessage(error, t('failed')));
    });
    return () => controller.abort();
  }, [t, reload]);

  if (error) return <p role="alert">{error} <Button variant="soft" onClick={() => setReload(value => value + 1)}>{t('retry')}</Button></p>;
  if (!programs) return <Loading />;
  if (programs.length === 0) return <p>{t('empty')}</p>;
  return <div className={styles.archiveCards}>
    {programs.map(program => <article className={styles.namePanel} key={program.id}>
      <h2>{program.name}</h2>
      <p><bdi>{program.start_date}</bdi> – <bdi>{program.end_date}</bdi></p>
      <p>{t('archive.issuedCount', { count: program.issued_count })}</p>
      <div><Button onClick={() => open(program.id)}>{t('archive.open')}</Button></div>
    </article>)}
  </div>;
}

function ArchiveProgram({ program }) {
  const { t } = useTranslation('certificates');
  const [search, setSearch] = useState('');
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(1);
  const [archive, setArchive] = useState(null);
  const [error, setError] = useState('');
  const [reload, setReload] = useState(0);
  const selection = useCertificateSelection(archive?.total ?? 0);

  useEffect(() => {
    if (search === query) return;
    const timer = setTimeout(() => { setPage(1); setQuery(search); }, 300);
    return () => clearTimeout(timer);
  }, [search, query]);

  useEffect(() => {
    if (search !== query) return;
    const controller = new AbortController();
    getArchiveCertificates(program, { search: query, page }, controller.signal).then(result => {
      if (controller.signal.aborted) return;
      setArchive(result); setError('');
      if (page > result.results.last_page) setPage(result.results.last_page);
    }).catch(error => {
      if (!controller.signal.aborted) setError(errorMessage(error, t('failed')));
    });
    return () => controller.abort();
  }, [program, search, query, page, t, reload]);

  return <CertificateFileProvider>
    <div className={styles.issuance}>
      {archive && <h2>{archive.program.name}</h2>}
      <div className={styles.filters}>
        <Field label={t('archive.search')}><Input type="search" maxLength={200} value={search}
          onChange={event => setSearch(event.target.value)} /></Field>
        <Button variant="soft" onClick={() => { setSearch(''); setQuery(''); setPage(1); }}>{t('issuance.reset')}</Button>
      </div>
      {error && <p role="alert">{error} <Button variant="soft" onClick={() => setReload(value => value + 1)}>{t('retry')}</Button></p>}
      {!archive && !error && <Loading />}
      {archive && <>
        <div className={styles.actions}>
          <span className={styles.selectionCount} aria-live="polite">{t('archive.selectedCount', { count: selection.selected })}</span>
          <Button variant="soft" disabled={selection.selected === 0} onClick={selection.clear}>{t('archive.deselectAll')}</Button>
        </div>
        <Table rows={archive.results.data} emptyText={t('archive.noResults')} columns={[
          { key: 'select', header: <input type="checkbox" aria-label={t('archive.selectAll')} checked={selection.allChecked}
            disabled={!archive.total} onChange={selection.toggleAll}
            ref={input => { if (input) input.indeterminate = selection.selected > 0 && !selection.allChecked; }} />,
          render: row => <input type="checkbox" aria-label={t('issuance.selectTrainee', { name: row.trainee_name })}
            checked={selection.isSelected(row.id)} onChange={() => selection.toggle(row.id)} /> },
          { key: 'trainee_name', header: t('trainee'), render: row => <><bdi>{row.trainee_name}</bdi>
            <small className={styles.subtext}><bdi>{row.email}</bdi></small></> },
          { key: 'trainee_id', header: t('archive.traineeId') },
          { key: 'number', header: t('archive.number'), render: row => row.number ? <bdi>{row.number}</bdi> : '—' },
          { key: 'issue_date', header: t('issuedOn') },
          { key: 'actions', header: t('actions'), render: certificate => <CertificateFileButtons certificate={certificate} /> },
        ]} />
        <Pagination currentPage={page} totalPages={archive.results.last_page} onPageChange={setPage} />
        <ProgramPackages program={program} total={archive.total} selection={selection} />
      </>}
    </div>
  </CertificateFileProvider>;
}

function ProgramPackages({ program, total, selection }) {
  const { t, i18n } = useTranslation('certificates');
  const [packages, setPackages] = useState([]);
  const [actionError, setActionError] = useState('');
  const [loadError, setLoadError] = useState('');
  const [busy, setBusy] = useState(false);
  const [reload, setReload] = useState(0);

  useEffect(() => {
    const controller = new AbortController();
    let timer;
    async function load(active = false) {
      try {
        const result = await getCertificatePackages(program, controller.signal);
        if (controller.signal.aborted) return;
        setPackages(result); setLoadError('');
        if (result.some(packagingActive)) timer = setTimeout(() => load(true), 3000);
      } catch (error) {
        if (controller.signal.aborted) return;
        setLoadError(errorMessage(error, t('failed')));
        if (active && ![401, 403, 404].includes(error.response?.status)) timer = setTimeout(() => load(true), 5000);
      }
    }
    load();
    return () => { controller.abort(); clearTimeout(timer); };
  }, [program, reload, t]);

  async function perform(action) {
    setBusy(true); setActionError('');
    try {
      await action();
    } catch (error) {
      setActionError(errorMessage(error, t('failed')));
    } finally {
      setBusy(false);
    }
  }
  function save(item) {
    setPackages(current => [item, ...current.filter(previous => previous.id !== item.id)]);
    setReload(value => value + 1);
  }
  function build(format, all) {
    return perform(async () => save(await requestCertificatePackage(program, {
      format, request_key: crypto.randomUUID(), ...(all ? { selection: 'all' } : selection.payload),
    })));
  }
  function download(item) {
    return perform(async () => {
      const file = await downloadCertificatePackage(program, item.id);
      const url = URL.createObjectURL(file);
      const link = document.createElement('a');
      link.href = url;
      link.download = `certificates-program-${program}.${item.format}`;
      document.body.appendChild(link); link.click(); link.remove();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
  }

  const active = packages.some(packagingActive);
  return <section className={styles.namePanel}>
    <h2>{t('archive.packages')}</h2>
    <p>{t('archive.packageHelp')}</p>
    <div className={styles.actions}>
      {['pdf', 'zip'].map(format => <Button key={format} disabled={busy || active || !selection.selected}
        onClick={() => build(format, false)}>{t(`archive.downloadSelected.${format}`)}</Button>)}
      {['pdf', 'zip'].map(format => <Button key={format} variant="soft" disabled={busy || active || !total}
        onClick={() => build(format, true)}>{t(`archive.downloadAll.${format}`)}</Button>)}
    </div>
    {loadError && <p role="alert">{loadError} <Button variant="soft" disabled={busy} onClick={() => setReload(value => value + 1)}>{t('retry')}</Button></p>}
    {actionError && <p role="alert">{actionError}</p>}
    {!!packages.length && <ul className={styles.batches}>{packages.map(item => <li key={item.id}>
      <div className={styles.actions}>
        <label>{t(`archive.format.${item.format}`)} · {t(`archive.status.${item.status}`)}
          {' · '}{t('archive.packageProgress', item)}
          {packagingActive(item) && <progress value={item.processed} max={item.total} />}
        </label>
        {item.status === 'ready' && <Button disabled={busy || Date.parse(item.expires_at) <= Date.now()} onClick={() => download(item)}>
          {t(`archive.download.${item.format}`)}
        </Button>}
        {item.status === 'failed' && <Button variant="soft" disabled={busy || active || Date.parse(item.expires_at) <= Date.now()}
          onClick={() => perform(async () => save(await retryCertificatePackage(program, item.id)))}>{t('retry')}</Button>}
      </div>
      {item.status === 'failed' && <p>{t(`archive.failures.${item.failure_code}`, { defaultValue: t('failed') })}
        {item.failed_certificate_id && ` ${t('archive.failedCertificate', { id: item.failed_certificate_id })}`}</p>}
      {item.expires_at && <small className={styles.subtext}>{t('archive.expires', { date: new Intl.DateTimeFormat(i18n.language, {
        dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Damascus',
      }).format(new Date(item.expires_at)) })}</small>}
    </li>)}</ul>}
  </section>;
}
