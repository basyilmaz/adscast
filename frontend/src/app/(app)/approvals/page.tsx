"use client";

import { useState } from "react";
import Link from "next/link";
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

export default function ApprovalsPage() {
  const [actionError, setActionError] = useState<string | null>(null);
  const approvalQuery = useApiQuery<ApprovalResponse, Approval[]>("/approvals", {
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.approvals,
    select: (response) => response.data.data ?? [],
  });
  const items = approvalQuery.data ?? [];
  const { error: queryError, isLoading, reload } = approvalQuery;

  const statusVariant = (status: string) =>
    status === "approved"
      ? "success"
      : status === "rejected" || status === "publish_failed"
        ? "danger"
        : "warning";

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

      invalidateApiCache("/approvals", { requireWorkspace: true });
      invalidateApiCache("/drafts", { requireWorkspace: true });
      if (item.approvable_type_label === "CampaignDraft") {
        invalidateApiCache(`/drafts/${item.approvable_id}`, { requireWorkspace: true });
      }
      await reload();
    } catch (err) {
      setActionError(err instanceof Error ? err.message : "Approval aksiyonu basarisiz.");
    }
  };

  return (
    <Card>
      {queryError || actionError ? <p className="mb-3 text-sm text-[var(--danger)]">{actionError ?? queryError}</p> : null}
      {isLoading && items.length === 0 ? <p className="mb-3 text-sm muted-text">Onay kayitlari yukleniyor.</p> : null}
      <div className="space-y-2">
        {items.map((item) => (
          <div key={item.id} className="rounded-md border border-[var(--border)] p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <p className="font-semibold">{item.approvable_label}</p>
                <p className="text-xs muted-text">{item.approvable_type_label} · {item.approvable_id}</p>
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
                        : item.publish_state.partial_publish_detected
                          ? "Partial Publish Tespit Edildi"
                          : item.publish_state.success
                            ? "Publish Basarili"
                            : "Publish Hatasi"
                    }
                    variant={
                      item.publish_state.manual_check_required
                        ? "danger"
                        : item.publish_state.success
                          ? "success"
                          : "warning"
                    }
                  />
                  {item.publish_state.recommended_action_label ? (
                    <Badge label={item.publish_state.recommended_action_label} variant="neutral" />
                  ) : null}
                </div>
                {item.publish_state.message ? <p className="mt-2 text-sm">{item.publish_state.message}</p> : null}
                {item.publish_state.operator_guidance ? <p className="mt-2 text-sm muted-text">{item.publish_state.operator_guidance}</p> : null}
                <div className="mt-2 flex flex-wrap gap-3 text-xs muted-text">
                  {item.publish_state.meta_campaign_id ? <span>Campaign: <strong>{item.publish_state.meta_campaign_id}</strong></span> : null}
                  {item.publish_state.meta_ad_set_id ? <span>Ad Set: <strong>{item.publish_state.meta_ad_set_id}</strong></span> : null}
                  {item.publish_state.cleanup_attempted ? (
                    <span>Cleanup: <strong>{item.publish_state.cleanup_success ? "Basarili" : "Basarisiz"}</strong></span>
                  ) : null}
                </div>
                {item.publish_state.cleanup_message ? <p className="mt-2 text-xs text-[var(--danger)]">{item.publish_state.cleanup_message}</p> : null}
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
              <Button variant="secondary" size="sm" onClick={() => callAction(item, "approve")}>
                Onayla
              </Button>
              <Button variant="outline" size="sm" onClick={() => callAction(item, "reject")}>
                Reddet
              </Button>
              <Button size="sm" onClick={() => callAction(item, "publish")}>
                {item.publish_state?.manual_check_required ? "Kontrol Sonrasi Tekrar Dene" : "Publish Dene"}
              </Button>
            </div>
          </div>
        ))}
      </div>

      {!isLoading && items.length === 0 ? <p className="text-sm muted-text">Onay kaydi bulunmuyor.</p> : null}
    </Card>
  );
}
