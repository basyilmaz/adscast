import { Suspense } from "react";
import { AdDetailClient } from "./ad-detail-client";

export default function AdDetailPage() {
  return (
    <Suspense fallback={<p className="text-sm muted-text">Yukleniyor...</p>}>
      <AdDetailClient />
    </Suspense>
  );
}
