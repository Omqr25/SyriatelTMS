import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Archive, Copy, FileText, Plus, Search } from 'lucide-react';
import Button from '../../../components/ui/Button';
import Loading from '../../../components/ui/Loading';
import Modal from '../../../components/ui/Modal';
import { Field, Input } from '../../../components/ui/Field';
import { archiveCertificateTemplate, duplicateCertificateTemplate, getCertificateTemplate, listCertificateTemplates } from '../../../Api/certificateApi';
import TemplateEditor from './TemplateEditor';
import { templateError } from './templateError';
import styles from './Templates.module.css';

export default function CertificateTemplatesTab({ guard }) {
  const { t } = useTranslation('certificates', { keyPrefix: 'templates' });
  const [search, setSearch] = useState('');
  const [archived, setArchived] = useState(false);
  const [page, setPage] = useState(1);
  const [result, setResult] = useState(null);
  const [revision, setRevision] = useState(0);
  const [editor, setEditor] = useState(null);
  const [dialog, setDialog] = useState(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const reload = () => setRevision(value => value + 1);

  useEffect(() => {
    if (editor) return;
    const controller = new AbortController();
    const timer = setTimeout(() => {
      setLoading(true);
      listCertificateTemplates({ search: search.trim(), archived: archived ? 1 : 0, page }, controller.signal)
        .then(data => { if (!controller.signal.aborted) setResult(data); })
        .catch(async err => { if (!controller.signal.aborted) setError(await templateError(err, t('requestFailed'))); })
        .finally(() => { if (!controller.signal.aborted) setLoading(false); });
    }, search ? 300 : 0);
    return () => { clearTimeout(timer); controller.abort(); };
  }, [search, archived, page, revision, editor, t]);

  async function perform(callback) {
    setBusy(true); setError('');
    try { await callback(); }
    catch (err) { setError(await templateError(err, t('requestFailed'))); }
    finally { setBusy(false); }
  }

  function open(item, mode) {
    perform(async () => setEditor({ mode, template: await getCertificateTemplate(item.id) }));
  }

  if (editor) return <TemplateEditor key={editor.template?.id ?? 'new'} {...editor} guard={guard}
    onClose={() => { setEditor(null); setPage(1); }} onSaved={reload} />;

  return <div className={styles.stack}>
    <div className={styles.toolbar}>
      <div className={styles.search}>
        <Search aria-hidden size={17} />
        <Input aria-label={t('search')} placeholder={t('search')} maxLength={255} value={search} onChange={event => {
          setSearch(event.target.value); setPage(1); setError('');
        }} />
      </div>
      <label className={styles.check}><input type="checkbox" checked={archived} onChange={event => {
        setArchived(event.target.checked); setPage(1); setError('');
      }} />{t('showArchived')}</label>
      <Button icon={Plus} onClick={() => setEditor({ mode: 'create', template: null })}>{t('create')}</Button>
    </div>
    {error && <div className={styles.actions} role="alert"><span>{error}</span>
      <Button size="sm" variant="soft" onClick={() => { setError(''); reload(); }}>{t('retry')}</Button></div>}
    {loading && !result ? <Loading /> : <div className={styles.cards} aria-busy={loading}>
      {(result?.data ?? []).map(item => <article key={item.id} className={styles.card}>
        <div className={styles.cardHeading}>
          <div className={styles.cardIcon}><FileText size={21} aria-hidden /></div>
          <div><h3>{item.name}</h3><span className={styles.muted}>{t('version', { number: item.version })}</span></div>
        </div>
        <div className={styles.actions}>
          <Button size="sm" variant="soft" disabled={busy} onClick={() => open(item, 'preview')}>{t('preview')}</Button>
          {!item.archived && <Button size="sm" variant="soft" disabled={busy} onClick={() => open(item, 'edit')}>{t('edit')}</Button>}
          <Button size="sm" variant="secondary" icon={Copy} disabled={busy}
            onClick={() => setDialog({ type: 'duplicate', item, name: `${item.name} — ${t('copy')}` })}>{t('duplicate')}</Button>
          {!item.archived && <Button size="sm" variant="secondary" icon={Archive} disabled={busy}
            onClick={() => setDialog({ type: 'archive', item })}>{t('archive')}</Button>}
        </div>
      </article>)}
      {!result?.data?.length && <p className={styles.muted}>{t('empty')}</p>}
    </div>}
    {result?.last_page > 1 && <div className={styles.actions}>
      <Button size="sm" variant="soft" disabled={page <= 1 || loading} onClick={() => setPage(page - 1)}>{t('previous')}</Button>
      <span>{page} / {result.last_page}</span>
      <Button size="sm" variant="soft" disabled={page >= result.last_page || loading} onClick={() => setPage(page + 1)}>{t('next')}</Button>
    </div>}
    {dialog && <Modal title={t(dialog.type)} onClose={() => { if (!busy) setDialog(null); }}>
      <form className={styles.stack} onSubmit={event => {
        event.preventDefault();
        perform(async () => {
          if (dialog.type === 'duplicate') await duplicateCertificateTemplate(dialog.item.id, dialog.name);
          else await archiveCertificateTemplate(dialog.item.id);
          setDialog(null); setPage(1); reload();
        });
      }}>
        {dialog.type === 'duplicate'
          ? <Field label={t('name')}><Input maxLength={255} required value={dialog.name}
            onChange={event => setDialog({ ...dialog, name: event.target.value })} /></Field>
          : <p>{t('archiveConfirm', { name: dialog.item.name })}</p>}
        {error && <p role="alert" className={styles.warning}>{error}</p>}
        <Button type="submit" loading={busy}>{t(dialog.type)}</Button>
      </form>
    </Modal>}
  </div>;
}
