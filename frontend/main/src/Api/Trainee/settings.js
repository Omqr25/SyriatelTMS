import api from '../axios';

export async function changePassword(body) {
  const response = await api.post('auth/changePassword', body);
  return response.data;
}
