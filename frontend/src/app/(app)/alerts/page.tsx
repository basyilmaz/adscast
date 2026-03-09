"use client";

import { useEffect, useState } from "react";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";

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
  const [items, setItems] = useState<AlertItem[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const loadAlerts = async () => {
    try {
      const response = await apiRequest<AlertResponse>("/alerts", {
        requireWorkspace: true,
      });
      setItems(response.data.data ?? []);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Alert listesi alinamadi.");
    }
  };

  const evaluateRules = async () => {
    setLoading(true);
    setError(null);
    try {
      await apiRequest("/alerts/evaluate", {
        method: "POST",
        requireWorkspace: true,
      });
      await loadAlerts();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Rules engine calistirilamadi.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadAlerts();
  }, []);

  return (
    <Card>
      <div className="mb-4 flex items-center justify-between">
        <p className="text-sm muted-text">Deterministic rules engine ciktilari</p>
        <Button variant="secondary" onClick={evaluateRules} disabled={loading}>
          {loading ? "Calisiyor..." : "Rules Engine Calistir"}
        </Button>
      </div>

      {error ? <p className="mb-4 text-sm text-[var(--danger)]">{error}</p> : null}

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

      {items.length === 0 ? <p className="text-sm muted-text">Henuz alert yok.</p> : null}
    </Card>
  );
}
