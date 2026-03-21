import { Suspense } from "react";
import DashboardPageClient from "./dashboard-page-client";

export default function DashboardPage() {
  return (
    <Suspense fallback={<p className="text-sm muted-text">Dashboard yukleniyor...</p>}>
      <DashboardPageClient />
    </Suspense>
  );
}
