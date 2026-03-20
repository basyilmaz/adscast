"use client";

import { useEffect, useState } from "react";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { prefetchApiResponse } from "@/lib/api-cache";
import { getToken, getWorkspaceId } from "@/lib/session";
import { WORKSPACE_CHANGED_EVENT } from "@/lib/session-constants";

export function AppRoutePrefetcher() {
  const [workspaceId, setWorkspaceId] = useState<string | null>(() => getWorkspaceId());

  useEffect(() => {
    const syncWorkspace = () => {
      setWorkspaceId(getWorkspaceId());
    };

    window.addEventListener(WORKSPACE_CHANGED_EVENT, syncWorkspace as EventListener);

    return () => {
      window.removeEventListener(WORKSPACE_CHANGED_EVENT, syncWorkspace as EventListener);
    };
  }, []);

  useEffect(() => {
    const token = getToken();

    if (!token || !workspaceId) {
      return;
    }

    const runPrefetch = () => {
      void prefetchApiResponse("/workspaces", {}, { ttlMs: QUERY_TTLS.workspaces });
      void prefetchApiResponse(
        "/dashboard/overview",
        { requireWorkspace: true },
        { ttlMs: QUERY_TTLS.dashboard },
      );
      void prefetchApiResponse(
        "/campaigns",
        { requireWorkspace: true },
        { ttlMs: QUERY_TTLS.campaigns },
      );
      void prefetchApiResponse(
        "/meta/ad-accounts",
        { requireWorkspace: true },
        { ttlMs: QUERY_TTLS.adAccounts },
      );
      void prefetchApiResponse(
        "/alerts",
        { requireWorkspace: true },
        { ttlMs: QUERY_TTLS.alerts },
      );
      void prefetchApiResponse(
        "/recommendations",
        { requireWorkspace: true },
        { ttlMs: QUERY_TTLS.recommendations },
      );
      void prefetchApiResponse(
        "/approvals",
        { requireWorkspace: true },
        { ttlMs: QUERY_TTLS.approvals },
      );
      void prefetchApiResponse(
        "/audit-logs",
        { requireWorkspace: true },
        { ttlMs: QUERY_TTLS.auditLogs },
      );
      void prefetchApiResponse(
        "/meta/connections",
        { requireWorkspace: true },
        { ttlMs: QUERY_TTLS.metaConnections },
      );
      void prefetchApiResponse(
        "/meta/connector-status",
        { requireWorkspace: true },
        { ttlMs: QUERY_TTLS.metaConnectorStatus },
      );
    };

    if (typeof window !== "undefined" && "requestIdleCallback" in window) {
      const callbackId = window.requestIdleCallback(runPrefetch, { timeout: 1200 });

      return () => {
        window.cancelIdleCallback(callbackId);
      };
    }

    const timeout = setTimeout(runPrefetch, 250);

    return () => {
      clearTimeout(timeout);
    };
  }, [workspaceId]);

  return null;
}
