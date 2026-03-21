"use client";

import Link from "next/link";
import { useState } from "react";
import { useSearchParams } from "next/navigation";
import { PageBreadcrumbs } from "@/components/layout/page-breadcrumbs";
import { ReportContactForm } from "@/components/reports/report-contact-form";
import { ReportContactManager } from "@/components/reports/report-contact-manager";
import { ReportDeliveryHistoryPanel } from "@/components/reports/report-delivery-history-panel";
import { ReportRecipientGroupAnalyticsPanel } from "@/components/reports/report-recipient-group-analytics-panel";
import { ReportRecipientGroupCatalog } from "@/components/reports/report-recipient-group-catalog";
import { ReportDeliverySetupForm } from "@/components/reports/report-delivery-setup-form";
import { ReportRecipientPresetManager } from "@/components/reports/report-recipient-preset-manager";
import { ReportRecipientPresetForm } from "@/components/reports/report-recipient-preset-form";
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
  ReportContactSegmentListItem,
  ReportDeliveryScheduleListItem,
  ReportDeliveryRunListItem,
  ReportDeliveryProfileListItem,
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

  const handleRetryRun = async (run: ReportDeliveryRunListItem) => {
    const actionKey = `retry:${run.id}`;
    setActiveActionKey(actionKey);
    setActionError(null);
    setActionMessage(null);

    try {
      const response = await apiRequest<{
        message: string;
        data: {
          run_id: string;
          status: string;
          snapshot_id: string | null;
          share_link?: {
            share_url: string | null;
          } | null;
        };
      }>(`/reports/delivery-runs/${run.id}/retry`, {
        method: "POST",
        requireWorkspace: true,
      });

      setActionMessage(
        response.data.share_link?.share_url
          ? "Basarisiz teslim yeniden denendi, yeni snapshot ve musteri paylasim linki hazirlandi."
          : "Basarisiz teslim yeniden denendi.",
      );
      await reload();
    } catch (requestError) {
      setActionError(requestError instanceof Error ? requestError.message : "Retry aksiyonu calistirilamadi.");
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

      <section className="grid grid-cols-1 gap-4 md:grid-cols-4 xl:grid-cols-8">
        <MetricCard label="Toplam Snapshot" value={data?.summary.total_snapshots ?? 0} />
        <MetricCard label="Account Snapshot" value={data?.summary.account_snapshots ?? 0} />
        <MetricCard label="Campaign Snapshot" value={data?.summary.campaign_snapshots ?? 0} />
        <MetricCard label="Kayitli Sablon" value={data?.template_summary.total_templates ?? 0} />
        <MetricCard label="Kisi Havuzu" value={data?.contact_summary.total_contacts ?? 0} />
        <MetricCard label="Kisi Segmenti" value={data?.contact_segment_summary.total_segments ?? 0} />
        <MetricCard label="Grup Katalogu" value={data?.recipient_group_catalog_summary.total_groups ?? 0} />
        <MetricCard label="Izlenen Grup" value={data?.recipient_group_analytics_summary.total_groups ?? 0} />
        <MetricCard label="Alici Grubu" value={data?.recipient_preset_summary.total_presets ?? 0} />
        <MetricCard label="Varsayilan Profil" value={data?.delivery_profile_summary.total_profiles ?? 0} />
        <MetricCard label="Aktif Schedule" value={data?.delivery_summary.active_schedules ?? 0} />
        <MetricCard label="Basarisiz Teslim" value={data?.delivery_run_summary.failed_runs ?? 0} />
        <MetricCard label="Aktif Paylasim" value={data?.share_summary.active_links ?? 0} />
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_1fr]">
        <Card>
          <CardTitle>Musteri Rapor Teslimi Kur</CardTitle>
          <p className="mt-2 text-sm muted-text">
            Kampanya veya reklam hesabi secin, once onerilen alici gruplarindan ilerleyin, gerekiyorsa manuel override ekleyin. Sistem uygun sablonu kullanarak schedule olusturur.
          </p>
          <div className="mt-4">
            <ReportDeliverySetupForm
              builders={data?.builders ?? { accounts: [], campaigns: [] }}
              deliveryCapabilities={data?.delivery_capabilities ?? null}
              contacts={data?.contacts ?? []}
              recipientPresets={data?.recipient_presets ?? []}
              deliveryProfiles={data?.delivery_profiles ?? []}
              onCreated={reload}
            />
          </div>
        </Card>

        <Card>
          <CardTitle>Mevcut Sablondan Schedule Kur</CardTitle>
          <p className="mt-2 text-sm muted-text">
            Mevcut bir rapor sablonu icin onerilen alici gruplariyla hizli bir schedule acin. Manuel alici ve etiket ayarlari ileri seviye override olarak kalir.
          </p>
          <div className="mt-4">
            <ReportScheduleForm
              templates={data?.templates ?? []}
              contacts={data?.contacts ?? []}
              recipientPresets={data?.recipient_presets ?? []}
              deliveryCapabilities={data?.delivery_capabilities ?? null}
              onCreated={reload}
            />
          </div>
        </Card>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_1fr]">
        <Card>
          <CardTitle>Musteri Kisi Havuzu</CardTitle>
          <p className="mt-2 text-sm muted-text">
            Musteri ve marka tarafindaki kisileri merkezi olarak tutun. Daha sonra preset ve schedule akislarinda bu havuzdan alici ekleyin.
          </p>
          <div className="mt-4">
            <ReportContactForm onCreated={reload} />
          </div>

          <div className="mt-4">
            <ReportContactManager contacts={data?.contacts ?? []} onChanged={reload} />
          </div>
        </Card>

        <Card>
          <CardTitle>Kayitli Alici Gruplari</CardTitle>
          <p className="mt-2 text-sm muted-text">
            Statik alicilari ve kisi segmentlerini bir kez gruplayin. Hizli teslim ve schedule akislarinda bu gruplari tekrar secerek kullanin.
          </p>
          <div className="mt-4">
            <ReportRecipientPresetForm contacts={data?.contacts ?? []} onCreated={reload} />
          </div>

          <div className="mt-4">
            <ReportRecipientPresetManager presets={data?.recipient_presets ?? []} contacts={data?.contacts ?? []} onChanged={reload} />
          </div>
        </Card>
      </section>

      <Card>
        <CardTitle>Kisi Segmentleri</CardTitle>
        <p className="mt-2 text-sm muted-text">
          Etiketler artik first-class segment gibi izlenir. Varsayilan teslim profilleri ve schedule&apos;lar bu segmentlerden dinamik alici cozer.
        </p>
        <div className="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-2">
          {(data?.contact_segments ?? []).map((segment: ReportContactSegmentListItem) => (
            <div key={segment.tag} className="rounded-lg border border-[var(--border)] p-3">
              <div className="flex flex-wrap gap-2">
                <Badge label={segment.tag} variant="neutral" />
                <Badge label={`${segment.contacts_count} kisi`} variant="neutral" />
                {segment.primary_contacts_count > 0 ? (
                  <Badge label={`${segment.primary_contacts_count} primary`} variant="success" />
                ) : null}
              </div>
              <p className="mt-2 text-sm muted-text">
                {segment.active_contacts_count} aktif kisi
                {segment.companies_count > 0 ? ` / ${segment.companies_count} sirket` : ""}
                {segment.last_used_at ? ` / Son kullanim: ${segment.last_used_at}` : ""}
              </p>
              {segment.sample_contacts.length > 0 ? (
                <p className="mt-2 text-sm">
                  {segment.sample_contacts.map((contact) => contact.name).join(", ")}
                </p>
              ) : null}
            </div>
          ))}
          {(data?.contact_segments ?? []).length === 0 ? (
            <p className="text-sm muted-text">Henuz tanimli kisi segmenti yok.</p>
          ) : null}
        </div>
      </Card>

      <Card>
        <CardTitle>Alici Grubu Katalogu</CardTitle>
        <p className="mt-2 text-sm muted-text">
          Kayitli grup, segment ve primary/sirket bazli akilli grup adaylari burada toplanir. Operator dogru alici yapisini secmeden once tum teslim seceneklerini tek listede gorur.
        </p>
        <div className="mt-3 flex flex-wrap gap-3 text-sm muted-text">
          <span>Toplam: {data?.recipient_group_catalog_summary.total_groups ?? 0}</span>
          <span>Kayitli grup: {data?.recipient_group_catalog_summary.preset_groups ?? 0}</span>
          <span>Segment: {data?.recipient_group_catalog_summary.segment_groups ?? 0}</span>
          <span>Akilli grup: {data?.recipient_group_catalog_summary.smart_groups ?? 0}</span>
        </div>
        <div className="mt-4">
          <ReportRecipientGroupCatalog
            items={data?.recipient_group_catalog ?? []}
            emptyText="Katalogda henuz gosterilecek alici grubu yok."
          />
        </div>
      </Card>

      <Card>
        <CardTitle>Alici Grubu Analytics</CardTitle>
        <p className="mt-2 text-sm muted-text">
          Hangi alici grubunun ne kadar kullanildigini, nerede hata urettigini ve hangi entity&apos;lere yayildigini tek panelde izleyin.
        </p>
        <div className="mt-3 flex flex-wrap gap-3 text-sm muted-text">
          <span>Pencere: {data?.recipient_group_analytics_summary.window_days ?? 0} gun</span>
          <span>En cok kullanilan: {data?.recipient_group_analytics_summary.most_used_group_label ?? "-"}</span>
          <span>En riskli: {data?.recipient_group_analytics_summary.highest_failure_group_label ?? "-"}</span>
        </div>
        <div className="mt-4">
          <ReportRecipientGroupAnalyticsPanel
            summary={data?.recipient_group_analytics_summary ?? null}
            items={data?.recipient_group_analytics ?? []}
          />
        </div>
      </Card>

      <section className="grid grid-cols-1 gap-4">
        <Card>
          <CardTitle>Varsayilan Teslim Profilleri</CardTitle>
          <p className="mt-2 text-sm muted-text">
            Kampanya veya hesap secildiginde hizli teslim formunu otomatik dolduran varsayilan cadence ve alici profilleri.
          </p>
          <div className="mt-4 space-y-3">
            {(data?.delivery_profiles ?? []).map((profile: ReportDeliveryProfileListItem) => (
              <div key={profile.id} className="rounded-lg border border-[var(--border)] p-3">
                <div className="flex flex-wrap gap-2">
                  <Badge label={profile.entity_type} variant="neutral" />
                  <Badge label={profile.cadence_label} variant="neutral" />
                  <Badge label={profile.delivery_channel_label} variant="neutral" />
                  {profile.share_delivery.enabled ? <Badge label="Auto Share" variant="success" /> : null}
                </div>
                <p className="mt-2 font-semibold">{profile.entity_label ?? "Varlik"}</p>
                <p className="mt-1 text-xs muted-text">
                  {profile.context_label ? `${profile.context_label} / ` : ""}
                  {profile.resolved_recipients_count} alici / {profile.default_range_days} gun / {profile.timezone}
                </p>
                <p className="mt-2 text-sm muted-text">{profile.recipient_group_summary.label}</p>
                <p className="mt-1 text-xs muted-text">
                  Statik: {profile.recipient_group_summary.static_recipients_count} / Dinamik: {profile.recipient_group_summary.dynamic_contacts_count}
                </p>
                {profile.recipient_group_summary.sample_contact_names.length > 0 ? (
                  <p className="mt-1 text-xs muted-text">
                    Ornek kisiler: {profile.recipient_group_summary.sample_contact_names.join(", ")}
                  </p>
                ) : null}
                {profile.report_url ? (
                  <Link
                    href={buildHrefWithFilters(profile.report_url, searchParams, GLOBAL_DATE_FILTER_KEYS)}
                    className="mt-3 inline-flex text-sm font-semibold text-[var(--accent)] hover:underline"
                  >
                    Ilgili raporu ac
                  </Link>
                ) : null}
              </div>
            ))}
            {(data?.delivery_profiles ?? []).length === 0 ? <p className="text-sm muted-text">Henuz kayitli varsayilan teslim profili yok.</p> : null}
          </div>
        </Card>
      </section>

      <Card>
        <CardTitle>Kaydedilmis Rapor Sablonu Olustur</CardTitle>
        <p className="mt-2 text-sm muted-text">
          Tekrar kullanilabilir sablonlari manuel yonetmek istiyorsaniz bu bolumu kullanin. Hemen ustteki hizli teslim formu daha tipik operator akisidir.
        </p>
        <div className="mt-4">
          <ReportTemplateForm builders={data?.builders ?? { accounts: [], campaigns: [] }} onCreated={reload} />
        </div>
      </Card>

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
        <CardTitle>Teslim Gecmisi ve Hata Merkezi</CardTitle>
        <p className="mt-2 text-sm muted-text">
          Son teslim calismalarini, hata nedenlerini ve tekrar denenebilir kayitlari tek listede takip edin.
        </p>
        <div className="mt-3 flex flex-wrap gap-3 text-sm muted-text">
          <span>Toplam run: {data?.delivery_run_summary.total_runs ?? 0}</span>
          <span>Basarili: {data?.delivery_run_summary.delivered_runs ?? 0}</span>
          <span>Retry bekleyen: {data?.delivery_run_summary.retryable_runs ?? 0}</span>
          <span>Son hata: {data?.delivery_run_summary.latest_failed_at ?? "-"}</span>
        </div>
        <div className="mt-4">
          <ReportDeliveryHistoryPanel
            runs={data?.delivery_runs ?? []}
            searchParams={searchParams}
            activeActionKey={activeActionKey}
            onRetry={handleRetryRun}
          />
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
                  {schedule.contact_tags.length > 0 ? (
                    <p className="text-sm muted-text">
                      Etiketler: {schedule.contact_tags.join(", ")} / Dinamik kisi: {schedule.tagged_contacts_count}
                    </p>
                  ) : null}
                  <p className="text-sm muted-text">Toplam cozumlenen alici: {schedule.resolved_recipients_count}</p>
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
                          {run.delivery ? (
                            <p className="mt-1 text-xs muted-text">
                              {run.delivery.channel_label} / {run.delivery.mailer ?? "-"} / {run.delivery.recipients_count} alici
                            </p>
                          ) : null}
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
                          {run.contact_tags.length > 0 ? (
                            <p className="mt-1 text-xs muted-text">
                              Etiketler: {run.contact_tags.join(", ")} / Dinamik kisi: {run.tagged_contacts_count}
                            </p>
                          ) : null}
                          {run.can_retry ? (
                            <Button
                              type="button"
                              variant="outline"
                              className="mt-2 h-8 px-2 text-xs"
                              onClick={() => void handleRetryRun(run)}
                              disabled={activeActionKey !== null}
                            >
                              {activeActionKey === `retry:${run.id}` ? "Retry..." : "Retry"}
                            </Button>
                          ) : null}
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
