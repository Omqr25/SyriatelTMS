import api from "../axios";

export const getVacationsRequests = async () => {
    const response = await api.get("vacation_request/listForTrainee");
    console.log(response.data)
    return response.data
}

export const requestVacation = async (body) => {
    console.log(body)
    const response = await api.post("vacation_request", body);
    console.log(response.data)
    return response.data
}

export const updateVacation = async (vacationId, body) => {
    console.log(`vacation_request/update/${vacationId}`, body)
    const response = await api.put(`vacation_request/update/${vacationId}`, body);
    console.log(response.data)
    return response.data
}

export const canceltVacation = async (vacatoinId) => {
    console.log(`vacation_request/${vacatoinId}`)
    const response = await api.delete(`vacation_request/${vacatoinId}`);
    console.log(response.data)
    return response.data
}