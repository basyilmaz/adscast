"use client";

import Link from "next/link";
import { useMemo } from "react";
import { Badge } from "@/components/ui/badge";
import {
  buildDecisionQueueRecommendationClusters,
  compareDecisionQueueRecommendationClusters,
  DecisionQueueRecommendationCluster,
} from "@/lib/report-decision-queue";
import {
  ReportDecisionQueueRecommendationAnalyticsItem,
  ReportDecisionQueueRecommendationAnalyticsSummary,
} from "@/lib/types";

type Props = {
  summary: ReportDecisionQueueRecommendationAnalyticsSummary | null;
  items: ReportDecisionQueueRecommendationAnalyticsItem[];
  featuredRecommendationCode?: string | null;
  featuredRecommendationStrategy?: "safety_override" | "analytics_boosted" | "static_priority" | null;
  focusedRecommendationCode?: string | null;
  focusedReasonCode?: string | null;
  focusedSurfaceKey?: string | null;
  buildQueueFocusHref: (item: ReportDecisionQueueRecommendationAnalyticsItem) => string;
  buildQueueClusterHref: (options: { reasonCode?: string | null; surfaceKey?: string | null; focusSource?: string | null }) => string;
  buildEntityDetailHref: (route: string, options?: { reasonCode?: string | null; surfaceKey?: string | null; focusSource?: string | null }) => string;
};

export function ReportDecisionQueueRecommendationAnalyticsPanel({
  summary,
  items,
  featuredRecommendationCode,
  featuredRecommendationStrategy,
  focusedRecommendationCode,
  focusedReasonCode,
  focusedSurfaceKey,
  buildQueueFocusHref,
  buildQueueClusterHref,
  buildEntityDetailHref,
}: Props) {
  const reasonClusters = useMemo(
    () =>
      buildDecisionQueueRecommendationClusters(items, {
        getKey: (item) => item.dominant_reason_code ?? "unknown",
        getLabel: (item) => reasonLabel(item.dominant_reason_code),
      })
        .sort(compareDecisionQueueRecommendationClusters)
        .slice(0, 4),
    [items],
  );

  const surfaceClusters = useMemo(
    () =>
      buildDecisionQueueRecommendationClusters(items, {
        getKey: (item) => (item.target_surface_keys.length === 1 ? item.target_surface_keys[0] : "multi_surface"),
        getLabel: (item) => surfaceGroupLabel(item.target_surface_keys.length === 1 ? item.target_surface_keys[0] : "multi_surface"),
      })
        .sort(compareDecisionQueueRecommendationClusters)
        .slice(0, 3),
    [items],
  );
  const topReasonPerformanceCluster = reasonClusters[0] ?? null;
  const topSurfacePerformanceCluster = surfaceClusters[0] ?? null;

  if (items.length === 0) {
    return <p className="text-sm muted-text">Queue onerileri icin henuz analytics verisi olusmadi.</p>;
  }

  return (
    <div className="space-y-4">
      {summary ? (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
          <SummaryMetric label="Izlenen Oneri" value={summary.tracked_recommendations} />
          <SummaryMetric label="Secim" value={summary.selection_only_recommendations} />
          <SummaryMetric label="Uygulama" value={summary.applied_recommendations} />
          <SummaryMetric label="Basarili" value={summary.successful_applications} />
          <SummaryMetric label="Kismi" value={summary.partial_applications} />
          <SummaryMetric label="Basarisiz" value={summary.failed_applications} />
        </div>
      ) : null}

      {topReasonPerformanceCluster || topSurfacePerformanceCluster ? (
        <div className="grid gap-4 xl:grid-cols-[1fr_1fr]">
          {topReasonPerformanceCluster ? (
            <ClusterPerformanceSpotlight
              title="En Hizli Is Kapatan Neden Kumesi"
              cluster={topReasonPerformanceCluster}
              variant="warning"
              queueHref={buildQueueClusterHref({
                reasonCode: topReasonPerformanceCluster.key,
                focusSource: "queue_analytics_cluster_performance",
              })}
              focusReasonCode={topReasonPerformanceCluster.key}
              buildEntityDetailHref={buildEntityDetailHref}
            />
          ) : null}
          {topSurfacePerformanceCluster ? (
            <ClusterPerformanceSpotlight
              title="En Hizli Is Kapatan Yuzey Kumesi"
              cluster={topSurfacePerformanceCluster}
              variant="neutral"
              queueHref={buildQueueClusterHref({
                surfaceKey: topSurfacePerformanceCluster.key === "multi_surface" ? null : topSurfacePerformanceCluster.key,
                focusSource: "queue_analytics_cluster_performance",
              })}
              focusSurfaceKey={topSurfacePerformanceCluster.key === "multi_surface" ? null : topSurfacePerformanceCluster.key}
              buildEntityDetailHref={buildEntityDetailHref}
            />
          ) : null}
        </div>
      ) : null}

      {reasonClusters.length > 0 || surfaceClusters.length > 0 ? (
        <div className="grid gap-4 xl:grid-cols-[1fr_1fr]">
          <div className="space-y-3 rounded-lg border border-[var(--border)] bg-[var(--surface-2)] p-4">
            <div className="flex flex-wrap items-center gap-2">
              <Badge label="Hizli Neden Kumeleme" variant="warning" />
              {focusedReasonCode && focusedReasonCode !== "all" ? <Badge label={`${reasonLabel(focusedReasonCode)} odakta`} variant="success" /> : null}
            </div>
            <div className="grid gap-3 md:grid-cols-2">
              {reasonClusters.map((cluster) => (
                <Link
                  key={cluster.key}
                  href={buildQueueClusterHref({
                    reasonCode: cluster.key,
                    focusSource: "queue_analytics_cluster",
                  })}
                  className={[
                    "rounded-lg border border-[var(--border)] bg-white p-3 hover:bg-[var(--surface)]",
                    focusedReasonCode === cluster.key ? "ring-2 ring-[var(--accent)]/20" : "",
                  ].join(" ").trim()}
                >
                  <div className="flex flex-wrap gap-2">
                    <Badge label={cluster.label} variant="warning" />
                    {focusedReasonCode === cluster.key ? <Badge label="Odakta" variant="success" /> : null}
                    {topReasonPerformanceCluster?.key === cluster.key ? <Badge label="Performans Lideri" variant="success" /> : null}
                    {cluster.applicationRate !== null ? (
                      <Badge label={`%${formatRateValue(cluster.applicationRate)} uygulama`} variant={rateVariant(cluster.applicationRate)} />
                    ) : null}
                    {cluster.itemSuccessRate !== null ? (
                      <Badge label={`%${formatRateValue(cluster.itemSuccessRate)} kayit basarisi`} variant={rateVariant(cluster.itemSuccessRate)} />
                    ) : null}
                  </div>
                  <p className="mt-2 text-sm font-semibold">{cluster.recommendations} recommendation</p>
                  <p className="mt-1 text-xs muted-text">
                    {cluster.trackedInteractions} izleme / {cluster.appliedInteractions} uygulama / {cluster.successfulItems} basarili kayit
                  </p>
                  {cluster.primaryEntity ? (
                    <p className="mt-2 text-xs muted-text">
                      Baskin entity: {cluster.primaryEntity.label ?? "Bilinmeyen varlik"}
                      {cluster.primaryEntity.context_label ? ` / ${cluster.primaryEntity.context_label}` : ""}
                    </p>
                  ) : null}
                </Link>
              ))}
            </div>
          </div>

          <div className="space-y-3 rounded-lg border border-[var(--border)] bg-[var(--surface-2)] p-4">
            <div className="flex flex-wrap items-center gap-2">
              <Badge label="Hizli Yuzey Kumeleme" variant="neutral" />
              {focusedSurfaceKey && focusedSurfaceKey !== "all" ? <Badge label={`${surfaceLabel(focusedSurfaceKey)} odakta`} variant="success" /> : null}
            </div>
            <div className="grid gap-3 md:grid-cols-2">
              {surfaceClusters.map((cluster) => (
                <Link
                  key={cluster.key}
                  href={buildQueueClusterHref({
                    surfaceKey: cluster.key === "multi_surface" ? null : cluster.key,
                    focusSource: "queue_analytics_cluster",
                  })}
                  className={[
                    "rounded-lg border border-[var(--border)] bg-white p-3 hover:bg-[var(--surface)]",
                    focusedSurfaceKey === cluster.key ? "ring-2 ring-[var(--accent)]/20" : "",
                  ].join(" ").trim()}
                >
                  <div className="flex flex-wrap gap-2">
                    <Badge label={cluster.label} variant="neutral" />
                    {focusedSurfaceKey === cluster.key ? <Badge label="Odakta" variant="success" /> : null}
                    {topSurfacePerformanceCluster?.key === cluster.key ? <Badge label="Performans Lideri" variant="success" /> : null}
                    {cluster.applicationRate !== null ? (
                      <Badge label={`%${formatRateValue(cluster.applicationRate)} uygulama`} variant={rateVariant(cluster.applicationRate)} />
                    ) : null}
                    {cluster.itemSuccessRate !== null ? (
                      <Badge label={`%${formatRateValue(cluster.itemSuccessRate)} kayit basarisi`} variant={rateVariant(cluster.itemSuccessRate)} />
                    ) : null}
                  </div>
                  <p className="mt-2 text-sm font-semibold">{cluster.recommendations} recommendation</p>
                  <p className="mt-1 text-xs muted-text">
                    {cluster.trackedInteractions} izleme / {cluster.appliedInteractions} uygulama / {cluster.successfulItems} basarili kayit
                  </p>
                  {cluster.primaryEntity ? (
                    <p className="mt-2 text-xs muted-text">
                      Baskin entity: {cluster.primaryEntity.label ?? "Bilinmeyen varlik"}
                      {cluster.primaryEntity.context_label ? ` / ${cluster.primaryEntity.context_label}` : ""}
                    </p>
                  ) : null}
                </Link>
              ))}
            </div>
          </div>
        </div>
      ) : null}

      <div className="space-y-3">
        {items.map((item) => (
          <div
            key={item.recommendation_code}
            className={[
              "rounded-lg border border-[var(--border)] p-3",
              item.recommendation_code === focusedRecommendationCode ? "ring-2 ring-[var(--accent)]/20" : "",
            ].join(" ").trim()}
          >
            <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
              <div className="space-y-2">
                <div className="flex flex-wrap gap-2">
                  <Badge label={item.label} variant={variantForGuidance(item.guidance_variant)} />
                  {item.suggested_status_label ? <Badge label={item.suggested_status_label} variant="neutral" /> : null}
                  {item.recommendation_code === featuredRecommendationCode ? (
                    <Badge
                      label={featuredStrategyLabel(featuredRecommendationStrategy)}
                      variant={featuredRecommendationStrategy === "analytics_boosted" ? "success" : featuredRecommendationStrategy === "safety_override" ? "danger" : "neutral"}
                    />
                  ) : null}
                  {item.recommendation_code === focusedRecommendationCode ? <Badge label="Odakta" variant="success" /> : null}
                  <Badge label={`${item.tracked_interactions} izleme`} variant="neutral" />
                  {item.item_success_rate !== null ? (
                    <Badge label={`%${formatRateValue(item.item_success_rate)} kayit basarisi`} variant={rateVariant(item.item_success_rate)} />
                  ) : null}
                </div>

                <p className="text-sm muted-text">{item.guidance_message ?? item.outcome_summary}</p>
                <p className="text-xs muted-text">{item.health_summary}</p>
                <p className="text-xs muted-text">
                  Son izleme: {item.last_tracked_at ?? "-"}
                  {item.top_priority_group_label ? ` / Oncelik grubu: ${item.top_priority_group_label}` : ""}
                  {item.dominant_reason_code ? ` / Baskin neden: ${item.dominant_reason_code}` : ""}
                </p>
                <div className="flex flex-wrap gap-2 text-xs muted-text">
                  <span>Secim: {item.selection_only_interactions}</span>
                  <span>Uygulama: {item.applied_interactions}</span>
                  <span>Hedef kayit: {item.total_target_items}</span>
                  <span>Denenen: {item.total_attempted_items}</span>
                  <span>Basarili kayit: {item.total_successful_items}</span>
                  <span>Hatali kayit: {item.total_failed_items}</span>
                </div>
                <div className="pt-1">
                  <Link
                    href={buildQueueFocusHref(item)}
                    className="inline-flex h-9 items-center rounded-md border border-[var(--border)] px-3 text-sm font-semibold hover:bg-[var(--surface-2)]"
                  >
                    Kuyrukta incele
                  </Link>
                </div>
              </div>

              <div className="xl:w-[360px]">
                <p className="text-xs font-semibold uppercase tracking-wide muted-text">En Cok Etkilenen Varliklar</p>
                <div className="mt-2 space-y-2">
                  {item.entities.map((entity) => (
                    <div key={`${entity.entity_type}:${entity.entity_id}:${entity.surface_key}`} className="rounded-md bg-[var(--surface-2)] px-3 py-2">
                      <div className="flex flex-wrap items-center gap-2">
                        <Badge label={entityTypeLabel(entity.entity_type)} variant="neutral" />
                        <Badge label={surfaceLabel(entity.surface_key)} variant="neutral" />
                        <span className="text-xs muted-text">{entity.uses_count} iz</span>
                      </div>
                      <p className="mt-1 text-sm font-semibold">{entity.label ?? "Bilinmeyen varlik"}</p>
                      {entity.context_label ? <p className="text-xs muted-text">{entity.context_label}</p> : null}
                    </div>
                  ))}
                  {item.entities.length === 0 ? <p className="text-sm muted-text">Entity baglami henuz olusmadi.</p> : null}
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function ClusterPerformanceSpotlight({
  title,
  cluster,
  variant,
  queueHref,
  focusReasonCode,
  focusSurfaceKey,
  buildEntityDetailHref,
}: {
  title: string;
  cluster: DecisionQueueRecommendationCluster;
  variant: "warning" | "neutral";
  queueHref: string;
  focusReasonCode?: string | null;
  focusSurfaceKey?: string | null;
  buildEntityDetailHref: (route: string, options?: { reasonCode?: string | null; surfaceKey?: string | null; focusSource?: string | null }) => string;
}) {
  return (
    <div className="rounded-lg border border-[var(--border)] bg-[var(--surface-2)] p-4">
      <div className="flex flex-wrap items-center gap-2">
        <Badge label={title} variant={variant} />
        <Badge label={cluster.label} variant="success" />
      </div>
      <div className="mt-3 grid gap-3 md:grid-cols-3">
        <SummaryMetric label="Izleme" value={cluster.trackedInteractions} />
        <SummaryMetric label="Uygulama" value={cluster.applicationRate !== null ? `%${formatRateValue(cluster.applicationRate)}` : "-"} />
        <SummaryMetric label="Kayit Basarisi" value={cluster.itemSuccessRate !== null ? `%${formatRateValue(cluster.itemSuccessRate)}` : "-"} />
      </div>
      <p className="mt-3 text-sm muted-text">
        {cluster.successfulItems} basarili kayit / {cluster.recommendations} recommendation
      </p>
      {cluster.primaryEntity ? (
        <div className="mt-3 rounded-lg border border-[var(--border)] bg-white p-3">
          <p className="text-xs font-semibold uppercase tracking-wide muted-text">Baskin Entity</p>
          <p className="mt-1 text-sm font-semibold">{cluster.primaryEntity.label ?? "Bilinmeyen varlik"}</p>
          {cluster.primaryEntity.context_label ? <p className="mt-1 text-xs muted-text">{cluster.primaryEntity.context_label}</p> : null}
          <p className="mt-1 text-xs muted-text">{cluster.primaryEntity.uses_count} izleme baglami</p>
          <div className="mt-3 flex flex-wrap gap-3">
            {cluster.primaryEntity.route ? (
              <Link
                href={buildEntityDetailHref(cluster.primaryEntity.route, {
                  reasonCode: focusReasonCode,
                  surfaceKey: focusSurfaceKey ?? cluster.primaryEntity.surface_key ?? null,
                  focusSource: "queue_analytics_cluster_performance",
                })}
                className="inline-flex h-9 items-center rounded-md border border-[var(--border)] px-3 text-sm font-semibold hover:bg-[var(--surface-2)]"
              >
                Entity detayina git
              </Link>
            ) : null}
            <Link
              href={queueHref}
              className="inline-flex h-9 items-center rounded-md border border-[var(--border)] px-3 text-sm font-semibold hover:bg-[var(--surface-2)]"
            >
              Kuyrukta incele
            </Link>
          </div>
        </div>
      ) : (
        <div className="mt-3">
          <Link
            href={queueHref}
            className="inline-flex h-9 items-center rounded-md border border-[var(--border)] px-3 text-sm font-semibold hover:bg-[var(--surface-2)]"
          >
            Kuyrukta incele
          </Link>
        </div>
      )}
    </div>
  );
}

function variantForGuidance(value: string): "success" | "warning" | "danger" | "neutral" {
  if (value === "success") return "success";
  if (value === "danger") return "danger";
  if (value === "warning") return "warning";

  return "neutral";
}

function rateVariant(value: number | null): "success" | "warning" | "danger" | "neutral" {
  if (value === null) {
    return "neutral";
  }

  if (value >= 80) {
    return "success";
  }

  if (value >= 40) {
    return "warning";
  }

  return "danger";
}

function formatRateValue(value: number | null): string {
  return value === null ? "-" : value.toFixed(1);
}

function entityTypeLabel(value: string): string {
  if (value === "account") return "Reklam Hesabi";
  if (value === "campaign") return "Kampanya";

  return value;
}

function surfaceLabel(value: string): string {
  if (value === "featured_fix") return "Hizli Duzeltme";
  if (value === "retry") return "Retry Rehberi";
  if (value === "profile") return "Profil Onerisi";
  if (value === "multi_surface") return "Coklu Yuzey";

  return value;
}

function surfaceGroupLabel(value: string): string {
  return surfaceLabel(value);
}

function reasonLabel(value: string | null): string {
  if (!value) return "Bilinmeyen Neden";
  if (value === "none") return "Nedeni Girilmemis";
  if (value === "blocked_external_dependency") return "Dis Bagimlilik Engeli";
  if (value === "waiting_data_validation") return "Veri Dogrulamasi Bekleniyor";
  if (value === "waiting_client_feedback") return "Musteri Donusu Bekleniyor";
  if (value === "scheduled_followup") return "Planli Takip Bekleniyor";
  if (value === "priority_window_shifted") return "Oncelik Penceresi Degisti";

  return value;
}

function featuredStrategyLabel(value: Props["featuredRecommendationStrategy"]) {
  if (value === "analytics_boosted") return "Queue'da Analytics Destekli";
  if (value === "safety_override") return "Queue'da Guvenlik Oncelemesi";
  if (value === "static_priority") return "Queue'da Oneriliyor";

  return "Queue'da Oneriliyor";
}

function SummaryMetric({ label, value }: { label: string; value: number | string }) {
  return (
    <div className="rounded-lg border border-[var(--border)] px-3 py-3">
      <p className="text-xs font-semibold uppercase tracking-wide muted-text">{label}</p>
      <p className="mt-2 text-2xl font-semibold">{value}</p>
    </div>
  );
}
