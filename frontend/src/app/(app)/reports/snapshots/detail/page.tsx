import { Suspense } from "react";
import { ReportSnapshotDetailClient } from "./report-snapshot-detail-client";

export default function ReportSnapshotDetailPage() {
  return (
    <Suspense fallback={<p className="text-sm muted-text">Snapshot yukleniyor...</p>}>
      <ReportSnapshotDetailClient />
    </Suspense>
  );
}
