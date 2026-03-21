"use client";

import { useState } from "react";
import { useSearchParams } from "next/navigation";
import { ClientReportCanvas } from "@/components/reports/client-report-canvas";
import { apiRequest, downloadApiFile } from "@/lib/api";
import { invalidateApiCache } from "@/lib/api-cache";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { useApiQuery } from "@/hooks/use-api-query";
import { ClientReportResponse } from "@/lib/types";

export function AccountReportClient() {
  const searchParams = useSearchParams();
  const accountId = searchParams.get("id");
  const [snapshotLoading, setSnapshotLoading] = useState(false);
  const [snapshotMessage, setSnapshotMessage] = useState<string | null>(null);
  const { data, error, isLoading, reload } = useApiQuery<ClientReportResponse, ClientReportResponse["data"]>(
    `/reports/account/${accountId ?? ""}`,
    {
      enabled: Boolean(accountId),
      requestOptions: {
        requireWorkspace: true,
      },
      ttlMs: QUERY_TTLS.accountReport,
      select: (response) => response.data,
    },
  );

  const saveSnapshot = async () => {
    if (!accountId || !data) return;
    setSnapshotLoading(true);
    setSnapshotMessage(null);
    try {
      await apiRequest("/reports/snapshots", {
        method: "POST",
        requireWorkspace: true,
        body: {
          entity_type: "account",
          entity_id: accountId,
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
    if (!accountId || !data) return;
    await downloadApiFile(
      `/reports/account/${accountId}/export.csv?start_date=${data.range.start_date}&end_date=${data.range.end_date}`,
      `account-report-${accountId}.csv`,
      { requireWorkspace: true },
    );
  };

  if (!accountId) {
    return <p className="text-sm text-[var(--danger)]">Account id eksik.</p>;
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
