import api from "../axios";

export const getAnnouncementsTraineeApi = async () => {
    const response = await api.get("announcement/traineeAnnouncements");
    console.log(response.data)
    return response.data
}