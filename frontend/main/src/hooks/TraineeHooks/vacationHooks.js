import { getVacationsRequests, requestVacation, canceltVacation, updateVacation } from "../../Api/Trainee/vacationApi.js"
import { useState, useRef } from "react";
import { toast } from "react-toastify";
export default function VacationHooks() {
    const vacationId = useRef(-1);
    const [vacationRequest, setVacationRequest] = useState({
        reason: "",
        start_date: "",
        end_date: "",
    })
    const [sendingVacationRequest, setSendingVacationRequest] = useState(false)
    const [updatingVacationRequest, setUpdatingVacationRequest] = useState(false)
    const [vacationsList, setVacationsList] = useState([])
    const [vacationState, setAddVacation] = useState(false)
    const [cancelVacationState, setCancelVacationState] = useState(false)
    const [loadingCancelVacationState, setLoadingCancelVacationState] = useState(false)
    const [loadingAction, setLoadingAction] = useState(false)
    const getVacatoinsList = async () => {
        try {
            setLoadingAction(true)
            const response = await getVacationsRequests()
            const data = response.data;
            setVacationsList(Array.isArray(data) ? data : [])

        } catch (error) {
            toast.error(error.message)
        } finally {
            setLoadingAction(false)
        }
    }
    const openCancelVacation = (vacationId) => {
        setCancelVacationState(true)
        vacationId.current = vacationId
    }

    const openUpdateVacation = (vacationData) => {
        vacationId.current = vacationData.id
        setVacationRequest({
            reason: vacationData.reason,
            start_date: vacationData.start_date.split(" ")[0],
            end_date: vacationData.end_date.split(" ")[0],
        })
        setAddVacation(true);
    }
    const sendCancelVacation = async () => {
        try {
            setLoadingCancelVacationState(true)
            const response = await canceltVacation(vacationId.current)
            toast.success(response.message)
            setCancelVacationState(false)
            await getVacatoinsList()
        } catch (error) {
            toast.error(error.message)
        } finally {
            setLoadingCancelVacationState(false)
        }
    }
    const sendUpdateVacation = async () => {
        const { reason, start_date, end_date } = vacationRequest;
        if (!reason.trim() || !start_date || !end_date) {
            toast.warn(t("vacation.requiredFields"));
            return;
        }
        if (start_date > end_date) {
            toast.warn(t("vacation.incorrectDate"));
            return;
        }
        try {
            setUpdatingVacationRequest(true)
            const response = await updateVacation(vacationId.current, vacationRequest)
            toast.success(response.message)
            setAddVacation(false)
            await getVacatoinsList();

        } catch (error) {
            toast.error(error.message)
        } finally {
            setUpdatingVacationRequest(false)
        }
    }
    const sendVacationRequest = async () => {
        const { reason, start_date, end_date } = vacationRequest;

        if (!reason.trim() || !start_date || !end_date) {
            toast.warn(t("vacation.requiredFields"));
            return;
        }
        if (start_date > end_date) {
            toast.warn(t("vacation.incorrectDate"));
            return;
        }
        try {
            setSendingVacationRequest(true);
            const response = await requestVacation(vacationRequest);
            toast.success(response.message);
            setAddVacation(false);

            await getVacatoinsList();
        } catch (error) {
            toast.error(error.message);
        } finally {
            setSendingVacationRequest(false);
        }
    };

    return {
        vacationId,
        vacationRequest,
        sendingVacationRequest,
        updatingVacationRequest,
        vacationsList,
        vacationState,
        cancelVacationState,
        loadingCancelVacationState,
        loadingAction,
        getVacatoinsList,
        openCancelVacation,
        openUpdateVacation,
        sendCancelVacation,
        sendUpdateVacation,
        sendVacationRequest,
        setAddVacation,
        setVacationRequest
    }
}