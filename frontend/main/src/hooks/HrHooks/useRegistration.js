import { useCallback, useEffect, useState } from "react";
import { createProgramsApi, getProgramsApi } from "../../Api/HrApi/registrationApi";

export default function useRegistration() {
  const [programs, setPrograms] = useState([]);
  const [loadingCreate, setLoadingCreate] = useState(false);
  const [loading,setLoading]=useState(true)
  const [error, setError] = useState(null);
  const getPrograms = useCallback(async () => {
    setLoading(true)
      setError(null);

    try {

      const data = await getProgramsApi();
      setPrograms(data.data);
      return data;
    } catch (error) {
      const message =
        error.response?.data?.message || error.message || "invalid credentials";
      console.error("Error fetching programs:", message);
        setError(error);

      throw error;
    }finally{
      setLoading(false)
    }
  }, []);

   const createPrograms = useCallback(async (programData) => {
      setLoadingCreate(true);
    try {
      const data = await createProgramsApi(programData);
      console.log("Program created successfully:", data);
      setPrograms(data.data);
      return data;
    } catch (error) {
      const message =
        error.response?.data?.message || error.message || "invalid credentials";
      console.error("Error fetching programs:", message);
      throw error;
    }finally {
      setLoadingCreate(false);
    }
  }, []);

  useEffect(() => {
    getPrograms().catch(() => {});
  }, [getPrograms]);

  return {
    programs,
    getPrograms,
    createPrograms,
    loadingCreate,
    loading,
    error
  };
}
