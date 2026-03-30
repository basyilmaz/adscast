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
      tracked_sources_count: number;
      top_interaction_source_key: string | null;
      top_interaction_source_label: string | null;
      top_success_source_key: string | null;
      top_success_source_label: string | null;
      tracked_featured_interactions: number;
      followed_featured_interactions: number;
      override_featured_interactions: number;
      featured_publish_attempts: number;
      successful_featured_publishes: number;
      featured_follow_rate: number | null;
      featured_publish_success_rate: number | null;
      window_days: number;
    };
    interaction_sources: Array<RemediationTelemetrySource>;
    outcome_chain_summary: RemediationOutcomeChainSummary;
    approvals_native_outcome_summary: RemediationOutcomeChainSummary;
    draft_detail_outcome_summary: RemediationOutcomeChainSummary;
    featured_recommendation: {
      cluster_key: string;
      label: string;
      description: string;
      recommended_action_code: string;
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
      decision_context_success_rate?: number | null;
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
      source_breakdown: Array<RemediationTelemetrySource>;
      outcome_chain_summary: RemediationOutcomeChainSummary;
      draft_detail_outcome_summary: RemediationOutcomeChainSummary;
    } | null;
    items: Array<{
      cluster_key: string;
      label: string;
      description: string;
      recommended_action_code: string;
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
      source_breakdown: Array<RemediationTelemetrySource>;
      outcome_chain_summary: RemediationOutcomeChainSummary;
      draft_detail_outcome_summary: RemediationOutcomeChainSummary;
    }>;
  };
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
  const approvalsNativeTelemetry = useMemo(
    () =>
      telemetrySources.filter((source) =>
        APPROVALS_NATIVE_SOURCE_KEYS.includes(source.source_key as RemediationTelemetrySourceKey),
      ),
    [telemetrySources],
  );
  const approvalsNativeSummary = useMemo(
    () =>
      approvalsNativeOutcomeSummary
        ? {
            tracked_interactions: approvalsNativeOutcomeSummary.tracked_interactions,
            publish_attempts: approvalsNativeOutcomeSummary.publish_attempts,
            successful_publishes: approvalsNativeOutcomeSummary.successful_publishes,
            failed_publishes: approvalsNativeOutcomeSummary.failed_publishes,
            total_retry_actions: approvalsNativeOutcomeSummary.total_retry_actions,
            publish_success_rate: approvalsNativeOutcomeSummary.publish_success_rate,
            top_source_key: approvalsNativeOutcomeSummary.top_source_key ?? null,
            top_source_label: approvalsNativeOutcomeSummary.top_source_label ?? null,
          }
        : summarizeTelemetrySources(approvalsNativeTelemetry),
    [approvalsNativeOutcomeSummary, approvalsNativeTelemetry],
  );
  const draftDetailSummary = useMemo(
    () =>
      draftDetailOutcomeSummary
        ? {
            tracked_interactions: draftDetailOutcomeSummary.tracked_interactions,
            publish_attempts: draftDetailOutcomeSummary.publish_attempts,
            successful_publishes: draftDetailOutcomeSummary.successful_publishes,
            failed_publishes: draftDetailOutcomeSummary.failed_publishes,
            total_retry_actions: draftDetailOutcomeSummary.total_retry_actions,
            publish_success_rate: draftDetailOutcomeSummary.publish_success_rate,
            top_source_key: draftDetailOutcomeSummary.top_source_key ?? null,
            top_source_label: draftDetailOutcomeSummary.top_source_label ?? null,
          }
        : summarizeTelemetrySources(
            telemetrySources.filter((source) => source.source_key.startsWith("draft_detail")),
          ),
    [draftDetailOutcomeSummary, telemetrySources],
  );
  const sourceComparisonWinner = useMemo(
    () => compareSourceAggressiveness(draftDetailSummary, approvalsNativeSummary),
    [approvalsNativeSummary, draftDetailSummary],
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
        ? buildDraftRoute(featuredClusterMatches[0], analyticsWindowDays, "approvals_featured")
        : null,
    [analyticsWindowDays, featuredClusterMatches],
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
                {featuredRecommendation.decision_context_source === "draft_detail"
                  && featuredRecommendation.decision_context_advantage != null ? (
                    <Badge
                      label={`Draft detail +%${featuredRecommendation.decision_context_advantage}`}
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
              <div className="mt-4 flex flex-wrap gap-2">
                <Button
                  size="sm"
                  onClick={() =>
                    void runClusterRecommendation(
                      featuredRecommendation.cluster_key,
                      featuredRecommendation.action_mode,
                      "approvals_featured",
                    )
                  }
                  disabled={
                    bulkPublishing
                    && featuredRecommendation.action_mode === "bulk_retry_publish"
                  }
                >
                  {featuredRecommendation.action_mode === "bulk_retry_publish"
                    ? "Onerilen Retry Akisini Calistir"
                    : "Onerilen Kume Uzerinde Calis"}
                </Button>
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
              <Badge label="Karsilastirma" variant="neutral" />
              {sourceComparisonWinner ? (
                <Badge label={sourceComparisonWinner.label} variant={sourceComparisonWinner.variant} />
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
            <p className="mt-3 text-xs muted-text">
              {sourceComparisonWinner?.reason ?? "Kaynak karsilastirmasi icin yeterli veri bekleniyor."}
            </p>
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
            matches.length > 0 ? buildDraftRoute(matches[0], analyticsWindowDays, "approvals_cluster") : null;
          const topSource = topSourceBreakdown(analyticsItem?.source_breakdown);
          const clusterOutcomeSummary = analyticsItem?.outcome_chain_summary ?? null;

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
                {analyticsItem ? (
                  <Badge
                    label={`Effectiveness ${analyticsItem.effectiveness_score}`}
                    variant={analyticsItem.effectiveness_status === "proven" ? "success" : "neutral"}
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
                      Top kaynak: {sourceLabel(topSource.source_key, topSource.label)}
                      {` / ${topSource.tracked_interactions} etkilesim`}
                    </p>
                  ) : null}
                  {clusterOutcomeSummary ? (
                    <p>
                      Outcome: {clusterOutcomeSummary.manual_check_completions} manuel kontrol /
                      {` ${clusterOutcomeSummary.total_retry_actions}`} retry /
                      {` ${clusterOutcomeSummary.successful_publishes}`} basarili publish
                    </p>
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
                <Button
                  type="button"
                  size="sm"
                  variant={cluster.key === "retry-ready" || cluster.key === "cleanup-recovered" ? "primary" : "secondary"}
                  onClick={() => {
                    if (cluster.key === "retry-ready" || cluster.key === "cleanup-recovered") {
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
                    || ((cluster.key === "retry-ready" || cluster.key === "cleanup-recovered") && retryableMatches.length === 0)
                  }
                >
                  {clusterActionLabel(cluster.key)}
                </Button>
                {clusterDraftRoute ? (
                  <Link href={clusterDraftRoute}>
                    <Button type="button" variant="outline" size="sm">
                      Draft Detayina Git
                    </Button>
                  </Link>
                ) : null}
              </div>
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
                    <Link href={buildDraftRoute(item, analyticsWindowDays, "approvals_retry_ready") ?? item.approvable_route}>
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
                <Link href={buildDraftRoute(item, analyticsWindowDays, "approvals_item") ?? item.approvable_route}>
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

function clusterActionLabel(clusterKey: string): string {
  return (
    {
      "manual-check-required": "Bekleyenleri Sec",
      "retry-ready": "Toplu Retry Publish",
      "cleanup-recovered": "Toplu Retry Publish",
      "review-error": "Hatalari Filtrele",
    }[clusterKey] ?? "Kumeyi Filtrele"
  );
}

function sourceLabel(sourceKey: string, fallbackLabel: string): string {
  return (
    {
      approvals_featured: "Featured Karar",
      approvals_cluster: "Cluster Karari",
      approvals_retry_ready: "Retry-Hazir Akisi",
      approvals_item: "Item Aksiyonu",
      approvals: "Approvals Merkezi",
      draft_detail: "Draft Detayi",
      other: "Diger",
    }[sourceKey] ?? fallbackLabel
  );
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
): {
  label: string;
  variant: "success" | "warning" | "neutral";
  reason: string;
} | null {
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
      };
    }

    if (nativeRate > draftRate) {
      return {
        label: "Approvals-native daha guclu",
        variant: "warning",
        reason: `Approvals-native publish basarisi %${nativeRate} ile draft detail %${draftRate} seviyesinin uzerinde.`,
      };
    }
  }

  if (draftDetailSummary.successful_publishes !== approvalsNativeSummary.successful_publishes) {
    return draftDetailSummary.successful_publishes > approvalsNativeSummary.successful_publishes
      ? {
          label: "Draft detail daha guclu",
          variant: "success",
          reason: "Draft detail daha fazla basarili publish uretmis gorunuyor.",
        }
      : {
          label: "Approvals-native daha guclu",
          variant: "warning",
          reason: "Approvals-native kaynaklar daha fazla basarili publish uretmis gorunuyor.",
        };
  }

  if (draftDetailSummary.tracked_interactions !== approvalsNativeSummary.tracked_interactions) {
    return draftDetailSummary.tracked_interactions > approvalsNativeSummary.tracked_interactions
      ? {
          label: "Draft detail daha aktif",
          variant: "neutral",
          reason: "Draft detail etkilesim hacmi approvals-native toplanmiÅŸ akislerden daha yuksek.",
        }
      : {
          label: "Approvals-native daha aktif",
          variant: "neutral",
          reason: "Approvals-native akisler draft detail'e gore daha yuksek etkilesim uretiyor.",
        };
  }

  return {
    label: "Denge",
    variant: "neutral",
    reason: "Draft detail ve approvals-native akislari birbirine yakin calisiyor.",
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
  focusSource: "approvals" | "approvals_featured" | "approvals_cluster" | "approvals_retry_ready" | "approvals_item",
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
