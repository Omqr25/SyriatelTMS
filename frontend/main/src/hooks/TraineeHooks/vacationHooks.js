import { getVacationsRequests, requestVacation, canceltVacation, updateVacation } from "../../Api/Trainee/vacationApi.js"
import { useState, useRef } from "react";
import { toast } from "react-toastify";
import useAttendanceList from "../useAttendanceList";
import { useTranslation } from "react-i18next";
export default function VacationHooks() {
    const vacationId = useRef(null);
    const [vacationRequest, setVacationRequest] = useState({
        reason: "",
        start_date: "",
        end_date: "",
    })
    const { t } = useTranslation("trainee");
    const [sendingVacationRequest, setSendingVacationRequest] = useState(false)
    const [updatingVacationRequest, setUpdatingVacationRequest] = useState(false)
    const { rows: vacationsList, extraData, paging, loading: loadingAction, load: getVacationsList } = useAttendanceList(getVacationsRequests);
    const [vacationState, setAddVacation] = useState(false)
    const [cancelVacationState, setCancelVacationState] = useState(false)
    const [loadingCancelVacationState, setLoadingCancelVacationState] = useState(false)
    const openCancelVacation = (id) => {
        setCancelVacationState(true)
        vacationId.current = id
    }

    const openUpdateVacation = (vacationData) => {
        vacationId.current = vacationData.id
        setVacationRequest({
            reason: vacationData.reason,
            start_date: vacationData.start_date,
            end_date: vacationData.end_date,
        })
        setAddVacation(true);
    }

    const openAddVacation = () => {
        vacationId.current = null;

        setVacationRequest({
            reason: "",
            start_date: "",
            end_date: "",
        });

        setAddVacation(true);
    }


    const sendCancelVacation = async () => {
        try {
            setLoadingCancelVacationState(true)
            const response = await canceltVacation(vacationId.current)
            toast.success(response.message)
            setCancelVacationState(false)
            await getVacationsList(paging.currentPage)
        } catch (error) {
            toast.error(error.response?.data?.message || error.message);
            await getVacationsList(paging.currentPage);
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
            await getVacationsList(paging.currentPage);

        } catch (error) {
            toast.error(error.response?.data?.message || error.message);
            await getVacationsList(paging.currentPage);
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

            await getVacationsList();
        } catch (error) {
            toast.error(error.response?.data?.message || error.message);
            await getVacationsList(paging.currentPage);;
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
        getVacationsList,
        extraData,
        paging,
        openCancelVacation,
        openUpdateVacation,
        openAddVacation, 
        sendCancelVacation,
        sendUpdateVacation,
        sendVacationRequest,
        setAddVacation,
        setCancelVacationState, 
        setVacationRequest
    }
}