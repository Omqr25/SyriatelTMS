import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { getCertificateName, saveCertificateName } from '../../Api/certificateApi';
import Button from '../../components/ui/Button';
import { Field, Input } from '../../components/ui/Field';
import styles from './CertificatesPage.module.css';

export default function CertificateNameForm() {
  const { t, i18n } = useTranslation('certificates');
  const [window, setWindow] = useState(null);
  const [name, setName] = useState('');
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState('');
  useEffect(() => {
    const controller = new AbortController();
    getCertificateName(controller.signal).then(data => {
      if (controller.signal.aborted) return;
      setWindow(data); setName(data.submitted_name ?? '');
    }).catch(err => {
      if (!controller.signal.aborted && err.response?.status !== 403) setError(t('failed'));
    });
    return () => controller.abort();
  }, [t]);

  async function submit(event) {
    event.preventDefault();
    setSaving(true); setError(''); setSaved(false);
    try {
      const data = await saveCertificateName(name);
      setWindow(data); setName(data.submitted_name ?? ''); setSaved(true);
    } catch (err) {
      setError(err.response?.data?.errors?.name?.[0] ?? t('failed'));
    } finally {
      setSaving(false);
    }
  }

  if (!window) return error ? <p role="alert">{error}</p> : null;
  const format = new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium', timeZone: 'Asia/Damascus' });
  const deadline = format.format(new Date(Date.parse(window.closes_at) - 1));
  return <section className={styles.namePanel}>
    <h2>{t('issuance.nameTitle')}</h2>
    <p>{t('issuance.nameHelp')}</p>
    <p>{t('issuance.nameDeadline', { date: deadline })}</p>
    {window.state === 'scheduled' && <p>{t('issuance.nameOpens', { date: format.format(new Date(window.opens_at)) })}</p>}
    {window.state === 'closed' && <p>{t('issuance.nameClosed')}</p>}
    {window.state === 'open' ? <form className={styles.actions} onSubmit={submit}>
      <Field label={t('issuance.nameTitle')}><Input value={name} required dir="ltr" lang="en" maxLength={200}
        onChange={event => { setName(event.target.value); setSaved(false); }} disabled={saving} /></Field>
      <Button type="submit" loading={saving}>{t('issuance.saveName')}</Button>
    </form> : window.submitted_name && <p><bdi>{window.submitted_name}</bdi></p>}
    {saved && <p role="status">{t('issuance.nameSaved')}</p>}
    {error && <p role="alert">{error}</p>}
  </section>;
}
