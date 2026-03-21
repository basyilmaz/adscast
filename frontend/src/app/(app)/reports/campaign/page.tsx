import { Suspense } from "react";
import { CampaignReportClient } from "./campaign-report-client";

export default function CampaignReportPage() {
  return (
    <Suspense fallback={<p className="text-sm muted-text">Rapor yukleniyor...</p>}>
      <CampaignReportClient />
    </Suspense>
  );
}
