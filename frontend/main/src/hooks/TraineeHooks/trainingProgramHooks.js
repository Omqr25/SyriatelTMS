import { getTrainingProgram } from "../../Api/Trainee/trainingProgramApi"
import { toast } from "react-toastify";
import { useState } from "react";
import { decodeJwt } from "../../features/auth/auth.utils"
export default function TrainingProgramHooks() {
    const [loadingAction, setLoadingAction] = useState(false);
    const [trainingProgram, setTrainingProgram] = useState({})
    const [trainingProgramPhases, setTrainingProgramPhases] = useState([])
    const [notFoundMessage, setNotFoundMessage] = useState("")
    const controller = new AbortController();

    const getTrainingProgramInfo = async () => {
        try {
            setLoadingAction(true);
            setNotFoundMessage("");

            const response = await getTrainingProgram({
                signal: controller.signal,
            });

            const data = response.data;

            setTrainingProgram({
                title: data?.title ?? "",
                description: data?.description ?? "",
                deadline: data?.deadline ?? "",
                phases_count: data?.phases_count ?? "",
            });

            const phases = data?.phases ?? [];
            setTrainingProgramPhases(
                Array.isArray(phases) ? phases : []
            );
        } catch (error) {
            if (error.name === "CanceledError" ||
                error.name === "AbortError") {
                return;
            }

            const serverMessage = error.response?.data?.message;

            // 404 = the supervisor has not created a program for this trainee yet.
            if (error.response?.status === 404) {
                setTrainingProgram({});
                setTrainingProgramPhases([]);
                setNotFoundMessage(serverMessage || "not_found");
                return;
            }

            console.error(error);
            toast.error(serverMessage || error.message);
        } finally {
            setLoadingAction(false);

        }
    };

    return {
        loadingAction,
        trainingProgram,
        trainingProgramPhases,
        notFoundMessage,
        controller,
        getTrainingProgramInfo,
    }
}