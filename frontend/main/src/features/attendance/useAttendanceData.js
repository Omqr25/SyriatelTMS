import { useEffect, useState } from "react";
import { getAttendance } from "./attendanceApi";

export default function useAttendanceData(path, query = "", revision = 0) {
  const [result, setResult] = useState(null);
  const [pending, setPending] = useState(true);
  const [error, setError] = useState(false);
  const [retry, setRetry] = useState(0);

  useEffect(() => {
    if (!path) return;
    const controller = new AbortController();
    setPending(true);
    setError(false);

    getAttendance(path, query, controller.signal)
      .then((response) => {
        if (!controller.signal.aborted) setResult(response);
      })
      .catch(() => {
        if (!controller.signal.aborted) setError(true);
      })
      .finally(() => {
        if (!controller.signal.aborted) setPending(false);
      });

    return () => controller.abort();
  }, [path, query, revision, retry]);

  return { result, pending, error, reload: () => setRetry((value) => value + 1) };
}
