import useLiveFilterUpdates from "./useLiveFilterUpdates";
import { useCallback, useEffect, useRef, useState } from "react";

import {
  getApplicants,
  getApplicant,
  rejectApplicant,
  searchApplicants,
  assignApplicant,
  //   filterApplicants,
} from "../../Api/HrApi/applicantsApi";

const EMPTY_FILTERS = {
  search: "",
  year: "",
  major: "",
  city: "",
  gpa: "",
  status: "",
};

export default function useApplicants() {
  const requestId = useRef(0);
  const invalidate = useCallback(() => { requestId.current += 1; }, []);

  // ==============================
  // Applicants data
  // ==============================
  const [applicants, setApplicants] = useState([]);

  // ==============================
  // Selected applicant
  // ==============================
  const [applicant, setApplicant] = useState(null);

  //   ==============================
  // pagination state
  // =============================
  const [page, setPage] = useState(1);
  const [limit] = useState(10);
  const [pagination, setPagination] = useState({
    total: 0,
    totalPages: 0,
  });

  // ==============================
  // Filters
  // ==============================
  const [filters, setFilters] = useState(EMPTY_FILTERS);

  const [appliedFilters, setAppliedFilters] = useState(EMPTY_FILTERS);

  // search pagination
  const [searchPage, setSearchPage] = useState(1);

  const [searchPagination, setSearchPagination] = useState({
    total: 0,
    totalPages: 0,
  });

  // search mode
  const [isSearchMode, setIsSearchMode] = useState(false);

  // ==============================
  // Loading states
  // ==============================
  const [loading, setLoading] = useState(false);
  const [applicantLoading, setApplicantLoading] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);
  const [rejectLOading, setRejectLOading] = useState("");
  // ==============================
  // Error state
  // ==============================
  const [error, setError] = useState(null);

  // ==============================
  // Load all applicants
  // ==============================
  const loadApplicants = useCallback(
    async (silent = false) => {
      const id = ++requestId.current;
      if (!silent) {
        setLoading(true);
      }
      try {
        setError(null);

        const data = isSearchMode
          ? await searchApplicants({ page: searchPage, limit, filters: appliedFilters })
          : await getApplicants(page, limit);
        if (id !== requestId.current) return null;

        setApplicants(data.data || []);
        const updatePagination = isSearchMode ? setSearchPagination : setPagination;
        updatePagination({
          total: data.meta.total,
          totalPages: data.meta.total_pages,
        });
      } catch (error) {
        if (id !== requestId.current) return null;
        console.error("Failed to load applicants:", error);
        setError(error);
      } finally {
        if (id === requestId.current) setLoading(false);
      }
    },
    [page, limit, isSearchMode, searchPage, appliedFilters],
  );

  // ==============================
  // Load one applicant
  // ==============================
  const loadApplicant = useCallback(async (id) => {
    try {
      setApplicantLoading(id);
      setError(null);

      const data = await getApplicant(id);

      setApplicant(data.data);
      return data;
    } catch (error) {
      console.error("Failed to load applicant:", error);
      setError(error);
      return null;
    } finally {
      setApplicantLoading(null);
    }
  }, []);

  const performSearch = useCallback(
    async (pageNumber, currentFilters) => {
      const id = ++requestId.current;
      try {
        setLoading(true);
        setError(null);

        const data = await searchApplicants({
          page: pageNumber,
          limit,
          filters: currentFilters,
        });

        if (id !== requestId.current) return null;
        setApplicants(data.data || []);

        setSearchPagination({
          total: data.meta?.total || 0,
          totalPages: data.meta?.total_pages || 0,
        });

        return data;
      } catch (error) {
        if (id !== requestId.current) return null;
        console.error("Failed to search applicants:", error);

        setError(error);

        return null;
      } finally {
        if (id === requestId.current) setLoading(false);
      }
    },
    [limit],
  );

  // ==============================
  // Reject applicant
  // ==============================
  const reject = useCallback(
    async (id) => {
      setRejectLOading(id);
      try {
        // setActionLoading(true);
        setError(null);

        const data = await rejectApplicant(id);

        // إعادة تحميل البيانات بعد الرفض
        await loadApplicants(true);

        return data;
      } catch (error) {
        console.error("Failed to reject applicant:", error);
        setError(error);
        return null;
      } finally {
        // setActionLoading(false);
        setRejectLOading("");
      }
    },
    [loadApplicants],
  );

  // assign application
  const assign = useCallback(
    async (applicationId, supervisorId) => {
      setActionLoading(true);
      try {
        // setActionLoading(true);
        setError(null);

        const data = await assignApplicant(applicationId, supervisorId);

        await loadApplicants(true);

        return data;
      } catch (error) {
        console.error("Failed to reject applicant:", error);
        setError(error);
        return null;
      } finally {
        setActionLoading(false);
      }
    },
    [loadApplicants],
  );

  // ============================
  const applySnapshot = useCallback(async (newFilters) => {
    setIsSearchMode(true);
    setSearchPage(1);
    setAppliedFilters(newFilters);
    await performSearch(1, newFilters);
  }, [performSearch]);

  const { schedule, cancel } = useLiveFilterUpdates(applySnapshot, invalidate);
  const applyFilters = useCallback(() => {
    cancel();
    return applySnapshot(filters);
  }, [cancel, applySnapshot, filters]);

  const changeSearchPage = useCallback(
    async (newPage) => {
      if (filters !== appliedFilters) return applyFilters();
    setSearchPage(newPage);

      await performSearch(newPage, appliedFilters);
    },
    [appliedFilters, performSearch, filters, applyFilters],
  );

  // ==============================
  // Search applicants
  // ==============================
  const search = applyFilters;

  // ==============================
  // Load applicants when hook mounts
  // ==============================
  useEffect(() => {
    if (!isSearchMode) loadApplicants();
  }, [loadApplicants, isSearchMode]);

  //   ==============================
  // Update filters
  // ==============================
  const updateFilter = useCallback((key, value) => {
    const next = { ...filters, [key]: value };
    setFilters(next);
    schedule(key, next);
  }, [filters, schedule]);

  // const applyFilters = useCallback(() => {
  //   setPage(1);
  //   setAppliedFilters(filters);
  // }, [filters]);

  // ==============================
  // Reset filters
  // ==============================
  const resetFilters = useCallback(() => {
    cancel();
    setFilters(EMPTY_FILTERS);
    setIsSearchMode(true);
    setPage(1);
    return applySnapshot(EMPTY_FILTERS);
  }, [cancel, applySnapshot]);

  // ==============================
  // Public API of the hook
  // ==============================

  return {
    // Data
    applicants,
    applicant,

    // Loading
    loading,
    applicantLoading,
    actionLoading,
    setActionLoading,
    rejectLOading,
    // Error
    error,

    // Operations
    loadApplicants,
    loadApplicant,
    reject,
    assign,
    search,

    // Filters
    filters,
    appliedFilters,
    updateFilter,
    applyFilters,
    resetFilters,

    // pagination
    page,
    limit,
    pagination,

    // Search pagination
    searchPage,
    searchPagination,
    changeSearchPage,
    setPage: (newPage) => filters !== appliedFilters ? applyFilters() : setPage(newPage),

    // Search
    isSearchMode,

    // Optional helper
    clearApplicant: () => setApplicant(null),
  };
}
