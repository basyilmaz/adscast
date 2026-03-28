"use client";

import Link from "next/link";
import { useMemo } from "react";
import { Badge } from "@/components/ui/badge";
import { Card, CardTitle } from "@/components/ui/card";
import {
  buildDecisionQueueRecommendationClusters,
  compareDecisionQueueRecommendationClusters,
} from "@/lib/report-decision-queue";
import {
  ReportDecisionQueueRecommendationAnalyticsItem,
  ReportDecisionQueueRecommendationAnalyticsSummary,
} from "@/lib/types";

type Props = {
  summary: ReportDecisionQueueRecommendationAnalyticsSummary;
  items: ReportDecisionQueueRecommendationAnalyticsItem[];
  entityLabel: string;
  buildFocusedEntityHref: (options: { reasonCode?: string | null; surfaceKey?: string | null }) => string;
};

export function ReportDecisionQueueEntityInsightsPanel({
  summary,
  items,
  entityLabel,
  buildFocusedEntityHref,
}: Props) {
  const topReasonCluster = useMemo(
    () =>
      buildDecisionQueueRecommendationClusters(items, {
        getKey: (item) => item.dominant_reason_code ?? "unknown",
        getLabel: (item) => reasonLabel(item.dominant_reason_code),
      })
        .sort(compareDecisionQueueRecommendationClusters)[0] ?? null,
    [items],
  );
  const topSurfaceCluster = useMemo(
    () =>
      buildDecisionQueueRecommendationClusters(items, {
        getKey: (item) => (item.target_surface_keys.length === 1 ? item.target_surface_keys[0] : "multi_surface"),
        getLabel: (item) => surfaceLabel(item.target_surface_keys.length === 1 ? item.target_surface_keys[0] : "multi_surface"),
      })
        .sort(compareDecisionQueueRecommendationClusters)[0] ?? null,
    [items],
  );
  const topRecommendation = items[0] ?? null;

  return (
    <Card id="report-decision-queue-insights">
      <CardTitle>Queue Etki Ozeti</CardTitle>
      <p className="mt-2 text-sm muted-text">
        {entityLabel} icin reports merkezindeki bulk queue kararlarinin hangisinin gercekten is kapattigi burada gorulur.
      </p>

      <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <Metric label="Izlenen Oneri" value={summary.tracked_recommendations} />
        <Metric label="Uygulanan" value={summary.applied_recommendations} />
        <Metric label="Basarili Uygulama" value={summary.successful_applications} />
        <Metric label="En Iyi Sonuc" value={summary.best_success_recommendation_label ?? "-"} />
      </div>

      <div className="mt-4 grid gap-4 xl:grid-cols-3">
        <div className="rounded-lg border border-[var(--border)] p-4">
          <div className="flex flex-wrap gap-2">
            <Badge label="En Cok Izlenen Bulk Oneri" variant="neutral" />
            {topRecommendation?.item_success_rate !== null ? (
              <Badge
                label={`%${formatRate(topRecommendation.item_success_rate)} kayit basarisi`}
                variant={rateVariant(topRecommendation.item_success_rate)}
              />
            ) : null}
          </div>
          <p className="mt-3 text-sm font-semibold">{topRecommendation?.label ?? "Henuz veri yok"}</p>
          <p className="mt-2 text-sm muted-text">{topRecommendation?.guidance_message ?? topRecommendation?.outcome_summary ?? "Queue izleme verisi olusmadi."}</p>
          {topRecommendation ? (
            <div className="mt-3 flex flex-wrap gap-2 text-xs muted-text">
              <span>{topRecommendation.tracked_interactions} izleme</span>
              <span>{topRecommendation.applied_interactions} uygulama</span>
              <span>{topRecommendation.total_successful_items} basarili kayit</span>
            </div>
          ) : null}
          {topRecommendation ? (
            <Link
              href={buildFocusedEntityHref({
                reasonCode: topRecommendation.dominant_reason_code,
                surfaceKey: topRecommendation.target_surface_keys.length === 1 ? topRecommendation.target_surface_keys[0] : null,
              })}
              className="mt-3 inline-flex h-9 items-center rounded-md border border-[var(--border)] px-3 text-sm font-semibold hover:bg-[var(--surface-2)]"
            >
              Ilgili yuzeye odaklan
            </Link>
          ) : null}
        </div>

        <div className="rounded-lg border border-[var(--border)] p-4">
          <div className="flex flex-wrap gap-2">
            <Badge label="En Hizli Neden Kumesi" variant="warning" />
            {topReasonCluster?.itemSuccessRate !== null ? (
              <Badge label={`%${formatRate(topReasonCluster.itemSuccessRate)} kayit basarisi`} variant={rateVariant(topReasonCluster.itemSuccessRate)} />
            ) : null}
          </div>
          <p className="mt-3 text-sm font-semibold">{topReasonCluster?.label ?? "Henuz veri yok"}</p>
          <p className="mt-2 text-sm muted-text">
            {topReasonCluster
              ? `${topReasonCluster.trackedInteractions} izleme / ${topReasonCluster.appliedInteractions} uygulama / ${topReasonCluster.successfulItems} basarili kayit`
              : "Bu entity icin neden kumesi verisi olusmadi."}
          </p>
          {topReasonCluster?.primaryEntity?.surface_key ? (
            <p className="mt-2 text-xs muted-text">Baskin yuzey: {surfaceLabel(topReasonCluster.primaryEntity.surface_key)}</p>
          ) : null}
          {topReasonCluster ? (
            <Link
              href={buildFocusedEntityHref({
                reasonCode: topReasonCluster.key === "unknown" ? null : topReasonCluster.key,
                surfaceKey: topReasonCluster.primaryEntity?.surface_key ?? null,
              })}
              className="mt-3 inline-flex h-9 items-center rounded-md border border-[var(--border)] px-3 text-sm font-semibold hover:bg-[var(--surface-2)]"
            >
              Bu cluster ile ac
            </Link>
          ) : null}
        </div>

        <div className="rounded-lg border border-[var(--border)] p-4">
          <div className="flex flex-wrap gap-2">
            <Badge label="En Hizli Yuzey Kumesi" variant="neutral" />
            {topSurfaceCluster?.itemSuccessRate !== null ? (
              <Badge label={`%${formatRate(topSurfaceCluster.itemSuccessRate)} kayit basarisi`} variant={rateVariant(topSurfaceCluster.itemSuccessRate)} />
            ) : null}
          </div>
          <p className="mt-3 text-sm font-semibold">{topSurfaceCluster?.label ?? "Henuz veri yok"}</p>
          <p className="mt-2 text-sm muted-text">
            {topSurfaceCluster
              ? `${topSurfaceCluster.trackedInteractions} izleme / ${topSurfaceCluster.appliedInteractions} uygulama / ${topSurfaceCluster.successfulItems} basarili kayit`
              : "Bu entity icin yuzey kumesi verisi olusmadi."}
          </p>
          {topSurfaceCluster?.primaryEntity?.label ? (
            <p className="mt-2 text-xs muted-text">
              Baskin baglam: {topSurfaceCluster.primaryEntity.label}
              {topSurfaceCluster.primaryEntity.context_label ? ` / ${topSurfaceCluster.primaryEntity.context_label}` : ""}
            </p>
          ) : null}
          {topSurfaceCluster ? (
            <Link
              href={buildFocusedEntityHref({
                surfaceKey: topSurfaceCluster.key === "multi_surface" ? null : topSurfaceCluster.key,
              })}
              className="mt-3 inline-flex h-9 items-center rounded-md border border-[var(--border)] px-3 text-sm font-semibold hover:bg-[var(--surface-2)]"
            >
              Bu yuzey ile ac
            </Link>
          ) : null}
        </div>
      </div>
    </Card>
  );
}

function Metric({ label, value }: { label: string; value: number | string }) {
  return (
    <div className="rounded-lg border border-[var(--border)] px-3 py-3">
      <p className="text-xs font-semibold uppercase tracking-wide muted-text">{label}</p>
      <p className="mt-2 text-2xl font-semibold">{value}</p>
    </div>
  );
}

function formatRate(value: number | null) {
  return value === null ? "-" : value.toFixed(1);
}

function rateVariant(value: number | null): "success" | "warning" | "danger" | "neutral" {
  if (value === null) return "neutral";
  if (value >= 80) return "success";
  if (value >= 40) return "warning";
  return "danger";
}

function surfaceLabel(value: string) {
  if (value === "featured_fix") return "Hizli Duzeltme";
  if (value === "retry") return "Retry Rehberi";
  if (value === "profile") return "Profil Onerisi";
  if (value === "multi_surface") return "Coklu Yuzey";
  return value;
}

function reasonLabel(value: string | null) {
  if (!value) return "Bilinmeyen Neden";
  if (value === "unknown") return "Bilinmeyen Neden";
  if (value === "none") return "Nedeni Girilmemis";
  if (value === "blocked_external_dependency") return "Dis Bagimlilik Engeli";
  if (value === "waiting_data_validation") return "Veri Dogrulamasi Bekleniyor";
  if (value === "waiting_client_feedback") return "Musteri Donusu Bekleniyor";
  if (value === "scheduled_followup") return "Planli Takip Bekleniyor";
  if (value === "priority_window_shifted") return "Oncelik Penceresi Degisti";
  return value;
}
