import api from "../axios";

export const getTrainingProgram = async () => {
    const response = await api.get("myProgram");
    console.log(response.data)
    return response.data
}

export const startTask = async (taskId) => {
    const response = await api.get(`task/tasks/${taskId}/start`);
    console.log(response.data)
    return response.data
}

export const submitEvidence = async (taskId, evidence) => {
    const formData = new FormData()
    formData.append("evidence", evidence)
    console.log("eve", formData)
    const response = await api.post(`task/tasks/${taskId}/evidence`, formData,);
    console.log(response.data)
    return response.data
}


