"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { PageBreadcrumbs } from "@/components/layout/page-breadcrumbs";
import { Card, CardTitle, CardValue } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { PageEmptyState, PageErrorState, PageLoadingState } from "@/components/ui/page-state";
import { useApiQuery } from "@/hooks/use-api-query";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { buildHrefWithFilters, GLOBAL_DATE_FILTER_KEYS } from "@/lib/filters";
import { ReportIndexResponse, ReportSnapshotListItem } from "@/lib/types";

export default function ReportsPage() {
  const searchParams = useSearchParams();
  const { data, error, isLoading } = useApiQuery<ReportIndexResponse, ReportIndexResponse["data"]>("/reports", {
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.reports,
    select: (response) => response.data,
  });

  return (
    <div className="space-y-4">
      <PageBreadcrumbs
        items={[
          { label: "Workspace", href: "/workspaces" },
          { label: "Raporlar" },
        ]}
      />

      <Card>
        <CardTitle>Raporlar</CardTitle>
        <p className="mt-2 text-sm muted-text">
          Musteriye verilecek account ve campaign raporlari bu modulde canli olusturulur, snapshot olarak kaydedilir ve export akislari buradan izlenir.
        </p>
      </Card>

      {error ? <PageErrorState title="Rapor merkezi acilamadi" detail={error} /> : null}
      {isLoading && !data ? (
        <PageLoadingState title="Raporlar yukleniyor" detail="Builder listesi ve snapshot gecmisi hazirlaniyor." />
      ) : null}

      <section className="grid grid-cols-1 gap-4 md:grid-cols-3">
        <Card>
          <CardTitle>Toplam Snapshot</CardTitle>
          <CardValue>{data?.summary.total_snapshots ?? 0}</CardValue>
        </Card>
        <Card>
          <CardTitle>Account Snapshot</CardTitle>
          <CardValue>{data?.summary.account_snapshots ?? 0}</CardValue>
        </Card>
        <Card>
          <CardTitle>Campaign Snapshot</CardTitle>
          <CardValue>{data?.summary.campaign_snapshots ?? 0}</CardValue>
        </Card>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_1fr]">
        <Card>
          <CardTitle>Account Report Builder</CardTitle>
          <div className="mt-3 space-y-3">
            {(data?.builders.accounts ?? []).map((item) => (
              <div key={item.id} className="rounded-lg border border-[var(--border)] p-3">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="font-semibold">{item.name}</p>
                    <p className="text-xs muted-text">{item.external_id ?? "-"}</p>
                  </div>
                  <Badge label={item.status} variant="neutral" />
                </div>
                <Link
                  href={buildHrefWithFilters(item.route, searchParams, GLOBAL_DATE_FILTER_KEYS)}
                  className="mt-3 inline-flex text-sm font-semibold text-[var(--accent)] hover:underline"
                >
                  Account raporunu ac
                </Link>
              </div>
            ))}
            {(data?.builders.accounts ?? []).length === 0 ? <p className="text-sm muted-text">Builder icin hesap bulunmuyor.</p> : null}
          </div>
        </Card>

        <Card>
          <CardTitle>Campaign Report Builder</CardTitle>
          <div className="mt-3 space-y-3">
            {(data?.builders.campaigns ?? []).map((item) => (
              <div key={item.id} className="rounded-lg border border-[var(--border)] p-3">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="font-semibold">{item.name}</p>
                    <p className="text-xs muted-text">
                      {item.context_label ?? "-"}
                      {item.objective ? ` / ${item.objective}` : ""}
                    </p>
                  </div>
                  <Badge label={item.status} variant="neutral" />
                </div>
                <Link
                  href={buildHrefWithFilters(item.route, searchParams, GLOBAL_DATE_FILTER_KEYS)}
                  className="mt-3 inline-flex text-sm font-semibold text-[var(--accent)] hover:underline"
                >
                  Campaign raporunu ac
                </Link>
              </div>
            ))}
            {(data?.builders.campaigns ?? []).length === 0 ? <p className="text-sm muted-text">Builder icin kampanya bulunmuyor.</p> : null}
          </div>
        </Card>
      </section>

      <Card>
        <CardTitle>Snapshot Gecmisi</CardTitle>
        <div className="mt-3 space-y-3">
          {(data?.items ?? []).map((item: ReportSnapshotListItem) => (
            <div key={item.id} className="rounded-lg border border-[var(--border)] p-3">
              <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                  <p className="font-semibold">{item.title}</p>
                  <p className="text-xs muted-text">
                    {item.entity_label ?? "Varlik"}
                    {item.context_label ? ` / ${item.context_label}` : ""}
                  </p>
                  <p className="mt-1 text-xs muted-text">
                    {item.start_date} / {item.end_date} / {item.created_at ?? "-"}
                  </p>
                </div>
                <div className="flex flex-wrap gap-3 text-sm">
                  <Link href={item.snapshot_url} className="font-semibold text-[var(--accent)] hover:underline">
                    Snapshot ac
                  </Link>
                  {item.report_url ? (
                    <Link href={item.report_url} className="font-semibold text-[var(--accent)] hover:underline">
                      Canli raporu ac
                    </Link>
                  ) : null}
                </div>
              </div>
            </div>
          ))}
          {(data?.items ?? []).length === 0 ? <p className="text-sm muted-text">Kayitli snapshot bulunmuyor.</p> : null}
        </div>
      </Card>

      {!isLoading && !error && (data?.builders.accounts ?? []).length === 0 && (data?.builders.campaigns ?? []).length === 0 ? (
        <PageEmptyState
          title="Rapor olusturulacak kayit bulunmuyor"
          detail="Workspace altinda senkronize reklam hesabi veya kampanya olmadigi icin rapor builder bos."
        />
      ) : null}
    </div>
  );
}
