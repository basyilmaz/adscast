"use client";

import { useSearchParams } from "next/navigation";
import { ClientReportCanvas } from "@/components/reports/client-report-canvas";
import { downloadApiFile } from "@/lib/api";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { useApiQuery } from "@/hooks/use-api-query";
import { ClientReportResponse } from "@/lib/types";

export function ReportSnapshotDetailClient() {
  const searchParams = useSearchParams();
  const snapshotId = searchParams.get("id");
  const { data, error, isLoading } = useApiQuery<ClientReportResponse, ClientReportResponse["data"]>(
    `/reports/snapshots/${snapshotId ?? ""}`,
    {
      enabled: Boolean(snapshotId),
      requestOptions: {
        requireWorkspace: true,
      },
      ttlMs: QUERY_TTLS.reportSnapshotDetail,
      select: (response) => response.data,
    },
  );

  const downloadCsv = async () => {
    if (!snapshotId || !data?.snapshot) return;
    await downloadApiFile(
      `/reports/snapshots/${snapshotId}/export.csv`,
      `report-snapshot-${snapshotId}.csv`,
      { requireWorkspace: true },
    );
  };

  if (!snapshotId) {
    return <p className="text-sm text-[var(--danger)]">Snapshot id eksik.</p>;
  }

  if (error) {
    return <p className="text-sm text-[var(--danger)]">{error}</p>;
  }

  if (isLoading && !data) {
    return <p className="text-sm muted-text">Snapshot yukleniyor...</p>;
  }

  if (!data) {
    return <p className="text-sm text-[var(--danger)]">Snapshot bulunamadi.</p>;
  }

  return <ClientReportCanvas data={data} onDownloadCsv={() => void downloadCsv()} snapshotActionLabel="Snapshot" />;
}
