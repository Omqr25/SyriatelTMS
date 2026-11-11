import { useCallback, useEffect, useState } from "react";
import {getSupervisorsApi ,createSupervisorsApi} from "../../Api/HrApi/SupervisorsApi"


export default function useSupervisors() {
  const [supervisors, setSupervisors] = useState([]);
  const [loadingCreate, setLoadingCreate] = useState(false);
  const [loading,setLoading]=useState(true)
  const [refreshing, setRefreshing] = useState(false);


  const getSupervisors = useCallback(async (silent = false) => {

     if (silent) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }
    try {
        const data= await  getSupervisorsApi();
        setSupervisors(data.data)
        // console.log("su:", supervisors)
     
    } catch (error) {
      const message =
        error.response?.data?.message || error.message || "error";
      console.error("Error fetching programs:", message);
      throw error;
    }finally{
      if (silent) {
        setRefreshing(false);
      } else {
        setLoading(false);
      }
    }
  }, []);


    const createSupervisors = useCallback(async (formData) => {
    setLoadingCreate(true)
    try {
        const data= await  createSupervisorsApi(formData);
        console.log("csu:", data)
        await getSupervisors(true)

        return data
     
    } catch (error) {
      const message =
        error.response?.data?.message || error.message || "error";
      console.error("Error fetching programs:", message);
      throw error;
    }finally{
      setLoadingCreate(false)
    }
  }, []);

 

  useEffect(() => {
    getSupervisors();
  }, []);

  return {
    // operation
    getSupervisorsApi,
    createSupervisors,
    // loading
    loading,
    loadingCreate,
    refreshing,
    // state
    supervisors
  };
}
