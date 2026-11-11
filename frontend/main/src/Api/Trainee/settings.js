import api from "../axios";

export const changePassword = async (body) => {
    const response = await api.post("auth/changePassword", body);
    console.log(response.data)
    return response.data
}