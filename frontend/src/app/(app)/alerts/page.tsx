"use client";

import { useState } from "react";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { invalidateApiCache } from "@/lib/api-cache";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { useApiQuery } from "@/hooks/use-api-query";

type AlertItem = {
  id: string;
  code: string;
  severity: string;
  summary: string;
  status: string;
  date_detected: string;
  recommended_action: string | null;
};

type AlertResponse = {
  data: {
    data: AlertItem[];
  };
};

export default function AlertsPage() {
  const [loading, setLoading] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const alertQuery = useApiQuery<AlertResponse, AlertItem[]>("/alerts", {
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.alerts,
    select: (response) => response.data.data ?? [],
  });
  const items = alertQuery.data ?? [];
  const { error: queryError, isLoading, reload } = alertQuery;

  const evaluateRules = async () => {
    setLoading(true);
    setActionError(null);
    try {
      await apiRequest("/alerts/evaluate", {
        method: "POST",
        requireWorkspace: true,
      });
      invalidateApiCache("/alerts", { requireWorkspace: true });
      invalidateApiCache("/dashboard/overview", { requireWorkspace: true });
      invalidateApiCache("/recommendations", { requireWorkspace: true });
      await reload();
    } catch (err) {
      setActionError(err instanceof Error ? err.message : "Rules engine calistirilamadi.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <Card>
      <div className="mb-4 flex items-center justify-between">
        <p className="text-sm muted-text">Deterministic rules engine ciktilari</p>
        <Button variant="secondary" onClick={evaluateRules} disabled={loading}>
          {loading ? "Calisiyor..." : "Rules Engine Calistir"}
        </Button>
      </div>

      {queryError || actionError ? <p className="mb-4 text-sm text-[var(--danger)]">{actionError ?? queryError}</p> : null}
      {isLoading && items.length === 0 ? <p className="mb-4 text-sm muted-text">Uyarilar yukleniyor.</p> : null}

      <div className="space-y-2">
        {items.map((item) => (
          <div key={item.id} className="rounded-md border border-[var(--border)] p-3">
            <div className="mb-2 flex flex-wrap items-center gap-2">
              <Badge
                label={item.severity}
                variant={
                  item.severity === "high"
                    ? "danger"
                    : item.severity === "medium"
                      ? "warning"
                      : "success"
                }
              />
              <Badge label={item.status} variant="neutral" />
              <span className="text-xs muted-text">{item.date_detected}</span>
            </div>
            <p className="font-semibold">{item.summary}</p>
            <p className="text-sm muted-text">{item.recommended_action ?? "-"}</p>
          </div>
        ))}
      </div>

      {!isLoading && items.length === 0 ? <p className="text-sm muted-text">Henuz alert yok.</p> : null}
    </Card>
  );
}
