import { useCallback, useEffect, useRef, useState } from "react";
import { toast } from "react-toastify";
import {
  getApplicant,
  rejectApplicant,
  searchApplicants,
  assignApplicant,
} from "../../Api/HrApi/applicantsApi";

const EMPTY_FILTERS = {
  search: "",
  year: "",
  major: "",
  city: "",
  gpa_min: 0,
  gpa_max: 100,
  status: "",
};
const notifyError = (error) => {
  const message = error.response?.data?.message || error.message;
  toast.error(Array.isArray(message) ? message.join("، ") : message);
};

export default function useApplicants() {
  const [applicants, setApplicants] = useState([]);
  const [applicant, setApplicant] = useState(null);
  const [filters, setFilters] = useState(EMPTY_FILTERS);
  const [query, setQuery] = useState({ filters: EMPTY_FILTERS, page: 1 });
  const [pagination, setPagination] = useState({ total: 0, totalPages: 0 });
  const [loading, setLoading] = useState(true);
  const [applicantLoading, setApplicantLoading] = useState(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [rejectLOading, setRejectLoading] = useState(null);
  const [error, setError] = useState(null);
  const [revision, setRevision] = useState(0);
  const timer = useRef(null);
  const listController = useRef(null);
  const detailController = useRef(null);
  const mutationBusy = useRef(false);
  const loadApplicants = useCallback(
    () => setRevision((value) => value + 1),
    [],
  );

  useEffect(() => {
    const controller = new AbortController();
    listController.current = controller;
    setLoading(true);
    setError(null);
    searchApplicants({ ...query, limit: 10, signal: controller.signal })
      .then((data) => {
        if (controller.signal.aborted) return;
        const totalPages = data.meta?.total_pages || 0;
        if (query.page > Math.max(1, totalPages)) {
          setQuery((current) => ({
            ...current,
            page: Math.max(1, totalPages),
          }));
          return;
        }
        setApplicants(data.data || []);
        setPagination({ total: data.meta?.total || 0, totalPages });
      })
      .catch((err) => {
        if (controller.signal.aborted) return;
        setError(err);
        notifyError(err);
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [query, revision]);

  useEffect(
    () => () => {
      clearTimeout(timer.current);
      detailController.current?.abort();
    },
    [],
  );

  const clearApplicant = useCallback(() => {
    detailController.current?.abort();
    setApplicant(null);
    setApplicantLoading(null);
  }, []);

  const loadApplicant = useCallback(async (id) => {
    detailController.current?.abort();
    const controller = new AbortController();
    detailController.current = controller;
    setApplicant(null);
    setApplicantLoading(id);
    try {
      const data = await getApplicant(id, controller.signal);
      if (controller.signal.aborted) return null;
      setApplicant(data.data);
      return data;
    } catch (err) {
      if (!controller.signal.aborted) {
        setError(err);
        notifyError(err);
      }
      return null;
    } finally {
      if (!controller.signal.aborted) setApplicantLoading(null);
    }
  }, []);

  const applySnapshot = useCallback((next) => {
    listController.current?.abort();
    setQuery({ filters: next, page: 1 });
  }, []);
  const updateFilter = (key, value) => {
    const next = { ...filters, [key]: value };
    setFilters(next);
    clearTimeout(timer.current);
    listController.current?.abort();
    if (key === "search")
      timer.current = setTimeout(() => applySnapshot(next), 300);
    else applySnapshot(next);
  };
  const applyFilters = () => {
    clearTimeout(timer.current);
    applySnapshot(filters);
  };
  const resetFilters = () => {
    clearTimeout(timer.current);
    setFilters(EMPTY_FILTERS);
    applySnapshot(EMPTY_FILTERS);
  };
  const changePage = (page) => {
    clearTimeout(timer.current);
    setQuery({ filters, page: filters === query.filters ? page : 1 });
  };
  const mutate = async (operation) => {
    if (mutationBusy.current) return null;
    mutationBusy.current = true;
    setActionLoading(true);
    setError(null);
    try {
      const data = await operation();
      loadApplicants();
      return data;
    } catch (err) {
      setError(err);
      notifyError(err);
      return null;
    } finally {
      mutationBusy.current = false;
      setActionLoading(false);
      setRejectLoading(null);
    }
  };
  const reject = (id) => {
    if (mutationBusy.current) return Promise.resolve(null);
    setRejectLoading(id);
    return mutate(() => rejectApplicant(id));
  };
  return {
    applicants,
    applicant,
    loading,
    applicantLoading,
    actionLoading,
    rejectLOading,
    error,
    loadApplicants,
    loadApplicant,
    clearApplicant,
    reject,
    assign: (id, supervisor) =>
      mutate(() => assignApplicant(id, supervisor)),
    filters,
    updateFilter,
    applyFilters,
    resetFilters,
    page: query.page,
    pagination,
    setPage: changePage,
    isSearchMode: false,
  };
}
