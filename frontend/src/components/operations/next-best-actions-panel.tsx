"use client";

import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import { Card, CardTitle } from "@/components/ui/card";
import type { NextBestActionItem } from "@/lib/types";

function variantFor(priority: string) {
  if (priority === "critical" || priority === "high") return "danger" as const;
  if (priority === "warning" || priority === "medium") return "warning" as const;
  if (priority === "low" || priority === "healthy") return "success" as const;

  return "neutral" as const;
}

function entityLabel(type: string) {
  return (
    {
      workspace: "Workspace",
      account: "Reklam Hesabi",
      campaign: "Kampanya",
      ad_set: "Ad Set",
      ad: "Reklam",
    }[type] ?? "Varlik"
  );
}

type Props = {
  title?: string;
  items: NextBestActionItem[];
  emptyText: string;
};

export function NextBestActionsPanel({
  title = "Siradaki En Dogru Adimlar",
  items,
  emptyText,
}: Props) {
  return (
    <Card>
      <CardTitle>{title}</CardTitle>
      <div className="mt-3 space-y-3">
        {items.map((item) => (
          <div key={`${item.source}-${item.id}`} className="rounded-lg border border-[var(--border)] p-3">
            <div className="flex flex-wrap items-center gap-2">
              <Badge label={item.source === "alert" ? "Uyari" : "Oneri"} variant="neutral" />
              <Badge label={item.priority} variant={variantFor(item.priority)} />
              <Badge label={entityLabel(item.entity_type)} variant="neutral" />
              <span className="text-xs muted-text">{item.detected_at ?? item.generated_at ?? "-"}</span>
            </div>
            <p className="mt-2 font-semibold">{item.title}</p>
            <p className="mt-1 text-xs muted-text">
              {item.entity_label ?? "Varlik"}
              {item.context_label ? ` / ${item.context_label}` : ""}
            </p>
            <div className="mt-3 space-y-2 text-sm">
              <div>
                <p className="font-semibold">Neden Onemli?</p>
                <p className="muted-text">{item.why_it_matters ?? "Etki ozeti bulunmuyor."}</p>
              </div>
              <div>
                <p className="font-semibold">Sonraki Adim</p>
                <p className="muted-text">{item.recommended_action ?? "Aksiyon notu bulunmuyor."}</p>
              </div>
            </div>
            {item.route ? (
              <Link href={item.route} className="mt-3 inline-flex text-sm font-semibold text-[var(--accent)] hover:underline">
                Ilgili kaydi ac
              </Link>
            ) : null}
          </div>
        ))}
        {items.length === 0 ? <p className="text-sm muted-text">{emptyText}</p> : null}
      </div>
    </Card>
  );
}
