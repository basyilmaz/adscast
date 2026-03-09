"use client";

import { useEffect, useState } from "react";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { getWorkspaceId, setWorkspaceId } from "@/lib/session";
import { Workspace } from "@/lib/types";

type Response = {
  data: Workspace[];
};

export default function WorkspacesPage() {
  const [items, setItems] = useState<Workspace[]>([]);
  const [current, setCurrent] = useState<string | null>(() => getWorkspaceId());
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const load = async () => {
      try {
        const response = await apiRequest<Response>("/workspaces");
        setItems(response.data ?? []);
      } catch (err) {
        setError(err instanceof Error ? err.message : "Workspace listesi alinamadi.");
      }
    };
    load();
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
    </Card>
  );
}
