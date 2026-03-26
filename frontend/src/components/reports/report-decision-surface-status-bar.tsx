"use client";

import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { ReportDecisionSurfaceKey, ReportDecisionSurfaceStatusItem } from "@/lib/types";

type Props = {
  entityType: "account" | "campaign";
  entityId: string;
  surfaceKey: ReportDecisionSurfaceKey;
  statusItem: ReportDecisionSurfaceStatusItem;
  onChanged?: () => Promise<void> | void;
};

const STATUS_OPTIONS = [
  { value: "pending", label: "Beklemede" },
  { value: "reviewed", label: "Gozden Gecirildi" },
  { value: "completed", label: "Tamamlandi" },
  { value: "deferred", label: "Ertelendi" },
] as const;

export function ReportDecisionSurfaceStatusBar({
  entityType,
  entityId,
  surfaceKey,
  statusItem,
  onChanged,
}: Props) {
  const [activeStatus, setActiveStatus] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const handleChange = async (nextStatus: string) => {
    if (statusItem.status === nextStatus) {
      return;
    }

    setActiveStatus(nextStatus);
    setError(null);

    try {
      await apiRequest(`/reports/decision-surface-statuses/${entityType}/${entityId}/${surfaceKey}`, {
        method: "PUT",
        requireWorkspace: true,
        body: {
          status: nextStatus,
        },
      });

      await onChanged?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Yuzey durumu kaydedilemedi.");
    } finally {
      setActiveStatus(null);
    }
  };

  return (
    <div className="mb-3 rounded-lg border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3">
      <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <p className="text-sm font-semibold">Takip Durumu</p>
            <Badge label={statusItem.status_label} variant={variantForStatus(statusItem.status)} />
          </div>
          <p className="mt-1 text-xs muted-text">
            {statusItem.updated_at
              ? `${statusItem.updated_by_name ?? "Operator"} / ${statusItem.updated_at}`
              : "Bu yuzey icin operator durumu henuz isaretlenmedi."}
          </p>
        </div>

        <div className="flex flex-wrap gap-2">
          {STATUS_OPTIONS.map((option) => (
            <Button
              key={option.value}
              type="button"
              size="sm"
              variant={statusItem.status === option.value ? "primary" : "secondary"}
              disabled={activeStatus !== null}
              onClick={() => void handleChange(option.value)}
            >
              {activeStatus === option.value ? "Kaydediliyor..." : option.label}
            </Button>
          ))}
        </div>
      </div>

      {error ? <p className="mt-3 text-sm text-[var(--danger)]">{error}</p> : null}
    </div>
  );
}

function variantForStatus(status: string) {
  if (status === "completed") return "success" as const;
  if (status === "reviewed") return "neutral" as const;
  if (status === "deferred") return "warning" as const;

  return "neutral" as const;
}
