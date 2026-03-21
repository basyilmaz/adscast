"use client";

import { useState } from "react";
import { useSearchParams } from "next/navigation";
import { ClientReportCanvas } from "@/components/reports/client-report-canvas";
import { apiRequest, downloadApiFile } from "@/lib/api";
import { invalidateApiCache } from "@/lib/api-cache";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { useApiQuery } from "@/hooks/use-api-query";
import { ClientReportResponse } from "@/lib/types";

export function CampaignReportClient() {
  const searchParams = useSearchParams();
  const campaignId = searchParams.get("id");
  const [snapshotLoading, setSnapshotLoading] = useState(false);
  const [snapshotMessage, setSnapshotMessage] = useState<string | null>(null);
  const { data, error, isLoading, reload } = useApiQuery<ClientReportResponse, ClientReportResponse["data"]>(
    `/reports/campaign/${campaignId ?? ""}`,
    {
      enabled: Boolean(campaignId),
      requestOptions: {
        requireWorkspace: true,
      },
      ttlMs: QUERY_TTLS.campaignReport,
      select: (response) => response.data,
    },
  );

  const saveSnapshot = async () => {
    if (!campaignId || !data) return;
    setSnapshotLoading(true);
    setSnapshotMessage(null);
    try {
      await apiRequest("/reports/snapshots", {
        method: "POST",
        requireWorkspace: true,
        body: {
          entity_type: "campaign",
          entity_id: campaignId,
          report_type: data.snapshot_defaults.report_type,
          start_date: data.range.start_date,
          end_date: data.range.end_date,
        },
      });
      invalidateApiCache("/reports", { requireWorkspace: true });
      await reload();
      setSnapshotMessage("Snapshot kaydedildi.");
    } catch (err) {
      setSnapshotMessage(err instanceof Error ? err.message : "Snapshot kaydedilemedi.");
    } finally {
      setSnapshotLoading(false);
    }
  };

  const downloadCsv = async () => {
    if (!campaignId || !data) return;
    await downloadApiFile(
      `/reports/campaign/${campaignId}/export.csv?start_date=${data.range.start_date}&end_date=${data.range.end_date}`,
      `campaign-report-${campaignId}.csv`,
      { requireWorkspace: true },
    );
  };

  if (!campaignId) {
    return <p className="text-sm text-[var(--danger)]">Campaign id eksik.</p>;
  }

  if (error) {
    return <p className="text-sm text-[var(--danger)]">{error}</p>;
  }

  if (isLoading && !data) {
    return <p className="text-sm muted-text">Rapor yukleniyor...</p>;
  }

  if (!data) {
    return <p className="text-sm text-[var(--danger)]">Rapor bulunamadi.</p>;
  }

  return (
    <ClientReportCanvas
      data={data}
      onSaveSnapshot={saveSnapshot}
      onDownloadCsv={() => void downloadCsv()}
      snapshotLoading={snapshotLoading}
      snapshotMessage={snapshotMessage}
    />
  );
}
