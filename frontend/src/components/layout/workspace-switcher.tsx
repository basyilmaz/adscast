"use client";

import { useEffect, useMemo, useState } from "react";
import { apiRequest } from "@/lib/api";
import { getWorkspaceId, setWorkspaceId } from "@/lib/session";
import { Workspace } from "@/lib/types";

type WorkspaceResponse = {
  data: Workspace[];
};

export function WorkspaceSwitcher() {
  const [workspaces, setWorkspaces] = useState<Workspace[]>([]);
  const [selected, setSelected] = useState<string>("");
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadWorkspaces = async () => {
      try {
        const response = await apiRequest<WorkspaceResponse>("/workspaces");
        const items = response.data ?? [];
        setWorkspaces(items);

        if (items.length === 0) return;

        const current = getWorkspaceId();
        const fallback = current && items.some((w) => w.id === current) ? current : items[0].id;
        setSelected(fallback);
        setWorkspaceId(fallback);
      } catch (err) {
        const message = err instanceof Error ? err.message : "Workspace listesi alinamadi.";
        setError(message);
      }
    };

    loadWorkspaces();
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
          <option value="">{error ? "Yuklenemedi" : "Yukleniyor..."}</option>
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
