import useLiveFilterUpdates from "./useLiveFilterUpdates";
import { useCallback, useEffect, useRef, useState } from "react";
import {
  getSupervisorsApi,
  createSupervisorsApi,
} from "../../Api/HrApi/SupervisorsApi";
import { fireTraineeApi, getAllTraineesApi } from "../../Api/HrApi/traineesApi";
const EMPTY_FILTERS = {
  search: "",
};

export default function useTrainees() {
  const requestId = useRef(0);
  const invalidate = useCallback(() => { requestId.current += 1; }, []);

  const [trainees, setTrainees] = useState([]);
  const [loadingCreate, setLoadingCreate] = useState(false);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  //   ==============================
  // pagination state
  // =============================
  const [page, setPage] = useState(1);
  const [limit] = useState(10);
  const [pagination, setPagination] = useState({
    total: 0,
    totalPages: 0,
  });
  const [filters, setFilters] = useState(EMPTY_FILTERS);
  const [appliedFilters, setAppliedFilters] = useState(EMPTY_FILTERS);
  const [searchPage, setSearchPage] = useState(1);
  const [searchPagination, setSearchPagination] = useState({
    total: 0,
    totalPages: 0,
  });
  const [isSearchMode, setIsSearchMode] = useState(false);
  const [error, setError] = useState(null);
  const getTrainees = useCallback(
    async (silent = false, currentFilters = appliedFilters) => {
      const id = ++requestId.current;
      if (silent) {
        setRefreshing(true);
      } else {
        setLoading(true);
      }
      try {
        setError(null);
        // بناء البارامترات الخاصة بالجلب العادي أو الفلترة
        const params = {
          page: isSearchMode ? searchPage : page,
          limit,
          ...currentFilters,
        };

        const data = await getAllTraineesApi(params);
        if (id !== requestId.current) return null;
        setTrainees(data.data || []);
        
        if (data.meta) {
          const updatePagination = isSearchMode ? setSearchPagination : setPagination;
          updatePagination({
            total: data.meta.total || 0,
            totalPages: data.meta.total_pages || 0,
          });
        }
      } catch (error) {
        if (id !== requestId.current) return null;
        const message =
          error.response?.data?.message || error.message || "error";
        console.error("Error fetching trainees:", message);
        setError(error);
        throw error;
      } finally {
        if (id === requestId.current) {
          if (silent) {
            setRefreshing(false);
          } else {
            setLoading(false);
          }
        }
      }
    },
    [page, limit, isSearchMode, searchPage, appliedFilters]
  );

  const performSearch = useCallback(
    async (pageNumber, currentFilters) => {
      const id = ++requestId.current;
      try {
        setLoading(true);
        setError(null);

        const params = { page: pageNumber, limit, ...currentFilters };
        const data = await getAllTraineesApi(params);
        if (id !== requestId.current) return null;
        setTrainees(data.data || []);
        setSearchPagination({
          total: data.meta?.total || 0,
          totalPages: data.meta?.total_pages || 0,
        });
        return data;
      } catch (error) {
        if (id !== requestId.current) return null;
        console.error("Failed to search trainees:", error);
        setError(error);
        return null;
      } finally {
        if (id === requestId.current) setLoading(false);
      }
    },
    [limit]
  );

  const fireTrainee = useCallback(async (id, reason) => {
    // setLoadingCreate(true)
    try {
      const data = await fireTraineeApi(id, reason);
      console.log("csu:", data);
      await getTrainees(true);

      return data;
    } catch (error) {
      const message = error.response?.data?.message || error.message || "error";
      console.error("Error fetching programs:", message);
      throw error;
    } finally {
      // setLoadingCreate(false)
    }
  }, [getTrainees]);

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
    [appliedFilters, performSearch, filters, applyFilters]
  );

  const updateFilter = useCallback((key, value) => {
    const next = { ...filters, [key]: value };
    setFilters(next);
    schedule(key, next);
  }, [filters, schedule]);

  const resetFilters = useCallback(() => {
    cancel();
    setFilters(EMPTY_FILTERS);
    setIsSearchMode(true);
    setPage(1);
    return applySnapshot(EMPTY_FILTERS);
  }, [cancel, applySnapshot]);

  useEffect(() => {
    if (!isSearchMode) getTrainees();
  }, [getTrainees, isSearchMode]);

  return {
    // operation
    getTrainees,
    fireTrainee,
    // loading
    loading,
    loadingCreate,
    refreshing,
    // state
    trainees,
    // pagination
    page,
    limit,
    pagination,
    setPage: (newPage) => filters !== appliedFilters ? applyFilters() : setPage(newPage),
    // search & filters
    error,
    filters,
    appliedFilters,
    updateFilter,
    applyFilters,
    resetFilters,
    isSearchMode,
    searchPage,
    searchPagination,
    changeSearchPage,
  };
}
