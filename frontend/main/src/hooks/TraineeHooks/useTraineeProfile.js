import { useEffect, useState } from 'react';
import { getTraineeProfile } from '../../Api/Trainee/profileApi';

export default function useTraineeProfile() {
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    const controller = new AbortController();

    getTraineeProfile({ signal: controller.signal })
      .then((data) => {
        if (!controller.signal.aborted) setProfile(data);
      })
      .catch(() => {
        if (!controller.signal.aborted) setError(true);
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });

    return () => controller.abort();
  }, [attempt]);

  function retry() {
    setLoading(true);
    setError(false);
    setAttempt((previous) => previous + 1);
  }

  return { profile, loading, error, retry };
}
