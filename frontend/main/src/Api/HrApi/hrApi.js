import api from "../axios";

export const getDashboardDataApi = async () => {
  const response = await api.get("dashboard/hr");
  console.log(response.data)
  return response.data;
};

export const getAllEvaluationApi = async () => {
  const response = await api.get("evaluation/allEvaluations");
  console.log(response.data)
  return response.data;
};


export const getAbsentAndSearchApi = async (name=null) => {
  const response = await api.get("absence/findAll",{params:{
      name:name ||null,
      date :  name == null? null :new Date().toISOString().split('T')[0]

  }});
  console.log(response.data)
  return response.data;
};


export const getAllAnnouncementsApi = async () => {
  const response = await api.get("announcement/getSentByMe");
  console.log(response.data)
  return response.data;
};


export const sendAnnouncementsApi = async (body) => {
  const response = await api.post("announcement/hrSendAnnouncement",body);
  console.log(response.data)
  return response.data;
};


export const getReportApi = async () => {
  const response = await api.get("reports/training_cycle");
  console.log(response.data)
  const data = response?.data?.data ?? response?.data ?? response;
  return data;
};
