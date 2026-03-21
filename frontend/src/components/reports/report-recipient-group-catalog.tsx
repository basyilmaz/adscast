"use client";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { RecipientGroupCatalogItem } from "@/lib/types";

type Props = {
  items: RecipientGroupCatalogItem[];
  emptyText: string;
  onApply?: (item: RecipientGroupCatalogItem) => void;
};

function sourceLabel(sourceType: string) {
  return matchSource(sourceType);
}

function matchSource(sourceType: string) {
  switch (sourceType) {
    case "preset":
      return "Kayitli Grup";
    case "segment":
      return "Segment";
    case "smart":
      return "Akilli Grup";
    default:
      return sourceType;
  }
}

export function ReportRecipientGroupCatalog({ items, emptyText, onApply }: Props) {
  if (items.length === 0) {
    return <p className="text-sm muted-text">{emptyText}</p>;
  }

  return (
    <div className="space-y-3">
      {items.map((item) => (
        <div key={item.id} className="rounded-lg border border-[var(--border)] p-3">
          <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
            <div className="space-y-2">
              <div className="flex flex-wrap gap-2">
                <Badge label={sourceLabel(item.source_type)} variant="neutral" />
                <Badge label={`${item.resolved_recipients_count} alici`} variant="neutral" />
                {item.recommendation_label ? <Badge label={item.recommendation_label} variant="success" /> : null}
              </div>
              <div>
                <p className="font-semibold">{item.name}</p>
                <p className="mt-1 text-sm muted-text">{item.description ?? item.recipient_group_summary.label}</p>
              </div>
              <p className="text-xs muted-text">{item.recipient_group_summary.label}</p>
              <p className="text-xs muted-text">
                Statik: {item.recipient_group_summary.static_recipients_count} / Dinamik: {item.recipient_group_summary.dynamic_contacts_count}
              </p>
              {item.recommendation_reason ? <p className="text-xs muted-text">{item.recommendation_reason}</p> : null}
              {item.recipient_group_summary.sample_contact_names.length > 0 ? (
                <p className="text-xs muted-text">
                  Ornek kisiler: {item.recipient_group_summary.sample_contact_names.join(", ")}
                </p>
              ) : null}
            </div>

            {onApply ? (
              <Button type="button" variant="secondary" size="sm" onClick={() => onApply(item)}>
                Bu grubu kullan
              </Button>
            ) : null}
          </div>
        </div>
      ))}
    </div>
  );
}
