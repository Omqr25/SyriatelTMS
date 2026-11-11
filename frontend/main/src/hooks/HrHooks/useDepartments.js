import { useCallback, useEffect, useState } from "react";
import {getDepartmentsApi,createDepartmentsApi} from "../../Api/HrApi/departmentsApi"

export default function useDepartments() {
  const [Departments, setDepartments] = useState([]);
  const [loadingCreate, setLoadingCreate] = useState(false);
  const [loading,setLoading]=useState(true)
    const [refreshing, setRefreshing] = useState(false);

  const getDepartments = useCallback(async (silent = false) => {
     if (silent) {
        setRefreshing(true);
      } else {
        setLoading(true);
      }
    try {
        const data= await  getDepartmentsApi();
        setDepartments(data.data)
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

    const createDepartments = useCallback(async (formData) => {
    setLoadingCreate(true)
    try {
        const data= await  createDepartmentsApi(formData);
        // setDepartments(data.data)
        // console.log("de:", data )
        await   getDepartments(true)
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
    getDepartments();
  }, [getDepartments]);

  return {
    // operation
    createDepartments,
    getDepartments,
    // loading
    loading,
    loadingCreate,
    refreshing,
    // state
    Departments
  };
}
