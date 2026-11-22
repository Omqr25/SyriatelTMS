import { useEffect, useRef, useState } from "react";
import { toast } from "react-toastify";

export default function useAnnouncements(loadAnnouncements, publishAnnouncement, revision = 0) {
  const [announcements, setAnnouncements] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadingCreate, setLoadingCreate] = useState(false);
  const pending = useRef(false);
  const initialLoad = useRef(true);

  useEffect(() => {
    let active = true;
    loadAnnouncements().then(({ data }) => {
      if (active) setAnnouncements(data);
    }).catch((error) => {
      if (active && initialLoad.current) toast.error(error.response?.data?.message || error.message);
    }).finally(() => {
      if (active) {
        initialLoad.current = false;
        setLoading(false);
      }
    });
    return () => { active = false; };
  }, [loadAnnouncements, revision]);

  async function mutateAnnouncement(request, updateList) {
    if (pending.current) return false;
    pending.current = true;
    setLoadingCreate(true);
    try {
      const { data, message } = await request();
      setAnnouncements((items) => updateList(items, data));
      toast.success(message);
      return true;
    } catch (error) {
      toast.error(error.response?.data?.message || error.message);
      return false;
    } finally {
      pending.current = false;
      setLoadingCreate(false);
    }
  }

  function sendAnnouncement(body) {
    return mutateAnnouncement(
      () => publishAnnouncement(body),
      (items, created) => [created, ...items.filter((item) => item.id !== created.id)],
    );
  }

  return { announcements, loading, loadingCreate, sendAnnouncement, mutateAnnouncement };
}
