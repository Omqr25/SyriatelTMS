import { useCallback, useEffect, useState } from "react";
import {
  getSupervisorsApi,
  createSupervisorsApi,
} from "../../Api/HrApi/SupervisorsApi";
import { fireTraineeApi, getAllTraineesApi } from "../../Api/HrApi/traineesApi";
const EMPTY_FILTERS = {
  search: "",
};

export default function useTrainees() {
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
    async (silent = false, currentFilters = {}) => {
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

        const data = await getAllTraineesApi({});
        console.log("TRAINEES RESULT:",data)
        setTrainees(data.data || []);
        
        if (data.meta) {
          setPagination({
            total: data.meta.total || 0,
            totalPages: data.meta.total_pages || 0,
          });
        }
      } catch (error) {
        const message =
          error.response?.data?.message || error.message || "error";
        console.error("Error fetching trainees:", message);
        setError(error);
        throw error;
      } finally {
        if (silent) {
          setRefreshing(false);
        } else {
          setLoading(false);
        }
      }
    },
    [page, limit, isSearchMode, searchPage]
  );

  const performSearch = useCallback(
  async (pageNumber, currentFilters) => {
    try {
      setLoading(true);
      setError(null);

      const params = {
        page: pageNumber,
        limit,
        ...currentFilters,
      };

      const data = await getAllTraineesApi(params);
      setTrainees(data.data || []);

      setSearchPagination({
        total: data.meta?.total || 0,
        totalPages: data.meta?.total_pages || 0,
      });

      return data;
    } catch (error) {
      console.error("Failed to search trainees:", error);
      setError(error);
      return null;
    } finally {
      setLoading(false);
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
  }, []);

  const applyFilters = useCallback(async () => {
  const newFilters = { ...filters };
  setSearchPage(1);
  setAppliedFilters(newFilters);
  setIsSearchMode(true);
  await performSearch(1, newFilters);
}, [filters, performSearch]);

const changeSearchPage = useCallback(
  async (newPage) => {
    setSearchPage(newPage);
    await performSearch(newPage, appliedFilters);
  },
  [appliedFilters, performSearch]
);

const updateFilter = useCallback((key, value) => {
  setFilters((prev) => ({
    ...prev,
    [key]: value,
  }));
}, []);

const resetFilters = useCallback(() => {
  setFilters(EMPTY_FILTERS);
  setAppliedFilters(EMPTY_FILTERS);
  setSearchPage(1);
  setSearchPagination({
    total: 0,
    totalPages: 0,
  });
  setIsSearchMode(false);
  setPage(1);
  getTrainees(false, EMPTY_FILTERS);
}, [getTrainees]);

  useEffect(() => {
    if (!isSearchMode) {
      getTrainees();
    }
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
    setPage,
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
