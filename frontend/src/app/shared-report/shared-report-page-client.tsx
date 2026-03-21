"use client";

import { useSearchParams } from "next/navigation";
import { ClientReportCanvas } from "@/components/reports/client-report-canvas";
import { Card, CardTitle } from "@/components/ui/card";
import { PageErrorState, PageLoadingState } from "@/components/ui/page-state";
import { downloadApiFile } from "@/lib/api";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { useApiQuery } from "@/hooks/use-api-query";
import { ClientReportResponse } from "@/lib/types";

export function SharedReportPageClient() {
  const searchParams = useSearchParams();
  const token = searchParams.get("token");
  const { data, error, isLoading } = useApiQuery<ClientReportResponse, ClientReportResponse["data"]>(
    token ? `/public/report-shares/${encodeURIComponent(token)}` : "/public/report-shares/missing",
    {
      enabled: Boolean(token),
      ttlMs: QUERY_TTLS.reportSnapshotDetail,
      persist: false,
      select: (response) => response.data,
    },
  );

  const handleDownloadCsv = async () => {
    if (!token || !data?.share_link?.allow_csv_download) {
      return;
    }

    await downloadApiFile(
      `/public/report-shares/${encodeURIComponent(token)}/export.csv`,
      `shared-report-${token}.csv`,
    );
  };

  if (!token) {
    return <PageErrorState title="Paylasilan rapor acilamadi" detail="Paylasim token'i eksik." />;
  }

  if (error) {
    return <PageErrorState title="Paylasilan rapor acilamadi" detail={error} />;
  }

  if (isLoading && !data) {
    return <PageLoadingState title="Paylasilan rapor yukleniyor" detail="Musteri gorunumu hazirlaniyor." />;
  }

  if (!data) {
    return <PageErrorState title="Paylasilan rapor bulunamadi" detail="Link gecersiz veya artik aktif degil." />;
  }

  return (
    <div className="mx-auto max-w-6xl space-y-4 p-4 md:p-6">
      <Card>
        <CardTitle>Musteri Paylasim Gorunumu</CardTitle>
        <p className="mt-2 text-sm muted-text">
          Bu ekran kaydedilmis snapshot&apos;in client-facing kopyasini gosterir.
        </p>
      </Card>

      <ClientReportCanvas
        data={data}
        onDownloadCsv={data.share_link?.allow_csv_download ? () => void handleDownloadCsv() : undefined}
        snapshotActionLabel="Paylasim"
        mode="client"
      />
    </div>
  );
}
