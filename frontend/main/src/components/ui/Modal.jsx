import { useEffect, useRef } from 'react';
import { X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import styles from './Modal.module.css';

export default function Modal({ title, onClose, children }) {
  const { t } = useTranslation();
  const dialog = useRef(null);
  const close = useRef(onClose);
  close.current = onClose;

  useEffect(() => {
    const previous = document.activeElement;
    const element = dialog.current;
    const focusables = () => [...element.querySelectorAll(
      'button, input, select, textarea, a[href], [tabindex]',
    )].filter((item) => item.tabIndex >= 0 && !item.matches(':disabled') && !item.closest('[hidden]'));
    (focusables()[0] ?? element).focus();
    function onKey(event) {
      if (Array.from(document.querySelectorAll('[aria-modal="true"]')).at(-1) !== element) return;
      if (event.key === 'Escape' && !event.defaultPrevented) {
        event.preventDefault();
        close.current();
      } else if (event.key === 'Tab') {
        const items = focusables();
        const first = items[0];
        const last = items.at(-1);
        if (!first || !element.contains(document.activeElement)
          || (!event.shiftKey && document.activeElement === last)
          || (event.shiftKey && document.activeElement === first)) {
          event.preventDefault();
          ((event.shiftKey ? last : first) ?? element).focus();
        }
      }
    }
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('keydown', onKey);
      if (previous?.isConnected) previous.focus();
    };
  }, []);

  return (
    <div className={styles.overlay} onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div className={styles.dialog} ref={dialog} tabIndex={-1} role="dialog" aria-modal="true" aria-label={title}>
        <header className={styles.header}>
          <h3>{title}</h3>
          
          <button type="button" className={styles.close} onClick={onClose} aria-label={t('action.close')}>
            <X size={18} />
          </button>
        </header>
        <div className={styles.body}>{children}</div>
      </div>
    </div>
  );
}
