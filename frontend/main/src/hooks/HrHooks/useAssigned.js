import { useCallback, useEffect, useState } from "react";
import { searchApplicants } from "../../Api/HrApi/applicantsApi";
import { getAllAssignApi } from "../../Api/HrApi/assignApi";

export default function useAssign() {
  const [assigned, setAssigned] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const getAssigned = useCallback(async (silent = false) => {
    if (!silent) {
      setLoading(true);
    }

    try {
      setError(null);

      const data = await getAllAssignApi();
      console.log(data);
      setAssigned(data.data || []);

      return data;
    } catch (error) {
      console.error("Failed to search applicants:", error);
      setError(error);
      return [];
    } finally {
      setLoading(false);
    }
  }, []);

 

  return {
    // operation
    getAssigned,
    // loading
    loading,

    // state
    assigned,

    // Error
    error,
  };
}
