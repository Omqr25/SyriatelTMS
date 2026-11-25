import api from '../../Api/axios';

export async function getAccountProfile(endpoint, config) {
  const { data } = await api.get(endpoint, config);
  return data?.data === undefined ? data : data.data;
}

export async function changePassword(body, token) {
  const response = await api.post('auth/changePassword', body, {
    headers: { Authorization: `Bearer ${token}` },
  });
  return response.data;
}
