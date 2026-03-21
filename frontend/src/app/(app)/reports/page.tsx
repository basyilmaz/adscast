import { Suspense } from "react";
import ReportsPageClient from "./reports-page-client";

export default function ReportsPage() {
  return (
    <Suspense fallback={<p className="text-sm muted-text">Raporlar yukleniyor...</p>}>
      <ReportsPageClient />
    </Suspense>
  );
}
