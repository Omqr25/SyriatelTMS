import api from '../axios';

export async function getTraineeProfile(config) {
  const { data } = await api.get('profile', config);
  return data?.data === undefined ? data : data.data;
}
