
import { useState } from 'react'
import { getTraineeEvaluation, requestReEvaluation } from "../../Api/Trainee/evaluationApi.js"
import { toast } from "react-toastify";

export default function evaluationHooks() {
    const [reEvaluationForm, setRevaluationForm] = useState(false);
    const [loadingAction, setLoadingAction] = useState(false);
    const [reEvaluationState, setReEvaluationState] = useState(false);
    const [reEvaluationAvaliability, setReEvaluationAvaliability] = useState(false);
    const [reEvaluationReason, setReEvaluationReason] = useState("");
    const [evaluationScore, setEvaluationScore] = useState({});
    const [evaluationIndicators, setEvaluationIndicators] = useState([]);

    const fetchEvaluation = async () => {
        try {
            setLoadingAction(true);
            const response = await getTraineeEvaluation();
            const evaluationData = response.data;
            const indicators = evaluationData?.evaluation.indicators ?? [];
            setEvaluationIndicators(
                Array.isArray(indicators) ? indicators : []
            );
            setEvaluationScore({
                score: evaluationData?.evaluation.score ?? "",
                notice: evaluationData?.evaluation.description ?? "",
            });
            setReEvaluationAvaliability(evaluationData?.reevaluation ?? false)
        } catch (error) {
            toast.error(error.message)
            console.error("Failed to fetch evaluation:", error);
        } finally {
            setLoadingAction(false);
        }
    };
    const reEvaluationFunction = async (e) => {
        try {
            e.preventDefault();
            setReEvaluationState(true)
            const response = await requestReEvaluation({ reason: reEvaluationReason })
            setRevaluationForm(false)
            toast.success(response.message)
        } catch (error) {
            toast.error(error.message)
            console.error("Failed to send re-evaluation request:", error);
        } finally {
            setReEvaluationState(false)
        }
    }

    const closeTheForm = () => {
        setRevaluationForm(false)
        setReEvaluationReason("")
    }

    return {
        reEvaluationForm,
        loadingAction,
        reEvaluationState,
        reEvaluationAvaliability,
        reEvaluationReason,
        evaluationScore,
        evaluationIndicators,
        fetchEvaluation,
        setRevaluationForm,
        reEvaluationFunction,
        closeTheForm,
    }
}