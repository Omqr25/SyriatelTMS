import { useCallback, useState } from 'react';

/**
 * يتتبّع حالة التحميل لعدّة أزرار بمفاتيح مختلفة.
 *   const { run, isBusy } = useBusy();
 *   run(`reject-${id}`, () => rejectApplicant(id));
 *   <Button loading={isBusy(`reject-${id}`)} />
 */
export default function useBusy() {
  const [busy, setBusy] = useState({});

  const run = useCallback(async (key, task) => {
    setBusy((b) => ({ ...b, [key]: true }));
    try {
      return await task();
    } finally {
      setBusy((b) => ({ ...b, [key]: false }));
    }
  }, []);

  const isBusy = useCallback((key) => Boolean(busy[key]), [busy]);

  return { run, isBusy };
}
