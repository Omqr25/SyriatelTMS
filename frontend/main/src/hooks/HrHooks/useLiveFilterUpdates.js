import { useCallback, useEffect, useRef } from "react";

export default function useLiveFilterUpdates(apply, invalidate) {
  const timer = useRef(null);
  const cancel = useCallback(() => clearTimeout(timer.current), []);

  useEffect(() => () => {
    cancel();
    invalidate();
  }, [cancel, invalidate]);

  const schedule = useCallback((key, filters) => {
    cancel();
    invalidate();
    if (key === "search") {
      timer.current = setTimeout(() => apply(filters), 300);
    } else {
      apply(filters);
    }
  }, [apply, cancel, invalidate]);

  return { schedule, cancel };
}
