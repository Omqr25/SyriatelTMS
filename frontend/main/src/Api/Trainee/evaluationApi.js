import api from "../axios";
// evaluation/trainer/reevaluation_requests
export const getTraineeEvaluation = async () => {
    const response = await api.get("evaluation/me");
    console.log(response.data)
    return response.data
}

export const requestReEvaluation = async (formData) => {
    const response = await api.post("evaluation/me/reevaluation_request", formData);
    console.log(response.data)
    return response.data
}