"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import type { Dispatch, SetStateAction } from "react";
import type { RequestOptions } from "@/lib/api";
import { fetchCachedApiResponse, getCachedApiResponse } from "@/lib/api-cache";
import { WORKSPACE_CHANGED_EVENT, WORKSPACE_KEY } from "@/lib/session-constants";

type UseApiQueryOptions<TResponse, TData> = {
  requestOptions?: RequestOptions;
  ttlMs?: number;
  persist?: boolean;
  enabled?: boolean;
  initialData?: TData | null;
  select?: (response: TResponse) => TData;
};

type UseApiQueryResult<TData> = {
  data: TData | null;
  error: string | null;
  isLoading: boolean;
  isRefreshing: boolean;
  reload: () => Promise<void>;
  setData: Dispatch<SetStateAction<TData | null>>;
};

export function useApiQuery<TResponse, TData = TResponse>(
  path: string,
  options: UseApiQueryOptions<TResponse, TData> = {},
): UseApiQueryResult<TData> {
  const {
    requestOptions,
    ttlMs = 30 * 1000,
    persist = true,
    enabled = true,
    initialData = null,
    select,
  } = options;

  const selectRef = useRef<(response: TResponse) => TData>(
    select ?? ((response) => response as unknown as TData),
  );
  const requestKey = JSON.stringify(requestOptions ?? {});
  const normalizedRequestOptions = useMemo<RequestOptions | undefined>(
    () => (requestKey === "{}" ? undefined : (JSON.parse(requestKey) as RequestOptions)),
    [requestKey],
  );
  const requireWorkspace = normalizedRequestOptions?.requireWorkspace === true;
  const [workspaceScope, setWorkspaceScope] = useState(() => {
    if (!requireWorkspace || typeof window === "undefined") {
      return "global";
    }

    return window.localStorage.getItem(WORKSPACE_KEY) ?? "missing";
  });

  useEffect(() => {
    selectRef.current = select ?? ((response) => response as unknown as TData);
  }, [select]);

  useEffect(() => {
    if (!requireWorkspace || typeof window === "undefined") {
      return;
    }

    const syncWorkspace = () => {
      setWorkspaceScope(window.localStorage.getItem(WORKSPACE_KEY) ?? "missing");
    };

    syncWorkspace();
    window.addEventListener(WORKSPACE_CHANGED_EVENT, syncWorkspace as EventListener);

    return () => {
      window.removeEventListener(WORKSPACE_CHANGED_EVENT, syncWorkspace as EventListener);
    };
  }, [requireWorkspace]);

  const getInitialData = useCallback(() => {
    if (!enabled) {
      return initialData;
    }

    const cached = getCachedApiResponse<TResponse>(path, normalizedRequestOptions);

    if (cached.value) {
      return selectRef.current(cached.value);
    }

    return initialData;
  }, [enabled, initialData, normalizedRequestOptions, path]);

  const [data, setData] = useState<TData | null>(getInitialData);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(() => getInitialData() === null && enabled);
  const [isRefreshing, setIsRefreshing] = useState(false);

  const runFetch = useCallback(
    async (force = false) => {
      if (!enabled) {
        return;
      }

      if (data === null || force) {
        setIsLoading(true);
      } else {
        setIsRefreshing(true);
      }

      try {
        const response = await fetchCachedApiResponse<TResponse>(
          path,
          normalizedRequestOptions,
          {
            ttlMs,
            persist,
          },
          force,
        );

        setData(selectRef.current(response));
        setError(null);
      } catch (err) {
        setError(err instanceof Error ? err.message : "Veri alinamadi.");
      } finally {
        setIsLoading(false);
        setIsRefreshing(false);
      }
    },
    [data, enabled, normalizedRequestOptions, path, persist, ttlMs],
  );

  useEffect(() => {
    if (!enabled) {
      setData(initialData);
      setIsLoading(false);
      setIsRefreshing(false);

      return;
    }

    const cached = getCachedApiResponse<TResponse>(path, normalizedRequestOptions);

    if (cached.value) {
      setData(selectRef.current(cached.value));
      setIsLoading(false);
      setError(null);

      if (cached.isFresh) {
        setIsRefreshing(false);

        return;
      }

      setIsRefreshing(true);
    } else {
      setIsLoading(true);
      setIsRefreshing(false);
    }

    void runFetch(false);
  }, [enabled, initialData, normalizedRequestOptions, path, runFetch, workspaceScope]);

  const reload = useCallback(async () => {
    await runFetch(true);
  }, [runFetch]);

  return {
    data,
    error,
    isLoading,
    isRefreshing,
    reload,
    setData,
  };
}
