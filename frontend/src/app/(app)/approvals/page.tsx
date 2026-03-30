"use client";

import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useMemo, useState } from "react";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { invalidateApiCache } from "@/lib/api-cache";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { useApiQuery } from "@/hooks/use-api-query";

type Approval = {
  id: string;
  status: string;
  approvable_type_label: string;
  approvable_type: string;
  approvable_id: string;
  approvable_label: string;
  approvable_route: string | null;
  submitted_at: string | null;
  approved_at: string | null;
  rejected_at: string | null;
  published_at: string | null;
  rejection_reason: string | null;
  publish_state: {
    status: string | null;
    success: boolean | null;
    message: string | null;
    meta_campaign_id: string | null;
    meta_ad_set_id: string | null;
    partial_publish_detected: boolean;
    cleanup_attempted: boolean;
    cleanup_success: boolean | null;
    cleanup_message: string | null;
    manual_check_required: boolean;
    manual_check_completed: boolean;
    manual_check_completed_at: string | null;
    manual_check_completed_by: string | null;
    manual_check_note: string | null;
    recommended_action_code: string | null;
    recommended_action_label: string | null;
    operator_guidance: string | null;
  } | null;
};

type ApprovalResponse = {
  data: {
    data: Approval[];
  };
};

type ApprovalRemediationAnalyticsResponse = {
  data: {
    summary: {
      tracked_clusters: number;
      current_publish_failed: number;
      retry_ready_items: number;
      manual_check_required_items: number;
      tracked_manual_checks: number;
      tracked_publish_attempts: number;
      successful_publish_attempts: number;
      top_working_cluster_label: string | null;
      top_effective_cluster_label: string | null;
      top_effective_cluster_score: number | null;
      featured_cluster_label: string | null;
      top_draft_detail_cluster_label: string | null;
      top_long_term_stable_cluster_label?: string | null;
      top_long_term_stable_cluster_score?: number | null;
      tracked_sources_count: number;
      top_interaction_source_key: string | null;
      top_interaction_source_label: string | null;
      top_success_source_key: string | null;
      top_success_source_label: string | null;
      top_route_key?: string | null;
      top_route_label?: string | null;
      top_route_source_key?: string | null;
      top_route_source_label?: string | null;
      top_route_publish_success_rate?: number | null;
      top_route_advantage?: number | null;
      top_long_term_route_key?: string | null;
      top_long_term_route_label?: string | null;
      top_long_term_route_source_key?: string | null;
      top_long_term_route_source_label?: string | null;
      top_long_term_route_publish_success_rate?: number | null;
      top_long_term_route_advantage?: number | null;
      tracked_featured_interactions: number;
      followed_featured_interactions: number;
      override_featured_interactions: number;
      featured_publish_attempts: number;
      successful_featured_publishes: number;
      featured_follow_rate: number | null;
      featured_publish_success_rate: number | null;
      window_days: number;
      long_term_window_days?: number;
    };
    interaction_sources: Array<RemediationTelemetrySource>;
    route_trends: Array<RouteTrendMetric>;
    long_term_route_trends?: Array<RouteTrendMetric>;
    route_window_series?: Array<RouteWindowSeriesMetric>;
    outcome_chain_summary: RemediationOutcomeChainSummary;
    approvals_native_outcome_summary: RemediationOutcomeChainSummary;
    draft_detail_outcome_summary: RemediationOutcomeChainSummary;
    long_term_approvals_native_outcome_summary?: RemediationOutcomeChainSummary;
    long_term_draft_detail_outcome_summary?: RemediationOutcomeChainSummary;
    featured_recommendation: {
      cluster_key: string;
      label: string;
      description: string;
      recommended_action_code: string;
      retry_guidance_status?: string | null;
      retry_guidance_label?: string | null;
      retry_guidance_reason?: string | null;
      safe_bulk_retry?: boolean | null;
      long_term_retry_guidance_status?: string | null;
      long_term_retry_guidance_label?: string | null;
      long_term_retry_guidance_reason?: string | null;
      long_term_safe_bulk_retry?: boolean | null;
      current_items: number;
      manual_check_completions: number;
      publish_attempts: number;
      successful_publishes: number;
      failed_publishes: number;
      publish_success_rate: number | null;
      effectiveness_score: number;
      effectiveness_status: string;
      last_activity_at: string | null;
      health_status: string;
      health_summary: string;
      route: string;
      decision_status: string;
      decision_reason: string;
      decision_context_source?: string | null;
      decision_context_window_days?: number | null;
      decision_context_success_rate?: number | null;
      decision_context_baseline_success_rate?: number | null;
      decision_context_advantage?: number | null;
      action_mode: "focus_cluster" | "bulk_retry_publish";
      featured_interactions: number;
      featured_followed_interactions: number;
      featured_override_interactions: number;
      featured_publish_attempts: number;
      featured_successful_publishes: number;
      featured_follow_rate: number | null;
      featured_publish_success_rate: number | null;
      top_interaction_source_key: string | null;
      top_interaction_source_label: string | null;
      primary_action?: RetryGuidanceContext["primary_action"];
      source_breakdown: Array<RemediationTelemetrySource>;
      outcome_chain_summary: RemediationOutcomeChainSummary;
      draft_detail_outcome_summary: RemediationOutcomeChainSummary;
      long_term_publish_attempts?: number | null;
      long_term_publish_success_rate?: number | null;
      long_term_effectiveness_score?: number | null;
      long_term_effectiveness_status?: string | null;
    } | null;
    items: Array<{
      cluster_key: string;
      label: string;
      description: string;
      recommended_action_code: string;
      retry_guidance_status?: string | null;
      retry_guidance_label?: string | null;
      retry_guidance_reason?: string | null;
      safe_bulk_retry?: boolean | null;
      long_term_retry_guidance_status?: string | null;
      long_term_retry_guidance_label?: string | null;
      long_term_retry_guidance_reason?: string | null;
      long_term_safe_bulk_retry?: boolean | null;
      current_items: number;
      manual_check_completions: number;
      publish_attempts: number;
      successful_publishes: number;
      failed_publishes: number;
      publish_success_rate: number | null;
      effectiveness_score: number;
      effectiveness_status: string;
      last_activity_at: string | null;
      health_status: string;
      health_summary: string;
      route: string;
      featured_interactions: number;
      featured_followed_interactions: number;
      featured_override_interactions: number;
      featured_publish_attempts: number;
      featured_successful_publishes: number;
      featured_follow_rate: number | null;
      featured_publish_success_rate: number | null;
      top_interaction_source_key: string | null;
      top_interaction_source_label: string | null;
      primary_action?: RetryGuidanceContext["primary_action"];
      source_breakdown: Array<RemediationTelemetrySource>;
      long_term_source_breakdown?: Array<RemediationTelemetrySource>;
      route_window_series?: Array<RouteWindowSeriesMetric>;
      outcome_chain_summary: RemediationOutcomeChainSummary;
      draft_detail_outcome_summary: RemediationOutcomeChainSummary;
      long_term_publish_attempts?: number | null;
      long_term_publish_success_rate?: number | null;
      long_term_effectiveness_score?: number | null;
      long_term_effectiveness_status?: string | null;
    }>;
  };
};

type RouteTrendMetric = {
  route_key: "approvals" | "draft_detail" | "other" | string;
  label: string;
  tracked_interactions: number;
  publish_attempts: number;
  successful_publishes: number;
  failed_publishes: number;
  publish_success_rate: number | null;
  top_source_key: string | null;
  top_source_label: string | null;
};

type RouteWindowSeriesMetric = {
  window_days: number;
  label: string;
  preferred_flow: "draft_detail" | "approvals_native" | "balanced";
  confidence?: "high" | "medium" | "low" | null;
  current_route_key?: string | null;
  current_route_label?: string | null;
  current_route_success_rate?: number | null;
  current_route_attempts?: number | null;
  current_route_advantage?: number | null;
  top_route_key?: string | null;
  top_route_label?: string | null;
  top_route_success_rate?: number | null;
  top_route_attempts?: number | null;
  top_route_source_label?: string | null;
  top_route_advantage?: number | null;
  summary_label?: string | null;
  reason?: string | null;
  route_trends?: Array<RouteTrendMetric>;
};

type PrimaryActionRouteSeriesMetric = {
  window_days: number;
  route_key?: string | null;
  route_label?: string | null;
  is_window_leader?: boolean | null;
  tracked_interactions?: number | null;
  publish_attempts?: number | null;
  successful_publishes?: number | null;
  failed_publishes?: number | null;
  publish_success_rate?: number | null;
  leader_route_key?: string | null;
  leader_route_label?: string | null;
  support_status?: "proven" | "emerging" | "guarded" | "missing" | string | null;
};

type RemediationTelemetrySourceKey =
  | "approvals_featured"
  | "approvals_cluster"
  | "approvals_retry_ready"
  | "approvals_item"
  | "approvals_bulk"
  | "approvals"
  | "draft_detail"
  | "draft_detail_from_approvals_featured"
  | "draft_detail_from_approvals_cluster"
  | "draft_detail_from_approvals_retry_ready"
  | "draft_detail_from_approvals_item"
  | "other";

type RemediationTelemetrySource = {
  source_key: RemediationTelemetrySourceKey | string;
  label: string;
  description: string;
  tracked_interactions: number;
  followed_featured_interactions: number;
  override_interactions: number;
  manual_check_completions: number;
  publish_retry_actions: number;
  bulk_retry_actions: number;
  publish_attempts: number;
  successful_publishes: number;
  failed_publishes: number;
  follow_rate: number | null;
  publish_success_rate: number | null;
};

type RemediationOutcomeChainSummary = {
  tracked_interactions: number;
  manual_check_completions: number;
  publish_retry_actions: number;
  bulk_retry_actions: number;
  focus_actions: number;
  jump_actions: number;
  total_retry_actions: number;
  publish_attempts: number;
  successful_publishes: number;
  failed_publishes: number;
  publish_success_rate: number | null;
  top_source_key?: string | null;
  top_source_label?: string | null;
};

const APPROVALS_NATIVE_SOURCE_KEYS: ReadonlyArray<RemediationTelemetrySourceKey> = [
  "approvals_featured",
  "approvals_cluster",
  "approvals_retry_ready",
  "approvals_item",
  "approvals",
];

const PUBLISH_FAILURES_PATH = "/approvals?status=publish_failed";
const ANALYTICS_WINDOW_OPTIONS = [
  { value: 7, label: "7 Gun" },
  { value: 30, label: "30 Gun" },
  { value: 90, label: "90 Gun" },
] as const;

const STATUS_FILTER_OPTIONS = [
  { value: "all", label: "Tum Durumlar" },
  { value: "draft", label: "Draft" },
  { value: "pending_review", label: "Incelemede" },
  { value: "approved", label: "Onaylandi" },
  { value: "rejected", label: "Reddedildi" },
  { value: "publish_failed", label: "Publish Hatasi" },
  { value: "published", label: "Published" },
] as const;

const CLEANUP_FILTER_OPTIONS = [
  { value: "all", label: "Tum Cleanup Durumlari" },
  { value: "failed", label: "Cleanup Basarisiz" },
  { value: "successful", label: "Cleanup Basarili" },
  { value: "not_attempted", label: "Cleanup Yok" },
] as const;

const MANUAL_CHECK_FILTER_OPTIONS = [
  { value: "all", label: "Tum Manuel Kontrol Durumlari" },
  { value: "required", label: "Manuel Kontrol Bekliyor" },
  { value: "completed", label: "Manuel Kontrol Tamamlandi" },
  { value: "not_required", label: "Manuel Kontrol Gerekmedi" },
] as const;

const RECOMMENDED_ACTION_FILTER_OPTIONS = [
  { value: "all", label: "Tum Onerilen Aksiyonlar" },
  { value: "manual_meta_check", label: "Manuel Meta Kontrolu" },
  { value: "retry_publish_after_manual_check", label: "Kontrol Sonrasi Tekrar Publish" },
  { value: "fix_and_retry_publish", label: "Duzelt ve Tekrar Publish" },
  { value: "review_publish_error", label: "Publish Hatasini Incele" },
] as const;

type QuickCluster = {
  key: string;
  label: string;
  detail: string;
  count: number;
  variant: "warning" | "danger" | "success" | "neutral";
  filters: {
    status: (typeof STATUS_FILTER_OPTIONS)[number]["value"];
    cleanup: (typeof CLEANUP_FILTER_OPTIONS)[number]["value"];
    manualCheck: (typeof MANUAL_CHECK_FILTER_OPTIONS)[number]["value"];
    recommendedAction: (typeof RECOMMENDED_ACTION_FILTER_OPTIONS)[number]["value"];
  };
};

type RetryGuidanceStatus = "safe" | "guarded" | "blocked" | "unknown";

type RetryGuidanceContext = {
  retry_guidance_status?: string | null;
  retry_guidance_label?: string | null;
  retry_guidance_reason?: string | null;
  safe_bulk_retry?: boolean | null;
  action_mode?: "focus_cluster" | "bulk_retry_publish" | null;
  primary_action?: {
    mode: "focus_cluster" | "bulk_retry_publish" | "jump_to_item";
    route?: string | null;
    route_key?: string | null;
    route_label?: string | null;
    source_key?: string | null;
    source_label?: string | null;
    publish_attempts?: number | null;
    publish_success_rate?: number | null;
    tracked_interactions?: number | null;
    successful_publishes?: number | null;
    failed_publishes?: number | null;
    followed_featured_interactions?: number | null;
    preferred_flow?: "draft_detail" | "approvals_native" | "balanced" | null;
    confidence_status?: "proven" | "emerging" | "guarded" | null;
    confidence_label?: string | null;
    trend_status?: "stable" | "forming" | "softening" | "sparse" | "missing" | string | null;
    trend_reason?: string | null;
    route_series?: Array<PrimaryActionRouteSeriesMetric> | null;
    alternative_route_key?: string | null;
    alternative_route_label?: string | null;
    alternative_publish_success_rate?: number | null;
    advantage_vs_alternative_route?: number | null;
    reason?: string | null;
  } | null;
  decision_context_source?: string | null;
  long_term_retry_guidance_status?: string | null;
  long_term_retry_guidance_label?: string | null;
  long_term_retry_guidance_reason?: string | null;
  long_term_safe_bulk_retry?: boolean | null;
  long_term_effectiveness_status?: string | null;
};

type SourceComparisonInsight = {
  label: string;
  variant: "success" | "warning" | "neutral";
  reason: string;
  preferredFlow: "draft_detail" | "approvals_native" | "balanced";
};

type SourceSpotlightInsight = {
  label: string;
  variant: "success" | "warning" | "neutral";
  reason: string;
  nextStepLabel: string;
  preferredFlow: "draft_detail" | "approvals_native" | "balanced";
};

type RouteTrendInsight = {
  label: string;
  variant: "success" | "warning" | "neutral";
  reason: string;
  nextStepLabel: string;
  preferredFlow: "draft_detail" | "approvals_native" | "balanced";
  confidence: "high" | "medium" | "low";
  currentLabel: string;
  longTermLabel: string;
  currentRouteKey?: string | null;
  currentRouteSuccessRate?: number | null;
  currentRouteAttempts?: number | null;
  currentRouteAdvantage?: number | null;
  longTermRouteKey?: string | null;
  longTermRouteSuccessRate?: number | null;
  longTermRouteAttempts?: number | null;
  longTermRouteAdvantage?: number | null;
};

type DraftRouteFocusContext = {
  decisionStatus?: string | null;
  decisionReason?: string | null;
  retryGuidanceStatus?: string | null;
  retryGuidanceLabel?: string | null;
  retryGuidanceReason?: string | null;
  effectivenessScore?: number | null;
  sourceComparisonLabel?: string | null;
  sourceComparisonReason?: string | null;
  sourceComparisonWinner?: string | null;
  longTermWindowDays?: number | null;
  longTermSuccessRate?: number | null;
  longTermBaselineSuccessRate?: number | null;
  longTermEffectivenessStatus?: string | null;
  primaryActionMode?: "focus_cluster" | "bulk_retry_publish" | "jump_to_item" | null;
  primaryActionRouteLabel?: string | null;
  primaryActionSourceLabel?: string | null;
  primaryActionReason?: string | null;
  primaryActionSuccessRate?: number | null;
  primaryActionTrackedInteractions?: number | null;
  primaryActionConfidenceStatus?: "proven" | "emerging" | "guarded" | null;
  primaryActionConfidenceLabel?: string | null;
  primaryActionTrendStatus?: "stable" | "forming" | "softening" | "sparse" | "missing" | string | null;
  primaryActionTrendReason?: string | null;
  primaryActionRouteSeries?: Array<PrimaryActionRouteSeriesMetric> | null;
  primaryActionAdvantage?: number | null;
  primaryActionAlternativeRouteLabel?: string | null;
  primaryActionAlternativeSuccessRate?: number | null;
  routeTrendLabel?: string | null;
  routeTrendReason?: string | null;
  routePreferredFlow?: "draft_detail" | "approvals_native" | "balanced" | null;
  routeTrendConfidence?: "high" | "medium" | "low" | null;
  routeCurrentLabel?: string | null;
  routeCurrentAttempts?: number | null;
  routeCurrentSuccessRate?: number | null;
  routeCurrentAdvantage?: number | null;
  routeLongTermLabel?: string | null;
  routeLongTermAttempts?: number | null;
  routeLongTermSuccessRate?: number | null;
  routeLongTermAdvantage?: number | null;
};

export default function ApprovalsPage() {
  return (
    <Suspense
      fallback={
        <Card>
          <div>
            <h2 className="text-lg font-semibold">Approval Operasyonlari</h2>
            <p className="mt-2 text-sm muted-text">Approvals analytics yukleniyor.</p>
          </div>
        </Card>
      }
    >
      <ApprovalsPageRouteState />
    </Suspense>
  );
}

function ApprovalsPageRouteState() {
  const pathname = usePathname();
  const searchParams = useSearchParams();

  return (
    <ApprovalsPageContent
      key={searchParams.toString()}
      pathname={pathname}
      search={searchParams.toString()}
    />
  );
}

function ApprovalsPageContent({
  pathname,
  search,
}: {
  pathname: string;
  search: string;
}) {
  const router = useRouter();
  const initialRouteState = readApprovalsRouteState(search);
  const [statusFilter, setStatusFilter] =
    useState<(typeof STATUS_FILTER_OPTIONS)[number]["value"]>(initialRouteState.statusFilter);
  const [cleanupFilter, setCleanupFilter] =
    useState<(typeof CLEANUP_FILTER_OPTIONS)[number]["value"]>(initialRouteState.cleanupFilter);
  const [manualCheckFilter, setManualCheckFilter] =
    useState<(typeof MANUAL_CHECK_FILTER_OPTIONS)[number]["value"]>(initialRouteState.manualCheckFilter);
  const [recommendedActionFilter, setRecommendedActionFilter] =
    useState<(typeof RECOMMENDED_ACTION_FILTER_OPTIONS)[number]["value"]>(initialRouteState.recommendedActionFilter);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionMessage, setActionMessage] = useState<string | null>(null);
  const [selectedApprovalIds, setSelectedApprovalIds] = useState<string[]>([]);
  const [bulkPublishing, setBulkPublishing] = useState(false);
  const [focusedApprovalId, setFocusedApprovalId] = useState<string | null>(null);
  const [analyticsWindowDays, setAnalyticsWindowDays] =
    useState<(typeof ANALYTICS_WINDOW_OPTIONS)[number]["value"]>(initialRouteState.analyticsWindowDays);

  const approvalsPath = useMemo(() => {
    const params = new URLSearchParams();

    if (statusFilter !== "all") {
      params.set("status", statusFilter);
    }

    if (cleanupFilter !== "all") {
      params.set("cleanup_state", cleanupFilter);
    }

    if (manualCheckFilter !== "all") {
      params.set("manual_check_state", manualCheckFilter);
    }

    if (recommendedActionFilter !== "all") {
      params.set("recommended_action_code", recommendedActionFilter);
    }

    const queryString = params.toString();
    return queryString ? `/approvals?${queryString}` : "/approvals";
  }, [cleanupFilter, manualCheckFilter, recommendedActionFilter, statusFilter]);

  const approvalQuery = useApiQuery<ApprovalResponse, Approval[]>(approvalsPath, {
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.approvals,
    select: (response) => response.data.data ?? [],
  });
  const publishFailureQuery = useApiQuery<ApprovalResponse, Approval[]>(PUBLISH_FAILURES_PATH, {
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.approvals,
    select: (response) => response.data.data ?? [],
  });
  const remediationAnalyticsPath = useMemo(
    () => `/approvals/remediation-analytics?window_days=${analyticsWindowDays}`,
    [analyticsWindowDays],
  );
  const remediationAnalyticsQuery = useApiQuery<
    ApprovalRemediationAnalyticsResponse,
    ApprovalRemediationAnalyticsResponse["data"]
  >(remediationAnalyticsPath, {
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.approvals,
    select: (response) => response.data,
  });

  const items = useMemo(() => approvalQuery.data ?? [], [approvalQuery.data]);
  const publishFailureItems = useMemo(() => publishFailureQuery.data ?? [], [publishFailureQuery.data]);
  const remediationAnalytics = remediationAnalyticsQuery.data ?? null;
  const featuredRecommendation = remediationAnalytics?.featured_recommendation ?? null;
  const { isLoading, reload } = approvalQuery;
  const combinedError = actionError ?? approvalQuery.error ?? publishFailureQuery.error ?? remediationAnalyticsQuery.error ?? null;
  const analyticsByCluster = useMemo(
    () => new Map((remediationAnalytics?.items ?? []).map((item) => [item.cluster_key, item])),
    [remediationAnalytics],
  );
  const telemetrySources = useMemo(
    () =>
      [...(remediationAnalytics?.interaction_sources ?? [])].sort(
        (left, right) => right.tracked_interactions - left.tracked_interactions,
      ),
    [remediationAnalytics],
  );
  const topTelemetrySources = useMemo(() => telemetrySources.slice(0, 3), [telemetrySources]);
  const outcomeChainSummary = remediationAnalytics?.outcome_chain_summary ?? null;
  const draftDetailOutcomeSummary = remediationAnalytics?.draft_detail_outcome_summary ?? null;
  const approvalsNativeOutcomeSummary = remediationAnalytics?.approvals_native_outcome_summary ?? null;
  const longTermDraftDetailOutcomeSummary =
    remediationAnalytics?.long_term_draft_detail_outcome_summary ?? null;
  const longTermApprovalsNativeOutcomeSummary =
    remediationAnalytics?.long_term_approvals_native_outcome_summary ?? null;
  const approvalsNativeTelemetry = useMemo(
    () =>
      telemetrySources.filter((source) =>
        APPROVALS_NATIVE_SOURCE_KEYS.includes(source.source_key as RemediationTelemetrySourceKey),
      ),
    [telemetrySources],
  );
  const approvalsNativeSummary = useMemo(
    () =>
      telemetryAggregateFromOutcomeSummary(approvalsNativeOutcomeSummary)
      ?? summarizeTelemetrySources(approvalsNativeTelemetry),
    [approvalsNativeOutcomeSummary, approvalsNativeTelemetry],
  );
  const draftDetailSummary = useMemo(
    () =>
      telemetryAggregateFromOutcomeSummary(draftDetailOutcomeSummary)
      ?? summarizeTelemetrySources(
        telemetrySources.filter((source) => source.source_key.startsWith("draft_detail")),
      ),
    [draftDetailOutcomeSummary, telemetrySources],
  );
  const longTermDraftDetailSummary = useMemo(
    () =>
      telemetryAggregateFromOutcomeSummary(longTermDraftDetailOutcomeSummary)
      ?? draftDetailSummary,
    [draftDetailSummary, longTermDraftDetailOutcomeSummary],
  );
  const longTermApprovalsNativeSummary = useMemo(
    () =>
      telemetryAggregateFromOutcomeSummary(longTermApprovalsNativeOutcomeSummary)
      ?? approvalsNativeSummary,
    [approvalsNativeSummary, longTermApprovalsNativeOutcomeSummary],
  );
  const currentSourceComparison = useMemo(
    () => compareSourceAggressiveness(draftDetailSummary, approvalsNativeSummary),
    [approvalsNativeSummary, draftDetailSummary],
  );
  const longTermSourceComparison = useMemo(
    () =>
      compareSourceAggressiveness(
        longTermDraftDetailSummary,
        longTermApprovalsNativeSummary,
      ),
    [
      longTermApprovalsNativeSummary,
      longTermDraftDetailSummary,
    ],
  );
  const sourceComparisonWinner = useMemo(
    () => longTermSourceComparison ?? currentSourceComparison,
    [currentSourceComparison, longTermSourceComparison],
  );
  const routeTrendInsight = useMemo(
    () => {
      const currentRouteTrends = remediationAnalytics?.route_trends ?? [];
      const longTermRouteTrends = remediationAnalytics?.long_term_route_trends ?? [];

      return buildRouteTrendInsight(
        currentRouteTrends,
        longTermRouteTrends,
        currentSourceComparison,
        longTermSourceComparison,
        draftDetailSummary,
        approvalsNativeSummary,
        longTermDraftDetailSummary,
        longTermApprovalsNativeSummary,
      );
    },
    [
      approvalsNativeSummary,
      currentSourceComparison,
      draftDetailSummary,
      longTermApprovalsNativeSummary,
      longTermDraftDetailSummary,
      longTermSourceComparison,
      remediationAnalytics?.long_term_route_trends,
      remediationAnalytics?.route_trends,
    ],
  );
  const routeWindowSeries = useMemo(
    () => remediationAnalytics?.route_window_series ?? [],
    [remediationAnalytics?.route_window_series],
  );
  const summary = useMemo(() => {
    return {
      filteredTotal: items.length,
      publishFailures: publishFailureItems.length,
      cleanupFailed: publishFailureItems.filter((item) => item.publish_state?.cleanup_success === false).length,
      manualCheckRequired: publishFailureItems.filter((item) => item.publish_state?.manual_check_required).length,
      manualCheckCompleted: publishFailureItems.filter((item) => item.publish_state?.manual_check_completed).length,
      retryReady: publishFailureItems.filter(
        (item) => item.publish_state?.recommended_action_code === "retry_publish_after_manual_check",
      ).length,
    };
  }, [items.length, publishFailureItems]);

  const retryReadyItems = useMemo(() => {
    return publishFailureItems
      .filter((item) => item.publish_state?.recommended_action_code === "retry_publish_after_manual_check")
      .slice(0, 4);
  }, [publishFailureItems]);

  const visibleRetryReadyItems = useMemo(() => {
    return items.filter((item) => canRetryPublish(item));
  }, [items]);

  const quickClusters = useMemo<QuickCluster[]>(() => {
    const manualCheckRequired = publishFailureItems.filter(
      (item) => item.publish_state?.recommended_action_code === "manual_meta_check",
    ).length;
    const retryReady = publishFailureItems.filter(
      (item) => item.publish_state?.recommended_action_code === "retry_publish_after_manual_check",
    ).length;
    const cleanupRecovered = publishFailureItems.filter(
      (item) => item.publish_state?.recommended_action_code === "fix_and_retry_publish",
    ).length;
    const reviewOnly = publishFailureItems.filter(
      (item) => item.publish_state?.recommended_action_code === "review_publish_error",
    ).length;

    return [
      {
        key: "manual-check-required",
        label: "Manuel Kontrol Bekleyenler",
        detail: "Cleanup basarisiz kaldigi icin Meta tarafinda operator kontrolu gerektiriyor.",
        count: manualCheckRequired,
        variant: "danger",
        filters: {
          status: "publish_failed",
          cleanup: "failed",
          manualCheck: "required",
          recommendedAction: "manual_meta_check",
        },
      },
      {
        key: "retry-ready",
        label: "Tekrar Publish'e Hazir",
        detail: "Manuel kontrol tamamlanmis veya aksiyon netlesmis kayitlari ayirir.",
        count: retryReady,
        variant: "success",
        filters: {
          status: "publish_failed",
          cleanup: "all",
          manualCheck: "completed",
          recommendedAction: "retry_publish_after_manual_check",
        },
      },
      {
        key: "cleanup-recovered",
        label: "Cleanup Ile Temizlenenler",
        detail: "Rollback basarili, draft duzeltildikten sonra guvenle tekrar publish edilebilir.",
        count: cleanupRecovered,
        variant: "warning",
        filters: {
          status: "publish_failed",
          cleanup: "successful",
          manualCheck: "not_required",
          recommendedAction: "fix_and_retry_publish",
        },
      },
      {
        key: "review-error",
        label: "Dogrudan Hata Incelemesi",
        detail: "Partial publish birakmayan ama publish hatasi veren kayitlari toplar.",
        count: reviewOnly,
        variant: "neutral",
        filters: {
          status: "publish_failed",
          cleanup: "all",
          manualCheck: "not_required",
          recommendedAction: "review_publish_error",
        },
      },
    ];
  }, [publishFailureItems]);

  const clusterByKey = useMemo(
    () => new Map(quickClusters.map((cluster) => [cluster.key, cluster])),
    [quickClusters],
  );

  const statusVariant = (status: string) =>
    status === "approved" || status === "published"
      ? "success"
      : status === "rejected" || status === "publish_failed"
        ? "danger"
        : "warning";

  useEffect(() => {
    const params = new URLSearchParams(search);

    syncApprovalRouteQuery(params, {
      statusFilter,
      cleanupFilter,
      manualCheckFilter,
      recommendedActionFilter,
      analyticsWindowDays,
    });

    const nextQuery = params.toString();

    if (nextQuery === search) {
      return;
    }

    router.replace(nextQuery ? `${pathname}?${nextQuery}` : pathname, { scroll: false });
  }, [
    analyticsWindowDays,
    cleanupFilter,
    manualCheckFilter,
    pathname,
    recommendedActionFilter,
    router,
    search,
    statusFilter,
  ]);

  useEffect(() => {
    const visibleIds = new Set(items.map((item) => item.id));
    setSelectedApprovalIds((current) => current.filter((id) => visibleIds.has(id)));
  }, [items]);

  useEffect(() => {
    if (!focusedApprovalId) {
      return;
    }

    const timeoutId = window.setTimeout(() => {
      document.getElementById(`approval-item-${focusedApprovalId}`)?.scrollIntoView({
        behavior: "smooth",
        block: "center",
      });
    }, 120);

    return () => window.clearTimeout(timeoutId);
  }, [focusedApprovalId, items]);

  const clusterItems = useCallback((cluster: QuickCluster) =>
    publishFailureItems.filter((item) => {
      if (cluster.filters.status !== "all" && item.status !== cluster.filters.status) {
        return false;
      }

      if (cluster.filters.cleanup !== "all") {
        if (cluster.filters.cleanup === "failed" && item.publish_state?.cleanup_success !== false) {
          return false;
        }

        if (cluster.filters.cleanup === "successful" && item.publish_state?.cleanup_success !== true) {
          return false;
        }

        if (cluster.filters.cleanup === "not_attempted" && item.publish_state?.cleanup_attempted) {
          return false;
        }
      }

      if (cluster.filters.manualCheck !== "all") {
        if (cluster.filters.manualCheck === "required" && !item.publish_state?.manual_check_required) {
          return false;
        }

        if (cluster.filters.manualCheck === "completed" && !item.publish_state?.manual_check_completed) {
          return false;
        }

        if (
          cluster.filters.manualCheck === "not_required"
          && (item.publish_state?.manual_check_required || item.publish_state?.manual_check_completed)
        ) {
          return false;
        }
      }

      if (
        cluster.filters.recommendedAction !== "all"
        && item.publish_state?.recommended_action_code !== cluster.filters.recommendedAction
      ) {
        return false;
      }

      return true;
    }), [publishFailureItems]);

  const focusQuickCluster = (cluster: QuickCluster, selectMatches = true) => {
    setStatusFilter(cluster.filters.status);
    setCleanupFilter(cluster.filters.cleanup);
    setManualCheckFilter(cluster.filters.manualCheck);
    setRecommendedActionFilter(cluster.filters.recommendedAction);
    setActionError(null);
    if (selectMatches) {
      setSelectedApprovalIds(clusterItems(cluster).map((item) => item.id));
    }
    setActionMessage(
      `${cluster.label} kumesi filtrelendi${selectMatches ? " ve eslesen kayitlar secildi" : ""}.`,
    );
  };

  const trackFeaturedInteraction = async (payload: {
    actedClusterKey: string;
    interactionSource: RemediationTelemetrySourceKey | string;
    interactionType: "focus_cluster" | "jump_to_item" | "manual_check_completed" | "publish_retry" | "bulk_retry_publish";
    attemptedCount?: number;
    successCount?: number;
    failureCount?: number;
  }) => {
    if (!featuredRecommendation) {
      return;
    }

    try {
      await apiRequest("/approvals/remediation-analytics/track", {
        method: "POST",
        requireWorkspace: true,
        body: {
          featured_cluster_key: featuredRecommendation.cluster_key,
          acted_cluster_key: payload.actedClusterKey,
          interaction_source: payload.interactionSource,
          interaction_type: payload.interactionType,
          followed_featured: payload.actedClusterKey === featuredRecommendation.cluster_key,
          attempted_count: payload.attemptedCount ?? 0,
          success_count: payload.successCount ?? 0,
          failure_count: payload.failureCount ?? 0,
        },
      });

      await remediationAnalyticsQuery.reload();
    } catch {
      // Tracking must not block operator actions.
    }
  };

  const focusApprovalItem = (approvalId: string, message: string) => {
    setSelectedApprovalIds([approvalId]);
    setFocusedApprovalId(approvalId);
    setActionError(null);
    setActionMessage(message);
  };

  const runClusterRecommendation = async (
    clusterKey: string,
    actionMode: "focus_cluster" | "bulk_retry_publish",
    interactionSource: RemediationTelemetrySourceKey | string,
  ) => {
    const cluster = clusterByKey.get(clusterKey);

    if (!cluster) {
      return;
    }

    const retryableMatches = clusterItems(cluster).filter((item) => canRetryPublish(item));
    focusQuickCluster(cluster, true);

    if (actionMode === "bulk_retry_publish" && retryableMatches.length > 0) {
      await runBulkPublishRetry(retryableMatches, cluster.key, interactionSource);
      return;
    }

    setActionMessage(`${cluster.label} remediation kumesi odaga alindi.`);
    await trackFeaturedInteraction({
      actedClusterKey: cluster.key,
      interactionSource,
      interactionType: "focus_cluster",
    });
  };

  const jumpToClusterAction = async (clusterKey: string, interactionSource: RemediationTelemetrySourceKey | string) => {
    const cluster = clusterByKey.get(clusterKey);

    if (!cluster) {
      return;
    }

    const matches = clusterItems(cluster);
    if (matches.length === 0) {
      setActionError(null);
      setActionMessage(`${cluster.label} icin acik kayit bulunmuyor.`);
      return;
    }

    focusQuickCluster(cluster, true);
    focusApprovalItem(matches[0].id, `${cluster.label} icin ilk remediation kaydi odaga alindi.`);
    await trackFeaturedInteraction({
      actedClusterKey: cluster.key,
      interactionSource,
      interactionType: "jump_to_item",
    });
  };

  const resetFilters = () => {
    setStatusFilter("all");
    setCleanupFilter("all");
    setManualCheckFilter("all");
    setRecommendedActionFilter("all");
    setActionError(null);
    setActionMessage(null);
    setSelectedApprovalIds([]);
  };

  const invalidateApprovalCaches = (item: Approval) => {
    invalidateApiCache(approvalsPath, { requireWorkspace: true });
    invalidateApiCache("/approvals", { requireWorkspace: true });
    invalidateApiCache(PUBLISH_FAILURES_PATH, { requireWorkspace: true });
    invalidateApiCache("/drafts", { requireWorkspace: true });

    if (item.approvable_type_label === "CampaignDraft") {
      invalidateApiCache(`/drafts/${item.approvable_id}`, { requireWorkspace: true });
    }
  };

  const refreshApprovals = async (item: Approval) => {
    invalidateApprovalCaches(item);
    await Promise.all([
      reload(),
      publishFailureQuery.reload(),
      remediationAnalyticsQuery.reload(),
    ]);
  };

  const callAction = async (
    item: Approval,
    action: "approve" | "reject" | "publish",
    interactionSource: RemediationTelemetrySourceKey | string = "approvals_item",
  ) => {
    const clusterKey = approvalClusterKey(item);

    try {
      setActionError(null);
      setActionMessage(null);
      if (action === "reject") {
        const reason = window.prompt("Reddetme nedeni");
        if (!reason) return;
        await apiRequest(`/approvals/${item.id}/reject`, {
          method: "POST",
          requireWorkspace: true,
          body: { reason },
        });
      } else {
        await apiRequest(`/approvals/${item.id}/${action}`, {
          method: "POST",
          requireWorkspace: true,
        });
      }

      await refreshApprovals(item);

      if (action !== "publish" && clusterKey) {
        await trackFeaturedInteraction({
          actedClusterKey: clusterKey,
          interactionSource,
          interactionType: "jump_to_item",
        });
      }

      if (action === "publish" && clusterKey) {
        await trackFeaturedInteraction({
          actedClusterKey: clusterKey,
          interactionSource,
          interactionType: "publish_retry",
          attemptedCount: 1,
          successCount: 1,
          failureCount: 0,
        });
      }
    } catch (err) {
      if (action === "publish" && clusterKey) {
        await trackFeaturedInteraction({
          actedClusterKey: clusterKey,
          interactionSource,
          interactionType: "publish_retry",
          attemptedCount: 1,
          successCount: 0,
          failureCount: 1,
        });
      }

      setActionError(err instanceof Error ? err.message : "Approval aksiyonu basarisiz.");
    }
  };

  const completeManualCheck = async (item: Approval) => {
    const note = window.prompt("Manuel kontrol notu (opsiyonel)");
    if (note === null) {
      return;
    }

    try {
      setActionError(null);
      setActionMessage(null);
      await apiRequest(`/approvals/${item.id}/manual-check-completed`, {
        method: "POST",
        requireWorkspace: true,
        body: {
          note: note.trim() || undefined,
        },
      });

      await refreshApprovals(item);
      const clusterKey = approvalClusterKey(item);
      if (clusterKey) {
        await trackFeaturedInteraction({
          actedClusterKey: clusterKey,
          interactionSource: "approvals_item",
          interactionType: "manual_check_completed",
        });
      }
    } catch (err) {
      setActionError(err instanceof Error ? err.message : "Manuel kontrol guncellenemedi.");
    }
  };

  const selectedItems = useMemo(
    () => items.filter((item) => selectedApprovalIds.includes(item.id)),
    [items, selectedApprovalIds],
  );
  const selectedRetryReadyItems = useMemo(
    () => selectedItems.filter((item) => canRetryPublish(item)),
    [selectedItems],
  );
  const allVisibleSelected = items.length > 0 && selectedApprovalIds.length === items.length;
  const allVisibleRetryReadySelected =
    visibleRetryReadyItems.length > 0 &&
    visibleRetryReadyItems.every((item) => selectedApprovalIds.includes(item.id));

  const toggleApprovalSelection = (approvalId: string, checked: boolean) => {
    setSelectedApprovalIds((current) => {
      if (checked) {
        return current.includes(approvalId) ? current : [...current, approvalId];
      }

      return current.filter((id) => id !== approvalId);
    });
  };

  const toggleVisibleSelection = () => {
    if (allVisibleSelected) {
      setSelectedApprovalIds([]);
      return;
    }

    setSelectedApprovalIds(items.map((item) => item.id));
  };

  const toggleRetryReadySelection = () => {
    if (visibleRetryReadyItems.length === 0) {
      return;
    }

    if (allVisibleRetryReadySelected) {
      setSelectedApprovalIds((current) =>
        current.filter((id) => !visibleRetryReadyItems.some((item) => item.id === id)),
      );
      return;
    }

    setSelectedApprovalIds(Array.from(new Set(visibleRetryReadyItems.map((item) => item.id))));
  };

  const runBulkPublishRetry = async (
    targetItems: Approval[],
    trackingClusterKey?: string,
    interactionSource: RemediationTelemetrySourceKey | string = "approvals_item",
  ) => {
    if (targetItems.length === 0) {
      setActionError(null);
      setActionMessage("Toplu retry publish icin once retry-hazir kayit secin.");
      return;
    }

    setBulkPublishing(true);
    setActionError(null);
    setActionMessage(null);

    const results = await Promise.allSettled(
      targetItems.map((item) =>
        apiRequest(`/approvals/${item.id}/publish`, {
          method: "POST",
          requireWorkspace: true,
        }),
      ),
    );

    const successCount = results.filter((result) => result.status === "fulfilled").length;
    const failureResults = results.filter(
      (result): result is PromiseRejectedResult => result.status === "rejected",
    );

    try {
      if (successCount > 0) {
        await Promise.all([
          reload(),
          publishFailureQuery.reload(),
          remediationAnalyticsQuery.reload(),
        ]);
      }
    } finally {
      setBulkPublishing(false);
      setSelectedApprovalIds([]);
    }

    if (failureResults.length === 0) {
      if (trackingClusterKey) {
        await trackFeaturedInteraction({
          actedClusterKey: trackingClusterKey,
          interactionSource,
          interactionType: "bulk_retry_publish",
          attemptedCount: targetItems.length,
          successCount,
          failureCount: 0,
        });
      }
      setActionMessage(`${successCount} approval icin retry publish denemesi baslatildi.`);
      return;
    }

    if (successCount > 0) {
      if (trackingClusterKey) {
        await trackFeaturedInteraction({
          actedClusterKey: trackingClusterKey,
          interactionSource,
          interactionType: "bulk_retry_publish",
          attemptedCount: targetItems.length,
          successCount,
          failureCount: failureResults.length,
        });
      }
      setActionMessage(`${successCount} approval icin retry publish basladi, ${failureResults.length} kayit hata verdi.`);
      setActionError(extractErrorMessage(failureResults[0].reason));
      return;
    }

    if (trackingClusterKey) {
      await trackFeaturedInteraction({
        actedClusterKey: trackingClusterKey,
        interactionSource,
        interactionType: "bulk_retry_publish",
        attemptedCount: targetItems.length,
        successCount: 0,
        failureCount: failureResults.length,
      });
    }

    setActionError(extractErrorMessage(failureResults[0].reason));
  };

  const featuredClusterMatches = useMemo(() => {
    if (!featuredRecommendation) {
      return [];
    }

    const cluster = clusterByKey.get(featuredRecommendation.cluster_key);

    return cluster ? clusterItems(cluster) : [];
  }, [clusterByKey, clusterItems, featuredRecommendation]);

  const featuredDraftRoute = useMemo(
    () =>
      featuredClusterMatches.length > 0
        ? buildDraftRoute(
            featuredClusterMatches[0],
            analyticsWindowDays,
            featuredRecommendation?.decision_context_source === "long_term"
              ? "approvals_featured_long_term"
              : "approvals_featured",
            {
            decisionStatus:
              featuredRecommendation?.decision_context_source === "long_term"
                ? "long_term_preferred"
                : featuredRecommendation?.decision_status ?? null,
            decisionReason: buildFocusRouteReason({
              baseReason: featuredRecommendation?.decision_reason ?? null,
              decisionContextSource: featuredRecommendation?.decision_context_source ?? null,
              longTermWindowDays: featuredRecommendation?.decision_context_window_days ?? remediationAnalytics?.summary.long_term_window_days ?? null,
              longTermSuccessRate: featuredRecommendation?.long_term_publish_success_rate ?? null,
              longTermBaselineSuccessRate: featuredRecommendation?.decision_context_baseline_success_rate ?? null,
              sourceComparisonReason: sourceComparisonWinner?.reason ?? null,
              sourceComparisonLabel: sourceComparisonWinner?.label ?? null,
              decisionContextAdvantage: featuredRecommendation?.decision_context_advantage ?? null,
            }),
            retryGuidanceStatus:
              featuredRecommendation?.decision_context_source === "long_term"
                ? featuredRecommendation?.long_term_retry_guidance_status ?? featuredRecommendation?.retry_guidance_status ?? null
                : featuredRecommendation?.retry_guidance_status ?? null,
            retryGuidanceLabel:
              featuredRecommendation?.decision_context_source === "long_term"
                ? featuredRecommendation?.long_term_retry_guidance_label ?? featuredRecommendation?.retry_guidance_label ?? null
                : featuredRecommendation?.retry_guidance_label ?? null,
            retryGuidanceReason:
              featuredRecommendation?.decision_context_source === "long_term"
                ? featuredRecommendation?.long_term_retry_guidance_reason ?? featuredRecommendation?.retry_guidance_reason ?? null
                : featuredRecommendation?.retry_guidance_reason ?? null,
            effectivenessScore:
              featuredRecommendation?.decision_context_source === "long_term"
                ? featuredRecommendation?.long_term_effectiveness_score ?? featuredRecommendation?.effectiveness_score ?? null
                : featuredRecommendation?.effectiveness_score ?? null,
            sourceComparisonLabel: sourceComparisonWinner?.label ?? null,
            sourceComparisonReason: sourceComparisonWinner?.reason ?? null,
            sourceComparisonWinner: sourceComparisonWinner?.label ?? null,
            longTermWindowDays: featuredRecommendation?.decision_context_window_days ?? remediationAnalytics?.summary.long_term_window_days ?? null,
            longTermSuccessRate: featuredRecommendation?.long_term_publish_success_rate ?? null,
            longTermBaselineSuccessRate: featuredRecommendation?.decision_context_baseline_success_rate ?? null,
            longTermEffectivenessStatus: featuredRecommendation?.long_term_effectiveness_status ?? null,
            primaryActionMode: featuredRecommendation?.primary_action?.mode ?? null,
            primaryActionRouteLabel: featuredRecommendation?.primary_action?.route_label ?? null,
            primaryActionSourceLabel: featuredRecommendation?.primary_action?.source_label ?? null,
            primaryActionReason: featuredRecommendation?.primary_action?.reason ?? null,
            primaryActionSuccessRate: featuredRecommendation?.primary_action?.publish_success_rate ?? null,
            primaryActionTrackedInteractions: featuredRecommendation?.primary_action?.tracked_interactions ?? null,
            primaryActionConfidenceStatus: featuredRecommendation?.primary_action?.confidence_status ?? null,
            primaryActionConfidenceLabel: featuredRecommendation?.primary_action?.confidence_label ?? null,
            primaryActionTrendStatus: featuredRecommendation?.primary_action?.trend_status ?? null,
            primaryActionTrendReason: featuredRecommendation?.primary_action?.trend_reason ?? null,
            primaryActionRouteSeries: resolvePrimaryActionRouteSeries(
              featuredRecommendation?.primary_action,
              routeWindowSeries,
            ),
            primaryActionAdvantage: featuredRecommendation?.primary_action?.advantage_vs_alternative_route ?? null,
            primaryActionAlternativeRouteLabel: featuredRecommendation?.primary_action?.alternative_route_label ?? null,
            primaryActionAlternativeSuccessRate: featuredRecommendation?.primary_action?.alternative_publish_success_rate ?? null,
            ...buildRouteTrendFocusContext(routeTrendInsight),
            }
          )
        : null,
    [analyticsWindowDays, featuredClusterMatches, featuredRecommendation, remediationAnalytics?.summary.long_term_window_days, routeTrendInsight, routeWindowSeries, sourceComparisonWinner],
  );
  const featuredPrimaryAction = useMemo(
    () => resolveFeaturedPrimaryAction(featuredRecommendation, sourceComparisonWinner, routeTrendInsight, featuredDraftRoute),
    [featuredDraftRoute, featuredRecommendation, routeTrendInsight, sourceComparisonWinner],
  );
  const featuredPrimaryActionRouteSeriesSummary = useMemo(
    () => summarizePrimaryActionRouteSeries(featuredRecommendation?.primary_action, routeWindowSeries),
    [featuredRecommendation?.primary_action, routeWindowSeries],
  );
  const sourceSpotlight = useMemo(
    () =>
      buildSourceSpotlight(
        routeTrendInsight,
        sourceComparisonWinner,
        featuredRecommendation,
        featuredDraftRoute,
        telemetrySources[0] ?? null,
        longTermDraftDetailOutcomeSummary,
        longTermApprovalsNativeOutcomeSummary,
      ),
    [
      featuredDraftRoute,
      featuredRecommendation,
      longTermApprovalsNativeOutcomeSummary,
      longTermDraftDetailOutcomeSummary,
      routeTrendInsight,
      sourceComparisonWinner,
      telemetrySources,
    ],
  );

  return (
    <Card>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold">Approval Operasyonlari</h2>
          <p className="mt-1 text-sm muted-text">
            Publish hatalarini cleanup, manuel kontrol ve onerilen aksiyon baglaminda tek kuyruktan yonetin.
          </p>
        </div>
        <div className="flex flex-wrap gap-2 text-xs">
          <Badge label={`${summary.filteredTotal} filtreli kayit`} variant="neutral" />
          <Badge label={`${summary.publishFailures} publish hatasi`} variant="danger" />
          <Badge label={`${summary.manualCheckRequired} manuel kontrol bekliyor`} variant="warning" />
          <Badge label={`${summary.retryReady} retry-hazir`} variant="success" />
        </div>
      </div>

      <div className="mt-4 grid gap-3 md:grid-cols-4">
        <label className="space-y-1 text-sm">
          <span className="block font-medium">Durum</span>
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={statusFilter}
            onChange={(event) => setStatusFilter(event.target.value as (typeof STATUS_FILTER_OPTIONS)[number]["value"])}
          >
            {STATUS_FILTER_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>

        <label className="space-y-1 text-sm">
          <span className="block font-medium">Cleanup</span>
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={cleanupFilter}
            onChange={(event) => setCleanupFilter(event.target.value as (typeof CLEANUP_FILTER_OPTIONS)[number]["value"])}
          >
            {CLEANUP_FILTER_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>

        <label className="space-y-1 text-sm">
          <span className="block font-medium">Manuel Kontrol</span>
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={manualCheckFilter}
            onChange={(event) =>
              setManualCheckFilter(event.target.value as (typeof MANUAL_CHECK_FILTER_OPTIONS)[number]["value"])
            }
          >
            {MANUAL_CHECK_FILTER_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>

        <label className="space-y-1 text-sm">
          <span className="block font-medium">Onerilen Aksiyon</span>
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={recommendedActionFilter}
            onChange={(event) =>
              setRecommendedActionFilter(event.target.value as (typeof RECOMMENDED_ACTION_FILTER_OPTIONS)[number]["value"])
            }
          >
            {RECOMMENDED_ACTION_FILTER_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>
      </div>

      <div className="mt-4 flex flex-wrap gap-3">
        <Button variant="outline" size="sm" onClick={resetFilters}>
          Filtreleri Sifirla
        </Button>
        <Button variant="outline" size="sm" onClick={toggleVisibleSelection}>
          {allVisibleSelected ? "Gorunen Secimi Temizle" : "Gorunenleri Sec"}
        </Button>
        <Button
          variant="outline"
          size="sm"
          onClick={toggleRetryReadySelection}
          disabled={visibleRetryReadyItems.length === 0}
        >
          {allVisibleRetryReadySelected ? "Retry-Hazir Secimi Temizle" : "Retry-Hazirlari Sec"}
        </Button>
        <Button
          size="sm"
          onClick={() => runBulkPublishRetry(selectedRetryReadyItems)}
          disabled={bulkPublishing || selectedRetryReadyItems.length === 0}
        >
          {bulkPublishing ? "Toplu Publish Calisiyor..." : "Secili Kayitlarda Retry Publish"}
        </Button>
        <Badge label={`${selectedApprovalIds.length} secili`} variant="neutral" />
        <Badge label={`${visibleRetryReadyItems.length} gorunen retry-hazir`} variant="success" />
      </div>

      {remediationAnalytics ? (
        <>
        <div className="mt-4 grid gap-3 xl:grid-cols-4">
          <div className="rounded-lg border border-[var(--border)] bg-[var(--surface-2)] p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="flex flex-wrap items-center gap-2">
                <Badge label="Remediation Analytics" variant="neutral" />
                <Badge label={`${remediationAnalytics.summary.window_days} gun`} variant="neutral" />
              </div>
              <label className="space-y-1 text-xs">
                <span className="block font-medium">Analytics Penceresi</span>
                <select
                  className="h-9 rounded-md border border-[var(--border)] bg-white px-3 text-xs"
                  value={analyticsWindowDays}
                  onChange={(event) =>
                    setAnalyticsWindowDays(Number(event.target.value) as (typeof ANALYTICS_WINDOW_OPTIONS)[number]["value"])
                  }
                >
                  {ANALYTICS_WINDOW_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </label>
            </div>
            <p className="mt-3 text-sm font-semibold">Cluster performans ozeti</p>
            <div className="mt-3 space-y-1 text-sm muted-text">
              <p>Takip edilen cluster: {remediationAnalytics.summary.tracked_clusters}</p>
              <p>Takip edilen kaynak: {remediationAnalytics.summary.tracked_sources_count}</p>
              <p>Manuel kontrol aksiyonu: {remediationAnalytics.summary.tracked_manual_checks}</p>
              <p>Retry publish denemesi: {remediationAnalytics.summary.tracked_publish_attempts}</p>
              <p>Basarili publish: {remediationAnalytics.summary.successful_publish_attempts}</p>
              <p>Featured takip: {remediationAnalytics.summary.tracked_featured_interactions}</p>
            </div>
            {remediationAnalytics.summary.top_working_cluster_label ? (
              <p className="mt-3 text-xs muted-text">
                En iyi calisan cluster: {remediationAnalytics.summary.top_working_cluster_label}
              </p>
            ) : null}
            {remediationAnalytics.summary.top_effective_cluster_label ? (
              <p className="mt-2 text-xs muted-text">
                En etkili cluster: {remediationAnalytics.summary.top_effective_cluster_label}
                {remediationAnalytics.summary.top_effective_cluster_score != null
                  ? ` (skor ${remediationAnalytics.summary.top_effective_cluster_score})`
                  : ""}
              </p>
            ) : null}
            {remediationAnalytics.summary.top_draft_detail_cluster_label ? (
              <p className="mt-2 text-xs muted-text">
                Draft detail uzerinde en iyi toparlayan cluster: {remediationAnalytics.summary.top_draft_detail_cluster_label}
              </p>
            ) : null}
            {remediationAnalytics.summary.top_long_term_stable_cluster_label ? (
              <p className="mt-2 text-xs muted-text">
                {remediationAnalytics.summary.long_term_window_days ?? 90} gun stabil lider:
                {" "}
                {remediationAnalytics.summary.top_long_term_stable_cluster_label}
                {remediationAnalytics.summary.top_long_term_stable_cluster_score != null
                  ? ` (skor ${remediationAnalytics.summary.top_long_term_stable_cluster_score})`
                  : ""}
              </p>
            ) : null}
            {remediationAnalytics.summary.featured_follow_rate != null ? (
              <p className="mt-2 text-xs muted-text">
                Featured takip orani %{remediationAnalytics.summary.featured_follow_rate}
                {remediationAnalytics.summary.featured_publish_success_rate != null
                  ? ` / publish basarisi %${remediationAnalytics.summary.featured_publish_success_rate}`
                  : ""}
              </p>
            ) : null}
            {remediationAnalytics.summary.top_interaction_source_label ? (
              <p className="mt-2 text-xs muted-text">
                En cok etkilesim ureten kaynak: {remediationAnalytics.summary.top_interaction_source_label}
              </p>
            ) : null}
            {remediationAnalytics.summary.top_success_source_label ? (
              <p className="mt-2 text-xs muted-text">
                En iyi publish sonucu ureten kaynak: {remediationAnalytics.summary.top_success_source_label}
              </p>
            ) : null}
          </div>
          {featuredRecommendation ? (
            <div className="rounded-lg border border-[var(--accent)]/30 bg-[var(--surface-2)] p-4 xl:col-span-3">
              <div className="flex flex-wrap items-center gap-2">
                <Badge label="Remediation Analytics" variant="neutral" />
                <Badge label="One Cikan Remediation" variant="success" />
                <Badge
                  label={
                    featuredRecommendation.decision_status === "manual_attention"
                      ? "Manuel Dikkat"
                      : featuredRecommendation.decision_status === "long_term_preferred"
                        ? "Uzun Donem Stabil"
                      : featuredRecommendation.decision_status === "draft_detail_preferred"
                        ? "Draft Detail Lideri"
                      : featuredRecommendation.decision_status === "effectiveness_preferred"
                        ? "Effectiveness Destekli"
                      : featuredRecommendation.decision_status === "analytics_preferred"
                        ? "Analytics Destekli"
                        : "Kural Tabanli"
                  }
                  variant={
                    featuredRecommendation.decision_status === "manual_attention"
                      ? "danger"
                      : featuredRecommendation.decision_status === "long_term_preferred"
                        ? "success"
                      : featuredRecommendation.decision_status === "draft_detail_preferred"
                        ? "success"
                      : featuredRecommendation.decision_status === "effectiveness_preferred"
                        ? "success"
                      : featuredRecommendation.decision_status === "analytics_preferred"
                        ? "success"
                        : "warning"
                  }
                />
                <Badge label={featuredRecommendation.label} variant="neutral" />
                <Badge label={`${featuredRecommendation.current_items} acik kayit`} variant="neutral" />
                {featuredRecommendation.featured_follow_rate != null ? (
                  <Badge label={`%${featuredRecommendation.featured_follow_rate} takip`} variant="neutral" />
                ) : null}
                {featuredRecommendation.publish_success_rate != null ? (
                  <Badge
                    label={`%${featuredRecommendation.publish_success_rate} publish basarisi`}
                    variant="success"
                  />
                ) : null}
                <Badge
                  label={`Effectiveness ${featuredRecommendation.effectiveness_score}`}
                  variant={featuredRecommendation.effectiveness_status === "proven" ? "success" : "neutral"}
                />
                <Badge
                  label={effectivenessStatusLabel(featuredRecommendation.effectiveness_status)}
                  variant={effectivenessStatusVariant(featuredRecommendation.effectiveness_status)}
                />
                {featuredRecommendation.featured_publish_success_rate != null ? (
                  <Badge
                    label={`%${featuredRecommendation.featured_publish_success_rate} featured publish basarisi`}
                    variant="success"
                  />
                ) : null}
                <Badge
                  label={resolveRetryGuidanceLabel(featuredRecommendation)}
                  variant={retryGuidanceVariant(resolveRetryGuidanceStatus(featuredRecommendation))}
                />
                {featuredRecommendation.decision_context_source === "draft_detail"
                  && featuredRecommendation.decision_context_advantage != null ? (
                    <Badge
                      label={`Draft detail +%${featuredRecommendation.decision_context_advantage}`}
                      variant="success"
                    />
                  ) : null}
                {featuredRecommendation.decision_context_source === "long_term"
                  && featuredRecommendation.decision_context_window_days != null ? (
                    <Badge
                      label={`${featuredRecommendation.decision_context_window_days} gun stabilite`}
                      variant="success"
                    />
                  ) : null}
              </div>
              <p className="mt-3 text-sm font-semibold">
                {featuredRecommendation.decision_reason}
              </p>
              <p className="mt-1 text-sm muted-text">
                {featuredRecommendation.health_summary}
              </p>
              <p className="mt-2 text-xs muted-text">
                Bu karar {remediationAnalytics.summary.window_days} gunluk analytics penceresinden uretildi.
              </p>
              <p className="mt-2 text-xs muted-text">
                {featuredRecommendation.featured_interactions} izlenen featured etkilesim /
                {` ${featuredRecommendation.featured_followed_interactions}`} takip /
                {` ${featuredRecommendation.featured_override_interactions}`} override
              </p>
              {featuredRecommendation.decision_context_source === "draft_detail"
                && featuredRecommendation.decision_context_success_rate != null ? (
                  <p className="mt-2 text-xs muted-text">
                    Draft detail kaynakli remediation publish basarisi %{featuredRecommendation.decision_context_success_rate}.
                  </p>
                ) : null}
              {featuredRecommendation.decision_context_source === "long_term"
                && featuredRecommendation.decision_context_success_rate != null ? (
                  <p className="mt-2 text-xs muted-text">
                    {featuredRecommendation.decision_context_window_days ?? remediationAnalytics.summary.long_term_window_days ?? 90}
                    {" "}gunluk uzun donem publish basarisi %{featuredRecommendation.decision_context_success_rate}
                    {featuredRecommendation.decision_context_baseline_success_rate != null
                      ? ` / mevcut pencere referansi %${featuredRecommendation.decision_context_baseline_success_rate}`
                      : ""}.
                  </p>
                ) : null}
              {featuredRecommendation.retry_guidance_reason ? (
                <p className="mt-2 text-xs muted-text">{featuredRecommendation.retry_guidance_reason}</p>
              ) : null}
              {featuredRecommendation.primary_action?.trend_status ? (
                <div className="mt-2 flex flex-wrap items-center gap-2 text-xs muted-text">
                  <Badge
                    label={primaryActionTrendStatusLabel(featuredRecommendation.primary_action.trend_status)}
                    variant={primaryActionTrendStatusVariant(featuredRecommendation.primary_action.trend_status)}
                  />
                  {featuredRecommendation.primary_action.trend_reason ? (
                    <span>{featuredRecommendation.primary_action.trend_reason}</span>
                  ) : null}
                </div>
              ) : null}
              {featuredPrimaryActionRouteSeriesSummary ? (
                <p className="mt-2 text-xs muted-text">
                  Route serisi: {featuredPrimaryActionRouteSeriesSummary}
                </p>
              ) : null}
              <div className="mt-3 rounded-md border border-[var(--border)] bg-white p-3">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge label="Uzun Donem Karsilastirma" variant="neutral" />
                  {sourceComparisonWinner ? (
                    <Badge label={sourceComparisonWinner.label} variant={sourceComparisonWinner.variant} />
                  ) : null}
                  {featuredRecommendation.decision_context_source === "draft_detail" ? (
                    <Badge label="Draft Detail Odakli" variant="success" />
                  ) : featuredRecommendation.decision_context_source === "long_term" ? (
                    <Badge label="Uzun Donem Stabilite" variant="success" />
                  ) : (
                    <Badge label="Approvals Native Odakli" variant="warning" />
                  )}
                </div>
                <p className="mt-2 text-sm muted-text">
                  {sourceComparisonWinner?.reason ?? "Draft detail ve approvals-native karsilastirmasi icin yeterli veri bekleniyor."}
                </p>
                {featuredRecommendation.decision_context_source === "draft_detail"
                  && featuredRecommendation.decision_context_success_rate != null ? (
                    <p className="mt-1 text-xs muted-text">
                      Bu featured karar draft detail tarafinda %{featuredRecommendation.decision_context_success_rate}
                      publish basarisi goren akisla destekleniyor.
                    </p>
                  ) : null}
                <div className="mt-3 flex flex-wrap gap-2">
                  {featuredDraftRoute && sourceComparisonWinner?.preferredFlow === "draft_detail" ? (
                    <Link href={featuredDraftRoute}>
                      <Button variant="outline" size="sm">
                        Draft Detail Akisina In
                      </Button>
                    </Link>
                  ) : null}
                  {featuredRecommendation && sourceComparisonWinner?.preferredFlow !== "draft_detail" ? (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() =>
                        void jumpToClusterAction(featuredRecommendation.cluster_key, "approvals_featured")
                      }
                    >
                      Featured Kumeye Git
                    </Button>
                  ) : null}
                </div>
              </div>
              <div className="mt-4 flex flex-wrap gap-2">
                {featuredPrimaryAction.mode === "jump_to_draft_detail" && featuredDraftRoute ? (
                  <Link href={featuredDraftRoute}>
                    <Button
                      size="sm"
                      onClick={() =>
                        void trackFeaturedInteraction({
                          actedClusterKey: featuredRecommendation.cluster_key,
                          interactionSource: "approvals_featured",
                          interactionType: "jump_to_item",
                        })
                      }
                    >
                      {featuredPrimaryAction.label}
                    </Button>
                  </Link>
                ) : (
                  <Button
                    size="sm"
                    onClick={() =>
                      void runClusterRecommendation(
                        featuredRecommendation.cluster_key,
                        featuredPrimaryAction.mode === "bulk_retry_publish" ? "bulk_retry_publish" : "focus_cluster",
                        "approvals_featured",
                      )
                    }
                    disabled={
                      bulkPublishing
                      && featuredPrimaryAction.mode === "bulk_retry_publish"
                    }
                  >
                    {featuredPrimaryAction.label}
                  </Button>
                )}
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => void jumpToClusterAction(featuredRecommendation.cluster_key, "approvals_featured")}
                >
                  Ilk Alt Aksiyona Git
                </Button>
                {featuredDraftRoute ? (
                  <Link href={featuredDraftRoute}>
                    <Button variant="outline" size="sm">
                      Ilk Draft Detayina Git
                    </Button>
                  </Link>
                ) : null}
              </div>
              <p className="mt-2 text-xs muted-text">{featuredPrimaryAction.hint}</p>
            </div>
          ) : null}
        </div>
        <div className="grid gap-3 md:grid-cols-2">
          <div className="rounded-lg border border-[var(--border)] bg-[var(--surface-2)] p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <Badge label="Telemetry Kaynaklari" variant="neutral" />
              <Badge label={`${telemetrySources.length} kaynak`} variant="neutral" />
            </div>
            <p className="mt-3 text-sm font-semibold">Etkilesim kaynak dagilimi</p>
            {topTelemetrySources.length > 0 ? (
              <div className="mt-3 space-y-2">
                {topTelemetrySources.map((source) => (
                  <div key={source.source_key} className="rounded-md border border-[var(--border)] bg-white p-3">
                    <div className="flex flex-wrap items-center gap-2">
                      <Badge label={sourceLabel(source.source_key, source.label)} variant="neutral" />
                      <Badge label={`${source.tracked_interactions} etkilesim`} variant="success" />
                    </div>
                    <p className="mt-2 text-xs muted-text">{source.description}</p>
                    <p className="mt-2 text-xs muted-text">
                      {source.followed_featured_interactions} takip / {source.override_interactions} override
                    </p>
                    <p className="mt-1 text-xs muted-text">
                      {source.manual_check_completions} manuel kontrol / {source.publish_retry_actions + source.bulk_retry_actions} retry aksiyonu
                    </p>
                    <p className="mt-1 text-xs muted-text">
                      {source.publish_attempts} publish denemesi / {source.successful_publishes} basarili / {source.failed_publishes} basarisiz
                    </p>
                  </div>
                ))}
              </div>
            ) : (
              <p className="mt-3 text-sm muted-text">Kaynak dagilimi henuz olusmadi.</p>
            )}
          </div>
          <div className="rounded-lg border border-[var(--border)] bg-[var(--surface-2)] p-4">
            <Badge label="Outcome Chain" variant="neutral" />
            <p className="mt-3 text-sm font-semibold">Karar akisi ozeti</p>
            {outcomeChainSummary ? (
              <div className="mt-3 space-y-1 text-sm muted-text">
                <p>Toplam etkilesim: {outcomeChainSummary.tracked_interactions}</p>
                <p>Manuel kontrol: {outcomeChainSummary.manual_check_completions}</p>
                <p>Retry aksiyonu: {outcomeChainSummary.total_retry_actions}</p>
                <p>Publish denemesi: {outcomeChainSummary.publish_attempts}</p>
                <p>Basarili publish: {outcomeChainSummary.successful_publishes}</p>
                <p>Basarisiz publish: {outcomeChainSummary.failed_publishes}</p>
                {outcomeChainSummary.publish_success_rate != null ? (
                  <p>Publish basari orani: %{outcomeChainSummary.publish_success_rate}</p>
                ) : null}
              </div>
            ) : (
              <p className="mt-3 text-sm muted-text">Outcome chain verisi yok.</p>
            )}
          </div>
          <div className="rounded-lg border border-[var(--accent)]/25 bg-white p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <Badge label="Route Trend" variant="neutral" />
              {routeTrendInsight ? (
                <Badge label={routeTrendInsight.label} variant={routeTrendInsight.variant} />
              ) : null}
            </div>
            <p className="mt-3 text-sm font-semibold">Draft detail vs approvals native</p>
            <div className="mt-3 grid gap-2 text-xs muted-text md:grid-cols-2">
              <div className="rounded-md border border-[var(--border)] bg-[var(--surface-2)] p-3">
                <p className="font-semibold">Draft detail</p>
                <p className="mt-1">Takip edilen: {draftDetailSummary.tracked_interactions}</p>
                <p>Retry aksiyonu: {draftDetailSummary.total_retry_actions}</p>
                <p>Publish denemesi: {draftDetailSummary.publish_attempts}</p>
                <p>
                  Publish basarisi:{" "}
                  {draftDetailSummary.publish_success_rate != null ? `%${draftDetailSummary.publish_success_rate}` : "Veri yok"}
                </p>
                {draftDetailSummary.top_source_label ? (
                  <p className="mt-1">Top kaynak: {draftDetailSummary.top_source_label}</p>
                ) : null}
              </div>
              <div className="rounded-md border border-[var(--border)] bg-[var(--surface-2)] p-3">
                <p className="font-semibold">Approvals native</p>
                <p className="mt-1">Takip edilen: {approvalsNativeSummary.tracked_interactions}</p>
                <p>Retry aksiyonu: {approvalsNativeSummary.total_retry_actions}</p>
                <p>Publish denemesi: {approvalsNativeSummary.publish_attempts}</p>
                <p>
                  Publish basarisi:{" "}
                  {approvalsNativeSummary.publish_success_rate != null
                    ? `%${approvalsNativeSummary.publish_success_rate}`
                    : "Veri yok"}
                </p>
                {approvalsNativeSummary.top_source_label ? (
                  <p className="mt-1">Top kaynak: {approvalsNativeSummary.top_source_label}</p>
                ) : null}
              </div>
            </div>
            <div className="mt-3 grid gap-2 text-xs muted-text md:grid-cols-2">
              <div className="rounded-md border border-[var(--border)] bg-[var(--surface-2)] p-3">
                <div className="flex flex-wrap items-center gap-2">
                  <p className="font-semibold">Mevcut pencere</p>
                  {currentSourceComparison ? (
                    <Badge label={currentSourceComparison.label} variant={currentSourceComparison.variant} />
                  ) : null}
                </div>
                <p className="mt-2">
                  {currentSourceComparison?.reason ?? "Mevcut pencere route karsilastirmasi icin veri birikmedi."}
                </p>
              </div>
              <div className="rounded-md border border-[var(--border)] bg-[var(--surface-2)] p-3">
                <div className="flex flex-wrap items-center gap-2">
                  <p className="font-semibold">{remediationAnalytics.summary.long_term_window_days ?? 90} gun</p>
                  {longTermSourceComparison ? (
                    <Badge label={longTermSourceComparison.label} variant={longTermSourceComparison.variant} />
                  ) : null}
                </div>
                <p className="mt-2">
                  {longTermSourceComparison?.reason ?? "Uzun donem route karsilastirmasi icin veri birikmedi."}
                </p>
              </div>
            </div>
            <p className="mt-3 text-xs muted-text">
              {routeTrendInsight?.reason ?? sourceComparisonWinner?.reason ?? "Kaynak karsilastirmasi icin yeterli veri bekleniyor."}
            </p>
            {routeTrendInsight ? (
              <p className="mt-2 text-xs muted-text">
                Sonraki adim: {routeTrendInsight.nextStepLabel}
              </p>
            ) : null}
          </div>
          <div className="rounded-lg border border-[var(--accent)]/25 bg-white p-4 md:col-span-2">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <Badge label="Route Window Series" variant="neutral" />
              <Badge label={`${routeWindowSeries.length} pencere`} variant="neutral" />
            </div>
            <p className="mt-3 text-sm font-semibold">Birden fazla zaman penceresinde route sinyali</p>
            {routeWindowSeries.length > 0 ? (
              <div className="mt-3 grid gap-3 lg:grid-cols-2">
                {routeWindowSeries.map((windowSeries) => {
                  const topRoute = windowSeries.route_trends?.[0] ?? null;
                  const preferredFlowLabel =
                    windowSeries.preferred_flow === "draft_detail"
                      ? "Draft Detail"
                      : windowSeries.preferred_flow === "approvals_native"
                        ? "Approvals Native"
                        : "Dengeli";

                  return (
                    <div key={`${windowSeries.window_days}-${windowSeries.label}`} className="rounded-md border border-[var(--border)] bg-[var(--surface-2)] p-3">
                      <div className="flex flex-wrap items-center gap-2">
                        <Badge label={windowSeries.label} variant="neutral" />
                        <Badge label={`${windowSeries.window_days} gun`} variant="neutral" />
                        <Badge label={preferredFlowLabel} variant={routeTrendWindowVariant(windowSeries.preferred_flow)} />
                        {windowSeries.confidence ? (
                          <Badge
                            label={routeTrendConfidenceLabel(windowSeries.confidence)}
                            variant={routeTrendConfidenceVariant(windowSeries.confidence)}
                          />
                        ) : null}
                      </div>
                      <p className="mt-2 text-sm font-semibold">
                        {windowSeries.summary_label ?? windowSeries.reason ?? "Route trend ozeti"}
                      </p>
                      <p className="mt-1 text-xs muted-text">
                        {windowSeries.reason ?? "Bu pencere icin route trend verisi hazir."}
                      </p>
                      <div className="mt-2 grid gap-2 text-xs muted-text md:grid-cols-2">
                        <div className="rounded-md border border-[var(--border)] bg-white p-2">
                          <p className="font-semibold">Secilen Rota</p>
                          <p className="mt-1">{windowSeries.current_route_label ?? "Veri yok"}</p>
                          {windowSeries.current_route_success_rate != null ? (
                            <p className="mt-1">%{windowSeries.current_route_success_rate} basari</p>
                          ) : null}
                          {windowSeries.current_route_attempts != null ? (
                            <p className="mt-1">{windowSeries.current_route_attempts} deneme</p>
                          ) : null}
                          {windowSeries.current_route_advantage != null ? (
                            <p className="mt-1">
                              {windowSeries.current_route_advantage >= 0 ? "+" : ""}
                              {windowSeries.current_route_advantage} puan fark
                            </p>
                          ) : null}
                        </div>
                        <div className="rounded-md border border-[var(--border)] bg-white p-2">
                          <p className="font-semibold">Kazanan Rota</p>
                          <p className="mt-1">{topRoute?.label ?? windowSeries.top_route_label ?? "Veri yok"}</p>
                          {windowSeries.top_route_success_rate != null ? (
                            <p className="mt-1">%{windowSeries.top_route_success_rate} basari</p>
                          ) : null}
                          {windowSeries.top_route_attempts != null ? (
                            <p className="mt-1">{windowSeries.top_route_attempts} deneme</p>
                          ) : null}
                          {windowSeries.top_route_source_label ? (
                            <p className="mt-1">Top kaynak: {windowSeries.top_route_source_label}</p>
                          ) : null}
                        </div>
                      </div>
                      {topRoute ? (
                        <div className="mt-2 flex flex-wrap gap-2">
                          <Badge label={routeTrendFlowLabel(topRoute.route_key)} variant="neutral" />
                          {topRoute.publish_success_rate != null ? (
                            <Badge label={`%${topRoute.publish_success_rate} basari`} variant="success" />
                          ) : null}
                          {topRoute.top_source_label ? (
                            <Badge label={topRoute.top_source_label} variant="neutral" />
                          ) : null}
                        </div>
                      ) : null}
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className="mt-3 text-sm muted-text">Route window series henuz gelmedi.</p>
            )}
          </div>
          <div className="rounded-lg border border-[var(--accent)]/25 bg-white p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <Badge label="Source Spotlight" variant="neutral" />
              {sourceSpotlight ? (
                <Badge label={sourceSpotlight.label} variant={sourceSpotlight.variant} />
              ) : null}
            </div>
            <p className="mt-3 text-sm font-semibold">Hangi route daha guclu?</p>
            {sourceSpotlight ? (
              <>
                <p className="mt-2 text-sm muted-text">{sourceSpotlight.reason}</p>
                <p className="mt-2 text-xs muted-text">
                  Sonraki adim: {sourceSpotlight.nextStepLabel}
                </p>
                <div className="mt-3 flex flex-wrap gap-2">
                  {sourceSpotlight.preferredFlow === "draft_detail" && featuredDraftRoute ? (
                    <Link href={featuredDraftRoute}>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                          if (!featuredRecommendation) {
                            return;
                          }

                          void trackFeaturedInteraction({
                            actedClusterKey: featuredRecommendation.cluster_key,
                            interactionSource: "approvals_featured",
                            interactionType: "jump_to_item",
                          });
                        }}
                      >
                        {sourceSpotlight.nextStepLabel}
                      </Button>
                    </Link>
                  ) : featuredRecommendation ? (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() =>
                        void runClusterRecommendation(
                          featuredRecommendation.cluster_key,
                          featuredPrimaryAction.mode === "bulk_retry_publish"
                            ? "bulk_retry_publish"
                            : "focus_cluster",
                          "approvals_featured",
                        )
                      }
                      disabled={
                        bulkPublishing
                        && featuredPrimaryAction.mode === "bulk_retry_publish"
                      }
                    >
                      {sourceSpotlight.nextStepLabel}
                    </Button>
                  ) : null}
                </div>
              </>
            ) : (
              <p className="mt-3 text-sm muted-text">
                Source spotlight icin yeterli telemetry henuz olusmadi.
              </p>
            )}
          </div>
        </div>
        </>
      ) : null}

      <div className="mt-4 grid gap-3 xl:grid-cols-4">
        {quickClusters.map((cluster) => {
          const analyticsItem = analyticsByCluster.get(cluster.key);
          const matches = clusterItems(cluster);
          const retryableMatches = matches.filter((item) => canRetryPublish(item));
          const clusterDraftRoute =
            matches.length > 0
              ? buildDraftRoute(
                  matches[0],
                  analyticsWindowDays,
                  resolveLongTermSafeBulkRetry(analyticsItem)
                    ? "approvals_cluster_long_term"
                    : "approvals_cluster",
                  {
                  decisionStatus:
                    resolveClusterDecisionStatus(analyticsItem) ??
                    analyticsItem?.retry_guidance_status ??
                    analyticsItem?.effectiveness_status ??
                    null,
                  decisionReason: buildClusterFocusReason(cluster, analyticsItem, remediationAnalytics?.summary.long_term_window_days ?? null),
                  retryGuidanceStatus: analyticsItem?.long_term_retry_guidance_status ?? analyticsItem?.retry_guidance_status ?? null,
                  retryGuidanceLabel: analyticsItem?.long_term_retry_guidance_label ?? analyticsItem?.retry_guidance_label ?? null,
                  retryGuidanceReason: analyticsItem?.long_term_retry_guidance_reason ?? analyticsItem?.retry_guidance_reason ?? null,
                  effectivenessScore: analyticsItem?.long_term_effectiveness_score ?? analyticsItem?.effectiveness_score ?? null,
                  sourceComparisonLabel: sourceComparisonWinner?.label ?? null,
                  sourceComparisonReason: sourceComparisonWinner?.reason ?? null,
                  sourceComparisonWinner: sourceComparisonWinner?.label ?? null,
                  longTermWindowDays: remediationAnalytics?.summary.long_term_window_days ?? null,
                  longTermSuccessRate: analyticsItem?.long_term_publish_success_rate ?? null,
                  longTermEffectivenessStatus: analyticsItem?.long_term_effectiveness_status ?? null,
                  primaryActionMode: analyticsItem?.primary_action?.mode ?? null,
                  primaryActionRouteLabel: analyticsItem?.primary_action?.route_label ?? null,
                  primaryActionSourceLabel: analyticsItem?.primary_action?.source_label ?? null,
                  primaryActionReason: analyticsItem?.primary_action?.reason ?? null,
                  primaryActionSuccessRate: analyticsItem?.primary_action?.publish_success_rate ?? null,
                  primaryActionTrackedInteractions: analyticsItem?.primary_action?.tracked_interactions ?? null,
                  primaryActionConfidenceStatus: analyticsItem?.primary_action?.confidence_status ?? null,
                  primaryActionConfidenceLabel: analyticsItem?.primary_action?.confidence_label ?? null,
                  primaryActionTrendStatus: analyticsItem?.primary_action?.trend_status ?? null,
                  primaryActionTrendReason: analyticsItem?.primary_action?.trend_reason ?? null,
                  primaryActionRouteSeries: resolvePrimaryActionRouteSeries(
                    analyticsItem?.primary_action,
                    analyticsItem?.route_window_series,
                  ),
                  primaryActionAdvantage: analyticsItem?.primary_action?.advantage_vs_alternative_route ?? null,
                  primaryActionAlternativeRouteLabel: analyticsItem?.primary_action?.alternative_route_label ?? null,
                  primaryActionAlternativeSuccessRate: analyticsItem?.primary_action?.alternative_publish_success_rate ?? null,
                  ...buildRouteTrendFocusContext(routeTrendInsight),
                  }
                )
              : null;
          const currentTopSource = topSourceBreakdown(analyticsItem?.source_breakdown);
          const longTermTopSource = topSourceBreakdown(analyticsItem?.long_term_source_breakdown);
          const topSource = longTermTopSource ?? currentTopSource;
          const clusterOutcomeSummary = analyticsItem?.outcome_chain_summary ?? null;
          const clusterPrimaryActionRouteSeriesSummary = summarizePrimaryActionRouteSeries(
            analyticsItem?.primary_action,
            analyticsItem?.route_window_series,
          );
          const clusterPrimaryAction = resolveClusterPrimaryAction(
            cluster.key,
            analyticsItem,
            topSource,
            routeTrendInsight,
            clusterDraftRoute,
            retryableMatches.length,
          );

          return (
            <div
              key={cluster.key}
              className="rounded-lg border border-[var(--border)] bg-[var(--surface-2)] p-4 text-left"
            >
              <div className="flex flex-wrap items-center gap-2">
                <Badge label={cluster.label} variant={cluster.variant} />
                <Badge label={`${cluster.count} kayit`} variant="neutral" />
                {remediationAnalytics?.featured_recommendation?.cluster_key === cluster.key ? (
                  <Badge label="One Cikti" variant="success" />
                ) : null}
                {analyticsItem?.featured_follow_rate != null ? (
                  <Badge label={`%${analyticsItem.featured_follow_rate} takip`} variant="neutral" />
                ) : null}
                {analyticsItem?.publish_success_rate != null ? (
                  <Badge label={`%${analyticsItem.publish_success_rate} publish basarisi`} variant="success" />
                ) : null}
                {analyticsItem?.primary_action?.trend_status ? (
                  <Badge
                    label={primaryActionTrendStatusLabel(analyticsItem.primary_action.trend_status)}
                    variant={primaryActionTrendStatusVariant(analyticsItem.primary_action.trend_status)}
                  />
                ) : null}
                {analyticsItem ? (
                  <Badge
                    label={`Effectiveness ${analyticsItem.effectiveness_score}`}
                    variant={analyticsItem.effectiveness_status === "proven" ? "success" : "neutral"}
                  />
                ) : null}
                {analyticsItem ? (
                  <Badge
                    label={resolveRetryGuidanceLabel(analyticsItem)}
                    variant={retryGuidanceVariant(resolveRetryGuidanceStatus(analyticsItem))}
                  />
                ) : null}
                {analyticsItem?.long_term_publish_success_rate != null ? (
                  <Badge
                    label={`${remediationAnalytics?.summary.long_term_window_days ?? 90}g %${analyticsItem.long_term_publish_success_rate} basari`}
                    variant="neutral"
                  />
                ) : null}
                {analyticsItem?.long_term_effectiveness_status ? (
                  <Badge
                    label={`${remediationAnalytics?.summary.long_term_window_days ?? 90}g ${effectivenessStatusLabel(analyticsItem.long_term_effectiveness_status)}`}
                    variant={effectivenessStatusVariant(analyticsItem.long_term_effectiveness_status)}
                  />
                ) : null}
              </div>
              <p className="mt-3 text-sm font-semibold">{cluster.label}</p>
              <p className="mt-1 text-sm muted-text">{cluster.detail}</p>
              {analyticsItem ? (
                <div className="mt-3 space-y-1 text-xs muted-text">
                  <p>{analyticsItem.health_summary}</p>
                  <p>{effectivenessStatusLabel(analyticsItem.effectiveness_status)}</p>
                  <p>
                    {analyticsItem.manual_check_completions} manuel kontrol / {analyticsItem.publish_attempts} publish denemesi
                  </p>
                  {analyticsItem.featured_interactions > 0 ? (
                    <p>
                      {analyticsItem.featured_followed_interactions} takip /
                      {` ${analyticsItem.featured_override_interactions}`} override /
                      {` ${analyticsItem.featured_publish_attempts}`} featured publish denemesi
                    </p>
                  ) : null}
                  {topSource ? (
                    <p>
                      {longTermTopSource ? "Uzun donem top kaynak" : "Top kaynak"}:
                      {" "}
                      {sourceLabel(topSource.source_key, topSource.label)}
                      {` / ${topSource.tracked_interactions} etkilesim`}
                    </p>
                  ) : null}
                  {currentTopSource && longTermTopSource && currentTopSource.source_key !== longTermTopSource.source_key ? (
                    <p>
                      Mevcut pencere top kaynagi: {sourceLabel(currentTopSource.source_key, currentTopSource.label)}
                      {` / ${currentTopSource.tracked_interactions} etkilesim`}
                    </p>
                  ) : null}
                  {clusterOutcomeSummary ? (
                    <p>
                      Outcome: {clusterOutcomeSummary.manual_check_completions} manuel kontrol /
                      {` ${clusterOutcomeSummary.total_retry_actions}`} retry /
                      {` ${clusterOutcomeSummary.successful_publishes}`} basarili publish
                    </p>
                  ) : null}
                  {analyticsItem.long_term_publish_attempts != null ? (
                    <p>
                      {remediationAnalytics?.summary.long_term_window_days ?? 90} gun: {analyticsItem.long_term_publish_attempts}
                      {" "}publish denemesi /
                      {` %${analyticsItem.long_term_publish_success_rate ?? 0}`} basari
                    </p>
                  ) : null}
                  {analyticsItem.retry_guidance_reason ? (
                    <p>{analyticsItem.retry_guidance_reason}</p>
                  ) : null}
                  {analyticsItem.long_term_retry_guidance_reason ? (
                    <p>{analyticsItem.long_term_retry_guidance_reason}</p>
                  ) : null}
                  {analyticsItem.primary_action?.trend_reason ? (
                    <p>{analyticsItem.primary_action.trend_reason}</p>
                  ) : null}
                  {clusterPrimaryActionRouteSeriesSummary ? (
                    <p>Route serisi: {clusterPrimaryActionRouteSeriesSummary}</p>
                  ) : null}
                </div>
              ) : null}
              <div className="mt-4 flex flex-wrap gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => {
                    focusQuickCluster(cluster);
                    void trackFeaturedInteraction({
                      actedClusterKey: cluster.key,
                      interactionSource: "approvals_cluster",
                      interactionType: "focus_cluster",
                    });
                  }}
                >
                  Kumeyi Filtrele
                </Button>
                {clusterPrimaryAction.mode === "jump_to_draft_detail" && clusterDraftRoute ? (
                  <Link href={clusterDraftRoute}>
                    <Button
                      type="button"
                      size="sm"
                      variant={clusterPrimaryAction.variant}
                      onClick={() =>
                        void trackFeaturedInteraction({
                          actedClusterKey: cluster.key,
                          interactionSource: "approvals_cluster",
                          interactionType: "jump_to_item",
                        })
                      }
                    >
                      {clusterPrimaryAction.label}
                    </Button>
                  </Link>
                ) : (
                  <Button
                    type="button"
                    size="sm"
                    variant={clusterPrimaryAction.variant}
                    onClick={() => {
                      if (clusterPrimaryAction.mode === "bulk_retry_publish") {
                        void runBulkPublishRetry(retryableMatches, cluster.key, "approvals_cluster");
                        return;
                      }

                      focusQuickCluster(cluster);
                      void trackFeaturedInteraction({
                        actedClusterKey: cluster.key,
                        interactionSource: "approvals_cluster",
                        interactionType: "focus_cluster",
                      });
                    }}
                    disabled={
                      bulkPublishing
                      || (clusterPrimaryAction.mode === "bulk_retry_publish" && retryableMatches.length === 0)
                    }
                  >
                    {clusterPrimaryAction.label}
                  </Button>
                )}
                {clusterDraftRoute ? (
                  <Link href={clusterDraftRoute}>
                    <Button type="button" variant="outline" size="sm">
                      Draft Detayina Git
                    </Button>
                  </Link>
                ) : null}
              </div>
              <p className="mt-2 text-xs muted-text">{clusterPrimaryAction.hint}</p>
            </div>
          );
        })}
      </div>

      {retryReadyItems.length > 0 ? (
        <div className="mt-4 rounded-lg border border-[var(--success)]/30 bg-[var(--surface-2)] p-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <Badge label="Tekrar Publish'e Hazir" variant="success" />
                <Badge label={`${summary.retryReady} kayit`} variant="neutral" />
              </div>
              <p className="mt-2 text-sm muted-text">
                Manuel kontrolu tamamlanmis ve tekrar publish icin hazir kayitlar.
              </p>
            </div>
            <Button
              variant="secondary"
              size="sm"
              onClick={() =>
                void jumpToClusterAction(
                  quickClusters.find((cluster) => cluster.key === "retry-ready")?.key ?? "retry-ready",
                  "approvals_retry_ready",
                )
              }
            >
              Ilk Retry Kaydina Git
            </Button>
            <Button
              size="sm"
              onClick={() =>
                runBulkPublishRetry(
                  publishFailureItems.filter((item) => canRetryPublish(item)),
                  "retry-ready",
                  "approvals_retry_ready",
                )
              }
              disabled={bulkPublishing || summary.retryReady === 0}
            >
              Retry-Hazirlarda Toplu Publish
            </Button>
          </div>

          <div className="mt-4 space-y-3">
            {retryReadyItems.map((item) => (
              <div key={item.id} id={`retry-ready-preview-${item.id}`} className="rounded-md border border-[var(--border)] bg-white p-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <p className="font-semibold">{item.approvable_label}</p>
                    <p className="text-xs muted-text">{item.publish_state?.recommended_action_label}</p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Badge label="Kontrol Tamamlandi" variant="success" />
                    {item.publish_state?.manual_check_completed_at ? (
                      <Badge
                        label={new Date(item.publish_state.manual_check_completed_at).toLocaleString("tr-TR")}
                        variant="neutral"
                      />
                    ) : null}
                  </div>
                </div>
                {item.publish_state?.manual_check_note ? (
                  <p className="mt-2 text-xs muted-text">Kontrol notu: {item.publish_state.manual_check_note}</p>
                ) : null}
                <div className="mt-3 flex flex-wrap gap-2">
              {item.approvable_route ? (
                <Link
                  href={
                    buildDraftRoute(item, analyticsWindowDays, "approvals_retry_ready", {
                      decisionStatus: deriveFocusPublishState(item),
                      decisionReason: item.publish_state?.recommended_action_label ?? item.publish_state?.operator_guidance ?? null,
                      retryGuidanceStatus: canRetryPublish(item)
                        ? "safe"
                        : item.publish_state?.manual_check_required
                          ? "blocked"
                          : "guarded",
                      retryGuidanceLabel: item.publish_state?.recommended_action_label ?? null,
                      retryGuidanceReason: item.publish_state?.operator_guidance ?? null,
                      ...buildRouteTrendFocusContext(routeTrendInsight),
                    }) ?? item.approvable_route
                  }
                >
                  <Button variant="outline" size="sm">
                    Drafta Git
                  </Button>
                </Link>
              ) : null}
                  <Button size="sm" onClick={() => callAction(item, "publish", "approvals_retry_ready")}>
                    Tekrar Publish Dene
                  </Button>
                </div>
              </div>
            ))}
          </div>
        </div>
      ) : null}

      {combinedError ? <p className="mt-4 text-sm text-[var(--danger)]">{combinedError}</p> : null}
      {actionMessage ? <p className="mt-4 text-sm text-[var(--success)]">{actionMessage}</p> : null}
      {isLoading && items.length === 0 ? <p className="mt-4 text-sm muted-text">Onay kayitlari yukleniyor.</p> : null}

      <div className="mt-4 space-y-3">
        {items.map((item) => (
          <div
            key={item.id}
            id={`approval-item-${item.id}`}
            className={`rounded-md border p-3 ${
              focusedApprovalId === item.id ? "border-[var(--accent)] ring-2 ring-[var(--accent)]/25" : "border-[var(--border)]"
            }`}
          >
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div className="flex items-start gap-3">
                <input
                  type="checkbox"
                  className="mt-1 h-4 w-4 rounded border border-[var(--border)]"
                  checked={selectedApprovalIds.includes(item.id)}
                  onChange={(event) => toggleApprovalSelection(item.id, event.target.checked)}
                  aria-label={`${item.approvable_label} sec`}
                />
                <div>
                  <p className="font-semibold">{item.approvable_label}</p>
                  <p className="text-xs muted-text">{item.approvable_type_label} - {item.approvable_id}</p>
                </div>
              </div>
              <div className="flex flex-wrap items-center gap-2">
                {canRetryPublish(item) ? <Badge label="Retry-Hazir" variant="success" /> : null}
                <Badge label={item.status} variant={statusVariant(item.status)} />
              </div>
            </div>
            {item.publish_state ? (
              <div
                className={`mt-3 rounded-md border p-3 ${
                  item.publish_state.manual_check_required
                    ? "border-[var(--danger)] bg-[var(--surface-2)]"
                    : "border-[var(--border)] bg-[var(--surface-2)]"
                }`}
              >
                <div className="flex flex-wrap items-center gap-2">
                  <Badge
                    label={
                      item.publish_state.manual_check_required
                        ? "Manuel Kontrol Gerekli"
                        : item.publish_state.manual_check_completed
                          ? "Manuel Kontrol Tamamlandi"
                          : item.publish_state.partial_publish_detected
                            ? "Partial Publish Tespit Edildi"
                            : item.publish_state.success
                              ? "Publish Basarili"
                              : "Publish Hatasi"
                    }
                    variant={
                      item.publish_state.manual_check_required
                        ? "danger"
                        : item.publish_state.manual_check_completed
                          ? "success"
                          : item.publish_state.success
                            ? "success"
                            : "warning"
                    }
                  />
                  {item.publish_state.recommended_action_label ? (
                    <Badge label={item.publish_state.recommended_action_label} variant="neutral" />
                  ) : null}
                  {item.publish_state.cleanup_attempted ? (
                    <Badge
                      label={item.publish_state.cleanup_success ? "Cleanup Basarili" : "Cleanup Basarisiz"}
                      variant={item.publish_state.cleanup_success ? "success" : "danger"}
                    />
                  ) : null}
                </div>
                {item.publish_state.message ? <p className="mt-2 text-sm">{item.publish_state.message}</p> : null}
                {item.publish_state.operator_guidance ? (
                  <p className="mt-2 text-sm muted-text">{item.publish_state.operator_guidance}</p>
                ) : null}
                <div className="mt-2 flex flex-wrap gap-3 text-xs muted-text">
                  {item.publish_state.meta_campaign_id ? (
                    <span>Campaign: <strong>{item.publish_state.meta_campaign_id}</strong></span>
                  ) : null}
                  {item.publish_state.meta_ad_set_id ? (
                    <span>Ad Set: <strong>{item.publish_state.meta_ad_set_id}</strong></span>
                  ) : null}
                  {item.publish_state.manual_check_completed_at ? (
                    <span>Kontrol: <strong>{new Date(item.publish_state.manual_check_completed_at).toLocaleString("tr-TR")}</strong></span>
                  ) : null}
                </div>
                {item.publish_state.cleanup_message ? (
                  <p className="mt-2 text-xs text-[var(--danger)]">{item.publish_state.cleanup_message}</p>
                ) : null}
                {item.publish_state.manual_check_note ? (
                  <p className="mt-2 text-xs muted-text">Kontrol notu: {item.publish_state.manual_check_note}</p>
                ) : null}
              </div>
            ) : null}
            {item.rejection_reason ? (
              <p className="mt-3 text-sm text-[var(--danger)]">Red nedeni: {item.rejection_reason}</p>
            ) : null}
            <div className="mt-3 flex flex-wrap gap-2">
              {item.approvable_route ? (
                <Link
                  href={
                    buildDraftRoute(item, analyticsWindowDays, "approvals_item", {
                      decisionStatus: deriveFocusPublishState(item),
                      decisionReason: item.publish_state?.recommended_action_label ?? item.publish_state?.operator_guidance ?? null,
                      retryGuidanceStatus: item.publish_state?.manual_check_required
                        ? "blocked"
                        : canRetryPublish(item)
                          ? "safe"
                          : "guarded",
                      retryGuidanceLabel: item.publish_state?.recommended_action_label ?? null,
                      retryGuidanceReason: item.publish_state?.operator_guidance ?? null,
                      ...buildRouteTrendFocusContext(routeTrendInsight),
                    }) ?? item.approvable_route
                  }
                >
                  <Button variant="outline" size="sm">
                    Drafta Git
                  </Button>
                </Link>
              ) : null}
              {item.publish_state?.manual_check_required ? (
                <Button variant="secondary" size="sm" onClick={() => completeManualCheck(item)}>
                  Manuel Kontrol Tamamlandi
                </Button>
              ) : null}
              <Button variant="secondary" size="sm" onClick={() => callAction(item, "approve")}>
                Onayla
              </Button>
              <Button variant="outline" size="sm" onClick={() => callAction(item, "reject")}>
                Reddet
              </Button>
              <Button size="sm" onClick={() => callAction(item, "publish")}>
                {item.publish_state?.manual_check_required
                  ? "Kontrol Sonrasi Tekrar Dene"
                  : item.publish_state?.manual_check_completed
                    ? "Tekrar Publish Dene"
                    : "Publish Dene"}
              </Button>
            </div>
          </div>
        ))}
      </div>

      {!isLoading && items.length === 0 ? (
        <p className="mt-4 text-sm muted-text">Filtreye uygun onay kaydi bulunmuyor.</p>
      ) : null}
    </Card>
  );
}

function canRetryPublish(item: Approval): boolean {
  return (
    item.status === "publish_failed"
    && (
      item.publish_state?.recommended_action_code === "retry_publish_after_manual_check"
      || item.publish_state?.recommended_action_code === "fix_and_retry_publish"
    )
  );
}

function approvalClusterKey(item: Approval): string | null {
  return (
    {
      manual_meta_check: "manual-check-required",
      retry_publish_after_manual_check: "retry-ready",
      fix_and_retry_publish: "cleanup-recovered",
      review_publish_error: "review-error",
    }[item.publish_state?.recommended_action_code ?? ""] ?? null
  );
}

function deriveFocusPublishState(item: Approval): string | null {
  if (!item.publish_state) {
    return null;
  }

  if (item.publish_state.manual_check_required) {
    return "manual_check_required";
  }

  if (item.publish_state.manual_check_completed) {
    return "manual_check_completed";
  }

  if (item.publish_state.cleanup_attempted && item.publish_state.cleanup_success === false) {
    return "cleanup_failed";
  }

  if (item.publish_state.cleanup_attempted && item.publish_state.cleanup_success === true) {
    return "cleanup_successful";
  }

  if (item.publish_state.partial_publish_detected) {
    return "partial_publish";
  }

  if (item.publish_state.success === false) {
    return "publish_failed";
  }

  if (item.publish_state.success === true) {
    return "published";
  }

  return null;
}

function extractErrorMessage(reason: unknown): string {
  if (reason instanceof Error && reason.message.trim()) {
    return reason.message;
  }

  return "Toplu publish retry aksiyonu basarisiz.";
}

function clusterActionLabel(
  clusterKey: string,
  analyticsItem?: {
    retry_guidance_status?: string | null;
    safe_bulk_retry?: boolean | null;
    long_term_retry_guidance_status?: string | null;
    long_term_safe_bulk_retry?: boolean | null;
    long_term_effectiveness_status?: string | null;
    long_term_publish_success_rate?: number | null;
  },
): string {
  const guidanceStatus = resolveRetryGuidanceStatus(analyticsItem);
  const longTermSafe = resolveLongTermSafeBulkRetry(analyticsItem);

  if (guidanceStatus === "blocked") {
    return "Manuel Kontrole Git";
  }

  if (guidanceStatus === "guarded") {
    return "Kumeyi Incele";
  }

  if (longTermSafe) {
    return "Uzun Donem Stabil Retry Publish";
  }

  if (shouldRunClusterBulkRetry(clusterKey, analyticsItem, 1)) {
    return "Toplu Retry Publish";
  }

  return (
    {
      "manual-check-required": "Bekleyenleri Sec",
      "retry-ready": "Toplu Retry Publish",
      "cleanup-recovered": "Toplu Retry Publish",
      "review-error": "Hatalari Filtrele",
    }[clusterKey] ?? "Kumeyi Filtrele"
  );
}

function resolveRetryGuidanceStatus(
  context?: RetryGuidanceContext | null,
): RetryGuidanceStatus {
  if (!context) {
    return "unknown";
  }

  if (context.safe_bulk_retry === true) {
    return "safe";
  }

  const normalized = (context.retry_guidance_status ?? "").trim().toLowerCase();

  if (normalized === "safe" || normalized === "guarded" || normalized === "blocked") {
    return normalized;
  }

  if (normalized.includes("safe")) {
    return "safe";
  }

  if (normalized.includes("guard")) {
    return "guarded";
  }

  if (normalized.includes("manual") || normalized.includes("block")) {
    return "blocked";
  }

  return "unknown";
}

function resolveRetryGuidanceLabel(context?: RetryGuidanceContext | null): string {
  const status = resolveRetryGuidanceStatus(context);

  if (context?.retry_guidance_label) {
    return context.retry_guidance_label;
  }

  return (
    {
      safe: "Guvenli Retry",
      guarded: "Guarded Retry",
      blocked: "Toplu Retry Uygun Degil",
      unknown: "Retry Durumu Bilinmiyor",
    }[status] ?? "Retry Durumu Bilinmiyor"
  );
}

function retryGuidanceVariant(status: RetryGuidanceStatus): "success" | "warning" | "danger" | "neutral" {
  switch (status) {
    case "safe":
      return "success";
    case "guarded":
      return "warning";
    case "blocked":
      return "danger";
    default:
      return "neutral";
  }
}

function resolveGuidedActionMode(
  context?: RetryGuidanceContext | null,
): "focus_cluster" | "bulk_retry_publish" {
  const status = resolveRetryGuidanceStatus(context);

  if (status === "safe" && (context?.action_mode === "bulk_retry_publish" || context?.safe_bulk_retry !== false)) {
    return "bulk_retry_publish";
  }

  if (
    context?.decision_context_source === "long_term"
    && context?.long_term_safe_bulk_retry !== false
    && context?.long_term_effectiveness_status === "proven"
    && (status === "unknown" || status === "safe")
  ) {
    return "bulk_retry_publish";
  }

  return "focus_cluster";
}

function resolveGuidedActionLabel(
  context: RetryGuidanceContext | undefined | null,
  actionMode: "focus_cluster" | "bulk_retry_publish",
): string {
  const status = resolveRetryGuidanceStatus(context);

  if (context?.decision_context_source === "long_term" && actionMode === "bulk_retry_publish") {
    return "Uzun Donem Stabil Retry Publish";
  }

  if (context?.decision_context_source === "long_term") {
    return "Uzun Donem Stabil Kume Uzerinde Calis";
  }

  if (context?.retry_guidance_label) {
    return context.retry_guidance_label;
  }

  if (status === "blocked") {
    return "Manuel Kontrole Git";
  }

  if (status === "guarded") {
    return "Kumeyi Incele";
  }

  if (actionMode === "bulk_retry_publish") {
    return "Onerilen Retry Akisini Calistir";
  }

  return "Onerilen Kume Uzerinde Calis";
}

function shouldPreferDraftDetailPrimaryAction(
  sourceComparison: SourceComparisonInsight | null,
  routeTrendInsight: RouteTrendInsight | null,
  draftRoute: string | null,
  context?: RetryGuidanceContext | null,
): boolean {
  if (!draftRoute) {
    return false;
  }

  if (resolveLongTermSafeBulkRetry(context)) {
    return false;
  }

  if (context?.primary_action?.confidence_status === "guarded") {
    return false;
  }

  if (context?.decision_context_source === "draft_detail") {
    return true;
  }

  if (routeTrendInsight?.preferredFlow === "approvals_native") {
    return false;
  }

  if (routeTrendInsight?.preferredFlow === "draft_detail" && routeTrendInsight.confidence !== "low") {
    return true;
  }

  return sourceComparison?.preferredFlow === "draft_detail";
}

function resolveFeaturedPrimaryAction(
  context: RetryGuidanceContext | undefined | null,
  sourceComparison: SourceComparisonInsight | null,
  routeTrendInsight: RouteTrendInsight | null,
  draftRoute: string | null,
): {
  mode: "focus_cluster" | "bulk_retry_publish" | "jump_to_draft_detail";
  label: string;
  hint: string;
} {
  if (
    context?.primary_action?.mode === "jump_to_item"
    && draftRoute
    && context.primary_action.confidence_status !== "guarded"
  ) {
    return {
      mode: "jump_to_draft_detail",
      label: routeTrendInsight?.nextStepLabel ?? "Draft Detail Akisina Git",
      hint: withPrimaryActionTrendHint(
        context.primary_action.reason ?? routeTrendInsight?.reason ?? sourceComparison?.reason ?? "Bu remediation detay ekranda daha guclu sonuc veriyor.",
        context.primary_action,
      ),
    };
  }

  if (shouldPreferDraftDetailPrimaryAction(sourceComparison, routeTrendInsight, draftRoute, context)) {
    return {
      mode: "jump_to_draft_detail",
      label: routeTrendInsight?.nextStepLabel ?? "Draft Detail Akisina Git",
      hint: withPrimaryActionTrendHint(
        routeTrendInsight?.reason ?? sourceComparison?.reason ?? "Bu remediation detay ekranda daha guclu sonuc veriyor.",
        context?.primary_action,
      ),
    };
  }

  const actionMode = resolveGuidedActionMode(context);

  return {
    mode: actionMode,
    label: resolveGuidedActionLabel(context, actionMode),
    hint:
      actionMode === "bulk_retry_publish"
        ? (context?.retry_guidance_reason ?? "Bu cluster guvenli retry sinyali verdigi icin tek tik retry oneriliyor.")
        : (context?.retry_guidance_reason ?? "Bu cluster toplu retry yerine once inceleme odagi gerektiriyor."),
  };
}

function shouldPreferClusterDraftDetailAction(
  analyticsItem: {
    retry_guidance_status?: string | null;
    retry_guidance_reason?: string | null;
    safe_bulk_retry?: boolean | null;
    long_term_retry_guidance_status?: string | null;
    long_term_retry_guidance_reason?: string | null;
    long_term_safe_bulk_retry?: boolean | null;
    long_term_effectiveness_status?: string | null;
    long_term_publish_success_rate?: number | null;
    primary_action?: RetryGuidanceContext["primary_action"];
  } | undefined,
  topSource: RemediationTelemetrySource | null,
  routeTrendInsight: RouteTrendInsight | null,
  draftRoute: string | null,
): boolean {
  if (!analyticsItem || !topSource || !draftRoute) {
    return false;
  }

  if (resolveLongTermSafeBulkRetry(analyticsItem) || resolveRetryGuidanceStatus(analyticsItem) === "safe") {
    return false;
  }

  if (analyticsItem?.primary_action?.confidence_status === "guarded") {
    return false;
  }

  if (routeTrendInsight?.preferredFlow === "approvals_native") {
    return false;
  }

  const normalizedSourceKey = topSource.source_key.trim().toLowerCase();
  const sourcePrefersDraftDetail = normalizedSourceKey.startsWith("draft_detail");
  const sourceSuccessRate = topSource.publish_success_rate ?? 0;
  const routeTrendSupportsDraftDetail = routeTrendInsight?.preferredFlow === "draft_detail"
    && routeTrendInsight.confidence !== "low";

  return sourcePrefersDraftDetail && (
    routeTrendSupportsDraftDetail
    || sourceSuccessRate >= 60
    || topSource.successful_publishes > 0
  );
}

function resolveClusterPrimaryAction(
  clusterKey: string,
  analyticsItem: {
    retry_guidance_status?: string | null;
    retry_guidance_reason?: string | null;
    safe_bulk_retry?: boolean | null;
    long_term_retry_guidance_status?: string | null;
    long_term_retry_guidance_reason?: string | null;
    long_term_safe_bulk_retry?: boolean | null;
    long_term_effectiveness_status?: string | null;
    long_term_publish_success_rate?: number | null;
    primary_action?: RetryGuidanceContext["primary_action"];
  } | undefined,
  topSource: RemediationTelemetrySource | null,
  routeTrendInsight: RouteTrendInsight | null,
  draftRoute: string | null,
  retryableMatchesCount: number,
): {
  mode: "focus_cluster" | "bulk_retry_publish" | "jump_to_draft_detail";
  label: string;
  variant: "primary" | "secondary" | "outline";
  hint: string;
} {
  if (
    analyticsItem?.primary_action?.mode === "jump_to_item"
    && draftRoute
    && analyticsItem.primary_action.confidence_status !== "guarded"
  ) {
    return {
      mode: "jump_to_draft_detail",
      label: routeTrendInsight?.nextStepLabel ?? "Draft Detail Akisina Git",
      variant: analyticsItem.primary_action.confidence_status === "proven" ? "primary" : "secondary",
      hint: withPrimaryActionTrendHint(
        analyticsItem.primary_action.reason
          ?? routeTrendInsight?.reason
          ?? "Bu cluster icin draft detail aksiyonu daha guclu sonuc veriyor.",
        analyticsItem.primary_action,
      ),
    };
  }

  if (shouldPreferClusterDraftDetailAction(analyticsItem, topSource, routeTrendInsight, draftRoute)) {
    return {
      mode: "jump_to_draft_detail",
      label: routeTrendInsight?.nextStepLabel ?? "Draft Detail Akisina Git",
      variant: "secondary",
      hint: topSource?.publish_success_rate != null
        ? `${routeTrendInsight?.reason ?? `Bu cluster icin ${sourceLabel(topSource.source_key, topSource.label)} kaynagi %${topSource.publish_success_rate} publish basarisi uretmis.`}`
        : (routeTrendInsight?.reason ?? "Bu cluster detay ekranda daha guclu sinyal veriyor."),
    };
  }

  if (shouldRunClusterBulkRetry(clusterKey, analyticsItem, retryableMatchesCount)) {
    return {
      mode: "bulk_retry_publish",
      label: clusterActionLabel(clusterKey, analyticsItem),
      variant: resolveClusterActionVariant(clusterKey, analyticsItem),
      hint: analyticsItem?.retry_guidance_reason ?? "Bu cluster icin toplu retry uygulanabilir.",
    };
  }

  return {
    mode: "focus_cluster",
    label: clusterActionLabel(clusterKey, analyticsItem),
    variant: resolveClusterActionVariant(clusterKey, analyticsItem),
    hint: analyticsItem?.retry_guidance_reason ?? "Bu cluster once odakli inceleme gerektiriyor.",
  };
}

function resolveClusterActionVariant(
  clusterKey: string,
  analyticsItem?: {
    retry_guidance_status?: string | null;
    safe_bulk_retry?: boolean | null;
    long_term_retry_guidance_status?: string | null;
    long_term_safe_bulk_retry?: boolean | null;
    long_term_effectiveness_status?: string | null;
    long_term_publish_success_rate?: number | null;
  },
): "primary" | "secondary" | "outline" {
  const guidanceStatus = resolveRetryGuidanceStatus(analyticsItem);
  const longTermSafe = resolveLongTermSafeBulkRetry(analyticsItem);

  if (guidanceStatus === "blocked" || guidanceStatus === "guarded") {
    return "outline";
  }

  if (longTermSafe) {
    return "primary";
  }

  return shouldRunClusterBulkRetry(clusterKey, analyticsItem, 1) ? "primary" : "secondary";
}

function shouldRunClusterBulkRetry(
  clusterKey: string,
  analyticsItem?: {
    retry_guidance_status?: string | null;
    safe_bulk_retry?: boolean | null;
    long_term_retry_guidance_status?: string | null;
    long_term_safe_bulk_retry?: boolean | null;
    long_term_effectiveness_status?: string | null;
  },
  retryableMatchesCount = 0,
): boolean {
  if (retryableMatchesCount === 0) {
    return false;
  }

  const guidanceStatus = resolveRetryGuidanceStatus(analyticsItem);

  if (guidanceStatus === "guarded" || guidanceStatus === "blocked") {
    return false;
  }

  if (analyticsItem?.safe_bulk_retry === false) {
    return false;
  }

  if (resolveLongTermSafeBulkRetry(analyticsItem)) {
    return true;
  }

  if (guidanceStatus === "safe") {
    return true;
  }

  return clusterKey === "retry-ready" || clusterKey === "cleanup-recovered";
}

function sourceLabel(sourceKey: string, fallbackLabel: string): string {
  return (
    {
      approvals_featured: "Featured Karar",
      approvals_cluster: "Cluster Karari",
      approvals_retry_ready: "Retry-Hazir Akisi",
      approvals_item: "Item Aksiyonu",
      approvals_featured_long_term: "Featured Uzun Donem",
      approvals_cluster_long_term: "Cluster Uzun Donem",
      approvals: "Approvals Merkezi",
      draft_detail: "Draft Detayi",
      draft_detail_from_approvals_featured: "Draft Detay - Featured",
      draft_detail_from_approvals_cluster: "Draft Detay - Cluster",
      draft_detail_from_approvals_retry_ready: "Draft Detay - Retry Hazir",
      draft_detail_from_approvals_item: "Draft Detay - Item",
      other: "Diger",
    }[sourceKey] ?? fallbackLabel
  );
}

function buildFocusRouteReason({
  baseReason,
  decisionContextSource,
  longTermWindowDays,
  longTermSuccessRate,
  longTermBaselineSuccessRate,
  sourceComparisonReason,
  sourceComparisonLabel,
  decisionContextAdvantage,
}: {
  baseReason: string | null;
  decisionContextSource?: string | null;
  longTermWindowDays?: number | null;
  longTermSuccessRate?: number | null;
  longTermBaselineSuccessRate?: number | null;
  sourceComparisonReason?: string | null;
  sourceComparisonLabel?: string | null;
  decisionContextAdvantage?: number | null;
}): string {
  const parts: string[] = [];

  if (decisionContextSource === "long_term" && longTermWindowDays != null) {
    parts.push(`${longTermWindowDays} gunluk uzun donem stabil cluster odagi.`);
    if (longTermSuccessRate != null) {
      parts.push(`Uzun donem publish basarisi %${longTermSuccessRate}.`);
    }
    if (longTermBaselineSuccessRate != null) {
      parts.push(`Mevcut pencere referansi %${longTermBaselineSuccessRate}.`);
    }
  }

  if (decisionContextSource === "draft_detail" && decisionContextAdvantage != null) {
    parts.push(`Draft detail akisiyla %+${decisionContextAdvantage} avantaj.`);
  }

  if (sourceComparisonLabel || sourceComparisonReason) {
    parts.push(`${sourceComparisonLabel ?? "Kaynak karsilastirmasi"}: ${sourceComparisonReason ?? ""}`.trim());
  }

  if (baseReason) {
    parts.push(baseReason);
  }

  return parts.filter(Boolean).join(" ").trim();
}

function resolveLongTermSafeBulkRetry(
  context?: {
    long_term_retry_guidance_status?: string | null;
    long_term_safe_bulk_retry?: boolean | null;
    long_term_effectiveness_status?: string | null;
    long_term_publish_success_rate?: number | null;
  } | null,
): boolean {
  if (!context) {
    return false;
  }

  if (context.long_term_safe_bulk_retry === true) {
    return true;
  }

  const guidanceStatus = resolveRetryGuidanceStatus({
    retry_guidance_status: context.long_term_retry_guidance_status ?? null,
    safe_bulk_retry: context.long_term_safe_bulk_retry ?? null,
  });

  return guidanceStatus === "safe" && context.long_term_effectiveness_status === "proven";
}

function resolveClusterDecisionStatus(
  analyticsItem?: {
    long_term_effectiveness_status?: string | null;
    long_term_safe_bulk_retry?: boolean | null;
    long_term_publish_success_rate?: number | null;
    retry_guidance_status?: string | null;
    effectiveness_status?: string | null;
  } | null,
): string | null {
  if (!analyticsItem) {
    return null;
  }

  if (resolveLongTermSafeBulkRetry(analyticsItem)) {
    return "long_term_preferred";
  }

  return analyticsItem.retry_guidance_status ?? analyticsItem.effectiveness_status ?? null;
}

function buildClusterFocusReason(
  cluster: QuickCluster,
  analyticsItem?: {
    health_summary?: string | null;
    long_term_publish_success_rate?: number | null;
    long_term_effectiveness_status?: string | null;
    long_term_retry_guidance_reason?: string | null;
    long_term_window_days?: number | null;
    retry_guidance_reason?: string | null;
    effectiveness_status?: string | null;
  } | null,
  longTermWindowDays?: number | null,
): string {
  const parts: string[] = [];

  if (analyticsItem?.long_term_effectiveness_status === "proven" && analyticsItem.long_term_publish_success_rate != null) {
    parts.push(
      `${longTermWindowDays ?? 90} gunluk uzun donem basari %${analyticsItem.long_term_publish_success_rate} ile bu kume oneriliyor.`,
    );
  }

  if (analyticsItem?.long_term_retry_guidance_reason) {
    parts.push(analyticsItem.long_term_retry_guidance_reason);
  } else if (analyticsItem?.retry_guidance_reason) {
    parts.push(analyticsItem.retry_guidance_reason);
  }

  if (analyticsItem?.health_summary) {
    parts.push(analyticsItem.health_summary);
  } else {
    parts.push(cluster.detail);
  }

  return parts.filter(Boolean).join(" ").trim();
}

function topSourceBreakdown(
  sourceBreakdown: ReadonlyArray<RemediationTelemetrySource> | undefined,
): RemediationTelemetrySource | null {
  if (!sourceBreakdown || sourceBreakdown.length === 0) {
    return null;
  }

  return [...sourceBreakdown].sort((left, right) => right.tracked_interactions - left.tracked_interactions)[0] ?? null;
}

type TelemetryAggregateSummary = {
  tracked_interactions: number;
  total_retry_actions: number;
  publish_attempts: number;
  successful_publishes: number;
  failed_publishes: number;
  publish_success_rate: number | null;
  top_source_key: string | null;
  top_source_label: string | null;
};

function telemetryAggregateFromOutcomeSummary(
  summary: RemediationOutcomeChainSummary | null | undefined,
): TelemetryAggregateSummary | null {
  if (!summary) {
    return null;
  }

  return {
    tracked_interactions: summary.tracked_interactions,
    total_retry_actions: summary.total_retry_actions,
    publish_attempts: summary.publish_attempts,
    successful_publishes: summary.successful_publishes,
    failed_publishes: summary.failed_publishes,
    publish_success_rate: summary.publish_success_rate,
    top_source_key: summary.top_source_key ?? null,
    top_source_label: summary.top_source_label ?? null,
  };
}

function summarizeTelemetrySources(
  sources: ReadonlyArray<RemediationTelemetrySource> | undefined,
): TelemetryAggregateSummary {
  const sourceList = sources ?? [];

  const trackedInteractions = sourceList.reduce((sum, source) => sum + source.tracked_interactions, 0);
  const totalRetryActions = sourceList.reduce(
    (sum, source) => sum + source.publish_retry_actions + source.bulk_retry_actions,
    0,
  );
  const publishAttempts = sourceList.reduce((sum, source) => sum + source.publish_attempts, 0);
  const successfulPublishes = sourceList.reduce((sum, source) => sum + source.successful_publishes, 0);
  const failedPublishes = sourceList.reduce((sum, source) => sum + source.failed_publishes, 0);
  const topSource = [...sourceList].sort((left, right) => right.tracked_interactions - left.tracked_interactions)[0];

  return {
    tracked_interactions: trackedInteractions,
    total_retry_actions: totalRetryActions,
    publish_attempts: publishAttempts,
    successful_publishes: successfulPublishes,
    failed_publishes: failedPublishes,
    publish_success_rate:
      publishAttempts > 0 ? roundToOneDecimal((successfulPublishes / publishAttempts) * 100) : null,
    top_source_key: topSource?.source_key ?? null,
    top_source_label: topSource?.label ?? null,
  };
}

function compareSourceAggressiveness(
  draftDetailSummary: TelemetryAggregateSummary,
  approvalsNativeSummary: TelemetryAggregateSummary,
): SourceComparisonInsight | null {
  if (
    draftDetailSummary.tracked_interactions === 0
    && approvalsNativeSummary.tracked_interactions === 0
  ) {
    return null;
  }

  const draftRate = draftDetailSummary.publish_success_rate;
  const nativeRate = approvalsNativeSummary.publish_success_rate;

  if (draftRate != null && nativeRate != null) {
    if (draftRate > nativeRate) {
      return {
        label: "Draft detail daha guclu",
        variant: "success",
        reason: `Draft detail publish basarisi %${draftRate} ile approvals-native %${nativeRate} seviyesinin uzerinde.`,
        preferredFlow: "draft_detail",
      };
    }

    if (nativeRate > draftRate) {
      return {
        label: "Approvals-native daha guclu",
        variant: "warning",
        reason: `Approvals-native publish basarisi %${nativeRate} ile draft detail %${draftRate} seviyesinin uzerinde.`,
        preferredFlow: "approvals_native",
      };
    }
  }

  if (draftDetailSummary.successful_publishes !== approvalsNativeSummary.successful_publishes) {
    return draftDetailSummary.successful_publishes > approvalsNativeSummary.successful_publishes
      ? {
          label: "Draft detail daha guclu",
          variant: "success",
          reason: "Draft detail daha fazla basarili publish uretmis gorunuyor.",
          preferredFlow: "draft_detail",
        }
      : {
          label: "Approvals-native daha guclu",
          variant: "warning",
          reason: "Approvals-native kaynaklar daha fazla basarili publish uretmis gorunuyor.",
          preferredFlow: "approvals_native",
        };
  }

  if (draftDetailSummary.tracked_interactions !== approvalsNativeSummary.tracked_interactions) {
    return draftDetailSummary.tracked_interactions > approvalsNativeSummary.tracked_interactions
      ? {
          label: "Draft detail daha aktif",
          variant: "neutral",
          reason: "Draft detail etkilesim hacmi approvals-native toplanmis akislerden daha yuksek.",
          preferredFlow: "draft_detail",
        }
      : {
          label: "Approvals-native daha aktif",
          variant: "neutral",
          reason: "Approvals-native akisler draft detail'e gore daha yuksek etkilesim uretiyor.",
          preferredFlow: "approvals_native",
        };
  }

  return {
    label: "Denge",
    variant: "neutral",
    reason: "Draft detail ve approvals-native akislari birbirine yakin calisiyor.",
    preferredFlow: "balanced",
  };
}

function preferredFlowFromRouteKey(
  routeKey: string | null | undefined,
): "draft_detail" | "approvals_native" | "balanced" {
  if (routeKey === "draft_detail") {
    return "draft_detail";
  }

  if (routeKey === "approvals") {
    return "approvals_native";
  }

  return "balanced";
}

function routeTrendConfidenceFromMetrics(
  preferredFlow: "draft_detail" | "approvals_native" | "balanced",
  currentTopRoute: RouteTrendMetric | null,
  longTermTopRoute: RouteTrendMetric | null,
  currentAdvantage: number | null,
  longTermAdvantage: number | null,
): "high" | "medium" | "low" {
  if (preferredFlow === "balanced") {
    return "low";
  }

  const currentSupportsFlow = preferredFlowFromRouteKey(currentTopRoute?.route_key) === preferredFlow;
  const longTermSupportsFlow = preferredFlowFromRouteKey(longTermTopRoute?.route_key) === preferredFlow;
  const currentAttempts = currentSupportsFlow ? (currentTopRoute?.publish_attempts ?? 0) : 0;
  const longTermAttempts = longTermSupportsFlow ? (longTermTopRoute?.publish_attempts ?? 0) : 0;

  if (
    currentSupportsFlow
    && longTermSupportsFlow
    && currentAttempts >= 2
    && longTermAttempts >= 2
    && ((currentAdvantage ?? 0) >= 12 || (longTermAdvantage ?? 0) >= 12)
  ) {
    return "high";
  }

  if (
    (currentSupportsFlow && currentAttempts >= 2 && (currentAdvantage ?? 0) >= 10)
    || (longTermSupportsFlow && longTermAttempts >= 2 && (longTermAdvantage ?? 0) >= 10)
  ) {
    return "medium";
  }

  return "low";
}

function routeTrendLine(
  trend: RouteTrendMetric | null,
  fallbackLabel: string,
  advantage: number | null,
): string {
  if (!trend) {
    return fallbackLabel;
  }

  const parts = [trend.label];

  if (trend.publish_success_rate != null) {
    parts.push(`%${trend.publish_success_rate}`);
  }

  if (trend.publish_attempts > 0) {
    parts.push(`${trend.publish_attempts} deneme`);
  }

  if (advantage != null) {
    parts.push(`${advantage >= 0 ? "+" : ""}${advantage} puan fark`);
  }

  return parts.join(" / ");
}

function routeTrendFlowLabel(routeKey: string | null | undefined): string {
  return (
    {
      approvals: "Approvals Rota",
      draft_detail: "Draft Detail Rota",
      other: "Diger Rota",
    }[routeKey ?? ""] ?? routeKey ?? "Route"
  );
}

function routeTrendWindowVariant(
  flow: "draft_detail" | "approvals_native" | "balanced",
): "success" | "warning" | "neutral" {
  if (flow === "draft_detail") {
    return "success";
  }

  if (flow === "approvals_native") {
    return "warning";
  }

  return "neutral";
}

function routeTrendConfidenceLabel(confidence: "high" | "medium" | "low" | null | undefined): string {
  if (confidence === "high") {
    return "Yuksek Guven";
  }

  if (confidence === "medium") {
    return "Orta Guven";
  }

  if (confidence === "low") {
    return "Dusuk Guven";
  }

  return "Guven Bilgisi Yok";
}

function routeTrendConfidenceVariant(
  confidence: "high" | "medium" | "low" | null | undefined,
): "success" | "warning" | "neutral" {
  if (confidence === "high") {
    return "success";
  }

  if (confidence === "medium") {
    return "warning";
  }

  return "neutral";
}

function primaryActionTrendStatusLabel(status: string | null | undefined): string {
  return (
    {
      stable: "Trend Stabil",
      forming: "Trend Olusuyor",
      softening: "Trend Zayifliyor",
      sparse: "Trend Seyrek",
      missing: "Trend Eksik",
    }[status ?? ""] ?? "Trend"
  );
}

function primaryActionTrendStatusVariant(
  status: string | null | undefined,
): "success" | "warning" | "danger" | "neutral" {
  if (status === "stable") {
    return "success";
  }

  if (status === "forming" || status === "softening") {
    return "warning";
  }

  if (status === "sparse") {
    return "danger";
  }

  return "neutral";
}

function primaryActionRouteSeriesSupportLabel(
  status: PrimaryActionRouteSeriesMetric["support_status"],
): string {
  return (
    {
      proven: "Kanitli",
      emerging: "Yukselen",
      guarded: "Temkinli",
      missing: "Eksik",
    }[status ?? ""] ?? "Temkinli"
  );
}

function fallbackSupportStatus(confidence: RouteWindowSeriesMetric["confidence"]): PrimaryActionRouteSeriesMetric["support_status"] {
  if (confidence === "high") {
    return "proven";
  }

  if (confidence === "medium") {
    return "emerging";
  }

  if (confidence === "low") {
    return "guarded";
  }

  return "missing";
}

function resolvePrimaryActionRouteSeries(
  primaryAction?: RetryGuidanceContext["primary_action"] | null,
  fallbackSeries?: ReadonlyArray<RouteWindowSeriesMetric> | null,
): Array<PrimaryActionRouteSeriesMetric> {
  if (primaryAction?.route_series && primaryAction.route_series.length > 0) {
    return primaryAction.route_series;
  }

  return (fallbackSeries ?? [])
    .map((windowSeries) => {
      const routeKey = windowSeries.current_route_key ?? windowSeries.top_route_key ?? null;
      const routeLabel = windowSeries.current_route_label ?? windowSeries.top_route_label ?? null;
      const matchingRoute = windowSeries.route_trends?.find((route) => route.route_key === routeKey);

      return {
        window_days: windowSeries.window_days,
        route_key: routeKey,
        route_label: routeLabel,
        is_window_leader: routeKey != null && routeKey === (windowSeries.top_route_key ?? null),
        tracked_interactions: matchingRoute?.tracked_interactions ?? null,
        publish_attempts: matchingRoute?.publish_attempts ?? windowSeries.current_route_attempts ?? windowSeries.top_route_attempts ?? null,
        successful_publishes: matchingRoute?.successful_publishes ?? null,
        failed_publishes: matchingRoute?.failed_publishes ?? null,
        publish_success_rate: matchingRoute?.publish_success_rate
          ?? windowSeries.current_route_success_rate
          ?? windowSeries.top_route_success_rate
          ?? null,
        leader_route_key: windowSeries.top_route_key ?? null,
        leader_route_label: windowSeries.top_route_label ?? null,
        support_status: fallbackSupportStatus(windowSeries.confidence),
      };
    })
    .filter((seriesPoint) => seriesPoint.route_key || seriesPoint.leader_route_key);
}

function summarizePrimaryActionRouteSeries(
  primaryAction?: RetryGuidanceContext["primary_action"] | null,
  fallbackSeries?: ReadonlyArray<RouteWindowSeriesMetric> | null,
): string | null {
  const routeSeries = resolvePrimaryActionRouteSeries(primaryAction, fallbackSeries);

  if (routeSeries.length === 0) {
    return null;
  }

  return routeSeries
    .map((seriesPoint) => {
      const routeLabel = seriesPoint.route_label ?? seriesPoint.leader_route_label ?? "Veri yok";
      const supportLabel = seriesPoint.support_status
        ? primaryActionRouteSeriesSupportLabel(seriesPoint.support_status)
        : null;

      return [
        `${seriesPoint.window_days}g`,
        routeLabel,
        supportLabel,
      ].filter(Boolean).join(" ");
    })
    .join(" / ");
}

function withPrimaryActionTrendHint(
  baseHint: string,
  primaryAction?: RetryGuidanceContext["primary_action"] | null,
): string {
  if (!primaryAction?.trend_status && !primaryAction?.trend_reason) {
    return baseHint;
  }

  const suffix = [
    primaryAction?.trend_status ? primaryActionTrendStatusLabel(primaryAction.trend_status) : null,
    primaryAction?.trend_reason ?? null,
  ]
    .filter(Boolean)
    .join(": ");

  return suffix ? `${baseHint} ${suffix}.`.trim() : baseHint;
}

function buildRouteTrendInsight(
  currentRouteTrends: ReadonlyArray<RouteTrendMetric>,
  longTermRouteTrends: ReadonlyArray<RouteTrendMetric>,
  currentComparison: SourceComparisonInsight | null,
  longTermComparison: SourceComparisonInsight | null,
  draftDetailSummary: TelemetryAggregateSummary,
  approvalsNativeSummary: TelemetryAggregateSummary,
  longTermDraftDetailSummary: TelemetryAggregateSummary,
  longTermApprovalsNativeSummary: TelemetryAggregateSummary,
): RouteTrendInsight | null {
  const currentTopRoute = currentRouteTrends[0] ?? null;
  const longTermTopRoute = longTermRouteTrends[0] ?? null;
  const currentPreferredFlow = currentComparison?.preferredFlow ?? "balanced";
  const longTermPreferredFlow = longTermComparison?.preferredFlow ?? "balanced";
  const currentRoutePreferredFlow = preferredFlowFromRouteKey(currentTopRoute?.route_key);
  const longTermRoutePreferredFlow = preferredFlowFromRouteKey(longTermTopRoute?.route_key);
  const effectiveCurrentPreferredFlow = currentRoutePreferredFlow !== "balanced"
    ? currentRoutePreferredFlow
    : currentPreferredFlow;
  const effectiveLongTermPreferredFlow = longTermRoutePreferredFlow !== "balanced"
    ? longTermRoutePreferredFlow
    : longTermPreferredFlow;
  const currentDraftRate = draftDetailSummary.publish_success_rate;
  const currentNativeRate = approvalsNativeSummary.publish_success_rate;
  const longTermDraftRate = longTermDraftDetailSummary.publish_success_rate;
  const longTermNativeRate = longTermApprovalsNativeSummary.publish_success_rate;
  const currentAdvantage = currentDraftRate != null && currentNativeRate != null
    ? roundToOneDecimal(Math.abs(currentDraftRate - currentNativeRate))
    : null;
  const longTermAdvantage = longTermDraftRate != null && longTermNativeRate != null
    ? roundToOneDecimal(Math.abs(longTermDraftRate - longTermNativeRate))
    : null;
  const preferredFlow = effectiveCurrentPreferredFlow !== "balanced"
    ? effectiveCurrentPreferredFlow
    : effectiveLongTermPreferredFlow;
  const confidence = routeTrendConfidenceFromMetrics(
    preferredFlow,
    currentTopRoute,
    longTermTopRoute,
    currentAdvantage,
    longTermAdvantage,
  );
  const currentLabel = routeTrendLine(currentTopRoute, currentComparison?.label ?? "Denge", currentAdvantage);
  const longTermLabel = routeTrendLine(longTermTopRoute, longTermComparison?.label ?? "Denge", longTermAdvantage);

  if (effectiveCurrentPreferredFlow === "balanced" && effectiveLongTermPreferredFlow === "balanced") {
    return {
      label: "Route dengede",
      variant: "neutral",
      reason: "Mevcut pencere ve uzun donem tarafinda draft detail ile approvals-native akislar birbirine yakin gorunuyor.",
      nextStepLabel: "Odagi Incele",
      preferredFlow: "balanced",
      confidence: "low",
      currentLabel,
      longTermLabel,
      currentRouteKey: currentTopRoute?.route_key ?? null,
      currentRouteSuccessRate: currentTopRoute?.publish_success_rate ?? null,
      currentRouteAttempts: currentTopRoute?.publish_attempts ?? null,
      currentRouteAdvantage: currentAdvantage,
      longTermRouteKey: longTermTopRoute?.route_key ?? null,
      longTermRouteSuccessRate: longTermTopRoute?.publish_success_rate ?? null,
      longTermRouteAttempts: longTermTopRoute?.publish_attempts ?? null,
      longTermRouteAdvantage: longTermAdvantage,
    };
  }

  if (
    effectiveCurrentPreferredFlow !== "balanced"
    && effectiveCurrentPreferredFlow === effectiveLongTermPreferredFlow
  ) {
    const preferredDraftDetail = effectiveCurrentPreferredFlow === "draft_detail";

    return {
      label: preferredDraftDetail ? "Draft detail kalici lider" : "Approvals-native kalici lider",
      variant: preferredDraftDetail ? "success" : "warning",
      reason: `${currentComparison?.reason ?? "Mevcut pencere bu route'u onde gosteriyor."} ${longTermComparison?.reason ?? "Uzun donem de ayni route'u destekliyor."} Current: ${currentLabel}. Uzun donem: ${longTermLabel}.`.trim(),
      nextStepLabel: preferredDraftDetail ? "Draft Detail Akisina Git" : "Featured Kume Uzerinde Calis",
      preferredFlow: effectiveCurrentPreferredFlow,
      confidence,
      currentLabel,
      longTermLabel,
      currentRouteKey: currentTopRoute?.route_key ?? null,
      currentRouteSuccessRate: currentTopRoute?.publish_success_rate ?? null,
      currentRouteAttempts: currentTopRoute?.publish_attempts ?? null,
      currentRouteAdvantage: currentAdvantage,
      longTermRouteKey: longTermTopRoute?.route_key ?? null,
      longTermRouteSuccessRate: longTermTopRoute?.publish_success_rate ?? null,
      longTermRouteAttempts: longTermTopRoute?.publish_attempts ?? null,
      longTermRouteAdvantage: longTermAdvantage,
    };
  }

  if (effectiveLongTermPreferredFlow !== "balanced") {
    const preferredDraftDetail = effectiveLongTermPreferredFlow === "draft_detail";
    const longTermRate = preferredDraftDetail ? longTermDraftRate : longTermNativeRate;
    const currentRate = preferredDraftDetail ? currentDraftRate : currentNativeRate;

    return {
      label: preferredDraftDetail ? "Draft detail uzun donemde onde" : "Approvals-native uzun donemde onde",
      variant: preferredDraftDetail ? "success" : "warning",
      reason: `${longTermComparison?.reason ?? "Uzun donem verisi bu route'u onde gosteriyor."}${longTermRate != null ? ` Uzun donem basari %${longTermRate}.` : ""}${currentRate != null ? ` Mevcut pencere referansi %${currentRate}.` : ""} Current: ${currentLabel}. Uzun donem: ${longTermLabel}.`,
      nextStepLabel: preferredDraftDetail ? "Draft Detail Akisina Git" : "Featured Kume Uzerinde Calis",
      preferredFlow: effectiveLongTermPreferredFlow,
      confidence,
      currentLabel,
      longTermLabel,
      currentRouteKey: currentTopRoute?.route_key ?? null,
      currentRouteSuccessRate: currentTopRoute?.publish_success_rate ?? null,
      currentRouteAttempts: currentTopRoute?.publish_attempts ?? null,
      currentRouteAdvantage: currentAdvantage,
      longTermRouteKey: longTermTopRoute?.route_key ?? null,
      longTermRouteSuccessRate: longTermTopRoute?.publish_success_rate ?? null,
      longTermRouteAttempts: longTermTopRoute?.publish_attempts ?? null,
      longTermRouteAdvantage: longTermAdvantage,
    };
  }

  if (effectiveCurrentPreferredFlow !== "balanced") {
    const preferredDraftDetail = effectiveCurrentPreferredFlow === "draft_detail";

    return {
      label: preferredDraftDetail ? "Draft detail bu pencerede onde" : "Approvals-native bu pencerede onde",
      variant: preferredDraftDetail ? "success" : "warning",
      reason: `${currentComparison?.reason ?? "Mevcut pencere route karari icin daha guclu sinyal veriyor."} Current: ${currentLabel}.`,
      nextStepLabel: preferredDraftDetail ? "Draft Detail Akisina Git" : "Featured Kume Uzerinde Calis",
      preferredFlow: effectiveCurrentPreferredFlow,
      confidence,
      currentLabel,
      longTermLabel,
      currentRouteKey: currentTopRoute?.route_key ?? null,
      currentRouteSuccessRate: currentTopRoute?.publish_success_rate ?? null,
      currentRouteAttempts: currentTopRoute?.publish_attempts ?? null,
      currentRouteAdvantage: currentAdvantage,
      longTermRouteKey: longTermTopRoute?.route_key ?? null,
      longTermRouteSuccessRate: longTermTopRoute?.publish_success_rate ?? null,
      longTermRouteAttempts: longTermTopRoute?.publish_attempts ?? null,
      longTermRouteAdvantage: longTermAdvantage,
    };
  }

  return null;
}

function buildSourceSpotlight(
  routeTrendInsight: RouteTrendInsight | null,
  comparison: SourceComparisonInsight | null,
  featuredRecommendation: ApprovalRemediationAnalyticsResponse["data"]["featured_recommendation"] | null,
  featuredDraftRoute: string | null,
  topSource: RemediationTelemetrySource | null,
  longTermDraftDetailOutcomeSummary: RemediationOutcomeChainSummary | null,
  longTermApprovalsNativeOutcomeSummary: RemediationOutcomeChainSummary | null,
): SourceSpotlightInsight | null {
  const longTermDraftRate = longTermDraftDetailOutcomeSummary?.publish_success_rate;
  const longTermNativeRate = longTermApprovalsNativeOutcomeSummary?.publish_success_rate;

  if (routeTrendInsight && routeTrendInsight.preferredFlow !== "balanced") {
    return {
      label: routeTrendInsight.label,
      variant: routeTrendInsight.variant,
      reason: routeTrendInsight.reason,
      nextStepLabel: routeTrendInsight.nextStepLabel,
      preferredFlow: routeTrendInsight.preferredFlow,
    };
  }

  if (comparison?.preferredFlow === "draft_detail") {
    return {
      label: "Draft detail source spotlight",
      variant: "success",
      reason:
        comparison.reason
        + (longTermDraftRate != null
          ? ` Uzun donem draft detail basarisi %${longTermDraftRate}.`
          : ""),
      nextStepLabel: featuredDraftRoute ? "Draft Detail Akisina Git" : "Draft Detayini Ac",
      preferredFlow: "draft_detail",
    };
  }

  if (comparison?.preferredFlow === "approvals_native") {
    return {
      label: "Approvals native source spotlight",
      variant: "warning",
      reason:
        comparison.reason
        + (longTermNativeRate != null
          ? ` Uzun donem approvals-native basarisi %${longTermNativeRate}.`
          : ""),
      nextStepLabel: featuredRecommendation ? "Featured Kume Uzerinde Calis" : "Clusteri Incele",
      preferredFlow: "approvals_native",
    };
  }

  if (topSource) {
    const preferredFlow = topSource.source_key.startsWith("draft_detail")
      ? "draft_detail"
      : topSource.source_key.startsWith("approvals")
        ? "approvals_native"
        : "balanced";

    return {
      label: `En aktif kaynak: ${sourceLabel(topSource.source_key, topSource.label)}`,
      variant: preferredFlow === "draft_detail" ? "success" : preferredFlow === "approvals_native" ? "warning" : "neutral",
      reason:
        `Bu kaynak ${topSource.tracked_interactions} etkilesim uretmis ve %${topSource.publish_success_rate ?? 0} basari seviyesine sahip.`
        + (longTermDraftRate != null || longTermNativeRate != null
          ? ` Uzun donem draft detail / approvals-native: ${longTermDraftRate != null ? `%${longTermDraftRate}` : "veri yok"} / ${longTermNativeRate != null ? `%${longTermNativeRate}` : "veri yok"}.`
          : ""),
      nextStepLabel: preferredFlow === "draft_detail"
        ? (featuredDraftRoute ? "Draft Detail Akisina Git" : "Draft Detayini Ac")
        : preferredFlow === "approvals_native"
          ? "Cluster Odaginda Kal"
          : "Odagi Incele",
      preferredFlow,
    };
  }

  return null;
}

function buildRouteTrendFocusContext(
  routeTrendInsight: RouteTrendInsight | null,
): Pick<
  DraftRouteFocusContext,
  | "routeTrendLabel"
  | "routeTrendReason"
  | "routePreferredFlow"
  | "routeTrendConfidence"
  | "routeCurrentLabel"
  | "routeCurrentAttempts"
  | "routeCurrentSuccessRate"
  | "routeCurrentAdvantage"
  | "routeLongTermLabel"
  | "routeLongTermAttempts"
  | "routeLongTermSuccessRate"
  | "routeLongTermAdvantage"
> {
  return {
    routeTrendLabel: routeTrendInsight?.label ?? null,
    routeTrendReason: routeTrendInsight?.reason ?? null,
    routePreferredFlow: routeTrendInsight?.preferredFlow ?? null,
    routeTrendConfidence: routeTrendInsight?.confidence ?? null,
    routeCurrentLabel: routeTrendInsight?.currentLabel ?? null,
    routeCurrentAttempts: routeTrendInsight?.currentRouteAttempts ?? null,
    routeCurrentSuccessRate: routeTrendInsight?.currentRouteSuccessRate ?? null,
    routeCurrentAdvantage: routeTrendInsight?.currentRouteAdvantage ?? null,
    routeLongTermLabel: routeTrendInsight?.longTermLabel ?? null,
    routeLongTermAttempts: routeTrendInsight?.longTermRouteAttempts ?? null,
    routeLongTermSuccessRate: routeTrendInsight?.longTermRouteSuccessRate ?? null,
    routeLongTermAdvantage: routeTrendInsight?.longTermRouteAdvantage ?? null,
  };
}

function roundToOneDecimal(value: number): number {
  return Math.round(value * 10) / 10;
}

function effectivenessStatusLabel(status: string): string {
  return (
    {
      proven: "Kanitlandi",
      mixed: "Karisik Sonuc",
      weak: "Zayif",
      insufficient_data: "Veri Yetersiz",
      idle: "Pasif",
    }[status] ?? "Veri Yetersiz"
  );
}

function effectivenessStatusVariant(status: string): "success" | "warning" | "danger" | "neutral" {
  const variants: Record<string, "success" | "warning" | "danger" | "neutral"> = {
    proven: "success",
    mixed: "warning",
    weak: "danger",
    insufficient_data: "neutral",
    idle: "neutral",
  };

  return variants[status] ?? "neutral";
}

function buildDraftRoute(
  item: Approval,
  analyticsWindowDays: number,
  focusSource:
    | "approvals"
    | "approvals_featured"
    | "approvals_featured_long_term"
    | "approvals_cluster"
    | "approvals_cluster_long_term"
    | "approvals_retry_ready"
    | "approvals_item",
  focusContext?: DraftRouteFocusContext,
): string | null {
  if (!item.approvable_route) {
    return null;
  }

  const [path, queryString = ""] = item.approvable_route.split("?");
  const params = new URLSearchParams(queryString);
  const focusPublishState = deriveFocusPublishState(item);
  const clusterKey = approvalClusterKey(item);

  if (focusPublishState) {
    params.set("focus_publish_state", focusPublishState);
  }

  if (item.publish_state?.recommended_action_code) {
    params.set("focus_recommended_action", item.publish_state.recommended_action_code);
  }

  if (clusterKey) {
    params.set("focus_cluster_key", clusterKey);
  }

  if (analyticsWindowDays !== 30) {
    params.set("window_days", String(analyticsWindowDays));
  }

  params.set("focus_source", focusSource);

  if (focusContext?.decisionStatus) {
    params.set("focus_decision_status", focusContext.decisionStatus);
  }

  if (focusContext?.decisionReason) {
    params.set("focus_decision_reason", focusContext.decisionReason);
  }

  if (focusContext?.retryGuidanceStatus) {
    params.set("focus_retry_guidance_status", focusContext.retryGuidanceStatus);
  }

  if (focusContext?.retryGuidanceLabel) {
    params.set("focus_retry_guidance_label", focusContext.retryGuidanceLabel);
  }

  if (focusContext?.retryGuidanceReason) {
    params.set("focus_retry_guidance_reason", focusContext.retryGuidanceReason);
  }

  if (focusContext?.effectivenessScore != null) {
    params.set("focus_effectiveness_score", String(focusContext.effectivenessScore));
  }

  if (focusContext?.sourceComparisonLabel) {
    params.set("focus_source_comparison_label", focusContext.sourceComparisonLabel);
  }

  if (focusContext?.sourceComparisonReason) {
    params.set("focus_source_comparison_reason", focusContext.sourceComparisonReason);
  }

  if (focusContext?.sourceComparisonWinner) {
    params.set("focus_source_comparison_winner", focusContext.sourceComparisonWinner);
  }

  if (focusContext?.longTermWindowDays != null) {
    params.set("focus_long_term_window_days", String(focusContext.longTermWindowDays));
  }

  if (focusContext?.longTermSuccessRate != null) {
    params.set("focus_long_term_success_rate", String(focusContext.longTermSuccessRate));
  }

  if (focusContext?.longTermBaselineSuccessRate != null) {
    params.set("focus_long_term_baseline_success_rate", String(focusContext.longTermBaselineSuccessRate));
  }

  if (focusContext?.longTermEffectivenessStatus) {
    params.set("focus_long_term_effectiveness_status", focusContext.longTermEffectivenessStatus);
  }

  if (focusContext?.primaryActionMode) {
    params.set("focus_primary_action_mode", focusContext.primaryActionMode);
  }

  if (focusContext?.primaryActionRouteLabel) {
    params.set("focus_primary_action_route_label", focusContext.primaryActionRouteLabel);
  }

  if (focusContext?.primaryActionSourceLabel) {
    params.set("focus_primary_action_source_label", focusContext.primaryActionSourceLabel);
  }

  if (focusContext?.primaryActionReason) {
    params.set("focus_primary_action_reason", focusContext.primaryActionReason);
  }

  if (focusContext?.primaryActionSuccessRate != null) {
    params.set("focus_primary_action_success_rate", String(focusContext.primaryActionSuccessRate));
  }

  if (focusContext?.primaryActionTrackedInteractions != null) {
    params.set("focus_primary_action_tracked_interactions", String(focusContext.primaryActionTrackedInteractions));
  }

  if (focusContext?.primaryActionConfidenceStatus) {
    params.set("focus_primary_action_confidence_status", focusContext.primaryActionConfidenceStatus);
  }

  if (focusContext?.primaryActionConfidenceLabel) {
    params.set("focus_primary_action_confidence_label", focusContext.primaryActionConfidenceLabel);
  }

  if (focusContext?.primaryActionTrendStatus) {
    params.set("focus_primary_action_trend_status", focusContext.primaryActionTrendStatus);
  }

  if (focusContext?.primaryActionTrendReason) {
    params.set("focus_primary_action_trend_reason", focusContext.primaryActionTrendReason);
  }

  if (focusContext?.primaryActionRouteSeries && focusContext.primaryActionRouteSeries.length > 0) {
    params.set("focus_primary_action_route_series", JSON.stringify(focusContext.primaryActionRouteSeries));
  }

  if (focusContext?.primaryActionAdvantage != null) {
    params.set("focus_primary_action_advantage", String(focusContext.primaryActionAdvantage));
  }

  if (focusContext?.primaryActionAlternativeRouteLabel) {
    params.set("focus_primary_action_alternative_route_label", focusContext.primaryActionAlternativeRouteLabel);
  }

  if (focusContext?.primaryActionAlternativeSuccessRate != null) {
    params.set("focus_primary_action_alternative_success_rate", String(focusContext.primaryActionAlternativeSuccessRate));
  }

  if (focusContext?.routeTrendLabel) {
    params.set("focus_route_trend_label", focusContext.routeTrendLabel);
  }

  if (focusContext?.routeTrendReason) {
    params.set("focus_route_trend_reason", focusContext.routeTrendReason);
  }

  if (focusContext?.routePreferredFlow) {
    params.set("focus_route_preferred_flow", focusContext.routePreferredFlow);
  }

  if (focusContext?.routeTrendConfidence) {
    params.set("focus_route_trend_confidence", focusContext.routeTrendConfidence);
  }

  if (focusContext?.routeCurrentLabel) {
    params.set("focus_route_current_label", focusContext.routeCurrentLabel);
  }

  if (focusContext?.routeCurrentAttempts != null) {
    params.set("focus_route_current_attempts", String(focusContext.routeCurrentAttempts));
  }

  if (focusContext?.routeCurrentSuccessRate != null) {
    params.set("focus_route_current_success_rate", String(focusContext.routeCurrentSuccessRate));
  }

  if (focusContext?.routeCurrentAdvantage != null) {
    params.set("focus_route_current_advantage", String(focusContext.routeCurrentAdvantage));
  }

  if (focusContext?.routeLongTermLabel) {
    params.set("focus_route_long_term_label", focusContext.routeLongTermLabel);
  }

  if (focusContext?.routeLongTermAttempts != null) {
    params.set("focus_route_long_term_attempts", String(focusContext.routeLongTermAttempts));
  }

  if (focusContext?.routeLongTermSuccessRate != null) {
    params.set("focus_route_long_term_success_rate", String(focusContext.routeLongTermSuccessRate));
  }

  if (focusContext?.routeLongTermAdvantage != null) {
    params.set("focus_route_long_term_advantage", String(focusContext.routeLongTermAdvantage));
  }

  const nextQuery = params.toString();

  return nextQuery ? `${path}?${nextQuery}` : path;
}

function readApprovalsRouteState(search: string): {
  statusFilter: (typeof STATUS_FILTER_OPTIONS)[number]["value"];
  cleanupFilter: (typeof CLEANUP_FILTER_OPTIONS)[number]["value"];
  manualCheckFilter: (typeof MANUAL_CHECK_FILTER_OPTIONS)[number]["value"];
  recommendedActionFilter: (typeof RECOMMENDED_ACTION_FILTER_OPTIONS)[number]["value"];
  analyticsWindowDays: (typeof ANALYTICS_WINDOW_OPTIONS)[number]["value"];
} {
  const params = new URLSearchParams(search);

  return {
    statusFilter: resolveStringOption(params.get("status"), STATUS_FILTER_OPTIONS, "all"),
    cleanupFilter: resolveStringOption(params.get("cleanup_state"), CLEANUP_FILTER_OPTIONS, "all"),
    manualCheckFilter: resolveStringOption(params.get("manual_check_state"), MANUAL_CHECK_FILTER_OPTIONS, "all"),
    recommendedActionFilter: resolveStringOption(
      params.get("recommended_action_code"),
      RECOMMENDED_ACTION_FILTER_OPTIONS,
      "all",
    ),
    analyticsWindowDays: resolveNumberOption(params.get("window_days"), ANALYTICS_WINDOW_OPTIONS, 30),
  };
}

function syncApprovalRouteQuery(
  params: URLSearchParams,
  state: {
    statusFilter: (typeof STATUS_FILTER_OPTIONS)[number]["value"];
    cleanupFilter: (typeof CLEANUP_FILTER_OPTIONS)[number]["value"];
    manualCheckFilter: (typeof MANUAL_CHECK_FILTER_OPTIONS)[number]["value"];
    recommendedActionFilter: (typeof RECOMMENDED_ACTION_FILTER_OPTIONS)[number]["value"];
    analyticsWindowDays: (typeof ANALYTICS_WINDOW_OPTIONS)[number]["value"];
  },
): void {
  setOrDeleteQueryValue(params, "status", state.statusFilter, "all");
  setOrDeleteQueryValue(params, "cleanup_state", state.cleanupFilter, "all");
  setOrDeleteQueryValue(params, "manual_check_state", state.manualCheckFilter, "all");
  setOrDeleteQueryValue(params, "recommended_action_code", state.recommendedActionFilter, "all");
  setOrDeleteNumberQueryValue(params, "window_days", state.analyticsWindowDays, 30);
}

function resolveStringOption<T extends string>(
  rawValue: string | null,
  options: ReadonlyArray<{ value: T }>,
  fallback: T,
): T {
  if (! rawValue) {
    return fallback;
  }

  return options.some((option) => option.value === rawValue) ? (rawValue as T) : fallback;
}

function resolveNumberOption<T extends number>(
  rawValue: string | null,
  options: ReadonlyArray<{ value: T }>,
  fallback: T,
): T {
  const parsedValue = Number(rawValue);

  return options.some((option) => option.value === parsedValue) ? (parsedValue as T) : fallback;
}

function setOrDeleteQueryValue(
  params: URLSearchParams,
  key: string,
  value: string,
  fallback: string,
): void {
  if (value === fallback) {
    params.delete(key);
    return;
  }

  params.set(key, value);
}

function setOrDeleteNumberQueryValue(
  params: URLSearchParams,
  key: string,
  value: number,
  fallback: number,
): void {
  if (value === fallback) {
    params.delete(key);
    return;
  }

  params.set(key, String(value));
}
