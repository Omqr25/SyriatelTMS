import { Globe } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import styles from './LanguageSwitcher.module.css';

export default function LanguageSwitcher() {
  const { t, i18n } = useTranslation();
  const toggle = () => i18n.changeLanguage(i18n.language === 'ar' ? 'en' : 'ar');

  return (
    <button type="button" className={styles.btn} onClick={toggle}>
      <Globe size={16} />
      <span>{t('language.switchTo')}</span>
    </button>
  );
}
