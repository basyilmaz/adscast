import { Suspense } from "react";
import { AccountReportClient } from "./account-report-client";

export default function AccountReportPage() {
  return (
    <Suspense fallback={<p className="text-sm muted-text">Rapor yukleniyor...</p>}>
      <AccountReportClient />
    </Suspense>
  );
}
