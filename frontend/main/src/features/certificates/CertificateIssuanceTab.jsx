import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router-dom';
import Button from '../../components/ui/Button';
import { Field, Input, Select, Textarea } from '../../components/ui/Field';
import Modal from '../../components/ui/Modal';
import Pagination from '../../components/ui/Pagination';
import Table from '../../components/ui/Table';
import { getIssuanceOptions, getIssuanceRoster, postIssuance, updateIssuanceTrainee } from '../../Api/certificateApi';
import useCertificateSelection from './useCertificateSelection';
import styles from './CertificatesPage.module.css';

const statuses = ['ready', 'excluded', 'fired', 'program_not_ended', 'queued', 'generating', 'issued', 'failed'];
const errorMessage = (error, fallback) => {
  const data = error.response?.data;
  return data?.errors ? Object.values(data.errors).flat().join(' ') : data?.message ?? fallback;
};

export default function CertificateIssuanceTab() {
  const { t } = useTranslation('certificates');
  const [params, setParams] = useSearchParams();
  const program = params.get('program') ?? '';
  const [options, setOptions] = useState(null);
  const [version, setVersion] = useState('');
  const [search, setSearch] = useState('');
  const [query, setQuery] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const [roster, setRoster] = useState(null);
  const [refresh, setRefresh] = useState(0);
  const [loadError, setLoadError] = useState('');
  const [dialog, setDialog] = useState(null);
  const [entry, setEntry] = useState('');
  const [busy, setBusy] = useState(false);
  const [actionError, setActionError] = useState('');
  const [notice, setNotice] = useState('');

  useEffect(() => {
    const controller = new AbortController();
    getIssuanceOptions(controller.signal).then(result => {
      if (!controller.signal.aborted) setOptions(result);
    }).catch(error => {
      if (!controller.signal.aborted) setLoadError(errorMessage(error, t('failed')));
    });
    return () => controller.abort();
  }, [t]);

  useEffect(() => {
    if (search === query) return;
    const timer = setTimeout(() => { setPage(1); setQuery(search); }, 300);
    return () => clearTimeout(timer);
  }, [search, query]);

  useEffect(() => {
    if (!program || search !== query) return;
    const controller = new AbortController();
    let timer;
    async function load(keepPolling = false) {
      try {
        const result = await getIssuanceRoster(program, { search: query, status, page }, controller.signal);
        if (controller.signal.aborted) return;
        setRoster(result); setLoadError('');
        if (page > result.roster.last_page) {
          setPage(result.roster.last_page);
        } else if (result.counts.queued || result.counts.generating) {
          timer = setTimeout(() => load(true), 3000);
        }
      } catch (error) {
        if (controller.signal.aborted) return;
        setLoadError(errorMessage(error, t('failed')));
        if (keepPolling && ![401, 403, 404].includes(error.response?.status)) timer = setTimeout(() => load(true), 5000);
      }
    }
    load();
    return () => { controller.abort(); clearTimeout(timer); };
  }, [program, search, query, status, page, refresh, t]);

  const readyIds = roster?.ready_ids ?? [];
  const selection = useCertificateSelection(readyIds.length, readyIds);
  const { selected, allChecked, isSelected, toggle } = selection;
  const counts = roster?.counts ?? {};
  const rows = roster?.roster.data ?? [];
  const active = (counts.queued ?? 0) + (counts.generating ?? 0);
  function changeProgram(event) {
    const id = event.target.value;
    setParams(current => {
      if (id) current.set('program', id); else current.delete('program');
      return current;
    }, { replace: true });
    selection.clear(); setRoster(null); setLoadError(''); setNotice('');
    setStatus(''); setSearch(''); setQuery(''); setPage(1);
  }
  function open(kind, row) {
    setActionError(''); setNotice(''); setEntry(''); setDialog({ kind, row });
  }
  function decide() {
    if (dialog.kind === 'all') return { mode: 'all' };
    if (dialog.kind === 'selected') return selection.all
      ? { mode: 'all', except_ids: selection.ids }
      : { mode: 'explicit', trainee_ids: selection.ids };
    return { mode: 'explicit', trainee_ids: [dialog.row.id],
      ...(dialog.kind === 'override' ? { override_reason: entry } : {}) };
  }
  async function submit(event) {
    event.preventDefault();
    setBusy(true); setActionError('');
    try {
      if (['all', 'selected', 'individual', 'override'].includes(dialog.kind) && !dialog.plan) {
        const input = { template_version_id: Number(version), ...decide() };
        const plan = await postIssuance(program, 'plan', input);
        setDialog({ ...dialog, plan, input, requestKey: crypto.randomUUID() });
        return;
      }
      if (dialog.plan) {
        const result = await postIssuance(program, 'confirm', {
          template_version_id: dialog.input.template_version_id, trainee_ids: dialog.plan.trainee_ids,
          ...(dialog.input.override_reason ? { override_reason: dialog.input.override_reason } : {}),
          request_key: dialog.requestKey, confirmed: true,
        });
        setNotice(t('issuance.queuedNotice', { count: result.total }));
        selection.clear();
      } else if (dialog.kind === 'retry' || dialog.kind === 'retryAll') {
        const result = await postIssuance(program, 'retry', dialog.row ? { certificate_id: dialog.row.certificate_id } : {});
        setNotice(t('issuance.queuedNotice', { count: result.total }));
      } else if (dialog.kind === 'openWindow') {
        await postIssuance(program, 'name-window');
        setNotice(t('issuance.saved'));
      } else {
        const name = dialog.kind === 'setName';
        await updateIssuanceTrainee(program, dialog.row.id, name ? 'name' : 'exclusion',
          name ? { name: entry } : { reason: entry, excluded: dialog.kind === 'exclude' });
        setNotice(t('issuance.saved'));
      }
      setDialog(null); setRefresh(value => value + 1);
    } catch (error) {
      setActionError(errorMessage(error, t('failed')));
    } finally {
      setBusy(false);
    }
  }

  const rowActions = row => <>
    {row.status === 'ready' && <>
      <Button onClick={() => open('individual', row)} disabled={!version}>{t('issuance.individual')}</Button>
      <Button variant="soft" onClick={() => open('exclude', row)}>{t('issuance.exclude')}</Button>
    </>}
    {row.status === 'excluded' && <Button variant="soft" onClick={() => open('restore', row)}>{t('issuance.restore')}</Button>}
    {['excluded', 'fired'].includes(row.status) && <Button variant="soft" onClick={() => open('override', row)} disabled={!version}>{t('issuance.override')}</Button>}
    {row.status === 'failed' && <Button variant="soft" onClick={() => open('retry', row)}>{t('retry')}</Button>}
    {!row.certificate_name && !row.certificate_id && <Button variant="soft" onClick={() => open('setName', row)}>{t('issuance.setName')}</Button>}
  </>;

  const needsReason = dialog && ['exclude', 'restore', 'override'].includes(dialog.kind);
  const needsName = dialog?.kind === 'setName';
  const dialogTitle = dialog?.kind === 'retry' ? t('retry') : t(`issuance.${dialog?.kind}`);
  return <section className={styles.issuance}>
    <div className={styles.filters}>
      <Field label={t('program')}><Select value={program} onChange={changeProgram} disabled={!options}>
        <option value="">{t('issuance.chooseProgram')}</option>
        {options?.programs.map(item => <option key={item.id} value={item.id}>{item.name} ({item.end_date.slice(0, 10)})</option>)}
      </Select></Field>
      <Field label={t('issuance.templateVersion')}><Select value={version} onChange={event => setVersion(event.target.value)} disabled={!options}>
        <option value="">{t('issuance.chooseTemplate')}</option>
        {options?.versions.map(item => <option key={item.id} value={item.id}>{item.name} · {t('issuance.version', { version: item.version })}</option>)}
      </Select></Field>
    </div>
    {program && <>
      {roster?.name_window.state === 'scheduled' && <div><Button variant="soft" onClick={() => open('openWindow')}>{t('issuance.openWindow')}</Button></div>}
      {roster && <div className={styles.counters}>
        {statuses.map(key => <span key={key}>{t(`issuance.statuses.${key}`)} <strong>{counts[key] ?? 0}</strong></span>)}
      </div>}
      <div className={styles.filters}>
        <Field label={t('issuance.search')}><Input type="search" value={search} maxLength={200} onChange={event => setSearch(event.target.value)} /></Field>
        <Field label={t('issuance.status')}><Select value={status} onChange={event => { setStatus(event.target.value); setPage(1); }}>
          <option value="">{t('issuance.allStatuses')}</option>
          {statuses.map(key => <option value={key} key={key}>{t(`issuance.statuses.${key}`)}</option>)}
        </Select></Field>
        <Button variant="soft" onClick={() => { setSearch(''); setQuery(''); setStatus(''); setPage(1); }}>{t('issuance.reset')}</Button>
      </div>
      <div className={styles.actions}>
        <Button variant="soft" disabled={!selected} onClick={selection.clear}>{t('archive.deselectAll')}</Button>
        <Button disabled={!version || !selected} onClick={() => open('selected')}>{t('issuance.selected')} ({selected})</Button>
        <Button disabled={!version || !counts.ready} onClick={() => open('all')}>{t('issuance.all')}</Button>
        <Button variant="soft" disabled={!counts.failed} onClick={() => open('retryAll')}>{t('issuance.retryAll')}</Button>
      </div>
      {notice && <p role="status">{notice}</p>}
      {roster && <Table rows={rows} emptyText={t('issuance.noRows')} columns={[
        { key: 'select', header: <input type="checkbox" checked={allChecked} disabled={!readyIds.length}
          aria-label={t('issuance.selectAll')} ref={input => { if (input) input.indeterminate = selected > 0 && !allChecked; }}
          onChange={selection.toggleAll} />,
        render: row => <input type="checkbox" checked={row.status === 'ready' && isSelected(row.id)}
          aria-label={t('issuance.selectTrainee', { name: row.name })}
          disabled={row.status !== 'ready'} onChange={() => toggle(row.id)} /> },
        { key: 'name', header: t('trainee'), render: row => <><bdi>{row.name}</bdi><small className={styles.subtext}><bdi>{row.email}</bdi></small></> },
        { key: 'certificate_name', header: t('issuance.nameTitle'), render: row => row.certificate_name ? <bdi lang="en">{row.certificate_name}</bdi> : '—' },
        { key: 'status', header: t('issuance.status'), render: row => <>
          {t(`issuance.statuses.${row.status}`)}
          {row.status === 'failed' && <small className={styles.subtext}>{t(`issuance.failures.${row.failure_code}`, { defaultValue: t('failed') })}</small>}
        </> },
        { key: 'actions', header: t('actions'), render: row => <div className={styles.actions}>{rowActions(row)}</div> },
      ]} />}
      <Pagination currentPage={page} totalPages={roster?.roster.last_page} onPageChange={setPage} />
      {!!roster?.batches.length && <section className={styles.namePanel}>
        <h2>{t('issuance.progress')}</h2>
        {active > 0 && <p role="status">{t('issuance.activeNotice', { count: active })}</p>}
        <ul className={styles.batches}>{roster.batches.map(batch => <li key={batch.id}>
          <label>{t('issuance.batch', batch)} <progress value={batch.issued + batch.failed} max={batch.total} /></label>
        </li>)}</ul>
      </section>}
    </>}
    {loadError && <p role="alert">{loadError} {program && <Button variant="soft" onClick={() => setRefresh(value => value + 1)}>{t('retry')}</Button>}</p>}
    {dialog && <Modal title={dialogTitle} onClose={() => { if (!busy) setDialog(null); }}>
      <form className={styles.form} onSubmit={submit}>
        {dialog.row && <p><bdi>{dialog.row.name}</bdi></p>}
        {dialog.plan ? <>
          <p>{t('issuance.confirmCount', { count: dialog.plan.trainee_ids.length })}</p>
          {dialog.plan.blocked.length > 0 && <>
            <p>{t('issuance.blockedCount', { count: dialog.plan.blocked.length })}</p>
            <ul className={styles.blocked}>{dialog.plan.blocked.map(item => <li key={item.id}><bdi>{item.name ?? `#${item.id}`}</bdi> — {item.reason}</li>)}</ul>
          </>}
        </> : <>
          {dialog.kind === 'override' && <p>{t('issuance.overrideHelp')}</p>}
          {needsReason && <Field label={t('issuance.reason')}><Textarea required maxLength={2000} value={entry} onChange={event => setEntry(event.target.value)} disabled={busy} /></Field>}
          {needsName && <Field label={t('issuance.nameTitle')}><Input required dir="ltr" lang="en" maxLength={200}
            value={entry} onChange={event => setEntry(event.target.value)} disabled={busy} /></Field>}
          {!needsReason && !needsName && <p>{t('issuance.confirmAction', { action: dialogTitle })}</p>}
        </>}
        {actionError && <p role="alert">{actionError}</p>}
        <div className={styles.actions}>
          <Button type="button" variant="soft" disabled={busy} onClick={() => setDialog(null)}>{t('issuance.cancel')}</Button>
          <Button type="submit" loading={busy} disabled={dialog.plan?.trainee_ids.length === 0}>
            {dialog.plan || !['selected', 'all', 'individual', 'override'].includes(dialog.kind) ? t('issuance.confirm') : t('issuance.review')}
          </Button>
        </div>
      </form>
    </Modal>}
  </section>;
}
