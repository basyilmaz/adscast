import { Suspense } from "react";
import AdAccountsPageClient from "./ad-accounts-page-client";

export default function AdAccountsPage() {
  return (
    <Suspense fallback={<p className="text-sm muted-text">Reklam hesaplari yukleniyor...</p>}>
      <AdAccountsPageClient />
    </Suspense>
  );
}
