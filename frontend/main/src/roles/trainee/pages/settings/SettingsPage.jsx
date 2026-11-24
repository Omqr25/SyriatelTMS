import { CheckCircle2, Eye, EyeOff, ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Button from '../../../../components/ui/Button';
import Card from '../../../../components/ui/Card';
import usePasswordSettings from '../../../../hooks/TraineeHooks/settingsHooks';
import styles from './SettingsPage.module.css';

const passwordFields = [
  { name: 'currentPassword', autoComplete: 'current-password' },
  { name: 'newPassword', autoComplete: 'new-password', hintId: 'new-password-hint' },
  { name: 'confirmPassword', autoComplete: 'new-password' },
];

export default function SettingsPage() {
  const { t } = useTranslation('trainee');
  const { form, visible, errors, status, updateField, toggleVisibility, handleSubmit } = usePasswordSettings();
  const saving = status === 'saving';

  return (
    <Card as="div" className={styles.page}>
      <header className={styles.heading}>
        <div className={styles.icon}><ShieldCheck size={24} aria-hidden="true" /></div>
        <div>
          <h1>{t('settings.changePassword')}</h1>
          <p>{t('settings.passwordDescription')}</p>
        </div>
      </header>

      <form className={styles.form} onSubmit={handleSubmit} aria-busy={saving} noValidate>
        {passwordFields.map(({ name, autoComplete, hintId }) => {
          const errorId = errors[name] ? `${name}-error` : undefined;
          const label = t(`settings.${name}`);

          return (
            <div className={styles.field} key={name}>
              <label htmlFor={name}>{label}</label>
              <div className={`${styles.inputWrapper} ${errorId ? styles.invalid : ''}`}>
                <input
                  id={name}
                  name={name}
                  type={visible[name] ? 'text' : 'password'}
                  value={form[name]}
                  onChange={(event) => updateField(name, event.target.value)}
                  autoComplete={autoComplete}
                  aria-invalid={Boolean(errorId)}
                  aria-describedby={[hintId, errorId].filter(Boolean).join(' ') || undefined}
                  disabled={saving}
                  required
                />
                <button
                  type="button"
                  className={styles.visibilityButton}
                  onClick={() => toggleVisibility(name)}
                  aria-label={t(visible[name] ? 'settings.hidePassword' : 'settings.showPassword', { field: label })}
                  disabled={saving}
                >
                  {visible[name] ? <EyeOff size={18} aria-hidden="true" /> : <Eye size={18} aria-hidden="true" />}
                </button>
              </div>
              {hintId && <p id={hintId} className={styles.hint}>{t('settings.passwordHint')}</p>}
              {errorId && <p id={errorId} role="alert" className={styles.error}>{t(errors[name])}</p>}
            </div>
          );
        })}

        {status === 'failure' && <p role="alert" className={styles.error}>{t('settings.failure')}</p>}
        {status === 'success' && (
          <p role="status" className={styles.success}>
            <CheckCircle2 size={18} aria-hidden="true" /> {t('settings.success')}
          </p>
        )}

        <div className={styles.actions}>
          <Button type="submit" loading={saving}>
            {t(saving ? 'common.saving' : 'settings.saveChanges')}
          </Button>
        </div>
      </form>
    </Card>
  );
}
