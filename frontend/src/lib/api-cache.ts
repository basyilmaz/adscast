"use client";

import { apiRequest, type RequestOptions } from "./api";
import { WORKSPACE_KEY } from "./session-constants";

type CacheEntry<T> = {
  value: T;
  expiresAt: number;
  storedAt: number;
};

type CachedValue<T> = {
  value: T | null;
  isFresh: boolean;
};

type ApiCacheOptions = {
  ttlMs?: number;
  persist?: boolean;
};

const CACHE_PREFIX = "adscast_api_cache:";
const memoryCache = new Map<string, CacheEntry<unknown>>();
const inflightRequests = new Map<string, Promise<unknown>>();

function normalizePath(path: string): string {
  return path.startsWith("/") ? path : `/${path}`;
}

function getWorkspaceScope(requireWorkspace?: boolean): string {
  if (!requireWorkspace) {
    return "global";
  }

  if (typeof window === "undefined") {
    return "workspace:unknown";
  }

  const workspaceId = window.localStorage.getItem(WORKSPACE_KEY);

  return `workspace:${workspaceId ?? "missing"}`;
}

export function buildApiCacheKey(path: string, requestOptions: RequestOptions = {}): string {
  const method = requestOptions.method ?? "GET";

  return `${getWorkspaceScope(requestOptions.requireWorkspace)}:${method}:${normalizePath(path)}`;
}

function getStorageKey(cacheKey: string): string {
  return `${CACHE_PREFIX}${cacheKey}`;
}

function readPersistedCache<T>(cacheKey: string): CacheEntry<T> | null {
  if (typeof window === "undefined") {
    return null;
  }

  const raw = window.sessionStorage.getItem(getStorageKey(cacheKey));

  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw) as CacheEntry<T>;
  } catch {
    window.sessionStorage.removeItem(getStorageKey(cacheKey));

    return null;
  }
}

function writePersistedCache<T>(cacheKey: string, entry: CacheEntry<T>) {
  if (typeof window === "undefined") {
    return;
  }

  window.sessionStorage.setItem(getStorageKey(cacheKey), JSON.stringify(entry));
}

function removePersistedCache(cacheKey: string) {
  if (typeof window === "undefined") {
    return;
  }

  window.sessionStorage.removeItem(getStorageKey(cacheKey));
}

function isEntryFresh(entry: CacheEntry<unknown>): boolean {
  return entry.expiresAt > Date.now();
}

function cacheResponse<T>(
  cacheKey: string,
  response: T,
  options: ApiCacheOptions,
) {
  const ttlMs = options.ttlMs ?? 0;
  const entry: CacheEntry<T> = {
    value: response,
    storedAt: Date.now(),
    expiresAt: Date.now() + ttlMs,
  };

  memoryCache.set(cacheKey, entry);

  if (options.persist !== false) {
    writePersistedCache(cacheKey, entry);
  }
}

export function getCachedApiResponse<T>(
  path: string,
  requestOptions: RequestOptions = {},
): CachedValue<T> {
  const cacheKey = buildApiCacheKey(path, requestOptions);
  const memoryEntry = memoryCache.get(cacheKey) as CacheEntry<T> | undefined;
  const entry = memoryEntry ?? readPersistedCache<T>(cacheKey);

  if (!entry) {
    return {
      value: null,
      isFresh: false,
    };
  }

  if (!memoryEntry) {
    memoryCache.set(cacheKey, entry);
  }

  return {
    value: entry.value,
    isFresh: isEntryFresh(entry),
  };
}

export async function fetchCachedApiResponse<T>(
  path: string,
  requestOptions: RequestOptions = {},
  options: ApiCacheOptions = {},
  force = false,
): Promise<T> {
  const normalizedPath = normalizePath(path);
  const cacheKey = buildApiCacheKey(normalizedPath, requestOptions);

  if (!force) {
    const cached = getCachedApiResponse<T>(normalizedPath, requestOptions);

    if (cached.value && cached.isFresh) {
      return cached.value;
    }
  }

  const inflight = inflightRequests.get(cacheKey) as Promise<T> | undefined;
  if (!force && inflight) {
    return inflight;
  }

  const requestPromise = apiRequest<T>(normalizedPath, requestOptions)
    .then((response) => {
      cacheResponse(cacheKey, response, options);

      return response;
    })
    .finally(() => {
      inflightRequests.delete(cacheKey);
    });

  inflightRequests.set(cacheKey, requestPromise);

  return requestPromise;
}

export function prefetchApiResponse<T>(
  path: string,
  requestOptions: RequestOptions = {},
  options: ApiCacheOptions = {},
) {
  return fetchCachedApiResponse<T>(path, requestOptions, options).catch(() => null);
}

export function invalidateApiCache(path: string, requestOptions: RequestOptions = {}) {
  const cacheKey = buildApiCacheKey(path, requestOptions);
  memoryCache.delete(cacheKey);
  inflightRequests.delete(cacheKey);
  removePersistedCache(cacheKey);
}

export function clearAllApiCache() {
  memoryCache.clear();
  inflightRequests.clear();

  if (typeof window === "undefined") {
    return;
  }

  const keys = Object.keys(window.sessionStorage);
  for (const key of keys) {
    if (key.startsWith(CACHE_PREFIX)) {
      window.sessionStorage.removeItem(key);
    }
  }
}
