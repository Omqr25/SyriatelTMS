import api from "../axios";

export const changePassword = async (formData) => {
    const response = await api.post("auth/changePassword", formData);
    console.log(response.data)
    return response.data
}