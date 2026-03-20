"use client";

import { useEffect, useMemo, useState } from "react";
import { useApiQuery } from "@/hooks/use-api-query";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { getWorkspaceId, setWorkspaceId } from "@/lib/session";
import { WORKSPACE_CHANGED_EVENT } from "@/lib/session-constants";
import { Workspace } from "@/lib/types";

type WorkspaceResponse = {
  data: Workspace[];
};

export function WorkspaceSwitcher() {
  const [selected, setSelected] = useState<string>(() => getWorkspaceId() ?? "");
  const workspaceQuery = useApiQuery<WorkspaceResponse, Workspace[]>("/workspaces", {
    ttlMs: QUERY_TTLS.workspaces,
    select: (response) => response.data ?? [],
  });
  const workspaces = useMemo(() => workspaceQuery.data ?? [], [workspaceQuery.data]);
  const { error, isLoading } = workspaceQuery;

  useEffect(() => {
    if (workspaces.length === 0) {
      return;
    }

    const current = getWorkspaceId();
    const fallback = current && workspaces.some((workspace) => workspace.id === current)
      ? current
      : workspaces[0].id;

    if (current !== fallback) {
      setWorkspaceId(fallback);
    }
  }, [workspaces]);

  useEffect(() => {
    const syncSelected = () => {
      setSelected(getWorkspaceId() ?? "");
    };

    window.addEventListener(WORKSPACE_CHANGED_EVENT, syncSelected as EventListener);

    return () => {
      window.removeEventListener(WORKSPACE_CHANGED_EVENT, syncSelected as EventListener);
    };
  }, []);

  const selectedLabel = useMemo(() => {
    return workspaces.find((item) => item.id === selected)?.name ?? "Workspace sec";
  }, [selected, workspaces]);

  return (
    <div className="flex flex-col gap-1">
      <label className="text-xs font-semibold uppercase tracking-wide muted-text">Workspace</label>
      <select
        className="h-10 min-w-[220px] rounded-md border border-[var(--border)] bg-white px-3 text-sm"
        value={selected}
        onChange={(event) => {
          const next = event.target.value;
          setSelected(next);
          setWorkspaceId(next);
        }}
      >
        {workspaces.length === 0 && (
          <option value="">{error ? "Yuklenemedi" : isLoading ? "Yukleniyor..." : "Workspace yok"}</option>
        )}
        {workspaces.map((workspace) => (
          <option key={workspace.id} value={workspace.id}>
            {workspace.name}
          </option>
        ))}
      </select>
      <p className="text-xs muted-text">{selectedLabel}</p>
    </div>
  );
}
