import { Suspense } from "react";
import { CampaignDetailClient } from "./campaign-detail-client";

export default function CampaignDetailPage() {
  return (
    <Suspense fallback={<p className="text-sm muted-text">Yukleniyor...</p>}>
      <CampaignDetailClient />
    </Suspense>
  );
}
