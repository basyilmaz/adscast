"use client";

import { useSearchParams } from "next/navigation";
import { PageBreadcrumbs } from "@/components/layout/page-breadcrumbs";
import { ClientReportCanvas } from "@/components/reports/client-report-canvas";
import { PageErrorState, PageLoadingState } from "@/components/ui/page-state";
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
    return <PageErrorState title="Snapshot acilamadi" detail="Snapshot id eksik." />;
  }

  if (error) {
    return <PageErrorState title="Snapshot acilamadi" detail={error} />;
  }

  if (isLoading && !data) {
    return <PageLoadingState title="Snapshot yukleniyor" detail="Kaydedilmis rapor gorunumu hazirlaniyor." />;
  }

  if (!data) {
    return <PageErrorState title="Snapshot bulunamadi" detail="Secili snapshot artik mevcut degil." />;
  }

  return (
    <div className="space-y-4">
      <PageBreadcrumbs
        items={[
          { label: "Workspace", href: "/workspaces" },
          { label: "Raporlar", href: "/reports" },
          { label: "Snapshot" },
        ]}
      />
      <ClientReportCanvas data={data} onDownloadCsv={() => void downloadCsv()} snapshotActionLabel="Snapshot" />
    </div>
  );
}
