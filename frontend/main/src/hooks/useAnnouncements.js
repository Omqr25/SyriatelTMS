import { useCallback, useEffect, useState } from "react";


import { getAllAnnouncementsApi, sendAnnouncementsApi } from "../Api/HrApi/hrApi";
import { toast } from "react-toastify";
import { getAllAnnouncementsSupervisorApi, sendAnnouncementsSupervisorApi } from "../Api/SupervisorApi/supervisorApi";
import { getAnnouncementsTraineeApi } from "../Api/Trainee/announcementApi";

export default function useAnnouncements() {
  const [announcements, setAnnouncements] = useState([]);
  const [loadingCreate, setLoadingCreate] = useState(false);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  // HR =====================================
  const getAnnouncements = useCallback(async (silent = false) => {
    if (silent) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }
    try {
      const data = await getAllAnnouncementsApi();
      setAnnouncements(data.data);
    } catch (error) {
      const message =
        error.response?.data?.message || error.message || "Network Error";
      toast.error(message);
      console.error("Error fetching programs:", message);
      throw error;
    } finally {
      if (silent) {
        setRefreshing(false);
      } else {
        setLoading(false);
      }
    }
  }, []);

  const sendAnnouncements = useCallback(async (body) => {
    setLoadingCreate(true);
    try {
      const data = await sendAnnouncementsApi(body);
      toast.success(data.message || " Success");
      await getAnnouncements(true);
    } catch (error) {
      const message =
        error.response?.data?.message || error.message || "Network Error";
      toast.error(message);
      console.error("Send message:", message);
      throw error;
    } finally {
      setLoadingCreate(false);
    }
  }, []);



  // supervisor============================

    const getAnnouncementsSupervisor = useCallback(async (silent = false) => {
    if (silent) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }
    try {
      const data = await getAllAnnouncementsSupervisorApi();
      setAnnouncements(data.data);
    } catch (error) {
      const message =
        error.response?.data?.message || error.message || "Network Error";
      toast.error(message);
      console.error("Error fetching programs:", message);
      throw error;
    } finally {
      if (silent) {
        setRefreshing(false);
      } else {
        setLoading(false);
      }
    }
  }, []);

   const sendAnnouncementsSupervisor = useCallback(async (body) => {
    setLoadingCreate(true);
    try {
      const data = await sendAnnouncementsSupervisorApi(body);
      toast.success(data.message || " Success");
      await getAnnouncementsSupervisor(true);
    } catch (error) {
      const message =
        error.response?.data?.message || error.message || "Network Error";
      toast.error(message);
      console.error("Send message:", message);
      throw error;
    } finally {
      setLoadingCreate(false);
    }
  }, []);


  // trainee=================

    const getAnnouncementsTrainee = useCallback(async (silent = false) => {
    if (silent) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }
    try {
      const data = await getAnnouncementsTraineeApi();
      setAnnouncements(data.data);
    } catch (error) {
      const message =
        error.response?.data?.message || error.message || "Network Error";
      toast.error(message);
      console.error("Error fetching programs:", message);
      throw error;
    } finally {
      if (silent) {
        setRefreshing(false);
      } else {
        setLoading(false);
      }
    }
  }, []);


  return {
    getAnnouncements,
    sendAnnouncements,
    sendAnnouncementsSupervisor,
    getAnnouncementsSupervisor,
    getAnnouncementsTrainee,
    // loading
    loading,
    refreshing,
    loadingCreate,


    // data
    announcements
    
  };
}
