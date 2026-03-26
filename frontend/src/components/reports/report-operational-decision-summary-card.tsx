"use client";

import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardTitle } from "@/components/ui/card";
import {
  actionLabelForCode,
  deliveryProfileSuggestionExplanation,
  featuredRecommendationExplanation,
  focusReportDecisionSurface,
  focusSourceLabel,
  focusedRetryExplanation,
  isFocusedDeliveryProfileSuggestion,
  prioritizeFocusedRetryRecommendations,
  reasonLabelForCode,
  ReportDecisionSurfaceKey,
} from "@/lib/report-failure-focus";
import {
  ReportDeliveryProfileSuggestion,
  ReportDecisionSurfaceStatusItem,
  ReportDecisionSurfaceStatusSummary,
  ReportDeliveryRetryRecommendationItem,
  ReportDeliveryRetryRecommendationSummary,
  ReportFeaturedFailureResolution,
} from "@/lib/types";

type Props = {
  entityLabel: string;
  featuredRecommendation?: ReportFeaturedFailureResolution | null;
  retrySummary: ReportDeliveryRetryRecommendationSummary;
  retryItems: ReportDeliveryRetryRecommendationItem[];
  suggestion?: ReportDeliveryProfileSuggestion | null;
  decisionSurfaceStatusSummary: ReportDecisionSurfaceStatusSummary;
  decisionSurfaceStatuses: ReportDecisionSurfaceStatusItem[];
  focusActionCode?: string | null;
  focusReasonCode?: string | null;
  focusSource?: string | null;
};

type SurfaceDecision = {
  key: "featured_fix" | "retry" | "profile";
  surfaceLabel: string;
  title: string;
  detail: string;
  priority: number;
  badges: string[];
  statusLabel?: string;
};

export function ReportOperationalDecisionSummaryCard({
  entityLabel,
  featuredRecommendation,
  retrySummary,
  retryItems,
  suggestion,
  decisionSurfaceStatusSummary,
  decisionSurfaceStatuses,
  focusActionCode,
  focusReasonCode,
  focusSource,
}: Props) {
  const prioritizedRetryItems = prioritizeFocusedRetryRecommendations(
    retryItems,
    focusActionCode,
    focusReasonCode,
    featuredRecommendation,
  );
  const topRetryItem = prioritizedRetryItems[0] ?? null;
  const focusProfile = suggestion
    ? isFocusedDeliveryProfileSuggestion(suggestion, focusActionCode, focusReasonCode, featuredRecommendation)
    : false;
  const statusMap = new Map(
    decisionSurfaceStatuses.map((item) => [item.surface_key, item] as const),
  );

  const decisions = [
    buildFeaturedDecision(
      featuredRecommendation,
      statusMap.get("featured_fix") ?? null,
      focusActionCode,
      focusReasonCode,
      focusSource,
    ),
    buildRetryDecision(
      topRetryItem,
      retrySummary,
      statusMap.get("retry") ?? null,
      focusActionCode,
      focusReasonCode,
      focusSource,
      featuredRecommendation,
    ),
    buildProfileDecision(
      suggestion,
      statusMap.get("profile") ?? null,
      focusProfile,
      focusActionCode,
      focusReasonCode,
      focusSource,
      featuredRecommendation,
    ),
  ].filter((value): value is SurfaceDecision => value !== null);

  const primaryDecision = decisions.sort((left, right) => right.priority - left.priority)[0] ?? null;

  return (
    <Card>
      <CardTitle>Operasyon Karari Ozeti</CardTitle>
      <p className="mt-2 text-sm muted-text">
        {entityLabel} icin hizli aksiyon, retry rehberi ve profil onerisi ayni karar cizgisinde toplandi.
      </p>

      {(focusActionCode || focusReasonCode) ? (
        <div className="mt-3 rounded-lg border border-[var(--accent)]/30 bg-[var(--accent)]/5 px-3 py-2 text-sm">
          <p className="font-semibold">Rapor merkezinden odak geldi</p>
          <p className="mt-1 muted-text">
            {focusReasonCode ? `Hata nedeni: ${reasonLabelForCode(focusReasonCode)}` : "Belirli operasyon odagi"}
            {focusActionCode ? ` / Aksiyon: ${actionLabelForCode(focusActionCode)}` : ""}
            {focusSource ? ` / Kaynak: ${focusSourceLabel(focusSource)}` : ""}
          </p>
        </div>
      ) : null}

      <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <Metric label="Beklemede" value={decisionSurfaceStatusSummary.pending_surfaces} />
        <Metric label="Gozden Gecirildi" value={decisionSurfaceStatusSummary.reviewed_surfaces} />
        <Metric label="Tamamlandi" value={decisionSurfaceStatusSummary.completed_surfaces} />
        <Metric label="Ertelendi" value={decisionSurfaceStatusSummary.deferred_surfaces} />
      </div>

      {primaryDecision ? (
        <div className="mt-4 rounded-lg border border-[var(--accent)]/30 bg-[var(--accent)]/5 p-4">
          <div className="flex flex-wrap items-center gap-2">
            <Badge label="Simdi Once" variant="warning" />
            <Badge label={primaryDecision.surfaceLabel} variant="neutral" />
            {primaryDecision.statusLabel ? <Badge label={primaryDecision.statusLabel} variant="neutral" /> : null}
            {primaryDecision.badges.map((badge) => (
              <Badge key={badge} label={badge} variant="success" />
            ))}
          </div>
          <p className="mt-3 text-base font-semibold">{primaryDecision.title}</p>
          <p className="mt-2 text-sm muted-text">{primaryDecision.detail}</p>
          <div className="mt-3">
            <Button type="button" size="sm" variant="secondary" onClick={() => focusReportDecisionSurface(primaryDecision.key)}>
              Ilgili karta git
            </Button>
          </div>
        </div>
      ) : (
        <div className="mt-4 rounded-lg border border-[var(--border)] px-4 py-3 text-sm muted-text">
          Bu kayit icin birincil operasyon karari olusturacak yeterli retry, featured fix veya profil onerisi bulunmuyor.
        </div>
      )}

      <div className="mt-4 grid gap-3 xl:grid-cols-3">
        <SurfaceSummary
          surfaceKey="featured_fix"
          title="Hizli Duzeltme"
          value={featuredRecommendation?.action_label ?? "Kayitli featured fix yok"}
          note={featuredRecommendation?.reason_label ?? "Failure reason odagi yok"}
          statusItem={statusMap.get("featured_fix") ?? null}
          onJump={focusReportDecisionSurface}
        />
        <SurfaceSummary
          surfaceKey="retry"
          title="Retry Rehberi"
          value={topRetryItem ? `${topRetryItem.label} / ${topRetryItem.retry_policy_label}` : "Kayitli retry karari yok"}
          note={topRetryItem?.operator_note ?? "Retry odagi yok"}
          statusItem={statusMap.get("retry") ?? null}
          onJump={focusReportDecisionSurface}
        />
        <SurfaceSummary
          surfaceKey="profile"
          title="Profil Onerisi"
          value={suggestion?.recipient_preset_name ?? "Kayitli profil onerisi yok"}
          note={suggestion?.reason ?? "Profil odagi yok"}
          statusItem={statusMap.get("profile") ?? null}
          onJump={focusReportDecisionSurface}
        />
      </div>
    </Card>
  );
}

function buildFeaturedDecision(
  featuredRecommendation?: ReportFeaturedFailureResolution | null,
  statusItem?: ReportDecisionSurfaceStatusItem | null,
  focusActionCode?: string | null,
  focusReasonCode?: string | null,
  focusSource?: string | null,
): SurfaceDecision | null {
  if (!featuredRecommendation) {
    return null;
  }

  let priority = 40;
  const badges: string[] = [];

  if (focusActionCode && featuredRecommendation.action_code === focusActionCode) {
    priority += 30;
    badges.push("Odakla Uyumlu");
  }

  if (focusReasonCode && featuredRecommendation.reason_code === focusReasonCode) {
    priority += 20;
  }

  if (featuredRecommendation.status === "working_fix") {
    priority += 15;
    badges.push("Calisiyor");
  }

  const adjusted = withStatusWeight(priority, badges, statusItem);

  return {
    key: "featured_fix",
    surfaceLabel: "Featured Fix",
    title: featuredRecommendation.action_label,
    detail: featuredRecommendationExplanation(featuredRecommendation, focusActionCode, focusReasonCode, focusSource),
    priority: adjusted.priority,
    badges: adjusted.badges,
    statusLabel: statusItem?.status_label,
  };
}

function buildRetryDecision(
  topRetryItem: ReportDeliveryRetryRecommendationItem | null,
  retrySummary: ReportDeliveryRetryRecommendationSummary,
  statusItem?: ReportDecisionSurfaceStatusItem | null,
  focusActionCode?: string | null,
  focusReasonCode?: string | null,
  focusSource?: string | null,
  featuredRecommendation?: ReportFeaturedFailureResolution | null,
): SurfaceDecision | null {
  if (!topRetryItem) {
    return null;
  }

  let priority = 30;
  const badges: string[] = [];

  if (focusActionCode && topRetryItem.primary_action_code === focusActionCode) {
    priority += 25;
    badges.push("Odakla Uyumlu");
  }

  if (focusReasonCode && topRetryItem.reason_code === focusReasonCode) {
    priority += 15;
  }

  if (featuredRecommendation
    && (featuredRecommendation.reason_code === topRetryItem.reason_code
      || featuredRecommendation.action_code === topRetryItem.primary_action_code)) {
    priority += 10;
    badges.push("Featured ile Hizali");
  }

  if (retrySummary.auto_retry_recommendations > 0 && topRetryItem.retry_policy === "auto_retry") {
    priority += 10;
  }

  const adjusted = withStatusWeight(priority, badges, statusItem);

  return {
    key: "retry",
    surfaceLabel: "Retry Rehberi",
    title: `${topRetryItem.label} / ${topRetryItem.retry_policy_label}`,
    detail: focusedRetryExplanation(
      topRetryItem,
      focusActionCode,
      focusReasonCode,
      focusSource,
      featuredRecommendation,
    ),
    priority: adjusted.priority,
    badges: adjusted.badges,
    statusLabel: statusItem?.status_label,
  };
}

function buildProfileDecision(
  suggestion: ReportDeliveryProfileSuggestion | null | undefined,
  statusItem: ReportDecisionSurfaceStatusItem | null | undefined,
  isFocused: boolean,
  focusActionCode?: string | null,
  focusReasonCode?: string | null,
  focusSource?: string | null,
  featuredRecommendation?: ReportFeaturedFailureResolution | null,
): SurfaceDecision | null {
  if (!suggestion) {
    return null;
  }

  let priority = 20;
  const badges: string[] = [];

  if (focusActionCode === "focus_delivery_profile") {
    priority += 35;
    badges.push("Odakla Uyumlu");
  }

  if (isFocused) {
    priority += 15;
  }

  if (featuredRecommendation?.action_code === "focus_delivery_profile") {
    priority += 10;
    badges.push("Featured ile Hizali");
  }

  if (suggestion.can_apply) {
    badges.push("Tek Tikla Uygulanabilir");
  }

  const adjusted = withStatusWeight(priority, badges, statusItem);

  return {
    key: "profile",
    surfaceLabel: "Profil Onerisi",
    title: suggestion.can_apply ? "Teslim profilini uygula" : suggestion.recipient_preset_name,
    detail: deliveryProfileSuggestionExplanation(
      suggestion,
      focusActionCode,
      focusReasonCode,
      focusSource,
      featuredRecommendation,
    ),
    priority: adjusted.priority,
    badges: adjusted.badges,
    statusLabel: statusItem?.status_label,
  };
}

function SurfaceSummary({
  surfaceKey,
  title,
  value,
  note,
  statusItem,
  onJump,
}: {
  surfaceKey: ReportDecisionSurfaceKey;
  title: string;
  value: string;
  note: string;
  statusItem: ReportDecisionSurfaceStatusItem | null;
  onJump: (key: ReportDecisionSurfaceKey) => void;
}) {
  return (
    <div className="rounded-lg border border-[var(--border)] px-4 py-3">
      <div className="flex flex-wrap items-center gap-2">
        <p className="text-xs font-semibold uppercase tracking-wide muted-text">{title}</p>
        {statusItem ? <Badge label={statusItem.status_label} variant="neutral" /> : null}
      </div>
      <p className="mt-2 text-sm font-semibold">{value}</p>
      <p className="mt-2 text-xs muted-text">{note}</p>
      {statusItem?.updated_at ? (
        <p className="mt-2 text-xs muted-text">
          {statusItem.updated_by_name ?? "Operator"} / {statusItem.updated_at}
        </p>
      ) : null}
      <div className="mt-3">
        <Button type="button" size="sm" variant="secondary" onClick={() => onJump(surfaceKey)}>
          Yuzeye git
        </Button>
      </div>
    </div>
  );
}

function Metric({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-lg border border-[var(--border)] px-3 py-3">
      <p className="text-xs font-semibold uppercase tracking-wide muted-text">{label}</p>
      <p className="mt-2 text-2xl font-semibold">{value}</p>
    </div>
  );
}

function withStatusWeight(
  priority: number,
  badges: string[],
  statusItem?: ReportDecisionSurfaceStatusItem | null,
) {
  if (!statusItem) {
    return { priority, badges };
  }

  let nextPriority = priority;

  if (statusItem.status === "reviewed") {
    nextPriority -= 10;
  } else if (statusItem.status === "deferred") {
    nextPriority -= 20;
  } else if (statusItem.status === "completed") {
    nextPriority -= 30;
  }

  return {
    priority: nextPriority,
    badges,
  };
}
