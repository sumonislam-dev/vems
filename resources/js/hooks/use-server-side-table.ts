import { router } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';
import { SortDirection } from '@/types';

interface ServerSideTableParams {
    search?: string;
    sort?: string;
    direction?: SortDirection;
    filters?: Record<string, unknown>;
    per_page?: number;
    page?: number;
    [key: string]: unknown;
}

interface UseServerSideTableOptions {
    preserveState?: boolean;
    preserveScroll?: boolean;
    replace?: boolean;
}

export function useServerSideTable(
    currentParams: ServerSideTableParams = {},
    options: UseServerSideTableOptions = {}
) {
    const {
        preserveState = true,
        preserveScroll = true,
        replace = false,
    } = options;

    // Build URL with parameters
    const buildUrl = useCallback((params: ServerSideTableParams) => {
        const url = new URL(window.location.href);
        const searchParams = new URLSearchParams();

        // Add search parameter
        if (params.search && params.search.trim()) {
            searchParams.set('search', params.search.trim());
        }

        // Add sort parameters
        if (params.sort) {
            searchParams.set('sort', params.sort);
        }
        if (params.direction && params.direction !== 'desc') {
            searchParams.set('direction', params.direction);
        }

        // Add filter parameters
        if (params.filters) {
            Object.entries(params.filters).forEach(([key, values]) => {
                if (Array.isArray(values) && values.length > 0) {
                    values.forEach((value, index) => {
                        searchParams.set(`filters[${key}][${index}]`, String(value));
                    });
                }
            });
        }

        // Add pagination parameters
        if (params.per_page && params.per_page !== 10) {
            searchParams.set('per_page', String(params.per_page));
        }
        if (params.page && params.page !== 1) {
            searchParams.set('page', String(params.page));
        }

        url.search = searchParams.toString();
        return url.pathname + url.search;
    }, []);

    // Navigate to new URL with parameters
    const navigate = useCallback((params: Partial<ServerSideTableParams>) => {
        const newParams = { ...currentParams, ...params };

        // Reset page when search or filters change
        if (params.search !== undefined || params.filters !== undefined) {
            newParams.page = 1;
        }

        const url = buildUrl(newParams);

        router.visit(url, {
            preserveState,
            preserveScroll,
            replace,
        });
    }, [currentParams, buildUrl, preserveState, preserveScroll, replace]);

    // Search functionality
    const handleSearch = useCallback((search: string) => {
        navigate({ search });
    }, [navigate]);

    // Sorting functionality
    const handleSort = useCallback((column: string) => {
        const currentSort = currentParams.sort;
        const currentDirection = currentParams.direction || 'desc';

        let newDirection: SortDirection;

        if (currentSort === column) {
            // Cycle through: desc -> asc -> null
            newDirection = currentDirection === 'desc' ? 'asc' :
                          currentDirection === 'asc' ? null : 'desc';
        } else {
            // New column, start with desc
            newDirection = 'desc';
        }

        if (newDirection === null) {
            navigate({ sort: undefined, direction: undefined });
        } else {
            navigate({ sort: column, direction: newDirection });
        }
    }, [currentParams.sort, currentParams.direction, navigate]);

    // Filter functionality
    const handleFilter = useCallback((key: string, values: (string | number)[]) => {
        const newFilters = { ...currentParams.filters };

        if (values.length === 0) {
            delete newFilters[key];
        } else {
            newFilters[key] = values;
        }

        navigate({ filters: Object.keys(newFilters).length > 0 ? newFilters : undefined });
    }, [currentParams.filters, navigate]);

    // Clear all filters
    const clearFilters = useCallback(() => {
        navigate({ filters: undefined });
    }, [navigate]);

    // Clear search
    const clearSearch = useCallback(() => {
        navigate({ search: undefined });
    }, [navigate]);

    // Pagination
    const handlePageChange = useCallback((page: number) => {
        navigate({ page });
    }, [navigate]);

    const handlePerPageChange = useCallback((perPage: number) => {
        navigate({ per_page: perPage, page: 1 });
    }, [navigate]);

    // Get current sort direction for a column
    const getSortDirection = useCallback((column: string): SortDirection => {
        return currentParams.sort === column ? (currentParams.direction || 'desc') : null;
    }, [currentParams.sort, currentParams.direction]);

    // Get current filter values for a key
    const getFilterValues = useCallback((key: string): (string | number)[] => {
        return (currentParams.filters?.[key] as (string | number)[] | undefined) || [];
    }, [currentParams.filters]);

    // Check if any filters are active
    const hasActiveFilters = useMemo(() => {
        return currentParams.filters && Object.keys(currentParams.filters).length > 0;
    }, [currentParams.filters]);

    // Check if search is active
    const isSearching = useMemo(() => {
        return Boolean(currentParams.search && currentParams.search.trim());
    }, [currentParams.search]);

    return {
        // Current state
        currentParams,
        isSearching,
        hasActiveFilters,

        // Actions
        handleSearch,
        handleSort,
        handleFilter,
        handlePageChange,
        handlePerPageChange,
        clearFilters,
        clearSearch,

        // Getters
        getSortDirection,
        getFilterValues,

        // Utils
        navigate,
        buildUrl,
    };
}
