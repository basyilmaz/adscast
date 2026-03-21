"use client";

import { useState } from "react";
import { useSearchParams } from "next/navigation";
import { PageBreadcrumbs } from "@/components/layout/page-breadcrumbs";
import { ClientReportCanvas } from "@/components/reports/client-report-canvas";
import { PageErrorState, PageLoadingState } from "@/components/ui/page-state";
import { apiRequest, downloadApiFile } from "@/lib/api";
import { invalidateApiCache } from "@/lib/api-cache";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { buildApiPathWithFilters, GLOBAL_DATE_FILTER_KEYS } from "@/lib/filters";
import { useApiQuery } from "@/hooks/use-api-query";
import { ClientReportResponse } from "@/lib/types";

export function CampaignReportClient() {
  const searchParams = useSearchParams();
  const campaignId = searchParams.get("id");
  const [snapshotLoading, setSnapshotLoading] = useState(false);
  const [snapshotMessage, setSnapshotMessage] = useState<string | null>(null);
  const { data, error, isLoading, reload } = useApiQuery<ClientReportResponse, ClientReportResponse["data"]>(
    buildApiPathWithFilters(`/reports/campaign/${campaignId ?? ""}`, searchParams, GLOBAL_DATE_FILTER_KEYS),
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
    return <PageErrorState title="Campaign raporu acilamadi" detail="Campaign id eksik." />;
  }

  if (error) {
    return <PageErrorState title="Campaign raporu acilamadi" detail={error} />;
  }

  if (isLoading && !data) {
    return <PageLoadingState title="Campaign raporu yukleniyor" detail="Rapor bloklari ve snapshot gecmisi hazirlaniyor." />;
  }

  if (!data) {
    return <PageErrorState title="Campaign raporu bulunamadi" detail="Secili campaign icin rapor olusturulamadi." />;
  }

  return (
    <div className="space-y-4">
      <PageBreadcrumbs
        items={[
          { label: "Workspace", href: "/workspaces" },
          { label: "Raporlar", href: "/reports" },
          { label: "Campaign Raporu" },
        ]}
      />
      <ClientReportCanvas
        data={data}
        onSaveSnapshot={saveSnapshot}
        onDownloadCsv={() => void downloadCsv()}
        snapshotLoading={snapshotLoading}
        snapshotMessage={snapshotMessage}
      />
    </div>
  );
}
