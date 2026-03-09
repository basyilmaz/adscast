"use client";

import { useEffect, useState } from "react";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";

type Recommendation = {
  id: string;
  summary: string;
  details: string | null;
  priority: string;
  status: string;
  source: string;
  generated_at: string;
};

type RecommendationResponse = {
  data: {
    data: Recommendation[];
  };
};

export default function RecommendationsPage() {
  const [items, setItems] = useState<Recommendation[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const load = async () => {
    try {
      const response = await apiRequest<RecommendationResponse>("/recommendations", {
        requireWorkspace: true,
      });
      setItems(response.data.data ?? []);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Oneriler alinamadi.");
    }
  };

  const generate = async () => {
    setLoading(true);
    setError(null);
    try {
      await apiRequest("/recommendations/generate", {
        method: "POST",
        requireWorkspace: true,
      });
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : "AI onerisi olusturulamadi.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  return (
    <Card>
      <div className="mb-4 flex items-center justify-between">
        <p className="text-sm muted-text">Rules + AI pipeline onerileri</p>
        <Button onClick={generate} disabled={loading}>
          {loading ? "Olusturuluyor..." : "AI Onerisi Uret"}
        </Button>
      </div>

      {error ? <p className="mb-4 text-sm text-[var(--danger)]">{error}</p> : null}

      <div className="space-y-3">
        {items.map((item) => (
          <div key={item.id} className="rounded-md border border-[var(--border)] p-3">
            <div className="mb-2 flex items-center gap-2">
              <Badge label={item.source} variant="neutral" />
              <Badge
                label={item.priority}
                variant={
                  item.priority === "high"
                    ? "danger"
                    : item.priority === "medium"
                      ? "warning"
                      : "success"
                }
              />
            </div>
            <p className="font-semibold">{item.summary}</p>
            <p className="text-sm muted-text">{item.details ?? "-"}</p>
            <p className="mt-2 text-xs muted-text">{item.generated_at}</p>
          </div>
        ))}
      </div>
    </Card>
  );
}
