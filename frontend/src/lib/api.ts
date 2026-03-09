"use client";

import { getToken, getWorkspaceId } from "./session";

const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "/api/v1";

type RequestOptions = {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  body?: unknown;
  requireWorkspace?: boolean;
};

export async function apiRequest<T>(
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  const normalizedPath = path.startsWith("/") ? path : `/${path}`;
  const token = getToken();
  const workspaceId = getWorkspaceId();

  const headers: HeadersInit = {
    "Content-Type": "application/json",
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  if (options.requireWorkspace && workspaceId) {
    headers["X-Workspace-Id"] = workspaceId;
  }

  const response = await fetch(`${API_BASE_URL}${normalizedPath}`, {
    method: options.method ?? "GET",
    headers,
    body: options.body ? JSON.stringify(options.body) : undefined,
    cache: "no-store",
  });

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    const message =
      (data && typeof data.message === "string" && data.message) ||
      "API istegi basarisiz.";
    throw new Error(message);
  }

  return data as T;
}
