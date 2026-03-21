"use client";

import type { ReadonlyURLSearchParams } from "next/navigation";

export const GLOBAL_DATE_FILTER_KEYS = ["start_date", "end_date"] as const;
export const GLOBAL_CAMPAIGN_FILTER_KEYS = [
  "start_date",
  "end_date",
  "status",
  "objective",
  "ad_account_id",
] as const;

type FilterKey = (typeof GLOBAL_CAMPAIGN_FILTER_KEYS)[number];
type SearchParamsLike = URLSearchParams | ReadonlyURLSearchParams;

export function cloneSearchParams(searchParams: SearchParamsLike): URLSearchParams {
  return new URLSearchParams(searchParams.toString());
}

export function buildApiPathWithFilters(
  basePath: string,
  searchParams: SearchParamsLike,
  allowedKeys: readonly FilterKey[],
): string {
  const [path, existingQuery = ""] = basePath.split("?", 2);
  const params = new URLSearchParams(existingQuery);

  allowedKeys.forEach((key) => {
    const value = searchParams.get(key);

    if (value) {
      params.set(key, value);
    }
  });

  const query = params.toString();

  if (!query) {
    return path;
  }

  return `${path}?${query}`;
}

export function buildHrefWithFilters(
  basePath: string,
  searchParams: SearchParamsLike,
  allowedKeys: readonly FilterKey[],
): string {
  return buildApiPathWithFilters(basePath, searchParams, allowedKeys);
}

export function withDatePreset(days: 7 | 14 | 30): { startDate: string; endDate: string } {
  const endDate = new Date();
  const startDate = new Date();
  startDate.setDate(endDate.getDate() - (days - 1));

  return {
    startDate: toInputDate(startDate),
    endDate: toInputDate(endDate),
  };
}

function toInputDate(value: Date): string {
  const year = value.getFullYear();
  const month = `${value.getMonth() + 1}`.padStart(2, "0");
  const day = `${value.getDate()}`.padStart(2, "0");

  return `${year}-${month}-${day}`;
}
