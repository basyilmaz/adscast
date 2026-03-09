"use client";

import { useEffect, useState } from "react";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";

type Approval = {
  id: string;
  status: string;
  approvable_type: string;
  approvable_id: string;
  submitted_at: string | null;
  approved_at: string | null;
  rejected_at: string | null;
};

type ApprovalResponse = {
  data: {
    data: Approval[];
  };
};

export default function ApprovalsPage() {
  const [items, setItems] = useState<Approval[]>([]);
  const [error, setError] = useState<string | null>(null);

  const load = async () => {
    try {
      const response = await apiRequest<ApprovalResponse>("/approvals", {
        requireWorkspace: true,
      });
      setItems(response.data.data ?? []);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Approval listesi alinamadi.");
    }
  };

  useEffect(() => {
    const timer = setTimeout(() => {
      void load();
    }, 0);

    return () => clearTimeout(timer);
  }, []);

  const callAction = async (approvalId: string, action: "approve" | "reject" | "publish") => {
    try {
      if (action === "reject") {
        const reason = window.prompt("Reddetme nedeni");
        if (!reason) return;
        await apiRequest(`/approvals/${approvalId}/reject`, {
          method: "POST",
          requireWorkspace: true,
          body: { reason },
        });
      } else {
        await apiRequest(`/approvals/${approvalId}/${action}`, {
          method: "POST",
          requireWorkspace: true,
        });
      }

      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Approval aksiyonu basarisiz.");
    }
  };

  return (
    <Card>
      {error ? <p className="mb-3 text-sm text-[var(--danger)]">{error}</p> : null}
      <div className="space-y-2">
        {items.map((item) => (
          <div key={item.id} className="rounded-md border border-[var(--border)] p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <p className="font-semibold">{item.approvable_type.split("\\").pop()}</p>
                <p className="text-xs muted-text">{item.approvable_id}</p>
              </div>
              <Badge
                label={item.status}
                variant={
                  item.status === "approved"
                    ? "success"
                    : item.status === "rejected" || item.status === "publish_failed"
                      ? "danger"
                      : "warning"
                }
              />
            </div>
            <div className="mt-3 flex flex-wrap gap-2">
              <Button variant="secondary" size="sm" onClick={() => callAction(item.id, "approve")}>
                Onayla
              </Button>
              <Button variant="outline" size="sm" onClick={() => callAction(item.id, "reject")}>
                Reddet
              </Button>
              <Button size="sm" onClick={() => callAction(item.id, "publish")}>
                Publish Dene
              </Button>
            </div>
          </div>
        ))}
      </div>

      {items.length === 0 ? <p className="text-sm muted-text">Onay kaydi bulunmuyor.</p> : null}
    </Card>
  );
}
