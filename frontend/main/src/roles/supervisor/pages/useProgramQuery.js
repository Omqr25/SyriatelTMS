import { useEffect, useState } from "react";

export default function useProgramQuery(request, id) {
  const [version, setVersion] = useState(0);
  const [result, setResult] = useState(null);

  useEffect(() => {
    if (id === null) return;
    const controller = new AbortController();
    request(id, { signal: controller.signal })
      .then(({ data }) => {
        if (!controller.signal.aborted) setResult({ id, version, data });
      })
      .catch((error) => {
        if (!controller.signal.aborted) setResult({ id, version, error });
      });
    return () => controller.abort();
  }, [request, id, version]);

  const current = result?.id === id && result.version === version ? result : null;
  return {
    data: current?.data,
    error: current?.error,
    loading: id !== null && !current,
    reload: () => setVersion((value) => value + 1),
  };
}
