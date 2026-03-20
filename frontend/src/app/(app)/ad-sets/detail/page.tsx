import { Suspense } from "react";
import { AdSetDetailClient } from "./ad-set-detail-client";

export default function AdSetDetailPage() {
  return (
    <Suspense fallback={<p className="text-sm muted-text">Yukleniyor...</p>}>
      <AdSetDetailClient />
    </Suspense>
  );
}
