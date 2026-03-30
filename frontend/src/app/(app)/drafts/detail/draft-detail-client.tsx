"use client";

import { useState } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { invalidateApiCache } from "@/lib/api-cache";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { useApiQuery } from "@/hooks/use-api-query";

type DraftDetailResponse = {
  data: {
    id: string;
    objective: string;
    product_service: string;
    target_audience: string;
    status: string;
    notes: string | null;
    items: Array<{
      id: string;
      item_type: string;
      title: string | null;
      content: Record<string, unknown>;
    }>;
    approval: {
      id: string;
      status: string;
      approvable_route: string | null;
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
    } | null;
  };
};

type DraftPublishState = NonNullable<DraftDetailResponse["data"]["approval"]>["publish_state"];
type SearchParamsLike = {
  get(name: string): string | null;
};

export function DraftDetailClient() {
  const searchParams = useSearchParams();
  const draftId = searchParams.get("id");
  const focusPublishState = searchParams.get("focus_publish_state");
  const focusRecommendedAction = searchParams.get("focus_recommended_action");
  const focusClusterKey = searchParams.get("focus_cluster_key");
  const focusSource = searchParams.get("focus_source");
  const focusDecisionStatus = searchParams.get("focus_decision_status");
  const focusDecisionReason = searchParams.get("focus_decision_reason");
  const focusRetryGuidanceStatus = searchParams.get("focus_retry_guidance_status");
  const focusRetryGuidanceLabel = searchParams.get("focus_retry_guidance_label");
  const focusRetryGuidanceReason = searchParams.get("focus_retry_guidance_reason");
  const focusEffectivenessScore = resolveOptionalNumber(searchParams.get("focus_effectiveness_score"));
  const focusLongTermWindowDays = resolveOptionalWindowDays(
    readFirstSearchParam(searchParams, ["focus_long_term_window_days", "focus_window_days"]),
  );
  const focusLongTermPublishSuccessRate = resolveOptionalNumber(
    readFirstSearchParam(searchParams, [
      "focus_long_term_publish_success_rate",
      "focus_long_term_success_rate",
      "focus_long_term_approvals_native_publish_success_rate",
    ]),
  );
  const focusLongTermBaselineSuccessRate = resolveOptionalNumber(
    readFirstSearchParam(searchParams, ["focus_long_term_baseline_success_rate"]),
  );
  const focusLongTermEffectivenessScore = resolveOptionalNumber(
    readFirstSearchParam(searchParams, ["focus_long_term_effectiveness_score"]),
  );
  const focusLongTermEffectivenessStatus = readFirstSearchParam(searchParams, [
    "focus_long_term_effectiveness_status",
  ]);
  const focusSourceComparisonLabel = readFirstSearchParam(searchParams, [
    "focus_source_comparison_label",
    "focus_source_comparison_winner",
  ]);
  const focusSourceComparisonReason = readFirstSearchParam(searchParams, [
    "focus_source_comparison_reason",
  ]);
  const focusSourceComparisonWinner = readFirstSearchParam(searchParams, [
    "focus_source_comparison_winner",
  ]);
  const focusPrimaryActionMode = readFirstSearchParam(searchParams, ["focus_primary_action_mode"]);
  const focusPrimaryActionRouteLabel = readFirstSearchParam(searchParams, ["focus_primary_action_route_label"]);
  const focusPrimaryActionSourceLabel = readFirstSearchParam(searchParams, ["focus_primary_action_source_label"]);
  const focusPrimaryActionReason = readFirstSearchParam(searchParams, ["focus_primary_action_reason"]);
  const focusPrimaryActionSuccessRate = resolveOptionalNumber(
    readFirstSearchParam(searchParams, ["focus_primary_action_success_rate"]),
  );
  const focusPrimaryActionTrackedInteractions = resolveOptionalNumber(
    readFirstSearchParam(searchParams, ["focus_primary_action_tracked_interactions"]),
  );
  const focusPrimaryActionConfidenceStatus = readFirstSearchParam(searchParams, ["focus_primary_action_confidence_status"]);
  const focusPrimaryActionConfidenceLabel = readFirstSearchParam(searchParams, ["focus_primary_action_confidence_label"]);
  const focusPrimaryActionAdvantage = resolveOptionalNumber(
    readFirstSearchParam(searchParams, ["focus_primary_action_advantage"]),
  );
  const focusPrimaryActionAlternativeRouteLabel = readFirstSearchParam(
    searchParams,
    ["focus_primary_action_alternative_route_label"],
  );
  const focusPrimaryActionAlternativeSuccessRate = resolveOptionalNumber(
    readFirstSearchParam(searchParams, ["focus_primary_action_alternative_success_rate"]),
  );
  const focusRouteTrendLabel = readFirstSearchParam(searchParams, ["focus_route_trend_label"]);
  const focusRouteTrendReason = readFirstSearchParam(searchParams, ["focus_route_trend_reason"]);
  const focusRoutePreferredFlow = readFirstSearchParam(searchParams, ["focus_route_preferred_flow"]);
  const focusRouteTrendConfidence = readFirstSearchParam(searchParams, ["focus_route_trend_confidence"]);
  const focusRouteCurrentLabel = readFirstSearchParam(searchParams, ["focus_route_current_label"]);
  const focusRouteCurrentAttempts = resolveOptionalNumber(
    readFirstSearchParam(searchParams, ["focus_route_current_attempts"]),
  );
  const focusRouteCurrentSuccessRate = resolveOptionalNumber(
    readFirstSearchParam(searchParams, ["focus_route_current_success_rate"]),
  );
  const focusRouteCurrentAdvantage = resolveOptionalNumber(
    readFirstSearchParam(searchParams, ["focus_route_current_advantage"]),
  );
  const focusRouteLongTermLabel = readFirstSearchParam(searchParams, ["focus_route_long_term_label"]);
  const focusRouteLongTermAttempts = resolveOptionalNumber(
    readFirstSearchParam(searchParams, ["focus_route_long_term_attempts"]),
  );
  const focusRouteLongTermSuccessRate = resolveOptionalNumber(
    readFirstSearchParam(searchParams, ["focus_route_long_term_success_rate"]),
  );
  const focusRouteLongTermAdvantage = resolveOptionalNumber(
    readFirstSearchParam(searchParams, ["focus_route_long_term_advantage"]),
  );
  const analyticsWindowDays = resolveAnalyticsWindow(searchParams.get("window_days"));
  const hasDraftId = Boolean(draftId);
  const [submitting, setSubmitting] = useState(false);
  const [remediationSubmitting, setRemediationSubmitting] = useState(false);
  const [remediationMode, setRemediationMode] =
    useState<"manual_check_completed" | "retry_publish" | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [remediationMessage, setRemediationMessage] = useState<string | null>(null);
  const {
    data: draft,
    error,
    isLoading,
    reload,
  } = useApiQuery<DraftDetailResponse, DraftDetailResponse["data"]>(`/drafts/${draftId ?? ""}`, {
    enabled: hasDraftId,
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.draftDetail,
    select: (response) => response.data,
  });

  const submitForReview = async () => {
    if (!hasDraftId) {
      setActionError("Draft id eksik.");
      return;
    }

    setSubmitting(true);
    setActionError(null);
    try {
      await apiRequest(`/drafts/${draftId as string}/submit-review`, {
        method: "POST",
        requireWorkspace: true,
      });
      invalidateApiCache(`/drafts/${draftId as string}`, { requireWorkspace: true });
      invalidateApiCache("/drafts", { requireWorkspace: true });
      invalidateApiCache("/approvals", { requireWorkspace: true });
      await reload();
    } catch (err) {
      setActionError(err instanceof Error ? err.message : "Review adimi basarisiz.");
    } finally {
      setSubmitting(false);
    }
  };

  const applyApprovalRemediation = async (mode: "manual_check_completed" | "retry_publish") => {
    const approvalId = draft?.approval?.id;
    const actedClusterKey = deriveApprovalClusterKey(currentRecommendedAction);
    const interactionSource = resolveRemediationInteractionSource(focusSource);
    const shouldTrackRemediation =
      Boolean(focusSource?.startsWith("approvals"))
      && Boolean(focusClusterKey)
      && Boolean(actedClusterKey);

    if (!approvalId) {
      setActionError("Approval baglantisi bulunamadi.");
      return;
    }

    let notePayload: string | undefined;
    if (mode === "manual_check_completed") {
      const note = window.prompt("Manuel kontrol notu (opsiyonel)");
      if (note === null) {
        return;
      }

      notePayload = note.trim() || undefined;
    }

    setRemediationSubmitting(true);
    setRemediationMode(mode);
    setActionError(null);
    setRemediationMessage(null);

    try {
      if (mode === "manual_check_completed") {
        await apiRequest(`/approvals/${approvalId}/manual-check-completed`, {
          method: "POST",
          requireWorkspace: true,
          body: {
            note: notePayload,
          },
        });
        if (shouldTrackRemediation && focusClusterKey && actedClusterKey) {
          await apiRequest("/approvals/remediation-analytics/track", {
            method: "POST",
            requireWorkspace: true,
            body: {
              featured_cluster_key: focusClusterKey,
              acted_cluster_key: actedClusterKey,
              interaction_type: "manual_check_completed",
              interaction_source: interactionSource,
              followed_featured: focusClusterKey === actedClusterKey,
              attempted_count: 0,
              success_count: 0,
              failure_count: 0,
            },
          });
        }
        setRemediationMessage("Manuel kontrol tamamlandi. Publish aksiyonu tekrar denenebilir.");
      } else {
        await apiRequest(`/approvals/${approvalId}/publish`, {
          method: "POST",
          requireWorkspace: true,
        });
        if (shouldTrackRemediation && focusClusterKey && actedClusterKey) {
          await apiRequest("/approvals/remediation-analytics/track", {
            method: "POST",
            requireWorkspace: true,
            body: {
              featured_cluster_key: focusClusterKey,
              acted_cluster_key: actedClusterKey,
              interaction_type: "publish_retry",
              interaction_source: interactionSource,
              followed_featured: focusClusterKey === actedClusterKey,
              attempted_count: 1,
              success_count: 1,
              failure_count: 0,
            },
          });
        }
        setRemediationMessage("Tekrar publish denemesi baslatildi.");
      }

      invalidateApiCache(`/drafts/${draftId as string}`, { requireWorkspace: true });
      invalidateApiCache("/drafts", { requireWorkspace: true });
      invalidateApiCache("/approvals", { requireWorkspace: true });
      invalidateApiCache("/approvals?status=publish_failed", { requireWorkspace: true });
      invalidateApiCache(`/approvals/remediation-analytics?window_days=${analyticsWindowDays ?? 30}`, {
        requireWorkspace: true,
      });

      if (approvalsReturnRoute) {
        invalidateApiCache(approvalsReturnRoute, { requireWorkspace: true });
      }

      const retryReadyReturnRoute = buildApprovalsReturnRoute(
        "retry_publish_after_manual_check",
        "manual_check_completed",
        analyticsWindowDays,
      );

      if (retryReadyReturnRoute) {
        invalidateApiCache(retryReadyReturnRoute, { requireWorkspace: true });
      }

      await reload();
    } catch (err) {
      if (
        mode === "retry_publish"
        && shouldTrackRemediation
        && focusClusterKey
        && actedClusterKey
      ) {
        try {
          await apiRequest("/approvals/remediation-analytics/track", {
            method: "POST",
            requireWorkspace: true,
            body: {
              featured_cluster_key: focusClusterKey,
              acted_cluster_key: actedClusterKey,
              interaction_type: "publish_retry",
              interaction_source: interactionSource,
              followed_featured: focusClusterKey === actedClusterKey,
              attempted_count: 1,
              success_count: 0,
              failure_count: 1,
            },
          });
        } catch {
          // Tracking must not block operator remediation flow.
        }
      }
      setActionError(err instanceof Error ? err.message : "Remediation aksiyonu basarisiz.");
    } finally {
      setRemediationSubmitting(false);
      setRemediationMode(null);
    }
  };

  if (!hasDraftId) return <p className="text-sm text-[var(--danger)]">Draft id eksik.</p>;
  if (error) return <p className="text-sm text-[var(--danger)]">{error}</p>;
  if (isLoading && !draft) return <p className="text-sm muted-text">Yukleniyor...</p>;
  if (!draft) return <p className="text-sm text-[var(--danger)]">Draft bulunamadi.</p>;

  const hasPublishFocus = Boolean(
    draft.approval?.publish_state
    && (focusPublishState || focusRecommendedAction || focusClusterKey || Boolean(focusSource?.startsWith("approvals"))),
  );
  const remediationPublishState = draft.approval?.publish_state ?? null;
  const currentPublishFocusState = derivePublishFocusState(remediationPublishState);
  const currentRecommendedAction =
    remediationPublishState?.recommended_action_code ?? focusRecommendedAction;
  const approvalsReturnRoute = buildApprovalsReturnRoute(
    currentRecommendedAction,
    currentPublishFocusState ?? focusPublishState,
    analyticsWindowDays,
  );
  const remediationPrimaryAction = resolveRemediationPrimaryAction(
    currentRecommendedAction,
    remediationPublishState,
  );
  const hasPrimaryActionFocus = Boolean(
    focusPrimaryActionMode
    || focusPrimaryActionRouteLabel
    || focusPrimaryActionReason
    || focusPrimaryActionConfidenceStatus,
  );
  const hasRouteTrendFocus = Boolean(
    focusRouteTrendLabel
    || focusRouteTrendReason
    || focusRoutePreferredFlow
    || focusRouteTrendConfidence
    || focusRouteCurrentLabel
    || focusRouteLongTermLabel,
  );

  return (
    <div className="space-y-4">
      <Card>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 className="text-xl font-bold">{draft.objective}</h3>
            <p className="text-sm muted-text">{draft.product_service}</p>
          </div>
          <div className="flex items-center gap-2">
            <Badge label={draft.status} variant="warning" />
            {draft.approval ? <Badge label={`Approval: ${draft.approval.status}`} variant="neutral" /> : null}
          </div>
        </div>

        <p className="mt-3 text-sm">{draft.target_audience}</p>
        {draft.notes ? <p className="mt-2 text-xs muted-text">{draft.notes}</p> : null}
        {draft.approval?.publish_state ? (
          <div
            id="publish-remediation"
            className={[
              "mt-4 rounded-md border p-3",
              draft.approval.publish_state.manual_check_required
                ? "border-[var(--danger)] bg-[var(--surface-2)]"
                : "border-[var(--border)] bg-[var(--surface-2)]",
              hasPublishFocus ? "ring-2 ring-[var(--accent)]/25" : "",
            ].join(" ").trim()}
          >
            {hasPublishFocus ? (
              <div className="mb-3 rounded-md border border-[var(--accent)]/30 bg-white p-3">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge label="Approvals Odagi" variant="success" />
                  {focusPublishState ? (
                    <Badge label={focusPublishStateLabel(focusPublishState)} variant="warning" />
                  ) : null}
                  {focusRecommendedAction ? (
                    <Badge label={focusRecommendedActionLabel(focusRecommendedAction)} variant="neutral" />
                  ) : null}
                  {focusClusterKey ? (
                    <Badge label={focusClusterKeyLabel(focusClusterKey)} variant="neutral" />
                  ) : null}
                  {focusDecisionStatus ? (
                    <Badge label={focusDecisionStatusLabel(focusDecisionStatus)} variant="neutral" />
                  ) : null}
                  {focusRetryGuidanceStatus || focusRetryGuidanceLabel ? (
                    <Badge
                      label={focusRetryGuidanceLabel ?? focusRetryGuidanceStatusLabel(focusRetryGuidanceStatus)}
                      variant={focusRetryGuidanceStatusVariant(focusRetryGuidanceStatus)}
                    />
                  ) : null}
                  {focusEffectivenessScore != null ? (
                    <Badge label={`Effectiveness ${focusEffectivenessScore}`} variant="neutral" />
                  ) : null}
                  {focusLongTermWindowDays != null ? (
                    <Badge label={`${focusLongTermWindowDays} gun long-term`} variant="neutral" />
                  ) : null}
                  {focusLongTermPublishSuccessRate != null ? (
                    <Badge label={`%${focusLongTermPublishSuccessRate} long-term success`} variant="success" />
                  ) : null}
                  {focusLongTermBaselineSuccessRate != null ? (
                    <Badge label={`%${focusLongTermBaselineSuccessRate} pencere baz`} variant="neutral" />
                  ) : null}
                  {focusLongTermEffectivenessScore != null ? (
                    <Badge label={`LT Effectiveness ${focusLongTermEffectivenessScore}`} variant="neutral" />
                  ) : null}
                  {focusLongTermEffectivenessStatus ? (
                    <Badge
                      label={focusLongTermEffectivenessStatusLabel(focusLongTermEffectivenessStatus)}
                      variant={focusLongTermEffectivenessStatusVariant(focusLongTermEffectivenessStatus)}
                    />
                  ) : null}
                  {focusSourceComparisonLabel ? (
                    <Badge label={focusSourceComparisonLabel} variant="neutral" />
                  ) : null}
                  {analyticsWindowDays ? (
                    <Badge label={`${analyticsWindowDays} gun analytics`} variant="neutral" />
                  ) : null}
                </div>
                <p className="mt-2 text-sm muted-text">
                  {buildFocusGuidance(
                    focusPublishState,
                    focusRecommendedAction,
                    focusSource,
                    analyticsWindowDays,
                    focusDecisionStatus,
                    focusDecisionReason,
                    focusRetryGuidanceStatus,
                    focusRetryGuidanceLabel,
                    focusRetryGuidanceReason,
                    focusEffectivenessScore,
                    focusLongTermWindowDays,
                    focusLongTermPublishSuccessRate,
                    focusLongTermBaselineSuccessRate,
                    focusLongTermEffectivenessScore,
                    focusLongTermEffectivenessStatus,
                    focusSourceComparisonLabel,
                    focusSourceComparisonReason,
                    focusSourceComparisonWinner,
                    focusRouteTrendLabel,
                    focusRouteTrendReason,
                    focusRoutePreferredFlow,
                    focusRouteTrendConfidence,
                  )}
                </p>
                {focusSourceComparisonLabel || focusLongTermWindowDays != null ? (
                  <div className="mt-3 rounded-md border border-[var(--border)] bg-[var(--surface-2)] p-3">
                    <div className="flex flex-wrap items-center gap-2">
                      <Badge label="Kaynak Karsilastirmasi" variant="neutral" />
                      {focusSourceComparisonLabel ? (
                        <Badge
                          label={focusSourceComparisonLabel}
                          variant={focusSourceComparisonVariant(focusSourceComparisonLabel, focusSourceComparisonWinner)}
                        />
                      ) : null}
                      {focusLongTermWindowDays != null ? (
                        <Badge label={`${focusLongTermWindowDays} gun stabilite`} variant="neutral" />
                      ) : null}
                      {focusLongTermEffectivenessStatus ? (
                        <Badge
                          label={focusLongTermEffectivenessStatusLabel(focusLongTermEffectivenessStatus)}
                          variant={focusLongTermEffectivenessStatusVariant(focusLongTermEffectivenessStatus)}
                        />
                      ) : null}
                    </div>
                    <p className="mt-2 text-sm muted-text">
                      {buildSourceComparisonOperatorHint(
                        focusSourceComparisonLabel,
                        focusSourceComparisonReason,
                        focusSourceComparisonWinner,
                        focusLongTermWindowDays,
                        focusLongTermPublishSuccessRate,
                        focusLongTermBaselineSuccessRate,
                      )}
                    </p>
                  </div>
                ) : null}
                {approvalsReturnRoute ? (
                  <div className="mt-3">
                    <Link href={approvalsReturnRoute}>
                      <Button variant="outline" size="sm">
                        Approvals Akisina Geri Don
                      </Button>
                    </Link>
                  </div>
                ) : null}
              </div>
            ) : null}
            <div className="flex flex-wrap items-center gap-2">
              <Badge
                label={
                  draft.approval.publish_state.manual_check_required
                    ? "Manuel Kontrol Gerekli"
                    : draft.approval.publish_state.manual_check_completed
                      ? "Manuel Kontrol Tamamlandi"
                    : draft.approval.publish_state.partial_publish_detected
                      ? "Partial Publish Tespit Edildi"
                      : draft.approval.publish_state.success
                        ? "Publish Basarili"
                        : "Publish Hatasi"
                }
                variant={
                  draft.approval.publish_state.manual_check_required
                    ? "danger"
                    : draft.approval.publish_state.manual_check_completed
                      ? "success"
                    : draft.approval.publish_state.success
                      ? "success"
                      : "warning"
                }
              />
              {draft.approval.publish_state.recommended_action_label ? (
                <Badge label={draft.approval.publish_state.recommended_action_label} variant="neutral" />
              ) : null}
            </div>
            {draft.approval.publish_state.message ? <p className="mt-2 text-sm">{draft.approval.publish_state.message}</p> : null}
            {draft.approval.publish_state.operator_guidance ? <p className="mt-2 text-sm muted-text">{draft.approval.publish_state.operator_guidance}</p> : null}
            <div className="mt-2 flex flex-wrap gap-3 text-xs muted-text">
              {draft.approval.publish_state.meta_campaign_id ? <span>Campaign: <strong>{draft.approval.publish_state.meta_campaign_id}</strong></span> : null}
              {draft.approval.publish_state.meta_ad_set_id ? <span>Ad Set: <strong>{draft.approval.publish_state.meta_ad_set_id}</strong></span> : null}
              {draft.approval.publish_state.cleanup_attempted ? (
                <span>Cleanup: <strong>{draft.approval.publish_state.cleanup_success ? "Basarili" : "Basarisiz"}</strong></span>
              ) : null}
              {draft.approval.publish_state.manual_check_completed_at ? (
                <span>Kontrol: <strong>{new Date(draft.approval.publish_state.manual_check_completed_at).toLocaleString("tr-TR")}</strong></span>
              ) : null}
            </div>
            {draft.approval.publish_state.cleanup_message ? <p className="mt-2 text-xs text-[var(--danger)]">{draft.approval.publish_state.cleanup_message}</p> : null}
            {draft.approval.publish_state.manual_check_note ? (
              <p className="mt-2 text-xs muted-text">Kontrol notu: {draft.approval.publish_state.manual_check_note}</p>
                ) : null}
                {hasPublishFocus && (focusSource?.startsWith("approvals") || focusDecisionStatus || focusSourceComparisonLabel) ? (
                  <div className="mt-3 rounded-md border border-[var(--accent)]/35 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(249,250,251,0.98))] p-4 shadow-sm">
                    <div className="flex flex-wrap items-center gap-2">
                      <Badge label="Operasyon Karari" variant="success" />
                      {focusSource?.startsWith("approvals") ? <Badge label="Approvals Kaynakli" variant="neutral" /> : null}
                      {focusDecisionStatus ? <Badge label={focusDecisionStatusLabel(focusDecisionStatus)} variant="neutral" /> : null}
                      {focusSourceComparisonWinner ? (
                        <Badge label={focusSourceComparisonWinner} variant="success" />
                      ) : null}
                      {focusLongTermWindowDays != null ? (
                        <Badge label={`${focusLongTermWindowDays} gunluk pencere`} variant="neutral" />
                      ) : null}
                    </div>
                    <p className="mt-2 text-sm font-semibold">
                      {remediationPrimaryAction?.label ?? "Publish durumunu dogrula"}
                    </p>
                    <p className="mt-1 text-xs muted-text">
                      {focusSourceComparisonLabel
                        ? `${focusSourceComparisonLabel}${focusSourceComparisonReason ? ` - ${focusSourceComparisonReason}` : ""}${focusSourceComparisonWinner ? ` (kazanan: ${focusSourceComparisonWinner})` : ""}`
                        : "Approvals odagindan gelen karar ve route trend bilgisi burada toplanir."}
                    </p>
                    <div className="mt-3 grid gap-2 md:grid-cols-3">
                      <div className="rounded-md border border-[var(--border)] bg-white p-3">
                        <p className="text-xs font-semibold uppercase tracking-wide muted-text">Birincil Aksiyon</p>
                        <p className="mt-1 text-sm font-semibold">
                          {remediationPrimaryAction?.label ?? "Publish durumunu dogrula"}
                        </p>
                        <p className="mt-1 text-xs muted-text">
                          {remediationPrimaryAction?.hint ?? "Bu blok approvals kararini daha hizli aksiyona cevirir."}
                        </p>
                      </div>
                      <div className="rounded-md border border-[var(--border)] bg-white p-3">
                        <p className="text-xs font-semibold uppercase tracking-wide muted-text">Route Trendi</p>
                        <p className="mt-1 text-sm font-semibold">
                          {focusSourceComparisonWinner ?? focusSourceComparisonLabel ?? "Belirlenmedi"}
                        </p>
                        <p className="mt-1 text-xs muted-text">
                          {focusSourceComparisonReason
                            ? focusSourceComparisonReason
                            : focusSource?.startsWith("approvals")
                              ? "Approvals-native ve draft detail akislari bu odakta karsilastiriliyor."
                              : "Kaynak karsilastirmasi bu draft icin takip ediliyor."}
                        </p>
                      </div>
                      <div className="rounded-md border border-[var(--border)] bg-white p-3">
                        <p className="text-xs font-semibold uppercase tracking-wide muted-text">Uzun Donem</p>
                        <p className="mt-1 text-sm font-semibold">
                          {focusLongTermWindowDays != null ? `${focusLongTermWindowDays} gunluk pencere` : "Pencere yok"}
                        </p>
                        <p className="mt-1 text-xs muted-text">
                          {focusLongTermPublishSuccessRate != null
                            ? `%${focusLongTermPublishSuccessRate} publish basarisi`
                            : "Uzun donem performans burada izlenir."}
                        </p>
                        {focusLongTermEffectivenessStatus ? (
                          <p className="mt-1 text-xs muted-text">
                            {focusLongTermEffectivenessStatusLabel(focusLongTermEffectivenessStatus)}
                            {focusLongTermEffectivenessScore != null
                              ? ` / ${focusLongTermEffectivenessScore}`
                              : ""}
                          </p>
                        ) : null}
                      </div>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-2">
                      {remediationPrimaryAction ? (
                        <Button
                          size="sm"
                          onClick={() => void applyApprovalRemediation(remediationPrimaryAction.mode)}
                          disabled={Boolean(remediationSubmitting)}
                        >
                          {remediationSubmitting && remediationMode === remediationPrimaryAction.mode
                            ? "Isleniyor..."
                            : remediationPrimaryAction.label}
                        </Button>
                      ) : null}
                      {approvalsReturnRoute ? (
                        <Link href={approvalsReturnRoute}>
                          <Button variant="outline" size="sm">
                            Approvals Akisina Geri Don
                          </Button>
                        </Link>
                      ) : null}
                    </div>
                  </div>
                ) : null}
                {remediationPrimaryAction ? (
                  <div className="mt-3 rounded-md border border-[var(--accent)]/30 bg-white p-3">
                    <div className="flex flex-wrap items-center gap-2">
                      <Badge label="Operasyon Karari" variant="success" />
                      {focusSource === "approvals_featured" || focusSource === "approvals_featured_long_term" ? (
                        <Badge label="Featured Karardan Geldi" variant="neutral" />
                      ) : null}
                      {focusSource === "approvals_featured_long_term" ? (
                        <Badge label="Uzun Donem Featured" variant="success" />
                      ) : null}
                      {focusSource === "approvals_cluster" || focusSource === "approvals_cluster_long_term" ? (
                        <Badge label="Cluster Odagi" variant="neutral" />
                      ) : null}
                      {focusSource === "approvals_cluster_long_term" ? (
                        <Badge label="Uzun Donem Cluster" variant="success" />
                      ) : null}
                      {focusPrimaryActionRouteLabel ? (
                        <Badge label={focusPrimaryActionRouteLabel} variant="neutral" />
                      ) : null}
                      {focusPrimaryActionMode ? (
                        <Badge label={focusPrimaryActionModeLabel(focusPrimaryActionMode)} variant="neutral" />
                      ) : null}
                      {focusPrimaryActionConfidenceStatus ? (
                        <Badge
                          label={focusPrimaryActionConfidenceLabel ?? focusPrimaryActionConfidenceStatusLabel(focusPrimaryActionConfidenceStatus)}
                          variant={focusPrimaryActionConfidenceVariant(focusPrimaryActionConfidenceStatus)}
                        />
                      ) : null}
                    </div>
                    <p className="mt-2 text-sm font-semibold">{remediationPrimaryAction?.label ?? "Publish durumunu dogrula"}</p>
                    {remediationPrimaryAction.hint ? (
                      <p className="mt-1 text-xs muted-text">{remediationPrimaryAction.hint}</p>
                    ) : null}
                    {hasPrimaryActionFocus ? (
                      <div className="mt-2 rounded-md border border-[var(--border)] bg-[var(--surface-2)] p-3">
                        <p className="text-xs font-semibold uppercase tracking-wide muted-text">Birincil Aksiyon Rotasi</p>
                        <p className="mt-1 text-xs muted-text">
                          {buildPrimaryActionOperatorHint(
                            focusPrimaryActionMode,
                            focusPrimaryActionRouteLabel,
                            focusPrimaryActionSourceLabel,
                            focusPrimaryActionReason,
                            focusPrimaryActionSuccessRate,
                            focusPrimaryActionTrackedInteractions,
                            focusPrimaryActionConfidenceLabel ?? focusPrimaryActionConfidenceStatusLabel(focusPrimaryActionConfidenceStatus),
                            focusPrimaryActionAdvantage,
                            focusPrimaryActionAlternativeRouteLabel,
                            focusPrimaryActionAlternativeSuccessRate,
                          )}
                        </p>
                        <div className="mt-2 grid gap-2 text-xs muted-text md:grid-cols-3">
                          <div className="rounded-md border border-[var(--border)] bg-white p-2">
                            <p className="font-semibold">Secilen Rota</p>
                            <p className="mt-1">{focusPrimaryActionRouteLabel ?? "Belirlenmedi"}</p>
                            {focusPrimaryActionSourceLabel ? (
                              <p className="mt-1">{focusPrimaryActionSourceLabel}</p>
                            ) : null}
                          </div>
                          <div className="rounded-md border border-[var(--border)] bg-white p-2">
                            <p className="font-semibold">Telemetry</p>
                            {focusPrimaryActionSuccessRate != null ? (
                              <p className="mt-1">%{focusPrimaryActionSuccessRate} publish basarisi</p>
                            ) : (
                              <p className="mt-1">Publish sonucu birikiyor</p>
                            )}
                            {focusPrimaryActionTrackedInteractions != null ? (
                              <p className="mt-1">{focusPrimaryActionTrackedInteractions} izlenen etkilesim</p>
                            ) : null}
                          </div>
                          <div className="rounded-md border border-[var(--border)] bg-white p-2">
                            <p className="font-semibold">Alternatif</p>
                            <p className="mt-1">{focusPrimaryActionAlternativeRouteLabel ?? "Alternatif rota yok"}</p>
                            {focusPrimaryActionAlternativeSuccessRate != null ? (
                              <p className="mt-1">%{focusPrimaryActionAlternativeSuccessRate} publish basarisi</p>
                            ) : null}
                            {focusPrimaryActionAdvantage != null ? (
                              <p className="mt-1">
                                {focusPrimaryActionAdvantage >= 0 ? "+" : ""}
                                {focusPrimaryActionAdvantage} puan fark
                              </p>
                            ) : null}
                          </div>
                        </div>
                      </div>
                    ) : null}
                    {hasRouteTrendFocus ? (
                      <div className="mt-2 rounded-md border border-[var(--border)] bg-[var(--surface-2)] p-3">
                        <div className="flex flex-wrap items-center gap-2">
                          <Badge label="Route Karar Trendi" variant="neutral" />
                          {focusRouteTrendLabel ? (
                            <Badge label={focusRouteTrendLabel} variant={focusRouteTrendVariant(focusRoutePreferredFlow)} />
                          ) : null}
                          {focusRouteTrendConfidence ? (
                            <Badge
                              label={focusRouteTrendConfidenceLabel(focusRouteTrendConfidence)}
                              variant={focusRouteTrendConfidenceVariant(focusRouteTrendConfidence)}
                            />
                          ) : null}
                          {focusRoutePreferredFlow ? (
                            <Badge label={focusRoutePreferredFlowLabel(focusRoutePreferredFlow)} variant="neutral" />
                          ) : null}
                        </div>
                        <p className="mt-2 text-xs muted-text">
                          {buildRouteTrendOperatorHint(
                            focusRouteTrendLabel,
                            focusRouteTrendReason,
                            focusRoutePreferredFlow,
                            focusRouteTrendConfidence,
                            focusRouteCurrentLabel,
                            focusRouteCurrentAttempts,
                            focusRouteCurrentSuccessRate,
                            focusRouteCurrentAdvantage,
                            focusRouteLongTermLabel,
                            focusRouteLongTermAttempts,
                            focusRouteLongTermSuccessRate,
                            focusRouteLongTermAdvantage,
                          )}
                        </p>
                        <div className="mt-2 grid gap-2 text-xs muted-text md:grid-cols-2">
                          <div className="rounded-md border border-[var(--border)] bg-white p-2">
                            <p className="font-semibold">Mevcut Pencere</p>
                            <p className="mt-1">{focusRouteCurrentLabel ?? "Route trend verisi yok"}</p>
                            {focusRouteCurrentAttempts != null ? (
                              <p className="mt-1">{focusRouteCurrentAttempts} deneme</p>
                            ) : null}
                            {focusRouteCurrentSuccessRate != null ? (
                              <p className="mt-1">%{focusRouteCurrentSuccessRate} publish basarisi</p>
                            ) : null}
                            {focusRouteCurrentAdvantage != null ? (
                              <p className="mt-1">
                                {focusRouteCurrentAdvantage >= 0 ? "+" : ""}
                                {focusRouteCurrentAdvantage} puan fark
                              </p>
                            ) : null}
                          </div>
                          <div className="rounded-md border border-[var(--border)] bg-white p-2">
                            <p className="font-semibold">Uzun Donem</p>
                            <p className="mt-1">{focusRouteLongTermLabel ?? "Uzun donem route trend verisi yok"}</p>
                            {focusRouteLongTermAttempts != null ? (
                              <p className="mt-1">{focusRouteLongTermAttempts} deneme</p>
                            ) : null}
                            {focusRouteLongTermSuccessRate != null ? (
                              <p className="mt-1">%{focusRouteLongTermSuccessRate} publish basarisi</p>
                            ) : null}
                            {focusRouteLongTermAdvantage != null ? (
                              <p className="mt-1">
                                {focusRouteLongTermAdvantage >= 0 ? "+" : ""}
                                {focusRouteLongTermAdvantage} puan fark
                              </p>
                            ) : null}
                          </div>
                        </div>
                      </div>
                    ) : null}
                    {hasRouteTrendFocus ? (
                      <div className="mt-2 rounded-md border border-[var(--border)] bg-[var(--surface-2)] p-3">
                        <div className="flex flex-wrap items-center gap-2">
                          <Badge label={focusRouteTrendLabel ?? "Route Trend"} variant="neutral" />
                          {focusRouteTrendConfidence ? (
                            <Badge
                              label={focusRouteTrendConfidenceLabel(focusRouteTrendConfidence)}
                              variant={focusRouteTrendConfidenceVariant(focusRouteTrendConfidence)}
                            />
                          ) : null}
                          {focusRoutePreferredFlow ? (
                            <Badge label={focusRoutePreferredFlowLabel(focusRoutePreferredFlow)} variant="neutral" />
                          ) : null}
                        </div>
                        <p className="mt-2 text-xs muted-text">
                          {focusRouteTrendReason ?? "Current ve long-term route sinyalleri birlikte degerlendirildi."}
                        </p>
                        <div className="mt-2 grid gap-2 text-xs muted-text md:grid-cols-2">
                          <div className="rounded-md border border-[var(--border)] bg-white p-2">
                            <p className="font-semibold">Mevcut Pencere</p>
                            <p className="mt-1">{focusRouteCurrentLabel ?? "Veri yok"}</p>
                            {focusRouteCurrentAttempts != null ? (
                              <p className="mt-1">{focusRouteCurrentAttempts} deneme</p>
                            ) : null}
                            {focusRouteCurrentSuccessRate != null ? (
                              <p className="mt-1">%{focusRouteCurrentSuccessRate} publish basarisi</p>
                            ) : null}
                            {focusRouteCurrentAdvantage != null ? (
                              <p className="mt-1">
                                {focusRouteCurrentAdvantage >= 0 ? "+" : ""}
                                {focusRouteCurrentAdvantage} puan fark
                              </p>
                            ) : null}
                          </div>
                          <div className="rounded-md border border-[var(--border)] bg-white p-2">
                            <p className="font-semibold">Uzun Donem</p>
                            <p className="mt-1">{focusRouteLongTermLabel ?? "Veri yok"}</p>
                            {focusRouteLongTermAttempts != null ? (
                              <p className="mt-1">{focusRouteLongTermAttempts} deneme</p>
                            ) : null}
                            {focusRouteLongTermSuccessRate != null ? (
                              <p className="mt-1">%{focusRouteLongTermSuccessRate} publish basarisi</p>
                            ) : null}
                            {focusRouteLongTermAdvantage != null ? (
                              <p className="mt-1">
                                {focusRouteLongTermAdvantage >= 0 ? "+" : ""}
                                {focusRouteLongTermAdvantage} puan fark
                              </p>
                            ) : null}
                          </div>
                        </div>
                      </div>
                    ) : null}
                    {focusLongTermWindowDays != null || focusSourceComparisonLabel ? (
                      <div className="mt-2 rounded-md border border-[var(--border)] bg-[var(--surface-2)] p-2">
                        <p className="text-xs font-semibold uppercase tracking-wide muted-text">Uzun Donem Baglami</p>
                        {focusLongTermWindowDays != null ? (
                          <p className="mt-1 text-xs muted-text">
                            {focusLongTermWindowDays} gunluk uzun donem pencere, bu aksiyonun daha stabil calistigi akisi one cikarir.
                          </p>
                        ) : null}
                        {focusLongTermPublishSuccessRate != null ? (
                          <p className="mt-1 text-xs muted-text">
                            Uzun donem publish basarisi %{focusLongTermPublishSuccessRate}.
                          </p>
                        ) : null}
                        {focusLongTermBaselineSuccessRate != null ? (
                          <p className="mt-1 text-xs muted-text">
                            Mevcut pencere referansi %{focusLongTermBaselineSuccessRate}.
                          </p>
                        ) : null}
                        {focusLongTermEffectivenessScore != null ? (
                          <p className="mt-1 text-xs muted-text">
                            Uzun donem effectiveness {focusLongTermEffectivenessScore}
                            {focusLongTermEffectivenessStatus
                              ? ` (${focusLongTermEffectivenessStatusLabel(focusLongTermEffectivenessStatus)})`
                              : ""}.
                          </p>
                        ) : null}
                        {focusSourceComparisonLabel ? (
                          <p className="mt-1 text-xs muted-text">
                            Kaynak karsilastirmasi: {focusSourceComparisonLabel}
                            {focusSourceComparisonReason ? ` - ${focusSourceComparisonReason}` : ""}
                            {focusSourceComparisonWinner ? ` [kazanan: ${focusSourceComparisonWinner}]` : ""}
                          </p>
                        ) : null}
                      </div>
                    ) : null}
                    <div className="mt-3 flex flex-wrap gap-2">
                      <Button
                        size="sm"
                        onClick={() => void applyApprovalRemediation(remediationPrimaryAction.mode)}
                        disabled={Boolean(remediationSubmitting)}
                      >
                        {remediationSubmitting && remediationMode === remediationPrimaryAction.mode
                          ? "Isleniyor..."
                          : remediationPrimaryAction.label}
                      </Button>
                    </div>
                  </div>
                ) : null}
            {focusLongTermWindowDays != null || focusSourceComparisonLabel ? (
              <div className="mt-3 rounded-md border border-[var(--border)] bg-[var(--surface-2)] p-3">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge label="Kaynak Karar Ozeti" variant="neutral" />
                  {focusSourceComparisonWinner ? (
                    <Badge label={focusSourceComparisonWinner} variant="success" />
                  ) : null}
                  {focusLongTermWindowDays != null ? (
                    <Badge label={`${focusLongTermWindowDays} gun`} variant="neutral" />
                  ) : null}
                  {focusLongTermEffectivenessStatus ? (
                    <Badge
                      label={focusLongTermEffectivenessStatusLabel(focusLongTermEffectivenessStatus)}
                      variant={focusLongTermEffectivenessStatusVariant(focusLongTermEffectivenessStatus)}
                    />
                  ) : null}
                </div>
                <p className="mt-2 text-sm font-semibold">
                  {focusSourceComparisonLabel
                    ? focusSourceComparisonLabel
                    : focusLongTermWindowDays != null
                      ? `${focusLongTermWindowDays} gunluk uzun donem pencere`
                      : "Kaynak karsilastirmasi"}
                </p>
                <p className="mt-1 text-xs muted-text">
                  {focusSourceComparisonReason
                    ? focusSourceComparisonReason
                    : "Uzun donem stabilite ve approvals analytics baglami birlikte degerlendirildi."}
                </p>
                <div className="mt-2 grid gap-2 text-xs muted-text md:grid-cols-3">
                  <div className="rounded-md border border-[var(--border)] bg-white p-2">
                    <p className="font-semibold">Uzun Donem</p>
                    <p className="mt-1">
                      {focusLongTermWindowDays != null ? `${focusLongTermWindowDays} gunluk pencere` : "Pencere yok"}
                    </p>
                    {focusLongTermPublishSuccessRate != null ? (
                      <p className="mt-1">%{focusLongTermPublishSuccessRate} publish basarisi</p>
                    ) : null}
                    {focusLongTermBaselineSuccessRate != null ? (
                      <p className="mt-1">Referans %{focusLongTermBaselineSuccessRate}</p>
                    ) : null}
                  </div>
                  <div className="rounded-md border border-[var(--border)] bg-white p-2">
                    <p className="font-semibold">Kazanan Kaynak</p>
                    <p className="mt-1">
                      {focusSourceComparisonWinner ?? "Belirlenmedi"}
                    </p>
                    <p className="mt-1">
                      {focusSource === "approvals_featured_long_term"
                        ? "Long-term featured odagi"
                        : focusSource === "approvals_cluster_long_term"
                          ? "Long-term cluster odagi"
                          : focusSource === "approvals_featured"
                            ? "Featured odagi"
                            : focusSource === "approvals_cluster"
                              ? "Cluster odagi"
                              : "Approvals focus"}
                    </p>
                  </div>
                  <div className="rounded-md border border-[var(--border)] bg-white p-2">
                    <p className="font-semibold">Sonraki Adim</p>
                    <p className="mt-1">{remediationPrimaryAction?.label ?? "Publish durumunu dogrula"}</p>
                    <p className="mt-1">
                      {focusLongTermEffectivenessScore != null
                        ? `LT effectiveness ${focusLongTermEffectivenessScore}`
                        : "Remediation odagini bu blokta takip et."}
                    </p>
                  </div>
                </div>
              </div>
            ) : null}
          </div>
        ) : null}

        {draft.status === "draft" ? (
          <div className="mt-4">
            <Button onClick={submitForReview} disabled={submitting || remediationSubmitting}>
              {submitting ? "Gonderiliyor..." : "Incelemeye Gonder"}
            </Button>
          </div>
        ) : null}
        {remediationMessage ? <p className="mt-3 text-sm text-[var(--success)]">{remediationMessage}</p> : null}
        {actionError ? <p className="mt-3 text-sm text-[var(--danger)]">{actionError}</p> : null}
      </Card>

      <Card>
        <h4 className="text-sm font-bold uppercase tracking-wide">Draft Oge Detaylari</h4>
        <div className="mt-3 space-y-2">
          {draft.items.map((item) => (
            <div key={item.id} className="rounded-md border border-[var(--border)] p-3">
              <p className="font-semibold">{item.title ?? item.item_type}</p>
              <pre className="mt-2 overflow-x-auto rounded bg-[var(--surface-2)] p-2 text-xs">
                {JSON.stringify(item.content, null, 2)}
              </pre>
            </div>
          ))}
        </div>
      </Card>
    </div>
  );
}

function focusPublishStateLabel(code: string): string {
  return (
    {
      manual_check_required: "Manuel Kontrol Gerekli",
      manual_check_completed: "Manuel Kontrol Tamamlandi",
      cleanup_failed: "Cleanup Basarisiz",
      cleanup_successful: "Cleanup Basarili",
      partial_publish: "Partial Publish",
      publish_failed: "Publish Hatasi",
      published: "Published",
    }[code] ?? code
  );
}

function focusRecommendedActionLabel(code: string): string {
  return (
    {
      manual_meta_check: "Manuel Meta Kontrolu",
      retry_publish_after_manual_check: "Kontrol Sonrasi Tekrar Publish",
      fix_and_retry_publish: "Duzelt ve Tekrar Publish",
      review_publish_error: "Publish Hatasini Incele",
    }[code] ?? code
  );
}

function focusClusterKeyLabel(code: string): string {
  return (
    {
      "manual-check-required": "Manuel Kontrol Kumesi",
      "retry-ready": "Retry-Hazir Kumesi",
      "cleanup-recovered": "Cleanup-Recovered Kumesi",
      "review-error": "Hata Inceleme Kumesi",
    }[code] ?? code
  );
}

function focusDecisionStatusLabel(code: string): string {
  return (
    {
      draft_detail_preferred: "Draft Detail Lideri",
      effectiveness_preferred: "Effectiveness Destekli",
      analytics_preferred: "Analytics Destekli",
      long_term_preferred: "Uzun Donem Stabil",
      manual_attention: "Manuel Dikkat",
      rule_based: "Kural Tabanli",
      manual_check_required: "Manuel Kontrol Gerekli",
      manual_check_completed: "Manuel Kontrol Tamamlandi",
      cleanup_failed: "Cleanup Basarisiz",
      cleanup_successful: "Cleanup Basarili",
      partial_publish: "Partial Publish",
      publish_failed: "Publish Hatasi",
      published: "Published",
      safe: "Guvenli Retry",
      guarded: "Guarded Retry",
      manual: "Manuel Kontrol",
    }[code] ?? code
  );
}

function focusRetryGuidanceStatusLabel(code: string | null): string {
  return (
    {
      safe: "Guvenli Retry",
      guarded: "Guarded Retry",
      blocked: "Toplu Retry Uygun Degil",
      unknown: "Retry Durumu Bilinmiyor",
    }[normalizeRetryGuidanceStatus(code)] ?? "Retry Durumu Bilinmiyor"
  );
}

function focusRetryGuidanceStatusVariant(
  code: string | null,
): "success" | "warning" | "danger" | "neutral" {
  switch (normalizeRetryGuidanceStatus(code)) {
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

function buildFocusGuidance(
  focusPublishState: string | null,
  focusRecommendedAction: string | null,
  focusSource: string | null,
  analyticsWindowDays: number | null,
  focusDecisionStatus: string | null,
  focusDecisionReason: string | null,
  focusRetryGuidanceStatus: string | null,
  focusRetryGuidanceLabel: string | null,
  focusRetryGuidanceReason: string | null,
  focusEffectivenessScore: number | null,
  focusLongTermWindowDays: number | null,
  focusLongTermPublishSuccessRate: number | null,
  focusLongTermBaselineSuccessRate: number | null,
  focusLongTermEffectivenessScore: number | null,
  focusLongTermEffectivenessStatus: string | null,
  focusSourceComparisonLabel: string | null,
  focusSourceComparisonReason: string | null,
  focusSourceComparisonWinner: string | null,
  focusRouteTrendLabel: string | null,
  focusRouteTrendReason: string | null,
  focusRoutePreferredFlow: string | null,
  focusRouteTrendConfidence: string | null,
): string {
  const windowPrefix = analyticsWindowDays
    ? `Bu odak ${analyticsWindowDays} gunluk approvals analytics penceresinden geldi. `
    : "";

  if (focusRouteTrendLabel || focusRouteTrendReason || focusRoutePreferredFlow) {
    return `${windowPrefix}${buildRouteTrendOperatorHint(
      focusRouteTrendLabel,
      focusRouteTrendReason,
      focusRoutePreferredFlow,
      focusRouteTrendConfidence,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
    )}`;
  }

  if (focusDecisionStatus === "long_term_preferred" || focusSourceComparisonLabel || focusLongTermWindowDays != null) {
    const longTermPrefix = focusLongTermWindowDays != null
      ? `Bu odak ${focusLongTermWindowDays} gunluk uzun donem pencereden geldi. `
      : "";
    const comparisonSuffix = focusSourceComparisonLabel
      ? ` Kaynak karsilastirmasi: ${focusSourceComparisonLabel}${focusSourceComparisonReason ? ` - ${focusSourceComparisonReason}` : ""}${focusSourceComparisonWinner ? ` (kazanan: ${focusSourceComparisonWinner})` : ""}.`
      : "";
    const successSuffix = focusLongTermPublishSuccessRate != null
      ? ` Uzun donem publish basarisi %${focusLongTermPublishSuccessRate}.`
      : "";
    const baselineSuffix = focusLongTermBaselineSuccessRate != null
      ? ` Mevcut pencere referansi %${focusLongTermBaselineSuccessRate}.`
      : "";
    const effectivenessSuffix = focusLongTermEffectivenessScore != null
      ? ` Uzun donem effectiveness ${focusLongTermEffectivenessScore}${focusLongTermEffectivenessStatus ? ` (${focusLongTermEffectivenessStatusLabel(focusLongTermEffectivenessStatus)})` : ""}.`
      : "";

    return `${windowPrefix}${longTermPrefix}Uzun donem stabilite ve kaynak dagilimini birlikte inceleyin.${comparisonSuffix}${successSuffix}${baselineSuffix}${effectivenessSuffix}`;
  }

  if (focusDecisionStatus === "draft_detail_preferred") {
    return `${windowPrefix}Bu odak draft detail outcome'una gore secildi. Ustteki decision, guidance ve effectiveness badge'lerini birlikte inceleyin.`;
  }

  if (focusDecisionStatus === "effectiveness_preferred") {
    return `${windowPrefix}Bu odak effectiveness skoruyla one cikti. Publish sonucunu hizli toplama ihtimali daha yuksek olan akisa odaklanin.`;
  }

  if (focusDecisionStatus === "analytics_preferred") {
    return `${windowPrefix}Bu odak analytics sinyalleriyle one cikti. Kaynak dagilimini ve cluster bazli sonucu birlikte dogrulayin.`;
  }

  if (focusRecommendedAction === "retry_publish_after_manual_check") {
    return `${windowPrefix}Approvals merkezinden tekrar publish'e hazir kayit olarak geldiniz. Kontrol notunu ve cleanup sonucunu dogrulayip publish aksiyonunu buradan tekrar deneyin.`;
  }

  if (focusRecommendedAction === "manual_meta_check") {
    return `${windowPrefix}Approvals merkezinden manuel kontrol bekleyen kayit olarak geldiniz. Meta tarafini kontrol etmeden tekrar publish denemeyin.`;
  }

  if (focusRecommendedAction === "fix_and_retry_publish") {
    return `${windowPrefix}Cleanup tamamlanmis. Draft girdilerini duzeltip publish islemini guvenli sekilde tekrar deneyebilirsiniz.`;
  }

  if (focusPublishState === "cleanup_failed") {
    return `${windowPrefix}Bu draft cleanup basarisiz remediation odagiyla acildi. Cleanup mesajini ve Meta referanslarini once burada inceleyin.`;
  }

  if (focusPublishState === "manual_check_completed") {
    return `${windowPrefix}Bu draft manuel kontrol tamamlandi odagiyla acildi. Publish tekrar denemesi icin gereken baglam burada sabitlendi.`;
  }

  if (normalizeRetryGuidanceStatus(focusRetryGuidanceStatus) === "blocked") {
    return `${windowPrefix}Retry guidance manuel kontrol istiyor. ${focusRetryGuidanceLabel ?? "Oncelikli inceleme"} kararini uygulamadan once guidance notunu ve publish riskini kontrol edin.${focusRetryGuidanceReason ? ` ${focusRetryGuidanceReason}` : ""}`;
  }

  if (focusRetryGuidanceStatus === "guarded") {
    return `${windowPrefix}Retry guidance guarded olarak geldi. Bu aksiyon otomatik tekrar publish icin yeterli guvenlik sinyali vermiyor.${focusRetryGuidanceReason ? ` ${focusRetryGuidanceReason}` : ""}`;
  }

  if (focusRetryGuidanceStatus === "safe") {
    return `${windowPrefix}Retry guidance guvenli durumda. Tekrar publish adimini uygulamadan once etkinlik ve kaynak baglamini hizlica dogrulayin.${focusRetryGuidanceReason ? ` ${focusRetryGuidanceReason}` : ""}`;
  }

  if (focusSource === "approvals_featured") {
    return `${windowPrefix}Bu draft featured remediation kararindan acildi. Onerilen remediation akisini uygulamadan once publish durumunu bu blokta dogrulayin.`;
  }

  if (focusSource === "approvals_cluster") {
    return `${windowPrefix}Bu draft approvals cluster odagindan acildi. Ayni remediation kumesindeki diger kayitlarla tutarli karar vermek icin bu publish blokunu once inceleyin.`;
  }

  if (focusEffectivenessScore != null) {
    return `${windowPrefix}Bu draft effectiveness skoru ${focusEffectivenessScore} ile acildi. Decision ve guidance badge'leri bu skora bagli odagi gosterir.`;
  }

  if (focusDecisionReason) {
    return `${windowPrefix}${focusDecisionReason}`;
  }

  return `${windowPrefix}Bu draft approvals merkezinden remediation odagiyla acildi. Publish durumunu ve onerilen aksiyonu once bu blokta dogrulayin.`;
}

function resolveAnalyticsWindow(rawValue: string | null): 7 | 30 | 90 | null {
  const parsedValue = Number(rawValue);

  if (parsedValue === 7 || parsedValue === 30 || parsedValue === 90) {
    return parsedValue;
  }

  return null;
}

function resolveOptionalNumber(rawValue: string | null): number | null {
  if (rawValue === null) {
    return null;
  }

  const parsedValue = Number(rawValue);

  return Number.isFinite(parsedValue) ? parsedValue : null;
}

function resolveOptionalWindowDays(rawValue: string | null): 7 | 30 | 90 | null {
  return resolveAnalyticsWindow(rawValue);
}

function readFirstSearchParam(searchParams: SearchParamsLike, keys: string[]): string | null {
  for (const key of keys) {
    const value = searchParams.get(key);
    if (value != null && value.trim() !== "") {
      return value;
    }
  }

  return null;
}

function focusLongTermEffectivenessStatusLabel(code: string): string {
  return (
    {
      proven: "Kanitlandi",
      mixed: "Karisik Sonuc",
      weak: "Zayif",
      insufficient_data: "Veri Yetersiz",
      idle: "Pasif",
    }[code] ?? code
  );
}

function focusLongTermEffectivenessStatusVariant(
  code: string,
): "success" | "warning" | "danger" | "neutral" {
  const variants: Record<string, "success" | "warning" | "danger" | "neutral"> = {
    proven: "success",
    mixed: "warning",
    weak: "danger",
    insufficient_data: "neutral",
    idle: "neutral",
  };

  return variants[code] ?? "neutral";
}

function focusPrimaryActionModeLabel(code: string): string {
  return (
    {
      jump_to_item: "Detay Rotasi",
      bulk_retry_publish: "Bulk Retry Rotasi",
      focus_cluster: "Cluster Inceleme Rotasi",
    }[code] ?? code
  );
}

function focusPrimaryActionConfidenceStatusLabel(code: string | null): string {
  return (
    {
      proven: "Kanitli",
      emerging: "Yukselen",
      guarded: "Temkinli",
    }[code ?? ""] ?? "Temkinli"
  );
}

function focusPrimaryActionConfidenceVariant(
  code: string | null,
): "success" | "warning" | "danger" | "neutral" {
  switch (code) {
    case "proven":
      return "success";
    case "emerging":
      return "warning";
    case "guarded":
      return "neutral";
    default:
      return "neutral";
  }
}

function buildPrimaryActionOperatorHint(
  mode: string | null,
  routeLabel: string | null,
  sourceLabel: string | null,
  reason: string | null,
  successRate: number | null,
  trackedInteractions: number | null,
  confidenceLabel: string | null,
  advantage: number | null,
  alternativeRouteLabel: string | null,
  alternativeSuccessRate: number | null,
): string {
  const parts: string[] = [];

  if (routeLabel) {
    parts.push(`Secilen rota: ${routeLabel}.`);
  }

  if (sourceLabel) {
    parts.push(`Kazanan kaynak: ${sourceLabel}.`);
  }

  if (confidenceLabel) {
    parts.push(`Rota guveni ${confidenceLabel.toLowerCase()}.`);
  }

  if (successRate != null) {
    parts.push(`Bu rota %${successRate} publish basarisi uretmis.`);
  }

  if (trackedInteractions != null) {
    parts.push(`${trackedInteractions} izlenen etkilesim bu karari destekliyor.`);
  }

  if (advantage != null && alternativeRouteLabel) {
    parts.push(
      `${alternativeRouteLabel} rotasina gore ${advantage >= 0 ? "+" : ""}${advantage} puan fark goruluyor.`,
    );
  }

  if (alternativeSuccessRate != null && !alternativeRouteLabel) {
    parts.push(`Alternatif rota basarisi %${alternativeSuccessRate}.`);
  }

  if (reason) {
    parts.push(reason);
  } else if (mode === "jump_to_item") {
    parts.push("Bu nedenle operatoru dogrudan bu detay ekranina indiren rota secildi.");
  } else if (mode === "bulk_retry_publish") {
    parts.push("Bu nedenle approvals merkezindeki bulk retry akisi referans alinmali.");
  } else {
    parts.push("Bu nedenle once cluster inceleme odagi korunuyor.");
  }

  return parts.join(" ");
}

function focusSourceComparisonVariant(
  label: string | null,
  winner: string | null,
): "success" | "warning" | "danger" | "neutral" {
  const normalized = `${label ?? ""} ${winner ?? ""}`.trim().toLowerCase();

  if (normalized.includes("draft detail daha guclu") || normalized.includes("draft detail")) {
    return "success";
  }

  if (normalized.includes("approvals-native daha guclu") || normalized.includes("approvals-native")) {
    return "warning";
  }

  return "neutral";
}

function focusRoutePreferredFlowLabel(flow: string | null): string {
  return (
    {
      draft_detail: "Draft Detail Akisi",
      approvals_native: "Approvals Akisi",
      balanced: "Dengeli Akis",
    }[flow ?? ""] ?? "Route Trendi"
  );
}

function focusRouteTrendVariant(
  flow: string | null,
): "success" | "warning" | "danger" | "neutral" {
  if (flow === "draft_detail") {
    return "success";
  }

  if (flow === "approvals_native") {
    return "warning";
  }

  return "neutral";
}

function focusRouteTrendConfidenceLabel(confidence: string | null): string {
  return (
    {
      high: "Yuksek Guven",
      medium: "Orta Guven",
      low: "Dusuk Guven",
    }[confidence ?? ""] ?? "Guven Bilgisi Yok"
  );
}

function focusRouteTrendConfidenceVariant(
  confidence: string | null,
): "success" | "warning" | "danger" | "neutral" {
  if (confidence === "high") {
    return "success";
  }

  if (confidence === "medium") {
    return "warning";
  }

  if (confidence === "low") {
    return "neutral";
  }

  return "neutral";
}

function buildRouteTrendOperatorHint(
  label: string | null,
  reason: string | null,
  preferredFlow: string | null,
  confidence: string | null,
  currentLabel: string | null,
  currentAttempts: number | null,
  currentSuccessRate: number | null,
  currentAdvantage: number | null,
  longTermLabel: string | null,
  longTermAttempts: number | null,
  longTermSuccessRate: number | null,
  longTermAdvantage: number | null,
): string {
  const parts: string[] = [];

  if (label) {
    parts.push(`Route ozeti: ${label}.`);
  }

  if (reason) {
    parts.push(reason);
  }

  if (preferredFlow === "draft_detail") {
    parts.push("Bu nedenle operatorun detay ekranda kalip remediation aksiyonunu burada tamamlamasi daha dogru.");
  } else if (preferredFlow === "approvals_native") {
    parts.push("Bu nedenle approvals merkezindeki cluster akisini referans alip toplu resmi oradan yonetmek daha guvenli.");
  }

  if (confidence === "high") {
    parts.push("Route karari yuksek guven sinyali tasiyor.");
  } else if (confidence === "medium") {
    parts.push("Route karari orta guven sinyali tasiyor; publish guidance ile birlikte okunmali.");
  }

  if (currentLabel || currentAttempts != null || currentSuccessRate != null || currentAdvantage != null) {
    parts.push(
      `Mevcut pencere: ${currentLabel ?? "veri yok"}`
      + `${currentAttempts != null ? `, ${currentAttempts} deneme` : ""}`
      + `${currentSuccessRate != null ? `, %${currentSuccessRate} basari` : ""}`
      + `${currentAdvantage != null ? `, ${currentAdvantage >= 0 ? "+" : ""}${currentAdvantage} puan fark` : ""}.`,
    );
  }

  if (longTermLabel || longTermAttempts != null || longTermSuccessRate != null || longTermAdvantage != null) {
    parts.push(
      `Uzun donem: ${longTermLabel ?? "veri yok"}`
      + `${longTermAttempts != null ? `, ${longTermAttempts} deneme` : ""}`
      + `${longTermSuccessRate != null ? `, %${longTermSuccessRate} basari` : ""}`
      + `${longTermAdvantage != null ? `, ${longTermAdvantage >= 0 ? "+" : ""}${longTermAdvantage} puan fark` : ""}.`,
    );
  }

  return parts.join(" ");
}

function buildSourceComparisonOperatorHint(
  label: string | null,
  reason: string | null,
  winner: string | null,
  longTermWindowDays: number | null,
  longTermPublishSuccessRate: number | null,
  longTermBaselineSuccessRate: number | null,
): string {
  const parts: string[] = [];

  if (label) {
    parts.push(`Bu remediation icin one cikan kaynak: ${label}.`);
  }

  if (reason) {
    parts.push(reason);
  }

  if (longTermWindowDays != null) {
    parts.push(`${longTermWindowDays} gunluk pencere bu karari destekliyor.`);
  }

  if (longTermPublishSuccessRate != null) {
    parts.push(`Uzun donem publish basarisi %${longTermPublishSuccessRate}.`);
  }

  if (longTermBaselineSuccessRate != null) {
    parts.push(`Mevcut pencere referansi %${longTermBaselineSuccessRate}.`);
  }

  if ((winner ?? label ?? "").toLowerCase().includes("draft detail")) {
    parts.push("Bu nedenle bu detay ekraninda onerilen aksiyonu calistirmak daha dogru yol.");
  } else if ((winner ?? label ?? "").toLowerCase().includes("approvals-native")) {
    parts.push("Bu nedenle approvals merkezindeki cluster akisini da referans alip toplu resmi oradan yonetmek daha guvenli.");
  } else {
    parts.push("Kaynaklar birbirine yakin; remediation kararini publish durumu ve guidance ile birlikte okuyun.");
  }

  return parts.join(" ");
}

function normalizeRetryGuidanceStatus(rawValue: string | null | undefined): "safe" | "guarded" | "blocked" | "unknown" {
  const normalized = (rawValue ?? "").trim().toLowerCase();

  if (normalized === "safe" || normalized.includes("safe")) {
    return "safe";
  }

  if (normalized === "guarded" || normalized.includes("guard")) {
    return "guarded";
  }

  if (normalized === "blocked" || normalized.includes("block") || normalized === "manual" || normalized.includes("manual")) {
    return "blocked";
  }

  return "unknown";
}

function derivePublishFocusState(publishState: DraftPublishState): string | null {
  if (!publishState) {
    return null;
  }

  if (publishState.manual_check_required) {
    return "manual_check_required";
  }

  if (publishState.manual_check_completed) {
    return "manual_check_completed";
  }

  if (publishState.cleanup_attempted && publishState.cleanup_success === false) {
    return "cleanup_failed";
  }

  if (publishState.cleanup_attempted && publishState.cleanup_success === true) {
    return "cleanup_successful";
  }

  if (publishState.partial_publish_detected) {
    return "partial_publish";
  }

  if (publishState.success === false) {
    return "publish_failed";
  }

  if (publishState.success === true) {
    return "published";
  }

  return null;
}

function resolveRemediationPrimaryAction(
  focusRecommendedAction: string | null,
  publishState: DraftPublishState,
): { label: string; mode: "manual_check_completed" | "retry_publish"; hint: string } | null {
  if (!publishState) {
    return null;
  }

  if (focusRecommendedAction === "manual_meta_check" || publishState.manual_check_required) {
    return {
      label: "Manuel Kontrol Tamamlandi",
      mode: "manual_check_completed",
      hint: "Bu aksiyon approvals kuyrugundaki manuel kontrol durumunu kapatir.",
    };
  }

  if (
    focusRecommendedAction === "retry_publish_after_manual_check"
    || focusRecommendedAction === "fix_and_retry_publish"
    || publishState.manual_check_completed
  ) {
    return {
      label: "Tekrar Publish Dene",
      mode: "retry_publish",
      hint: "Bu aksiyon approvals kuyrugundaki retry hazir akisi baslatir.",
    };
  }

  return null;
}

function deriveApprovalClusterKey(recommendedActionCode: string | null): string | null {
  return (
    {
      manual_meta_check: "manual-check-required",
      retry_publish_after_manual_check: "retry-ready",
      fix_and_retry_publish: "cleanup-recovered",
      review_publish_error: "review-error",
    }[recommendedActionCode ?? ""] ?? null
  );
}

function resolveRemediationInteractionSource(focusSource: string | null): string {
  return (
    {
      approvals_featured: "draft_detail_from_approvals_featured",
      approvals_featured_long_term: "draft_detail_from_approvals_featured",
      approvals_cluster: "draft_detail_from_approvals_cluster",
      approvals_cluster_long_term: "draft_detail_from_approvals_cluster",
      approvals_retry_ready: "draft_detail_from_approvals_retry_ready",
      approvals_item: "draft_detail_from_approvals_item",
    }[focusSource ?? ""] ?? "draft_detail"
  );
}

function buildApprovalsReturnRoute(
  focusRecommendedAction: string | null,
  focusPublishState: string | null,
  analyticsWindowDays: 7 | 30 | 90 | null,
): string | null {
  const params = new URLSearchParams();
  params.set("status", "publish_failed");

  if (focusRecommendedAction) {
    params.set("recommended_action_code", focusRecommendedAction);
  }

  if (focusRecommendedAction === "manual_meta_check" || focusPublishState === "manual_check_required") {
    params.set("cleanup_state", "failed");
    params.set("manual_check_state", "required");
  }

  if (focusRecommendedAction === "retry_publish_after_manual_check" || focusPublishState === "manual_check_completed") {
    params.set("manual_check_state", "completed");
  }

  if (focusRecommendedAction === "fix_and_retry_publish" || focusPublishState === "cleanup_successful") {
    params.set("cleanup_state", "successful");
    params.set("manual_check_state", "not_required");
  }

  if (focusRecommendedAction === "review_publish_error") {
    params.set("manual_check_state", "not_required");
  }

  if (analyticsWindowDays && analyticsWindowDays !== 30) {
    params.set("window_days", String(analyticsWindowDays));
  }

  const query = params.toString();

  return query ? `/approvals?${query}` : "/approvals";
}


