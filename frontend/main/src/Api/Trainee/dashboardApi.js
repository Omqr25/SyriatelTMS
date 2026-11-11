import api from "../axios";

export const getDahshboard = async () => {
    const response = await api.get("dashboard/trainee");
    console.log(response.data)
    return response.data

}