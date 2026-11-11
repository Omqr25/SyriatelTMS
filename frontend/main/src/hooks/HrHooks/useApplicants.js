import { useCallback, useEffect, useState } from "react";

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
      if (!silent) {
        setLoading(true);
      }
      try {
        setError(null);

        const data = await getApplicants(page, limit);

        setApplicants(data.data || []);
        setPagination({
          total: data.meta.total,
          totalPages: data.meta.total_pages,
        });
      } catch (error) {
        console.error("Failed to load applicants:", error);
        setError(error);
      } finally {
        setLoading(false);
      }
    },
    [page, limit],
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
      try {
        setLoading(true);
        setError(null);

        const data = await searchApplicants({
          page: pageNumber,
          limit,
          filters: currentFilters,
        });

        setApplicants(data.data || []);

        setSearchPagination({
          total: data.meta?.total || 0,
          totalPages: data.meta?.total_pages || 0,
        });

        return data;
      } catch (error) {
        console.error("Failed to search applicants:", error);

        setError(error);

        return null;
      } finally {
        setLoading(false);
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
  const applyFilters = useCallback(async () => {
    const newFilters = { ...filters };

    // نبدأ نتائج البحث من الصفحة الأولى
    setSearchPage(1);

    // نحفظ الفلاتر التي تم تطبيقها
    setAppliedFilters(newFilters);

    // ننتقل إلى search mode
    setIsSearchMode(true);

    // تنفيذ البحث مباشرة
    await performSearch(1, newFilters);
  }, [filters, performSearch]);

  // =============================
  const changeSearchPage = useCallback(
    async (newPage) => {
      setSearchPage(newPage);

      await performSearch(newPage, appliedFilters);
    },
    [appliedFilters, performSearch],
  );

  // ==============================
  // Search applicants
  // ==============================
  const search = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);

      const data = await searchApplicants(page, limit, ...appliedFilters);

      setApplicants(data || []);

      return data;
    } catch (error) {
      console.error("Failed to search applicants:", error);
      setError(error);
      return [];
    } finally {
      setLoading(false);
    }
  }, [appliedFilters]);

  // ==============================
  // Load applicants when hook mounts
  // ==============================
  useEffect(() => {
    loadApplicants();
  }, [loadApplicants]);

  //   ==============================
  // Update filters
  // ==============================
  const updateFilter = useCallback((key, value) => {
    setFilters((prev) => ({
      ...prev,
      [key]: value,
    }));
  }, []);

  // const applyFilters = useCallback(() => {
  //   setPage(1);
  //   setAppliedFilters(filters);
  // }, [filters]);

  // ==============================
  // Reset filters
  // ==============================
  const resetFilters = useCallback(() => {
    setFilters(EMPTY_FILTERS);
    setAppliedFilters(EMPTY_FILTERS);

    setSearchPage(1);
    setSearchPagination({
      total: 0,
      totalPages: 0,
    });
    loadApplicants();

    setIsSearchMode(false);

    // العودة إلى أول صفحة للقائمة العادية
    setPage(1);
  }, []);
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
    setPage,

    // Search
    isSearchMode,

    // Optional helper
    clearApplicant: () => setApplicant(null),
  };
}
