import api from "../axios";

export async function getAnnouncementsTraineeApi() {
  const response = await api.get("announcement/traineeAnnouncements");
  return response.data;
}
