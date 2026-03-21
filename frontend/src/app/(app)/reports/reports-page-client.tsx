"use client";

import Link from "next/link";
import { useState } from "react";
import { useSearchParams } from "next/navigation";
import { PageBreadcrumbs } from "@/components/layout/page-breadcrumbs";
import { ReportScheduleForm } from "@/components/reports/report-schedule-form";
import { ReportTemplateForm } from "@/components/reports/report-template-form";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardTitle, CardValue } from "@/components/ui/card";
import { PageEmptyState, PageErrorState, PageLoadingState } from "@/components/ui/page-state";
import { useApiQuery } from "@/hooks/use-api-query";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { apiRequest } from "@/lib/api";
import { buildHrefWithFilters, GLOBAL_DATE_FILTER_KEYS } from "@/lib/filters";
import {
  ReportDeliveryScheduleListItem,
  ReportIndexResponse,
  ReportSnapshotListItem,
  ReportTemplateListItem,
} from "@/lib/types";

export default function ReportsPage() {
  const searchParams = useSearchParams();
  const { data, error, isLoading, isRefreshing, reload } = useApiQuery<
    ReportIndexResponse,
    ReportIndexResponse["data"]
  >("/reports", {
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.reports,
    select: (response) => response.data,
  });

  const [actionMessage, setActionMessage] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [activeActionKey, setActiveActionKey] = useState<string | null>(null);

  const handleToggleSchedule = async (schedule: ReportDeliveryScheduleListItem) => {
    const actionKey = `toggle:${schedule.id}`;
    setActiveActionKey(actionKey);
    setActionError(null);
    setActionMessage(null);

    try {
      await apiRequest(`/reports/delivery-schedules/${schedule.id}/toggle`, {
        method: "POST",
        requireWorkspace: true,
        body: {
          is_active: !schedule.is_active,
        },
      });

      setActionMessage(schedule.is_active ? "Schedule pasife alindi." : "Schedule tekrar aktif edildi.");
      await reload();
    } catch (requestError) {
      setActionError(requestError instanceof Error ? requestError.message : "Schedule guncellenemedi.");
    } finally {
      setActiveActionKey(null);
    }
  };

  const handleRunScheduleNow = async (schedule: ReportDeliveryScheduleListItem) => {
    const actionKey = `run:${schedule.id}`;
    setActiveActionKey(actionKey);
    setActionError(null);
    setActionMessage(null);

    try {
      const response = await apiRequest<{
        message: string;
        data: {
          snapshot_id: string | null;
          snapshot_url: string | null;
          share_link?: {
            share_url: string | null;
          } | null;
        };
      }>(`/reports/delivery-schedules/${schedule.id}/run-now`, {
        method: "POST",
        requireWorkspace: true,
      });

      setActionMessage(
        response.data.snapshot_id
          ? response.data.share_link?.share_url
            ? "Manual run tamamlandi, snapshot ve musteri paylasim linki hazirlandi."
            : "Manual run tamamlandi ve yeni snapshot hazirlandi."
          : "Manual run tamamlandi.",
      );
      await reload();
    } catch (requestError) {
      setActionError(requestError instanceof Error ? requestError.message : "Manual run calistirilamadi.");
    } finally {
      setActiveActionKey(null);
    }
  };

  return (
    <div className="space-y-4">
      <PageBreadcrumbs
        items={[
          { label: "Workspace", href: "/workspaces" },
          { label: "Raporlar" },
        ]}
      />

      <Card>
        <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
          <div>
            <CardTitle>Rapor Merkezi</CardTitle>
            <p className="mt-2 text-sm muted-text">
              Canli report builder, kaydedilmis sablonlar, schedule takibi ve snapshot gecmisi tek operasyon panelinde toplanir.
            </p>
          </div>
          {isRefreshing ? <Badge label="Guncelleniyor" variant="warning" /> : null}
        </div>
      </Card>

      {error ? <PageErrorState title="Rapor merkezi acilamadi" detail={error} /> : null}
      {actionError ? <PageErrorState title="Rapor aksiyonu tamamlanamadi" detail={actionError} /> : null}
      {actionMessage ? (
        <Card>
          <p className="text-sm text-[var(--accent)]">{actionMessage}</p>
        </Card>
      ) : null}
      {isLoading && !data ? (
        <PageLoadingState title="Raporlar yukleniyor" detail="Builder listesi, sablonlar ve schedule durumu hazirlaniyor." />
      ) : null}

      <section className="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-6">
        <MetricCard label="Toplam Snapshot" value={data?.summary.total_snapshots ?? 0} />
        <MetricCard label="Account Snapshot" value={data?.summary.account_snapshots ?? 0} />
        <MetricCard label="Campaign Snapshot" value={data?.summary.campaign_snapshots ?? 0} />
        <MetricCard label="Kayitli Sablon" value={data?.template_summary.total_templates ?? 0} />
        <MetricCard label="Aktif Schedule" value={data?.delivery_summary.active_schedules ?? 0} />
        <MetricCard label="Aktif Paylasim" value={data?.share_summary.active_links ?? 0} />
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_1fr]">
        <Card>
          <CardTitle>Kaydedilmis Rapor Sablonu Olustur</CardTitle>
          <p className="mt-2 text-sm muted-text">
            Tekrar kullanilabilir rapor yapisi kurun. Bu sablonlar manuel ya da schedule ile yeni snapshot uretebilir.
          </p>
          <div className="mt-4">
            <ReportTemplateForm builders={data?.builders ?? { accounts: [], campaigns: [] }} onCreated={reload} />
          </div>
        </Card>

        <Card>
          <CardTitle>Scheduled Delivery Foundation</CardTitle>
          <p className="mt-2 text-sm muted-text">
            Bu fazda gercek e-posta gonderimi yok. Schedule calistiginda yeni snapshot ve delivery run izi olusur.
          </p>
          <div className="mt-4">
            <ReportScheduleForm templates={data?.templates ?? []} onCreated={reload} />
          </div>
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
        <CardTitle>Kaydedilmis Sablonlar</CardTitle>
        <div className="mt-3 space-y-3">
          {(data?.templates ?? []).map((template: ReportTemplateListItem) => (
            <div key={template.id} className="rounded-lg border border-[var(--border)] p-3">
              <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                  <div className="flex flex-wrap gap-2">
                    <Badge label={template.entity_type} variant="neutral" />
                    <Badge label={template.is_active ? "active" : "inactive"} variant={template.is_active ? "success" : "warning"} />
                    <Badge label={`${template.default_range_days} gun`} variant="neutral" />
                  </div>
                  <p className="mt-2 font-semibold">{template.name}</p>
                  <p className="mt-1 text-xs muted-text">
                    {template.entity_label ?? "Varlik"}
                    {template.context_label ? ` / ${template.context_label}` : ""}
                    {` / ${template.report_type}`}
                  </p>
                  {template.notes ? <p className="mt-2 text-sm muted-text">{template.notes}</p> : null}
                </div>
                <div className="flex flex-wrap gap-3 text-sm">
                  <span className="muted-text">{template.delivery_schedules_count} schedule</span>
                  <Link
                    href={buildHrefWithFilters(template.report_url, searchParams, GLOBAL_DATE_FILTER_KEYS)}
                    className="font-semibold text-[var(--accent)] hover:underline"
                  >
                    Canli raporu ac
                  </Link>
                </div>
              </div>
            </div>
          ))}
          {(data?.templates ?? []).length === 0 ? <p className="text-sm muted-text">Kayitli rapor sablonu bulunmuyor.</p> : null}
        </div>
      </Card>

      <Card>
        <CardTitle>Scheduled Delivery Kayitlari</CardTitle>
        <div className="mt-3 space-y-3">
          {(data?.delivery_schedules ?? []).map((schedule: ReportDeliveryScheduleListItem) => (
            <div key={schedule.id} className="rounded-lg border border-[var(--border)] p-3">
              <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                <div className="space-y-2">
                  <div className="flex flex-wrap gap-2">
                    <Badge label={schedule.cadence_label} variant="neutral" />
                    <Badge label={schedule.is_active ? "active" : "inactive"} variant={schedule.is_active ? "success" : "warning"} />
                    <Badge label={schedule.delivery_channel_label} variant="neutral" />
                    {schedule.share_delivery.enabled ? <Badge label="Auto Share Acik" variant="success" /> : null}
                  </div>
                  <p className="font-semibold">{schedule.template.name ?? "Silinmis sablon"}</p>
                  <p className="text-xs muted-text">
                    {schedule.template.entity_label ?? "Varlik"}
                    {schedule.template.context_label ? ` / ${schedule.template.context_label}` : ""}
                  </p>
                  <p className="text-sm muted-text">
                    Sonraki calisma: {schedule.next_run_at ?? "-"} / Son durum: {schedule.last_status ?? "-"}
                  </p>
                  <p className="text-sm muted-text">Alicilar: {schedule.recipients.join(", ") || "-"}</p>
                  {schedule.share_delivery.enabled ? (
                    <p className="text-sm muted-text">
                      Paylasim: {schedule.share_delivery.label_template ?? "Snapshot basligi"} / {schedule.share_delivery.expires_in_days ?? 7} gun /{" "}
                      {schedule.share_delivery.allow_csv_download ? "CSV acik" : "CSV kapali"}
                    </p>
                  ) : null}
                  {schedule.last_report_snapshot_url ? (
                    <Link href={schedule.last_report_snapshot_url} className="inline-flex text-sm font-semibold text-[var(--accent)] hover:underline">
                      Son snapshot&apos;i ac
                    </Link>
                  ) : null}
                </div>

                <div className="flex flex-col gap-3 xl:min-w-[320px]">
                  <div className="flex flex-wrap gap-2">
                    <Button
                      type="button"
                      variant="secondary"
                      onClick={() => handleRunScheduleNow(schedule)}
                      disabled={activeActionKey !== null}
                    >
                      {activeActionKey === `run:${schedule.id}` ? "Calisiyor..." : "Run Now"}
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => handleToggleSchedule(schedule)}
                      disabled={activeActionKey !== null}
                    >
                      {activeActionKey === `toggle:${schedule.id}`
                        ? "Guncelleniyor..."
                        : schedule.is_active
                          ? "Pasife Al"
                          : "Aktif Et"}
                    </Button>
                    {schedule.template.report_url ? (
                      <Link
                        href={buildHrefWithFilters(schedule.template.report_url, searchParams, GLOBAL_DATE_FILTER_KEYS)}
                        className="inline-flex h-10 items-center rounded-md border border-[var(--border)] px-4 text-sm font-semibold hover:bg-[var(--surface-2)]"
                      >
                        Canli rapor
                      </Link>
                    ) : null}
                  </div>

                  <div className="rounded-lg border border-[var(--border)] p-3">
                    <p className="text-xs font-semibold uppercase tracking-wide muted-text">Son Run Kayitlari</p>
                    <div className="mt-2 space-y-2">
                      {schedule.recent_runs.map((run) => (
                        <div key={run.id} className="rounded-md bg-[var(--surface-2)] px-3 py-2">
                          <div className="flex flex-wrap items-center gap-2">
                            <Badge label={run.status} variant={run.status === "failed" ? "danger" : "neutral"} />
                            <span className="text-xs muted-text">{run.trigger_mode}</span>
                          </div>
                          <p className="mt-1 text-xs muted-text">
                            {run.prepared_at ?? "-"}
                            {run.delivered_at ? ` / ${run.delivered_at}` : ""}
                          </p>
                          {run.snapshot_url ? (
                            <Link href={run.snapshot_url} className="mt-1 inline-flex text-xs font-semibold text-[var(--accent)] hover:underline">
                              {run.snapshot_title ?? "Snapshot"}
                            </Link>
                          ) : null}
                          {run.share_link?.share_url ? (
                            <div className="mt-1 flex flex-wrap gap-3 text-xs">
                              <a
                                href={run.share_link.share_url}
                                target="_blank"
                                rel="noreferrer"
                                className="font-semibold text-[var(--accent)] hover:underline"
                              >
                                Musteri linkini ac
                              </a>
                              {run.share_link.export_csv_url ? (
                                <a
                                  href={run.share_link.export_csv_url}
                                  target="_blank"
                                  rel="noreferrer"
                                  className="font-semibold text-[var(--accent)] hover:underline"
                                >
                                  CSV indir
                                </a>
                              ) : null}
                            </div>
                          ) : null}
                          {run.error_message ? <p className="mt-1 text-xs text-[var(--danger)]">{run.error_message}</p> : null}
                        </div>
                      ))}
                      {schedule.recent_runs.length === 0 ? <p className="text-sm muted-text">Henuz run kaydi yok.</p> : null}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          ))}
          {(data?.delivery_schedules ?? []).length === 0 ? <p className="text-sm muted-text">Kayitli schedule bulunmuyor.</p> : null}
        </div>
      </Card>

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
                    <Link
                      href={buildHrefWithFilters(item.report_url, searchParams, GLOBAL_DATE_FILTER_KEYS)}
                      className="font-semibold text-[var(--accent)] hover:underline"
                    >
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

      {!isLoading
      && !error
      && (data?.builders.accounts ?? []).length === 0
      && (data?.builders.campaigns ?? []).length === 0 ? (
        <PageEmptyState
          title="Rapor olusturulacak kayit bulunmuyor"
          detail="Workspace altinda senkronize reklam hesabi veya kampanya olmadigi icin rapor builder bos."
        />
      ) : null}
    </div>
  );
}

function MetricCard({ label, value }: { label: string; value: number }) {
  return (
    <Card>
      <CardTitle>{label}</CardTitle>
      <CardValue>{value}</CardValue>
    </Card>
  );
}
