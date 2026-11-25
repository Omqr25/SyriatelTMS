import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'react-toastify';
import { ACCESS_TOKEN_KEY } from '../../Api/axios';
import { useAuth } from '../../context/AuthContext';
import { changePassword } from './accountApi';

const emptyForm = { currentPassword: '', newPassword: '', confirmPassword: '' };

function validate({ currentPassword, newPassword, confirmPassword }) {
  const errors = {};

  if (!currentPassword) errors.currentPassword = 'settings.validation.currentRequired';

  if (!newPassword) errors.newPassword = 'settings.validation.newRequired';
  else if (newPassword.length < 8) errors.newPassword = 'settings.validation.minLength';
  else if (newPassword === currentPassword) errors.newPassword = 'settings.validation.samePassword';

  if (!confirmPassword) errors.confirmPassword = 'settings.validation.confirmRequired';
  else if (newPassword !== confirmPassword) errors.confirmPassword = 'settings.validation.passwordMismatch';

  return errors;
}

export default function usePasswordSettings() {
  const { t } = useTranslation('account');
  const { token, logout } = useAuth();
  const [form, setForm] = useState(emptyForm);
  const [visible, setVisible] = useState({});
  const [errors, setErrors] = useState({});
  const [status, setStatus] = useState('idle');

  function updateField(name, value) {
    setForm((previous) => ({ ...previous, [name]: value }));
    setErrors((previous) => ({ ...previous, [name]: undefined }));
    setStatus('idle');
  }

  function toggleVisibility(name) {
    setVisible((previous) => ({ ...previous, [name]: !previous[name] }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    if (status === 'saving' || !token || localStorage.getItem(ACCESS_TOKEN_KEY) !== token) return;

    const validationErrors = validate(form);
    setErrors(validationErrors);
    if (Object.keys(validationErrors).length) {
      setStatus('idle');
      return;
    }

    setStatus('saving');
    try {
      await changePassword({
        current_password: form.currentPassword,
        new_password: form.newPassword,
        new_password_confirmation: form.confirmPassword,
      }, token);
    } catch (error) {
      if (error.response?.data?.errors?.current_password) {
        setErrors({ currentPassword: 'settings.validation.currentIncorrect' });
        setStatus('idle');
      } else {
        setStatus('failure');
      }
      return;
    }

    setForm(emptyForm);
    setVisible({});
    if (localStorage.getItem(ACCESS_TOKEN_KEY) !== token) {
      setStatus('idle');
      return;
    }

    toast.success(t('settings.success'));
    void logout();
  }

  return { form, visible, errors, status, updateField, toggleVisibility, handleSubmit };
}
