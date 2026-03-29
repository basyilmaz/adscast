"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
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

export default function ApprovalsPage() {
  const [statusFilter, setStatusFilter] =
    useState<(typeof STATUS_FILTER_OPTIONS)[number]["value"]>("all");
  const [cleanupFilter, setCleanupFilter] =
    useState<(typeof CLEANUP_FILTER_OPTIONS)[number]["value"]>("all");
  const [manualCheckFilter, setManualCheckFilter] =
    useState<(typeof MANUAL_CHECK_FILTER_OPTIONS)[number]["value"]>("all");
  const [actionError, setActionError] = useState<string | null>(null);

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

    const queryString = params.toString();
    return queryString ? `/approvals?${queryString}` : "/approvals";
  }, [cleanupFilter, manualCheckFilter, statusFilter]);

  const approvalQuery = useApiQuery<ApprovalResponse, Approval[]>(approvalsPath, {
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.approvals,
    select: (response) => response.data.data ?? [],
  });
  const items = useMemo(() => approvalQuery.data ?? [], [approvalQuery.data]);
  const { error: queryError, isLoading, reload } = approvalQuery;

  const summary = useMemo(() => {
    return {
      total: items.length,
      cleanupFailed: items.filter((item) => item.publish_state?.cleanup_success === false).length,
      manualCheckRequired: items.filter((item) => item.publish_state?.manual_check_required).length,
      manualCheckCompleted: items.filter((item) => item.publish_state?.manual_check_completed).length,
    };
  }, [items]);

  const statusVariant = (status: string) =>
    status === "approved" || status === "published"
      ? "success"
      : status === "rejected" || status === "publish_failed"
        ? "danger"
        : "warning";

  const invalidateApprovalCaches = (item: Approval) => {
    invalidateApiCache(approvalsPath, { requireWorkspace: true });
    invalidateApiCache("/approvals", { requireWorkspace: true });
    invalidateApiCache("/drafts", { requireWorkspace: true });

    if (item.approvable_type_label === "CampaignDraft") {
      invalidateApiCache(`/drafts/${item.approvable_id}`, { requireWorkspace: true });
    }
  };

  const callAction = async (item: Approval, action: "approve" | "reject" | "publish") => {
    try {
      setActionError(null);
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

      invalidateApprovalCaches(item);
      await reload();
    } catch (err) {
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
      await apiRequest(`/approvals/${item.id}/manual-check-completed`, {
        method: "POST",
        requireWorkspace: true,
        body: {
          note: note.trim() || undefined,
        },
      });

      invalidateApprovalCaches(item);
      await reload();
    } catch (err) {
      setActionError(err instanceof Error ? err.message : "Manuel kontrol guncellenemedi.");
    }
  };

  return (
    <Card>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold">Approval Operasyonlari</h2>
          <p className="mt-1 text-sm muted-text">
            Publish hatalarini cleanup ve manuel kontrol durumuna gore filtreleyip ayni kuyruktan yonetin.
          </p>
        </div>
        <div className="flex flex-wrap gap-2 text-xs">
          <Badge label={`${summary.total} kayit`} variant="neutral" />
          <Badge label={`${summary.cleanupFailed} cleanup hatasi`} variant="danger" />
          <Badge label={`${summary.manualCheckRequired} manuel kontrol bekliyor`} variant="warning" />
          <Badge label={`${summary.manualCheckCompleted} kontrol tamamlandi`} variant="success" />
        </div>
      </div>

      <div className="mt-4 grid gap-3 md:grid-cols-3">
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
      </div>

      {queryError || actionError ? <p className="mt-4 text-sm text-[var(--danger)]">{actionError ?? queryError}</p> : null}
      {isLoading && items.length === 0 ? <p className="mt-4 text-sm muted-text">Onay kayitlari yukleniyor.</p> : null}

      <div className="mt-4 space-y-3">
        {items.map((item) => (
          <div key={item.id} className="rounded-md border border-[var(--border)] p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <p className="font-semibold">{item.approvable_label}</p>
                <p className="text-xs muted-text">{item.approvable_type_label} - {item.approvable_id}</p>
              </div>
              <Badge
                label={item.status}
                variant={statusVariant(item.status)}
              />
            </div>
            {item.publish_state ? (
              <div className={`mt-3 rounded-md border p-3 ${item.publish_state.manual_check_required ? "border-[var(--danger)] bg-[var(--surface-2)]" : "border-[var(--border)] bg-[var(--surface-2)]"}`}>
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
                {item.publish_state.operator_guidance ? <p className="mt-2 text-sm muted-text">{item.publish_state.operator_guidance}</p> : null}
                <div className="mt-2 flex flex-wrap gap-3 text-xs muted-text">
                  {item.publish_state.meta_campaign_id ? <span>Campaign: <strong>{item.publish_state.meta_campaign_id}</strong></span> : null}
                  {item.publish_state.meta_ad_set_id ? <span>Ad Set: <strong>{item.publish_state.meta_ad_set_id}</strong></span> : null}
                  {item.publish_state.manual_check_completed_at ? (
                    <span>Kontrol: <strong>{new Date(item.publish_state.manual_check_completed_at).toLocaleString("tr-TR")}</strong></span>
                  ) : null}
                </div>
                {item.publish_state.cleanup_message ? <p className="mt-2 text-xs text-[var(--danger)]">{item.publish_state.cleanup_message}</p> : null}
                {item.publish_state.manual_check_note ? (
                  <p className="mt-2 text-xs muted-text">Kontrol notu: {item.publish_state.manual_check_note}</p>
                ) : null}
              </div>
            ) : null}
            {item.rejection_reason ? <p className="mt-3 text-sm text-[var(--danger)]">Red nedeni: {item.rejection_reason}</p> : null}
            <div className="mt-3 flex flex-wrap gap-2">
              {item.approvable_route ? (
                <Link href={item.approvable_route}>
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

      {!isLoading && items.length === 0 ? <p className="mt-4 text-sm muted-text">Filtreye uygun onay kaydi bulunmuyor.</p> : null}
    </Card>
  );
}
