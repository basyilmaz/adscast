import { Suspense } from "react";
import { AdAccountDetailClient } from "./ad-account-detail-client";

export default function AdAccountDetailPage() {
  return (
    <Suspense fallback={<p className="text-sm muted-text">Yukleniyor...</p>}>
      <AdAccountDetailClient />
    </Suspense>
  );
}
