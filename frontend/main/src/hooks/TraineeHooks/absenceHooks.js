import { toast } from "react-toastify";
import { useState } from 'react';

import { getAllAbsentApi } from "../../Api/SupervisorApi/supervisorApi.js"

export default function AbsenceHooks() {
    const [absenceList, setAbsenceList] = useState([])
    const [loadingAction, setLoadingAction] = useState([])
    const getAbsence = async () => {
        try {
            setLoadingAction(true);
            const response = await getAllAbsentApi()
            const data = response.data
            setAbsenceList(Array.isArray(data.data) ? data.data : [])

        } catch (error) {
            toast.error(error.message)
        } finally {
            setLoadingAction(false)
        }
    }

    return {
        absenceList, loadingAction, getAbsence
    }
}