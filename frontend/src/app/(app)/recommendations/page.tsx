"use client";

import { useState } from "react";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { invalidateApiCache } from "@/lib/api-cache";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { useApiQuery } from "@/hooks/use-api-query";

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
  const [loading, setLoading] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const recommendationQuery = useApiQuery<RecommendationResponse, Recommendation[]>("/recommendations", {
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.recommendations,
    select: (response) => response.data.data ?? [],
  });
  const items = recommendationQuery.data ?? [];
  const { error: queryError, isLoading, reload } = recommendationQuery;

  const generate = async () => {
    setLoading(true);
    setActionError(null);
    try {
      await apiRequest("/recommendations/generate", {
        method: "POST",
        requireWorkspace: true,
      });
      invalidateApiCache("/recommendations", { requireWorkspace: true });
      invalidateApiCache("/dashboard/overview", { requireWorkspace: true });
      await reload();
    } catch (err) {
      setActionError(err instanceof Error ? err.message : "AI onerisi olusturulamadi.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <Card>
      <div className="mb-4 flex items-center justify-between">
        <p className="text-sm muted-text">Rules + AI pipeline onerileri</p>
        <Button onClick={generate} disabled={loading}>
          {loading ? "Olusturuluyor..." : "AI Onerisi Uret"}
        </Button>
      </div>

      {queryError || actionError ? <p className="mb-4 text-sm text-[var(--danger)]">{actionError ?? queryError}</p> : null}
      {isLoading && items.length === 0 ? <p className="mb-4 text-sm muted-text">Oneriler yukleniyor.</p> : null}

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
      {!isLoading && items.length === 0 ? <p className="mt-3 text-sm muted-text">Henuz oneriler bulunmuyor.</p> : null}
    </Card>
  );
}
