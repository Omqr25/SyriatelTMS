import { useEffect, useState } from "react";
import { getTrainingProgram } from "../../Api/Trainee/trainingProgramApi";

export default function TrainingProgramHooks() {
  const [loadingAction, setLoadingAction] = useState(true);
  const [trainingProgram, setTrainingProgram] = useState({});
  const [trainingProgramPhases, setTrainingProgramPhases] = useState([]);
  const [notFoundMessage, setNotFoundMessage] = useState("");
  const [error, setError] = useState("");
  const [retry, setRetry] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    setLoadingAction(true);
    setNotFoundMessage("");
    setError("");
    getTrainingProgram({ signal: controller.signal })
      .then((response) => {
        if (controller.signal.aborted) return;
        setTrainingProgram(response.data || {});
        setTrainingProgramPhases(
          Array.isArray(response.data?.phases) ? response.data.phases : [],
        );
      })
      .catch((err) => {
        if (controller.signal.aborted) return;
        if (err.response?.status === 404) setNotFoundMessage("not_found");
        else setError(err.response?.data?.message || err.message);
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoadingAction(false);
      });
    return () => controller.abort();
  }, [retry]);
  return {
    loadingAction,
    trainingProgram,
    trainingProgramPhases,
    notFoundMessage,
    error,
    getTrainingProgramInfo: () => setRetry((value) => value + 1),
  };
}
