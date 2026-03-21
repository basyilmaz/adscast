import { RecipientGroupCatalogItem, ReportRecipientPresetListItem } from "@/lib/types";

type Params = {
  selectedSuggestedGroup: RecipientGroupCatalogItem | null;
  selectedPreset: ReportRecipientPresetListItem | null;
  parsedRecipients: string[];
  contactTags: string[];
};

type RecipientGroupSelectionPayload = {
  id: string;
  source_type: "preset" | "segment" | "smart" | "manual";
  source_subtype: string | null;
  source_id: string | null;
  name: string;
};

export function buildRecipientGroupSelectionPayload({
  selectedSuggestedGroup,
  selectedPreset,
  parsedRecipients,
  contactTags,
}: Params): RecipientGroupSelectionPayload | null {
  if (selectedSuggestedGroup) {
    return {
      id: selectedSuggestedGroup.id,
      source_type: normalizeSourceType(selectedSuggestedGroup.source_type),
      source_subtype: selectedSuggestedGroup.source_subtype ?? null,
      source_id: selectedSuggestedGroup.source_id,
      name: selectedSuggestedGroup.name,
    };
  }

  if (selectedPreset) {
    return {
      id: `preset:${selectedPreset.id}`,
      source_type: "preset",
      source_subtype: contactTags.length > 0 ? "preset_plus_segment" : null,
      source_id: selectedPreset.id,
      name: selectedPreset.name,
    };
  }

  if (contactTags.length > 0 && parsedRecipients.length === 0) {
    return {
      id: `segment:${slugify(contactTags.join("-"))}`,
      source_type: "segment",
      source_subtype: contactTags.length > 1 ? "multi_tag" : null,
      source_id: contactTags.join("|"),
      name: `Segment: ${contactTags.join(", ")}`,
    };
  }

  if (parsedRecipients.length > 0 || contactTags.length > 0) {
    const selectionHash = hashSelection(
      [...parsedRecipients].sort().join("|"),
      [...contactTags].sort().join("|"),
    );

    return {
      id: `manual:${selectionHash}`,
      source_type: "manual",
      source_subtype: contactTags.length > 0 ? "manual_plus_segment" : null,
      source_id: `custom:${selectionHash}`,
      name: contactTags.length > 0 ? `Manuel alici + segment (${contactTags.join(", ")})` : "Manuel alici listesi",
    };
  }

  return null;
}

function normalizeSourceType(value: string): "preset" | "segment" | "smart" | "manual" {
  switch (value) {
    case "preset":
    case "segment":
    case "smart":
    case "manual":
      return value;
    default:
      return "manual";
  }
}

function slugify(value: string): string {
  return value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
}

function hashSelection(...parts: string[]): string {
  const input = parts.join("#");
  let hash = 0;

  for (let index = 0; index < input.length; index += 1) {
    hash = (hash * 31 + input.charCodeAt(index)) >>> 0;
  }

  return hash.toString(16).padStart(8, "0");
}
