import { Suspense } from "react";
import CampaignListPageClient from "./campaigns-page-client";

export default function CampaignsPage() {
  return (
    <Suspense fallback={<p className="text-sm muted-text">Kampanyalar yukleniyor...</p>}>
      <CampaignListPageClient />
    </Suspense>
  );
}
