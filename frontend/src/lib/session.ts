"use client";

const TOKEN_KEY = "adscast_token";
const WORKSPACE_KEY = "adscast_workspace_id";

export function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string) {
  if (typeof window === "undefined") return;
  localStorage.setItem(TOKEN_KEY, token);
}

export function clearSession() {
  if (typeof window === "undefined") return;
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(WORKSPACE_KEY);
}

export function getWorkspaceId(): string | null {
  if (typeof window === "undefined") return null;
  return localStorage.getItem(WORKSPACE_KEY);
}

export function setWorkspaceId(workspaceId: string) {
  if (typeof window === "undefined") return;
  localStorage.setItem(WORKSPACE_KEY, workspaceId);
}
