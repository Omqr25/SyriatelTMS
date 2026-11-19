import { useCallback, useEffect, useRef, useState } from "react";
import { createProgramsApi, deleteProgramApi, getProgramsApi, setProgramArchivedApi, updateProgramApi } from "../../Api/HrApi/registrationApi";

export default function useRegistration() {
  const [programs, setPrograms] = useState([]);
  const [canManage, setCanManage] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const pending = useRef(null);

  const reload = useCallback(async () => {
    pending.current?.abort();
    const controller = new AbortController();
    pending.current = controller;
    setLoading(true);
    setError(null);
    try {
      const result = await getProgramsApi(controller.signal);
      if (!controller.signal.aborted) {
        setPrograms(result.programs);
        setCanManage(result.can_manage);
      }
    } catch (err) {
      if (!controller.signal.aborted) setError(err);
    } finally {
      if (!controller.signal.aborted) setLoading(false);
    }
  }, []);

  useEffect(() => {
    reload();
    return () => pending.current?.abort();
  }, [reload]);

  async function save(program, fields) {
    if (!program) {
      setPrograms(await createProgramsApi(fields));
      return;
    }
    const saved = await updateProgramApi(program.id, fields);
    setPrograms((rows) => rows.map((row) => row.id === saved.id ? saved : row));
  }

  async function remove(program) {
    await deleteProgramApi(program.id, program.revision);
    setPrograms((rows) => rows.filter((row) => row.id !== program.id));
  }

  async function setArchived(program, archive) {
    const saved = await setProgramArchivedApi(program.id, archive, program.revision);
    setPrograms((rows) => rows.map((row) => row.id === saved.id ? saved : row));
  }

  return { programs, canManage, loading, error, reload, save, remove, setArchived };
}
