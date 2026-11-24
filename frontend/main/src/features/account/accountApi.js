import api from '../../Api/axios';

export async function getAccountProfile(endpoint, config) {
  const { data } = await api.get(endpoint, config);
  return data?.data === undefined ? data : data.data;
}

export async function changePassword(body) {
  const response = await api.post('auth/changePassword', body);
  return response.data;
}
