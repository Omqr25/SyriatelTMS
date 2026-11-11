import { getTrainingProgram } from "../../Api/Trainee/trainingProgramApi"
import { toast } from "react-toastify";
import { useState } from "react";
import { decodeJwt } from "../../features/auth/auth.utils"
export default function TrainingProgramHooks() {
    const [loadingAction, setLoadingAction] = useState(false);
    const [trainingProgram, setTrainingProgram] = useState({})
    const [trainingProgramPhases, setTrainingProgramPhases] = useState([])
    const controller = new AbortController();

    const getTrainingProgramInfo = async () => {
        try {
            setLoadingAction(true);

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
            if (error.name !== "CanceledError" &&
                error.name !== "AbortError") {
                console.error(error);
                toast.error(error.message);
            }
        } finally {
            setLoadingAction(false);

        }
    };

    return {
        loadingAction,
        trainingProgram,
        trainingProgramPhases,
        controller,
        getTrainingProgramInfo,
    }
}