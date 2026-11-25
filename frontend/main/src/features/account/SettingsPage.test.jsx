import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import { toast } from 'react-toastify';
import api, { ACCESS_TOKEN_KEY } from '../../Api/axios';
import { AuthProvider, useAuth } from '../../context/AuthContext';
import { revokeBrowserRegistration } from '../../firebase/registration';
import SettingsPage from './SettingsPage';

vi.mock('react-i18next', () => ({ useTranslation: () => ({ t: (key) => key }) }));
vi.mock('react-toastify', () => ({ toast: { success: vi.fn(), warning: vi.fn() } }));
vi.mock('../../firebase/registration', () => ({
  revokeBrowserRegistration: vi.fn().mockResolvedValue(true),
}));

afterEach(() => {
  cleanup();
  localStorage.clear();
  vi.restoreAllMocks();
  vi.clearAllMocks();
});

function showSettings(role, userId = 42) {
  const expires = Math.floor(Date.now() / 1000) + 600;
  const token = `header.${btoa(JSON.stringify({ sub: userId, exp: expires, roles: [role] }))}.signature`;
  localStorage.setItem(ACCESS_TOKEN_KEY, token);

  function ProtectedSettings() {
    const { user } = useAuth();
    return user ? <SettingsPage /> : <h1>Sign in</h1>;
  }

  render(<AuthProvider><ProtectedSettings /></AuthProvider>);
  return token;
}

function submitPassword() {
  for (const [label, password] of [
    ['settings.currentPassword', 'current-fixture-password'],
    ['settings.newPassword', 'updated-fixture-password'],
    ['settings.confirmPassword', 'updated-fixture-password'],
  ]) {
    fireEvent.change(screen.getByLabelText(label, { exact: true }), { target: { value: password } });
  }
  fireEvent.click(screen.getByRole('button', { name: 'settings.saveChanges' }));
}

it.each(['HR', 'SUPERVISOR', 'TRAINEE'])('signs out %s only after the password change succeeds', async (role) => {
  const token = showSettings(role);
  let finish;
  const change = vi.spyOn(api, 'post').mockReturnValue(new Promise((resolve) => { finish = resolve; }));
  const revoke = vi.spyOn(api, 'get').mockResolvedValue({ status: 200 });

  submitPassword();

  expect(localStorage.getItem(ACCESS_TOKEN_KEY)).toBe(token);
  expect(revoke).not.toHaveBeenCalled();
  expect(change).toHaveBeenCalledWith('auth/changePassword', {
    current_password: 'current-fixture-password',
    new_password: 'updated-fixture-password',
    new_password_confirmation: 'updated-fixture-password',
  }, { headers: { Authorization: `Bearer ${token}` } });

  await act(async () => { finish({ data: {} }); });

  expect(screen.getByRole('heading', { name: 'Sign in' })).toBeTruthy();
  expect(localStorage.getItem(ACCESS_TOKEN_KEY)).toBeNull();
  expect(toast.success).toHaveBeenCalledWith('settings.success');
  expect(revokeBrowserRegistration).toHaveBeenCalledWith(token);
  expect(revoke).toHaveBeenCalledWith('auth/logout', {
    headers: { Authorization: `Bearer ${token}` }, timeout: 8000,
  });
});

it('keeps the session when the current password is rejected', async () => {
  const token = showSettings('HR');
  vi.spyOn(api, 'post').mockRejectedValue({ response: { data: { errors: { current_password: ['Incorrect'] } } } });
  const revoke = vi.spyOn(api, 'get');

  submitPassword();

  await waitFor(() => expect(screen.getByRole('alert').textContent).toBe('settings.validation.currentIncorrect'));
  expect(localStorage.getItem(ACCESS_TOKEN_KEY)).toBe(token);
  expect(revoke).not.toHaveBeenCalled();
  expect(toast.success).not.toHaveBeenCalled();
});

it('preserves a replacement session when an earlier password request succeeds', async () => {
  showSettings('HR');
  let finish;
  vi.spyOn(api, 'post').mockReturnValue(new Promise((resolve) => { finish = resolve; }));
  const revoke = vi.spyOn(api, 'get');
  submitPassword();
  localStorage.setItem(ACCESS_TOKEN_KEY, 'replacement-session');

  await act(async () => { finish({ data: {} }); });

  expect(localStorage.getItem(ACCESS_TOKEN_KEY)).toBe('replacement-session');
  expect(revoke).not.toHaveBeenCalled();
  expect(toast.success).not.toHaveBeenCalled();
});
