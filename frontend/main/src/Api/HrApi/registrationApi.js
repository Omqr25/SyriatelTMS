import api from "../axios";

export const getProgramsApi = async () => {
  const response = await api.get("/training_program", {
   
  });

  return response.data;
};


export const createProgramsApi = async (programData) => {
  const response = await api.post("/training_program", {
    name: programData.name,
    description:"no description",
    start_date: programData.startDate,
    end_date: programData.endDate,
    application_start_date: programData.startDateRegistration,
    application_end_date: programData.endDateRegistration,
  });

  console.log("Program created successfully:", response.data);

  return response.data;
};
