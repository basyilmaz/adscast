"use client";

import { ReactNode, useMemo, useState } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Button } from "@/components/ui/button";
import { useApiQuery } from "@/hooks/use-api-query";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { withDatePreset } from "@/lib/filters";
import { AdAccountListResponse } from "@/lib/types";

const OBJECTIVE_OPTIONS = [
  "LINK_CLICKS",
  "MESSAGES",
  "OUTCOME_ENGAGEMENT",
  "OUTCOME_LEADS",
  "LEADS",
  "TRAFFIC",
  "SALES",
];

type FilterMode = "campaign-list" | "date-only" | "none";

type FilterDraft = {
  startDate: string;
  endDate: string;
  status: string;
  objective: string;
  adAccountId: string;
};

function resolveFilterMode(pathname: string): FilterMode {
  if (pathname.startsWith("/campaigns") && !pathname.startsWith("/campaigns/detail")) {
    return "campaign-list";
  }

  if (
    pathname.startsWith("/dashboard")
    || pathname.startsWith("/ad-accounts")
    || pathname.startsWith("/campaigns/detail")
    || pathname.startsWith("/ad-sets/detail")
    || pathname.startsWith("/ads/detail")
    || pathname.startsWith("/reports")
  ) {
    return "date-only";
  }

  return "none";
}

function readDraft(search: string): FilterDraft {
  const params = new URLSearchParams(search);

  return {
    startDate: params.get("start_date") ?? "",
    endDate: params.get("end_date") ?? "",
    status: params.get("status") ?? "",
    objective: params.get("objective") ?? "",
    adAccountId: params.get("ad_account_id") ?? "",
  };
}

export function AppGlobalFilters() {
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const mode = resolveFilterMode(pathname);

  if (mode === "none") {
    return null;
  }

  const initialDraft = readDraft(searchParams.toString());

  return (
    <AppGlobalFiltersForm
      key={`${pathname}:${searchParams.toString()}`}
      pathname={pathname}
      search={searchParams.toString()}
      mode={mode}
      initialDraft={initialDraft}
    />
  );
}

function AppGlobalFiltersForm({
  pathname,
  search,
  mode,
  initialDraft,
}: {
  pathname: string;
  search: string;
  mode: Exclude<FilterMode, "none">;
  initialDraft: FilterDraft;
}) {
  const router = useRouter();
  const [startDate, setStartDate] = useState(initialDraft.startDate);
  const [endDate, setEndDate] = useState(initialDraft.endDate);
  const [status, setStatus] = useState(initialDraft.status);
  const [objective, setObjective] = useState(initialDraft.objective);
  const [adAccountId, setAdAccountId] = useState(initialDraft.adAccountId);

  const shouldLoadAccounts = mode === "campaign-list";
  const adAccountQuery = useApiQuery<AdAccountListResponse, AdAccountListResponse["data"]["data"]>(
    "/meta/ad-accounts",
    {
      enabled: shouldLoadAccounts,
      requestOptions: {
        requireWorkspace: true,
      },
      ttlMs: QUERY_TTLS.adAccounts,
      select: (response) => response.data.data ?? [],
    },
  );

  const adAccounts = useMemo(() => adAccountQuery.data ?? [], [adAccountQuery.data]);

  const applyFilters = () => {
    const params = new URLSearchParams(search);

    setOrDelete(params, "start_date", startDate);
    setOrDelete(params, "end_date", endDate);

    if (mode === "campaign-list") {
      setOrDelete(params, "status", status);
      setOrDelete(params, "objective", objective);
      setOrDelete(params, "ad_account_id", adAccountId);
    } else {
      params.delete("status");
      params.delete("objective");
      params.delete("ad_account_id");
    }

    const query = params.toString();
    router.replace(query ? `${pathname}?${query}` : pathname);
  };

  const clearFilters = () => {
    const params = new URLSearchParams(search);
    ["start_date", "end_date", "status", "objective", "ad_account_id"].forEach((key) => params.delete(key));
    router.replace(pathname);
  };

  const applyPreset = (days: 7 | 14 | 30) => {
    const preset = withDatePreset(days);
    setStartDate(preset.startDate);
    setEndDate(preset.endDate);
    const params = new URLSearchParams(search);
    params.set("start_date", preset.startDate);
    params.set("end_date", preset.endDate);
    const query = params.toString();
    router.replace(query ? `${pathname}?${query}` : pathname);
  };

  return (
    <section className="surface-card mb-4 space-y-3 p-4">
      <div className="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <p className="text-xs font-semibold uppercase tracking-wide muted-text">Global Filtreler</p>
          <p className="text-sm muted-text">
            Tarih baglamini ve desteklenen sayfalarda kampanya filtrelerini sabitleyin.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" size="sm" onClick={() => applyPreset(7)}>
            Son 7 Gun
          </Button>
          <Button variant="secondary" size="sm" onClick={() => applyPreset(14)}>
            Son 14 Gun
          </Button>
          <Button variant="secondary" size="sm" onClick={() => applyPreset(30)}>
            Son 30 Gun
          </Button>
        </div>
      </div>

      <div className="grid gap-3 xl:grid-cols-[repeat(5,minmax(0,1fr))_auto]">
        <FilterField label="Baslangic Tarihi">
          <input
            type="date"
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={startDate}
            onChange={(event) => setStartDate(event.target.value)}
          />
        </FilterField>

        <FilterField label="Bitis Tarihi">
          <input
            type="date"
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={endDate}
            onChange={(event) => setEndDate(event.target.value)}
          />
        </FilterField>

        {mode === "campaign-list" ? (
          <>
            <FilterField label="Reklam Hesabi">
              <select
                className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
                value={adAccountId}
                onChange={(event) => setAdAccountId(event.target.value)}
              >
                <option value="">Tum hesaplar</option>
                {adAccounts.map((account) => (
                  <option key={account.id} value={account.id}>
                    {account.name}
                  </option>
                ))}
              </select>
            </FilterField>

            <FilterField label="Objective">
              <select
                className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
                value={objective}
                onChange={(event) => setObjective(event.target.value)}
              >
                <option value="">Tum objective secimleri</option>
                {OBJECTIVE_OPTIONS.map((item) => (
                  <option key={item} value={item}>
                    {item}
                  </option>
                ))}
              </select>
            </FilterField>

            <FilterField label="Durum">
              <select
                className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
                value={status}
                onChange={(event) => setStatus(event.target.value)}
              >
                <option value="">Tum durumlar</option>
                <option value="active">active</option>
                <option value="paused">paused</option>
                <option value="archived">archived</option>
              </select>
            </FilterField>
          </>
        ) : null}

        <div className="flex items-end gap-2">
          <Button variant="secondary" onClick={applyFilters}>
            Uygula
          </Button>
          <Button variant="outline" onClick={clearFilters}>
            Temizle
          </Button>
        </div>
      </div>
    </section>
  );
}

function FilterField({
  label,
  children,
}: {
  label: string;
  children: ReactNode;
}) {
  return (
    <label className="flex flex-col gap-1">
      <span className="text-xs font-semibold uppercase tracking-wide muted-text">{label}</span>
      {children}
    </label>
  );
}

function setOrDelete(params: URLSearchParams, key: string, value: string) {
  if (value) {
    params.set(key, value);
    return;
  }

  params.delete(key);
}
