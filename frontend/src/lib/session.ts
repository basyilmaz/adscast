"use client";

import { clearAllApiCache } from "./api-cache";
import {
  META_OAUTH_STATE_KEY,
  TOKEN_KEY,
  WORKSPACE_CHANGED_EVENT,
  WORKSPACE_KEY,
} from "./session-constants";

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
  clearAllApiCache();
}

export function getWorkspaceId(): string | null {
  if (typeof window === "undefined") return null;
  return localStorage.getItem(WORKSPACE_KEY);
}

export function setWorkspaceId(workspaceId: string) {
  if (typeof window === "undefined") return;
  localStorage.setItem(WORKSPACE_KEY, workspaceId);
  window.dispatchEvent(
    new CustomEvent(WORKSPACE_CHANGED_EVENT, {
      detail: {
        workspaceId,
      },
    }),
  );
}

export function getMetaOAuthState(): string | null {
  if (typeof window === "undefined") return null;
  return sessionStorage.getItem(META_OAUTH_STATE_KEY);
}

export function setMetaOAuthState(state: string) {
  if (typeof window === "undefined") return;
  sessionStorage.setItem(META_OAUTH_STATE_KEY, state);
}

export function clearMetaOAuthState() {
  if (typeof window === "undefined") return;
  sessionStorage.removeItem(META_OAUTH_STATE_KEY);
}
