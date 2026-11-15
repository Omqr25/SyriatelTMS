import api from "../axios";

export const getTraineeProfile = async () => {
    return api.get("profile");
};
