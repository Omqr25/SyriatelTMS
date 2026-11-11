
import api from "../axios";

export const getMyInterview = async () => {

  const response = await api.get("application/trainer/interview");

  console.log(response.data)
  return response.data
}

export const SetInterviewResult = async (id, formData) => {
  console
    .log("Body", formData)
  console.log(id)
  const response = await api.patch(`application/trainer/${id}/interview/result`, formData);
  console.log(response.data)
  return response.data
}


export const getMyTraineesApi = async () => {
  const response = await api.get('trainee/trainer');
  // console.log(response.data)
  return response.data
}


export const createProgramApi = async (data) => {
  const body = {
    title: data.name,
    description: data.description,
    deadline: data.endDate,
    phases: data.phases
  }
  const response = await api.post('program', body);
  console.log(response.data)
  return response.data
}

export const getMyProgramApi = async () => {
  const response = await api.get('program/ownedPrograms');
  console.log(response.data)
  return response.data
}

export const getProgramApi = async (id) => {
  const response = await api.get(`program/${id}`);
  console.log(response.data)
  return response.data
}

export const updateProgramApi = async (id, data) => {
  const body = {
    title: data.name,
    description: data.description,
    deadline: data.endDate,
    phases: data.phases
  }
  const response = await api.put(`program/${id}`, body);
  console.log(response.data)
  return response.data
}


export const deleteProgramApi = async (id) => {
  const response = await api.delete(`program/${id}`);
  console.log(response.data)
  return response.data
}


export const createTaskApi = async (phaseId, data) => {

  const response = await api.post(`task/${phaseId}/tasks`, data);
  console.log(response.data)
  return response.data
}

export const getTaskApi = async (taskId) => {

  const response = await api.get(`task/tasks/${taskId}`,);
  console.log(response.data)
  return response.data
}

export const createFeedbackApi = async (taskId, body) => {
  const response = await api.post(`task/tasks/${taskId}/feedback`, body);
  console.log(response.data)
  return response.data
}


export const deleteTaskApi = async (taskId) => {
  const response = await api.delete(`task/tasks/${taskId}`);
  console.log(response.data)
  return response.data
}

export const updateTaskApi = async (taskId, body) => {
  const response = await api.put(`task/tasks/${taskId}`, body);
  console.log(response.data)
  return response.data
}


export const createEvaluationApi = async (id, body) => {
  const response = await api.post(`evaluation/trainee/${id}`, body);
  console.log(response.data)
  return response.data
}

export const getDashboardDataSupervisorApi = async () => {
  const response = await api.get("dashboard/trainer");
  // console.log(response.data)
  return response.data;
};


export const getReevaluationRequestsApi = async () => {
  const response = await api.get("evaluation/trainer/reevaluation_requests");
  console.log(response.data)
  return response.data;
};


export const createReEvaluationApi = async (id, body) => {
  const body2 = {
    decision: "accepted",
    ...body,
    decision_note: "note",
    evaluation_date: new Date().toString()
  }
  console.log(id)
  console.log(body2)
  const response = await api.patch(`evaluation/reevaluation_requests/${id}/decision`, body2);
  // console.log(response.data)
  return response.data
}

export const getVacationRequestsApi = async () => {
  const response = await api.get("vacation_request/listForTrainer");
  // console.log(response.data)
  return response.data;
};


export const AcceptOrRejectVacationRequestApi = async (id, decision) => {

  const response = await api.patch(`vacation_request/decide/${id}`, { status: decision });
  // console.log(response.data)
  return response.data
}

export const getAllAbsentApi = async () => {
  const response = await api.get("absence/findAll");
  console.log(response.data)
  return response.data;
};


export const addAbsentApi = async (id, reason) => {
  const response = await api.post(`absence/recordAbsence/${id}`, { reason });
  console.log(response.data)
  return response.data;
};


export const sendAnnouncementsSupervisorApi = async (body) => {
  const response = await api.post("announcement/trainerSendAnnouncement", body);
  console.log(response.data)
  return response.data;
};

export const getAllAnnouncementsSupervisorApi = async () => {
  const response = await api.get("announcement/getSentByMe");
  console.log(response.data)
  return response.data;
};



