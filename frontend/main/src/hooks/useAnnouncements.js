import { useEffect, useState } from "react";
import { toast } from "react-toastify";

export default function useAnnouncements(loadAnnouncements, publishAnnouncement) {
  const [announcements, setAnnouncements] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadingCreate, setLoadingCreate] = useState(false);

  useEffect(() => {
    let active = true;
    loadAnnouncements().then(({ data }) => {
      if (active) setAnnouncements(data);
    }).catch((error) => {
      if (active) toast.error(error.response?.data?.message || error.message);
    }).finally(() => {
      if (active) setLoading(false);
    });
    return () => { active = false; };
  }, [loadAnnouncements]);

  async function sendAnnouncement(body) {
    setLoadingCreate(true);
    try {
      const { data, message } = await publishAnnouncement(body);
      setAnnouncements((items) => [data, ...items.filter((item) => item.id !== data.id)]);
      toast.success(message);
      return true;
    } catch (error) {
      toast.error(error.response?.data?.message || error.message);
      return false;
    } finally {
      setLoadingCreate(false);
    }
  }

  return { announcements, loading, loadingCreate, sendAnnouncement };
}
