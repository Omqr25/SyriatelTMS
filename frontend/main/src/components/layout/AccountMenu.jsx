import { useEffect, useId, useRef, useState } from 'react';
import { ChevronDown, UserRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { NavLink, useLocation } from 'react-router-dom';
import styles from './AccountMenu.module.css';

export default function AccountMenu({ basePath, items }) {
  const { t } = useTranslation('account');
  const { pathname } = useLocation();
  const [open, setOpen] = useState(false);
  const panelId = useId();
  const root = useRef(null);
  const trigger = useRef(null);

  useEffect(() => setOpen(false), [pathname]);

  useEffect(() => {
    if (!open) return;

    function dismissOutside(event) {
      if (!root.current?.contains(event.target)) setOpen(false);
    }

    document.addEventListener('pointerdown', dismissOutside);
    return () => document.removeEventListener('pointerdown', dismissOutside);
  }, [open]);

  function closeAndFocusTrigger() {
    setOpen(false);
    trigger.current?.focus();
  }

  return (
    <div
      className={styles.menu}
      ref={root}
      onBlur={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget)) setOpen(false);
      }}
      onKeyDown={(event) => {
        if (!open || event.key !== 'Escape') return;
        event.preventDefault();
        closeAndFocusTrigger();
      }}
    >
      <button
        type="button"
        ref={trigger}
        className={`headerControl ${styles.trigger}`}
        aria-label={t('profile.menuLabel')}
        aria-expanded={open}
        aria-controls={panelId}
        onClick={() => setOpen((value) => !value)}
      >
        <span className={styles.avatar}><UserRound size={17} aria-hidden="true" /></span>
        <span className={styles.triggerLabel}>{t('settings.account')}</span>
        <ChevronDown size={14} className={styles.chevron} aria-hidden="true" />
      </button>

      <nav className={styles.panel} id={panelId} aria-label={t('profile.menuLabel')} hidden={!open}>
        {items.map(({ path, icon: Icon, labelKey, descriptionKey }) => (
          <NavLink key={path} to={`${basePath}/${path}`} className={styles.link} onClick={closeAndFocusTrigger}>
            <span className={styles.icon}><Icon size={18} aria-hidden="true" /></span>
            <span className={styles.linkCopy}>
              <strong>{t(labelKey)}</strong>
              <span>{t(descriptionKey)}</span>
            </span>
          </NavLink>
        ))}
      </nav>
    </div>
  );
}
