import { useCallback, useEffect, useState } from "react";
import { TrainerAcceptOrRejectApplicationApi } from "../../Api/SupervisorApi/assignedApi";
import {
  createEvaluationApi,
  deleteProgramApi,
  getMyProgramApi,
  getMyTraineesApi,
  getProgramApi,
  getTaskApi,
  getReevaluationRequestsApi,
  createReEvaluationApi,
  getVacationRequestsApi,
  getAllAbsentApi
} from "../../Api/SupervisorApi/supervisorApi";
import { toast } from "react-toastify";

export default function useAssigned() {
  const [loadingAction, setLoadingAction] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [myTrainees, setMyTrainees] = useState([]);
  const [myPrograms, setMyPrograms] = useState([]);
  const [program, setProgram] = useState("");
  const [task, setTask] = useState("");
  const [ReevaluationRequests, setReevaluationRequests] = useState([])
  const [VacationRequests, setVacationRequests] = useState([])
  const [absent, setAbsent] = useState([])

  const TrainerAcceptOrRejectApplication = useCallback(
    async (applicationId, type) => {
      setLoadingAction(`${type}-${applicationId}`);

      try {
        const data = await TrainerAcceptOrRejectApplicationApi(
          applicationId,
          type,
        );

        setLoadingAction("");
        return data;
      } catch (error) {
        console.log(error);
        setError(error);
        setLoadingAction("");
      }
    },
    [],
  );

  const getMyTrainees = useCallback(async (silent = false) => {
    if (!silent) {
      setLoading(true);
    }
    try {
      const data = await getMyTraineesApi();
      setMyTrainees(data.data);
    } catch (error) {
      console.log(error);
      setError(error);
    } finally {
      setLoading(false);
    }
  }, []);

  const getMyPrograms = useCallback(async (silent = false) => {
    if (!silent) {
      setLoading(true);
    }
    try {
      const data = await getMyProgramApi();
      setMyPrograms(data.data);
    } catch (error) {
      console.log(error);
      setError(error);
      throw error;
    } finally {
      setLoading(false);
    }
  }, []);

  const getProgram = useCallback(async (id, silent = false) => {
    if (!silent) {
      setLoadingAction(id);
    }

    try {
      const data = await getProgramApi(id);
      setProgram(data.data);
    } catch (error) {
      console.log(error);
      setError(error);
    } finally {
      setLoadingAction("");
    }
  }, []);

  const deleteProgram = useCallback(async (id) => {
    setLoadingAction(id);
    try {
      const data = await deleteProgramApi(id);
      console.log("delete", data);
      await getMyPrograms(true);
      return data.message;
    } catch (error) {
      console.log(error);
      setError(error);
    } finally {
      setLoadingAction("");
    }
  }, []);

  const getTask = useCallback(async (taskId, silent = false) => {
    if (!silent) {
      setLoading(true);
    }
    try {
      const data = await getTaskApi(taskId);
      setTask(data.data);
      console.log(`indied the api => ${data.data}`)
    } catch (error) {
      console.log(error);
      setError(error);
    } finally {
      setLoading(false);
    }
  }, []);

  const createEvaluation = useCallback(async (id, body) => {
    setLoadingAction(id);
    try {
      const data = await createEvaluationApi(id, body);
      toast.success(data.message);
    } catch (error) {
      console.log(error);
      setError(error);
    } finally {
      setLoadingAction("");
    }
  }, []);

  const getReevaluationRequests = useCallback(async (silent = false) => {
    if (!silent) {
      setLoading(true);
    }
    try {
      const data = await getReevaluationRequestsApi();
      setReevaluationRequests(data.data);
    } catch (error) {
      console.log(error);
      setError(error);
    } finally {
      setLoading(false);
    }
  }, []);

  const createReEvaluation = useCallback(async (id, body) => {
    setLoadingAction(id);
    try {
      const data = await createReEvaluationApi(id, body);
      toast.success(data.message);
      await getReevaluationRequests(true)
    } catch (error) {
      console.log(error);
      setError(error);
    } finally {
      setLoadingAction("");
    }
  }, []);


  const getVacationRequests = useCallback(async (silent = false) => {
    if (!silent) {
      setLoading(true);
    }
    try {
      const data = await getVacationRequestsApi();
      setVacationRequests(data.data);
    } catch (error) {
      console.log(error);
      setError(error);
    } finally {
      setLoading(false);
    }
  }, []);


  const getAllAbsent = useCallback(async (silent = false) => {
    if (!silent) {
      setLoading(true);
    }
    try {
      const data = await getAllAbsentApi();
      setAbsent(data.data);
    } catch (error) {
      console.log(error);
      const message =
        error.response?.data?.message ||
        error.message ||
        "Add Absent Error";
      toast.error(Array.isArray(message) ? message.join("، ") : message);
      setError(error);

    } finally {
      setLoading(false);
    }
  }, []);




  return {
    // loading
    loading,
    loadingAction,

    // operation
    TrainerAcceptOrRejectApplication,
    getMyTrainees,
    getMyPrograms,
    getProgram,
    deleteProgram,
    getTask,
    createEvaluation,
    getReevaluationRequests,
    createReEvaluation,
    getVacationRequests,
    getAllAbsent,
    // error
    error,

    // data
    myTrainees,
    myPrograms,
    program,
    task,
    ReevaluationRequests,
    VacationRequests,
    absent,
  };
}
