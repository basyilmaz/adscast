import { Suspense } from "react";
import { SharedReportPageClient } from "./shared-report-page-client";

export default function SharedReportPage() {
  return (
    <Suspense fallback={<p className="p-6 text-sm muted-text">Paylasilan rapor yukleniyor...</p>}>
      <SharedReportPageClient />
    </Suspense>
  );
}
