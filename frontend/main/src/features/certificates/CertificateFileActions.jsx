import { createContext, useContext, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '../../components/ui/Button';
import Modal from '../../components/ui/Modal';
import { getCertificateFile } from '../../Api/certificateApi';
import styles from './CertificatesPage.module.css';

const CertificateFiles = createContext(null);

export function CertificateFileProvider({ children }) {
  const { t } = useTranslation('certificates');
  const [actionError, setActionError] = useState('');
  const [busy, setBusy] = useState(false);
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

  return <CertificateFiles.Provider value={{ busy, openFile }}>
    {children}
    {actionError && <p role="alert">{actionError}</p>}
    {preview && <Modal title={t('preview')} onClose={() => setPreview(null)}>
      <p>{preview.certificate.program_name}</p>
      <iframe className={styles.preview} src={preview.url} title={t('preview')} />
      <p>{t('previewHint')}</p>
      <Button disabled={busy} onClick={() => openFile(preview.certificate, true)}>{t('download')}</Button>
    </Modal>}
  </CertificateFiles.Provider>;
}

export function CertificateFileButtons({ certificate }) {
  const { t } = useTranslation('certificates');
  const { busy, openFile } = useContext(CertificateFiles);
  return <div className={styles.actions}>
    <Button disabled={busy} onClick={() => openFile(certificate, false)}>{t('preview')}</Button>
    <Button disabled={busy} variant="soft" onClick={() => openFile(certificate, true)}>{t('download')}</Button>
  </div>;
}
