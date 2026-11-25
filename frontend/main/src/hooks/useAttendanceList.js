import { useCallback, useEffect, useRef, useState } from "react";
import { toast } from "react-toastify";

export default function useAttendanceList(fetchPage) {
  const latestRequest = useRef(0);
  const [result, setResult] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => () => { latestRequest.current += 1; }, []);

  const load = useCallback(async (page = 1, ...filters) => {
    const request = ++latestRequest.current;
    setLoading(true);

    try {
      let response = await fetchPage(page, ...filters);
      if (request !== latestRequest.current) return false;

      // Cancellation can remove the last item from the current page.
      if (page > response.meta.last_page) {
        response = await fetchPage(response.meta.last_page, ...filters);
      }

      if (request !== latestRequest.current) return false;
      setResult(response);
      return true;
    } catch (error) {
      if (request === latestRequest.current) {
        toast.error(error.response?.data?.message || error.message);
      }
      return false;
    } finally {
      if (request === latestRequest.current) setLoading(false);
    }
  }, [fetchPage]);

  return {
    load,
    loading,
    rows: result?.data ?? [],
    extraData: result?.extraData,
    paging: {
      currentPage: result?.meta.current_page ?? 1,
      totalPages: result?.meta.last_page ?? 1,
      total: result?.meta.total ?? 0,
    },
  };
}
