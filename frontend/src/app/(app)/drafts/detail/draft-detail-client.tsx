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

export function DraftDetailClient() {
  const searchParams = useSearchParams();
  const draftId = searchParams.get("id");
  const focusPublishState = searchParams.get("focus_publish_state");
  const focusRecommendedAction = searchParams.get("focus_recommended_action");
  const focusClusterKey = searchParams.get("focus_cluster_key");
  const focusSource = searchParams.get("focus_source");
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
                  {analyticsWindowDays ? (
                    <Badge label={`${analyticsWindowDays} gun analytics`} variant="neutral" />
                  ) : null}
                </div>
                <p className="mt-2 text-sm muted-text">
                  {buildFocusGuidance(focusPublishState, focusRecommendedAction, focusSource, analyticsWindowDays)}
                </p>
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
            {remediationPrimaryAction ? (
              <div className="mt-3 rounded-md border border-[var(--accent)]/30 bg-white p-3">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge label="Onerilen Aksiyon" variant="success" />
                  {focusSource === "approvals_featured" ? (
                    <Badge label="Featured Karardan Geldi" variant="neutral" />
                  ) : null}
                  {focusSource === "approvals_cluster" ? (
                    <Badge label="Cluster Odagi" variant="neutral" />
                  ) : null}
                </div>
                <p className="mt-2 text-sm font-semibold">{remediationPrimaryAction.label}</p>
                {remediationPrimaryAction.hint ? (
                  <p className="mt-1 text-xs muted-text">{remediationPrimaryAction.hint}</p>
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

function buildFocusGuidance(
  focusPublishState: string | null,
  focusRecommendedAction: string | null,
  focusSource: string | null,
  analyticsWindowDays: number | null,
): string {
  const windowPrefix = analyticsWindowDays
    ? `Bu odak ${analyticsWindowDays} gunluk approvals analytics penceresinden geldi. `
    : "";

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

  if (focusSource === "approvals_featured") {
    return `${windowPrefix}Bu draft featured remediation kararindan acildi. Onerilen remediation akisini uygulamadan once publish durumunu bu blokta dogrulayin.`;
  }

  if (focusSource === "approvals_cluster") {
    return `${windowPrefix}Bu draft approvals cluster odagindan acildi. Ayni remediation kumesindeki diger kayitlarla tutarli karar vermek icin bu publish blokunu once inceleyin.`;
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
      approvals_cluster: "draft_detail_from_approvals_cluster",
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
