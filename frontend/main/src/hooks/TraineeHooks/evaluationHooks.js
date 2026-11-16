import { useState } from 'react'
import { getTraineeEvaluation, requestReEvaluation } from "../../Api/Trainee/evaluationApi.js"
import { toast } from "react-toastify";

// Prefer the backend's message (e.g. validation errors) over Axios's generic text.
const getErrorMessage = (error) =>
    error.response?.data?.message || error.message;

export default function evaluationHooks() {
    const [reEvaluationForm, setRevaluationForm] = useState(false);
    const [loadingAction, setLoadingAction] = useState(true);
    const [reEvaluationState, setReEvaluationState] = useState(false);
    const [reEvaluationAvaliability, setReEvaluationAvaliability] = useState(false);
    const [reEvaluationReason, setReEvaluationReason] = useState("");
    const [evaluationScore, setEvaluationScore] = useState({});
    const [evaluationIndicators, setEvaluationIndicators] = useState([]);
    const [hasEvaluation, setHasEvaluation] = useState(true);

    const fetchEvaluation = async () => {
        try {
            setLoadingAction(true);
            const response = await getTraineeEvaluation();
            const evaluationData = response.data;
            const indicators = evaluationData?.evaluation?.indicators ?? [];
            setEvaluationIndicators(
                Array.isArray(indicators) ? indicators : []
            );
            setEvaluationScore({
                score: evaluationData?.evaluation?.score ?? "",
                notice: evaluationData?.evaluation?.description ?? "",
            });
            // `reevaluation` is true when a request ALREADY exists,
            // so the button is only available when it is false.
            setReEvaluationAvaliability(evaluationData?.can_request_reevaluation === true);
            setHasEvaluation(true);
        } catch (error) {
            // 404 = the supervisor has not evaluated this trainee yet.
            if (error.response?.status === 404) {
                setHasEvaluation(false);
                setEvaluationIndicators([]);
                setEvaluationScore({});
                setReEvaluationAvaliability(false);
                return;
            }
            toast.error(getErrorMessage(error))
            console.error("Failed to fetch evaluation:", error);
        } finally {
            setLoadingAction(false);
        }
    };
    const reEvaluationFunction = async (e) => {
        e.preventDefault();
        if (reEvaluationState || !reEvaluationAvaliability) return;
        try {
            setReEvaluationState(true)
            const response = await requestReEvaluation({ reason: reEvaluationReason.trim() })
            setRevaluationForm(false)
            setReEvaluationReason("")
            // Only one re-evaluation request is allowed, so hide the button.
            setReEvaluationAvaliability(false)
            toast.success(response.message)
        } catch (error) {
            toast.error(getErrorMessage(error))
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
        hasEvaluation,
        fetchEvaluation,
        setRevaluationForm,
        setReEvaluationReason,
        reEvaluationFunction,
        closeTheForm,
    }
}
