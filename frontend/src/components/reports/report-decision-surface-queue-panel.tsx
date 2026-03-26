"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardTitle } from "@/components/ui/card";
import { apiRequest } from "@/lib/api";
import { ReportDecisionSurfaceQueueItem, ReportDecisionSurfaceQueueSummary } from "@/lib/types";

type Props = {
  summary: ReportDecisionSurfaceQueueSummary | null;
  items: ReportDecisionSurfaceQueueItem[];
  routeBuilder: (route: string) => string;
  onChanged?: () => Promise<void> | void;
};

const STATUS_FILTER_OPTIONS = [
  { value: "all", label: "Tum Durumlar" },
  { value: "open", label: "Acik Kararlar" },
  { value: "pending", label: "Beklemede" },
  { value: "reviewed", label: "Gozden Gecirildi" },
  { value: "deferred", label: "Ertelendi" },
  { value: "completed", label: "Tamamlandi" },
] as const;

const STATUS_UPDATE_OPTIONS = [
  { value: "pending", label: "Beklemede" },
  { value: "reviewed", label: "Gozden Gecirildi" },
  { value: "completed", label: "Tamamlandi" },
  { value: "deferred", label: "Ertelendi" },
] as const;

const DEFER_REASON_OPTIONS = [
  { value: "", label: "Erteleme nedeni secin" },
  { value: "waiting_client_feedback", label: "Musteri Donusu Bekleniyor" },
  { value: "waiting_data_validation", label: "Veri Dogrulamasi Bekleniyor" },
  { value: "scheduled_followup", label: "Planli Takip Bekleniyor" },
  { value: "blocked_external_dependency", label: "Dis Bagimlilik Engeli" },
  { value: "priority_window_shifted", label: "Oncelik Penceresi Degisti" },
] as const;

const ENTITY_FILTER_OPTIONS = [
  { value: "all", label: "Tum Entity" },
  { value: "account", label: "Reklam Hesabi" },
  { value: "campaign", label: "Kampanya" },
] as const;

const SURFACE_FILTER_OPTIONS = [
  { value: "all", label: "Tum Yuzeyler" },
  { value: "featured_fix", label: "Hizli Duzeltme" },
  { value: "retry", label: "Retry Rehberi" },
  { value: "profile", label: "Profil Onerisi" },
] as const;

const REASON_FILTER_OPTIONS = [
  { value: "all", label: "Tum Blok Nedenleri" },
  { value: "none", label: "Nedeni Girilmemis" },
  ...DEFER_REASON_OPTIONS.filter((option) => option.value !== ""),
] as const;

const NOTE_FILTER_OPTIONS = [
  { value: "all", label: "Tum Not Durumlari" },
  { value: "with_note", label: "Not Girilenler" },
  { value: "without_note", label: "Not Girilmeyenler" },
] as const;

const OPEN_STATUSES = new Set(["pending", "reviewed", "deferred"]);

export function ReportDecisionSurfaceQueuePanel({ summary, items, routeBuilder, onChanged }: Props) {
  const [statusFilter, setStatusFilter] =
    useState<(typeof STATUS_FILTER_OPTIONS)[number]["value"]>("open");
  const [entityFilter, setEntityFilter] =
    useState<(typeof ENTITY_FILTER_OPTIONS)[number]["value"]>("all");
  const [surfaceFilter, setSurfaceFilter] =
    useState<(typeof SURFACE_FILTER_OPTIONS)[number]["value"]>("all");
  const [reasonFilter, setReasonFilter] =
    useState<(typeof REASON_FILTER_OPTIONS)[number]["value"]>("all");
  const [noteFilter, setNoteFilter] =
    useState<(typeof NOTE_FILTER_OPTIONS)[number]["value"]>("all");
  const [searchTerm, setSearchTerm] = useState("");
  const [selectedKeys, setSelectedKeys] = useState<string[]>([]);
  const [activeBulkStatus, setActiveBulkStatus] = useState<string | null>(null);
  const [bulkNote, setBulkNote] = useState("");
  const [bulkDeferReason, setBulkDeferReason] = useState("");
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const normalizedSearchTerm = searchTerm.trim().toLocaleLowerCase("tr");

  const filteredItems = useMemo(() => {
    return items.filter((item) => {
      if (statusFilter === "open" && !OPEN_STATUSES.has(item.status)) {
        return false;
      }

      if (statusFilter !== "all" && statusFilter !== "open" && item.status !== statusFilter) {
        return false;
      }

      if (entityFilter !== "all" && item.entity_type !== entityFilter) {
        return false;
      }

      if (surfaceFilter !== "all" && item.surface_key !== surfaceFilter) {
        return false;
      }

      if (reasonFilter !== "all") {
        if (item.status !== "deferred") {
          return false;
        }

        if (reasonFilter === "none") {
          if (item.defer_reason_code !== null) {
            return false;
          }
        } else if (item.defer_reason_code !== reasonFilter) {
          return false;
        }
      }

      if (noteFilter === "with_note" && !item.operator_note) {
        return false;
      }

      if (noteFilter === "without_note" && item.operator_note) {
        return false;
      }

      if (!normalizedSearchTerm) {
        return true;
      }

      const searchHaystack = [
        item.entity_label,
        item.context_label,
        item.surface_label,
        item.status_label,
      ]
        .filter(Boolean)
        .join(" ")
        .toLocaleLowerCase("tr");

      return searchHaystack.includes(normalizedSearchTerm);
    });
  }, [entityFilter, items, normalizedSearchTerm, noteFilter, reasonFilter, statusFilter, surfaceFilter]);

  const filteredKeySet = useMemo(
    () => new Set(filteredItems.map((item) => queueItemKey(item))),
    [filteredItems],
  );

  useEffect(() => {
    setSelectedKeys((current) => {
      const next = current.filter((key) => filteredKeySet.has(key));
      return next.length === current.length ? current : next;
    });
  }, [filteredKeySet]);

  const selectedVisibleItems = useMemo(
    () => filteredItems.filter((item) => selectedKeys.includes(queueItemKey(item))),
    [filteredItems, selectedKeys],
  );
  const allVisibleSelected =
    filteredItems.length > 0 && selectedVisibleItems.length === filteredItems.length;

  const handleToggleItem = (itemKey: string, checked: boolean) => {
    setSelectedKeys((current) => {
      if (checked) {
        return current.includes(itemKey) ? current : [...current, itemKey];
      }

      return current.filter((key) => key !== itemKey);
    });
  };

  const handleToggleVisibleSelection = () => {
    if (allVisibleSelected) {
      setSelectedKeys([]);
      return;
    }

    setSelectedKeys(filteredItems.map((item) => queueItemKey(item)));
  };

  const handleBulkStatusChange = async (nextStatus: (typeof STATUS_UPDATE_OPTIONS)[number]["value"]) => {
    const targetItems = selectedVisibleItems.filter((item) => item.status !== nextStatus);
    const normalizedBulkNote = bulkNote.trim();

    if (targetItems.length === 0) {
      setError(null);
      setMessage(
        selectedVisibleItems.length === 0
          ? "Toplu guncelleme icin once en az bir karar yuzeyi secin."
          : `Secili yuzeyler zaten ${statusLabel(nextStatus).toLocaleLowerCase("tr")} durumunda.`,
      );
      return;
    }

    if (nextStatus === "deferred" && !bulkDeferReason) {
      setMessage(null);
      setError("Toplu erteleme icin once bir erteleme nedeni secin.");
      return;
    }

    setActiveBulkStatus(nextStatus);
    setError(null);
    setMessage(null);

    const results = await Promise.allSettled(
      targetItems.map((item) => {
        const body: {
          status: string;
          operator_note?: string;
          defer_reason_code?: string;
        } = {
          status: nextStatus,
        };

        if (normalizedBulkNote !== "") {
          body.operator_note = normalizedBulkNote;
        }

        if (nextStatus === "deferred") {
          body.defer_reason_code = bulkDeferReason;
        }

        return apiRequest(`/reports/decision-surface-statuses/${item.entity_type}/${item.entity_id}/${item.surface_key}`, {
          method: "PUT",
          requireWorkspace: true,
          body,
        });
      }),
    );

    const successCount = results.filter((result) => result.status === "fulfilled").length;
    const failureResults = results.filter(
      (result): result is PromiseRejectedResult => result.status === "rejected",
    );

    try {
      if (successCount > 0) {
        await onChanged?.();
      }
    } catch (requestError) {
      setError(
        requestError instanceof Error && requestError.message.trim()
          ? requestError.message
          : "Karar kuyrugu yenilenemedi.",
      );
    } finally {
      setActiveBulkStatus(null);
      setSelectedKeys([]);
    }

    if (failureResults.length === 0) {
      setBulkNote("");
      setBulkDeferReason("");
      setMessage(
        `${successCount} karar yuzeyi ${statusLabel(nextStatus).toLocaleLowerCase("tr")} durumuna tasindi.`,
      );
      return;
    }

    if (successCount > 0) {
      setMessage(
        `${successCount} karar yuzeyi guncellendi, ${failureResults.length} karar yuzeyi hata verdi.`,
      );
      setError(errorMessageFromFailure(failureResults[0].reason));
      return;
    }

    setError(errorMessageFromFailure(failureResults[0].reason));
  };

  const filteredOpenItems = filteredItems.filter((item) => OPEN_STATUSES.has(item.status)).length;
  const filteredDeferredItems = filteredItems.filter((item) => item.status === "deferred");
  const filteredItemsWithNotes = filteredItems.filter((item) => item.operator_note).length;
  const deferredWithoutReasonCount = filteredDeferredItems.filter((item) => item.defer_reason_code === null).length;
  const deferredReasonGroups = useMemo(
    () =>
      Array.from(
        filteredDeferredItems.reduce(
          (groups, item) => {
            const groupKey = item.defer_reason_code ?? "none";
            const current = groups.get(groupKey) ?? {
              key: groupKey,
              label: item.defer_reason_label ?? "Erteleme Nedeni Girilmemis",
              count: 0,
              entities: new Set<string>(),
              notes: 0,
            };

            current.count += 1;
            current.entities.add(`${item.entity_type}:${item.entity_id}`);
            current.notes += item.operator_note ? 1 : 0;
            groups.set(groupKey, current);
            return groups;
          },
          new Map<string, { key: string; label: string; count: number; entities: Set<string>; notes: number }>(),
        ).values(),
      )
        .map((group) => ({
          key: group.key,
          label: group.label,
          count: group.count,
          entities: group.entities.size,
          notes: group.notes,
        }))
        .sort((left, right) => right.count - left.count || left.label.localeCompare(right.label, "tr")),
    [filteredDeferredItems],
  );
  const groupedItems = useMemo<Array<{
    key: string;
    label: string;
    tone: "warning" | "neutral";
    items: ReportDecisionSurfaceQueueItem[];
  }>>(() => {
    const deferredGroups: Array<{
      key: string;
      label: string;
      tone: "warning" | "neutral";
      items: ReportDecisionSurfaceQueueItem[];
    }> = deferredReasonGroups.map((group) => ({
      key: `deferred:${group.key}`,
      label: group.label,
      tone: "warning" as const,
      items: filteredItems.filter((item) =>
        item.status === "deferred"
          ? group.key === "none"
            ? item.defer_reason_code === null
            : item.defer_reason_code === group.key
          : false,
      ),
    }));

    const nonDeferredItems = filteredItems.filter((item) => item.status !== "deferred");
    if (nonDeferredItems.length > 0) {
      deferredGroups.push({
        key: "other-statuses",
        label: "Erteleme Disi Kararlar",
        tone: "neutral" as const,
        items: nonDeferredItems,
      });
    }

    return deferredGroups.filter((group) => group.items.length > 0);
  }, [deferredReasonGroups, filteredItems]);

  return (
    <Card>
      <CardTitle>Operasyon Karar Kuyrugu</CardTitle>
      <p className="mt-2 text-sm muted-text">
        Detail ekranlarinda isaretlenen featured fix, retry rehberi ve profil onerisi durumlarini workspace genelinde tek listede izleyin.
      </p>

      <div className="mt-3 flex flex-wrap gap-3 text-sm muted-text">
        <span>Takipte entity: {summary?.tracked_entities ?? 0}</span>
        <span>Acik yuzey: {summary?.open_items ?? 0}</span>
        <span>Beklemede: {summary?.pending_items ?? 0}</span>
        <span>Ertelenen: {summary?.deferred_items ?? 0}</span>
        <span>Tamamlanan: {summary?.completed_items ?? 0}</span>
      </div>

      <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-6">
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
          <span className="block font-medium">Entity</span>
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={entityFilter}
            onChange={(event) => setEntityFilter(event.target.value as (typeof ENTITY_FILTER_OPTIONS)[number]["value"])}
          >
            {ENTITY_FILTER_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>

        <label className="space-y-1 text-sm">
          <span className="block font-medium">Yuzey</span>
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={surfaceFilter}
            onChange={(event) => setSurfaceFilter(event.target.value as (typeof SURFACE_FILTER_OPTIONS)[number]["value"])}
          >
            {SURFACE_FILTER_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>

        <label className="space-y-1 text-sm">
          <span className="block font-medium">Ara</span>
          <input
            type="search"
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={searchTerm}
            onChange={(event) => setSearchTerm(event.target.value)}
            placeholder="Entity veya baglam ara"
          />
        </label>

        <label className="space-y-1 text-sm">
          <span className="block font-medium">Blok Nedeni</span>
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={reasonFilter}
            onChange={(event) => setReasonFilter(event.target.value as (typeof REASON_FILTER_OPTIONS)[number]["value"])}
          >
            {REASON_FILTER_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>

        <label className="space-y-1 text-sm">
          <span className="block font-medium">Not Durumu</span>
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={noteFilter}
            onChange={(event) => setNoteFilter(event.target.value as (typeof NOTE_FILTER_OPTIONS)[number]["value"])}
          >
            {NOTE_FILTER_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>
      </div>

      <div className="mt-4 rounded-lg border border-[var(--border)] bg-[var(--surface-2)] p-4">
        <div className="flex flex-wrap gap-3 text-sm muted-text">
          <span>Blokta kalan: {filteredDeferredItems.length}</span>
          <span>Not girilen: {filteredItemsWithNotes}</span>
          <span>Nedensiz erteleme: {deferredWithoutReasonCount}</span>
          <span>Blok nedeni tipi: {deferredReasonGroups.length}</span>
        </div>

        {deferredReasonGroups.length > 0 ? (
          <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            {deferredReasonGroups.map((group) => (
              <button
                key={group.key}
                type="button"
                className="rounded-lg border border-[var(--border)] bg-white p-3 text-left hover:bg-[var(--surface-2)]"
                onClick={() => setReasonFilter(group.key as (typeof REASON_FILTER_OPTIONS)[number]["value"])}
              >
                <div className="flex flex-wrap gap-2">
                  <Badge label={group.label} variant="warning" />
                  <Badge label={`${group.count} yuzey`} variant="neutral" />
                </div>
                <p className="mt-2 text-xs muted-text">
                  {group.entities} entity / {group.notes} notlu kayit
                </p>
              </button>
            ))}
          </div>
        ) : (
          <p className="mt-3 text-sm muted-text">
            Mevcut filtrelerle blok nedeni gorunurlugu yok.
          </p>
        )}
      </div>

      <div className="mt-4 rounded-lg border border-[var(--border)] bg-[var(--surface-2)] p-4">
        <div className="space-y-4">
          <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div className="space-y-2">
              <div className="flex flex-wrap gap-3 text-sm muted-text">
                <span>Filtrelenen: {filteredItems.length}</span>
                <span>Acik: {filteredOpenItems}</span>
                <span>Secili: {selectedVisibleItems.length}</span>
              </div>
              <div className="flex flex-wrap gap-2">
                <Button
                  type="button"
                  size="sm"
                  variant="secondary"
                  onClick={handleToggleVisibleSelection}
                  disabled={filteredItems.length === 0 || activeBulkStatus !== null}
                >
                  {allVisibleSelected ? "Secimi Temizle" : "Gorunenleri Sec"}
                </Button>
                {STATUS_UPDATE_OPTIONS.map((option) => (
                  <Button
                    key={option.value}
                    type="button"
                    size="sm"
                    variant={buttonVariantForBulkStatus(option.value)}
                    disabled={activeBulkStatus !== null || selectedVisibleItems.length === 0}
                    onClick={() => void handleBulkStatusChange(option.value)}
                  >
                    {activeBulkStatus === option.value ? "Guncelleniyor..." : option.label}
                  </Button>
                ))}
              </div>
            </div>
          </div>

          <div className="grid gap-3 xl:grid-cols-[1.2fr_0.8fr]">
            <label className="space-y-1 text-sm">
              <span className="block font-medium">Operator Notu</span>
              <textarea
                className="min-h-[84px] w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 text-sm"
                value={bulkNote}
                onChange={(event) => setBulkNote(event.target.value)}
                placeholder="Secili karar yuzeyleri icin ortak not"
                maxLength={500}
              />
            </label>

            <label className="space-y-1 text-sm">
              <span className="block font-medium">Erteleme Nedeni</span>
              <select
                className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
                value={bulkDeferReason}
                onChange={(event) => setBulkDeferReason(event.target.value)}
              >
                {DEFER_REASON_OPTIONS.map((option) => (
                  <option key={option.value || "empty"} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
              <p className="text-xs muted-text">
                Sadece `Ertelendi` durumuna toplu geciste zorunlu kullanilir. Diger durum gecislerinde mevcut notlar korunur.
              </p>
            </label>
          </div>
        </div>

        {message ? <p className="mt-3 text-sm text-[var(--accent)]">{message}</p> : null}
        {error ? <p className="mt-2 text-sm text-[var(--danger)]">{error}</p> : null}
      </div>

      <div className="mt-4 space-y-4">
        {groupedItems.map((group) => (
          <div key={group.key} className="space-y-3">
            <div className="flex flex-wrap items-center gap-2">
              <Badge label={group.label} variant={group.tone === "warning" ? "warning" : "neutral"} />
              <Badge label={`${group.items.length} kayit`} variant="neutral" />
            </div>

            {group.items.map((item) => {
              const itemKey = queueItemKey(item);

              return (
                <div key={itemKey} className="rounded-lg border border-[var(--border)] p-4">
                  <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                    <div className="flex gap-3">
                      <label className="mt-1 flex items-start">
                        <input
                          type="checkbox"
                          className="h-4 w-4 rounded border border-[var(--border)]"
                          checked={selectedKeys.includes(itemKey)}
                          onChange={(event) => handleToggleItem(itemKey, event.target.checked)}
                          disabled={activeBulkStatus !== null}
                          aria-label={`${item.entity_label ?? "Bilinmeyen varlik"} sec`}
                        />
                      </label>

                      <div>
                        <div className="flex flex-wrap items-center gap-2">
                          <Badge label={item.surface_label} variant="neutral" />
                          <Badge label={item.status_label} variant={variantForStatus(item.status)} />
                          <Badge label={entityTypeLabel(item.entity_type)} variant="neutral" />
                        </div>
                        <p className="mt-3 text-sm font-semibold">{item.entity_label ?? "Bilinmeyen varlik"}</p>
                        <p className="mt-1 text-xs muted-text">
                          {item.context_label ?? "-"}
                          {item.updated_at ? ` / Son guncelleme: ${item.updated_at}` : " / Henuz operator isareti yok"}
                          {item.updated_by_name ? ` / ${item.updated_by_name}` : ""}
                        </p>
                        {item.defer_reason_label ? (
                          <p className="mt-2 text-xs muted-text">Erteleme nedeni: {item.defer_reason_label}</p>
                        ) : null}
                        {item.operator_note ? (
                          <p className="mt-1 text-xs muted-text">Not: {item.operator_note}</p>
                        ) : null}
                      </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                      {item.route ? (
                        <Link
                          href={routeBuilder(item.route)}
                          className="inline-flex h-10 items-center rounded-md border border-[var(--border)] px-4 text-sm font-semibold hover:bg-[var(--surface-2)]"
                        >
                          Detaya git
                        </Link>
                      ) : null}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        ))}

        {filteredItems.length === 0 ? (
          <p className="text-sm muted-text">
            {items.length === 0
              ? "Henuz reports merkezine tasinmis operator takip kaydi yok. Detail ekranlarindaki karar yuzeyleri isaretlendikce bu kuyruk dolacak."
              : "Secili filtrelerle eslesen karar yuzeyi yok."}
          </p>
        ) : null}
      </div>
    </Card>
  );
}

function queueItemKey(item: ReportDecisionSurfaceQueueItem) {
  return `${item.entity_type}:${item.entity_id}:${item.surface_key}`;
}

function statusLabel(status: string) {
  return (
    STATUS_UPDATE_OPTIONS.find((option) => option.value === status)?.label ??
    STATUS_FILTER_OPTIONS.find((option) => option.value === status)?.label ??
    status
  );
}

function entityTypeLabel(entityType: string) {
  if (entityType === "account") {
    return "Reklam Hesabi";
  }

  if (entityType === "campaign") {
    return "Kampanya";
  }

  return entityType;
}

function buttonVariantForBulkStatus(status: string) {
  if (status === "completed") return "primary" as const;
  if (status === "deferred") return "outline" as const;

  return "secondary" as const;
}

function errorMessageFromFailure(reason: unknown) {
  if (reason instanceof Error && reason.message.trim()) {
    return reason.message;
  }

  return "Toplu karar durumu guncellenemedi.";
}

function variantForStatus(status: string) {
  if (status === "completed") return "success" as const;
  if (status === "deferred") return "warning" as const;
  if (status === "reviewed") return "neutral" as const;

  return "neutral" as const;
}
