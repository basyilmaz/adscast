import { Suspense } from "react";
import { DraftDetailClient } from "./draft-detail-client";

export default function DraftDetailPage() {
  return (
    <Suspense fallback={<p className="text-sm muted-text">Yukleniyor...</p>}>
      <DraftDetailClient />
    </Suspense>
  );
}
