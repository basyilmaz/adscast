"use client";

import { useEffect, useState } from "react";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { useApiQuery } from "@/hooks/use-api-query";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { getWorkspaceId, setWorkspaceId } from "@/lib/session";
import { WORKSPACE_CHANGED_EVENT } from "@/lib/session-constants";
import { Workspace } from "@/lib/types";

type Response = {
  data: Workspace[];
};

export default function WorkspacesPage() {
  const [current, setCurrent] = useState<string | null>(() => getWorkspaceId());
  const workspaceQuery = useApiQuery<Response, Workspace[]>("/workspaces", {
    ttlMs: QUERY_TTLS.workspaces,
    select: (response) => response.data ?? [],
  });
  const items = workspaceQuery.data ?? [];
  const { error, isLoading } = workspaceQuery;

  useEffect(() => {
    const syncCurrent = () => {
      setCurrent(getWorkspaceId());
    };

    window.addEventListener(WORKSPACE_CHANGED_EVENT, syncCurrent as EventListener);

    return () => {
      window.removeEventListener(WORKSPACE_CHANGED_EVENT, syncCurrent as EventListener);
    };
  }, []);

  return (
    <Card>
      {error ? <p className="mb-3 text-sm text-[var(--danger)]">{error}</p> : null}
      <div className="space-y-2">
        {items.map((workspace) => (
          <div key={workspace.id} className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-[var(--border)] p-3">
            <div>
              <p className="font-semibold">{workspace.name}</p>
              <p className="text-xs muted-text">
                {workspace.slug} | {workspace.timezone} | {workspace.currency}
              </p>
            </div>
            <Button
              variant={current === workspace.id ? "secondary" : "outline"}
              onClick={() => {
                setWorkspaceId(workspace.id);
                setCurrent(workspace.id);
              }}
            >
              {current === workspace.id ? "Secili" : "Bu Workspace'i Sec"}
            </Button>
          </div>
        ))}
      </div>
      {isLoading && items.length === 0 ? <p className="mt-3 text-sm muted-text">Workspace listesi yukleniyor.</p> : null}
    </Card>
  );
}
