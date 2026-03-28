"use client";

import {
  ReportDecisionQueueRecommendationAnalyticsItem,
  ReportDecisionSurfaceQueueItem,
} from "@/lib/types";

export type DecisionQueueRecommendationAnalyticsMeta = Pick<
  ReportDecisionQueueRecommendationAnalyticsItem,
  "tracked_interactions" | "selection_only_interactions" | "applied_interactions" | "item_success_rate" | "health_status"
>;

export type DecisionQueueRecommendation = {
  code: string;
  title: string;
  statusLabel: string;
  statusValue: "pending" | "reviewed" | "completed" | "deferred" | null;
  variant: "success" | "warning" | "danger" | "neutral";
  helperLabel: string;
  helperDescription: string;
  targetKeys: string[];
  targetItems: ReportDecisionSurfaceQueueItem[];
  basePriority: number;
  staticOrder: number;
  dominantReasonKey: string | null;
  selectionStrategy?: "safety_override" | "analytics_boosted" | "static_priority";
  selectionSummary?: string;
  adaptiveScore?: number;
  analytics?: DecisionQueueRecommendationAnalyticsMeta | null;
};

export type DecisionQueueDeferredReasonPriority = {
  rank: number;
  label: string;
  variant: "success" | "warning" | "danger" | "neutral";
  guidance: string;
};

export type DecisionQueueDeferredReasonGroup = {
  key: string;
  label: string;
  count: number;
  entities: number;
  notes: number;
  priority: DecisionQueueDeferredReasonPriority;
};

const OPEN_STATUSES = new Set(["pending", "reviewed", "deferred"]);

const DEFER_REASON_PRIORITY = {
  none: {
    rank: 5,
    label: "Acil",
    variant: "danger" as const,
    guidance: "Erteleme nedeni girilmemis. Once nedeni netlestirin.",
  },
  blocked_external_dependency: {
    rank: 4,
    label: "Yuksek",
    variant: "warning" as const,
    guidance: "Dis bagimlilik engeli var. Cozum sahibi netlestirilmeden kuyruk birikiyor.",
  },
  waiting_data_validation: {
    rank: 4,
    label: "Yuksek",
    variant: "warning" as const,
    guidance: "Veri dogrulamasi bekleyen isler karar akisini durduruyor.",
  },
  priority_window_shifted: {
    rank: 3,
    label: "Orta",
    variant: "neutral" as const,
    guidance: "Oncelik kaymasi var. Yeni pencereye gore yeniden planlayin.",
  },
  scheduled_followup: {
    rank: 2,
    label: "Planli",
    variant: "neutral" as const,
    guidance: "Planli takip bekleniyor. Tarih yaklastikca tekrar ele alin.",
  },
  waiting_client_feedback: {
    rank: 1,
    label: "Takip",
    variant: "neutral" as const,
    guidance: "Musteri geri donusu bekleniyor. Hemen teknik aksiyon beklenmez.",
  },
} as const;

export function isDecisionQueueOpenStatus(status: string) {
  return OPEN_STATUSES.has(status);
}

export function deferReasonPriority(reasonCode: string | null) {
  return (
    DEFER_REASON_PRIORITY[(reasonCode ?? "waiting_client_feedback") as keyof typeof DEFER_REASON_PRIORITY]
    ?? DEFER_REASON_PRIORITY.waiting_client_feedback
  );
}

export function queueItemKey(item: ReportDecisionSurfaceQueueItem) {
  return `${item.entity_type}:${item.entity_id}:${item.surface_key}`;
}

export function compareQueueItems(left: ReportDecisionSurfaceQueueItem, right: ReportDecisionSurfaceQueueItem) {
  const priorityDifference = queueItemPriorityScore(right) - queueItemPriorityScore(left);
  if (priorityDifference !== 0) {
    return priorityDifference;
  }

  const updatedDifference = safeTimestamp(right.updated_at) - safeTimestamp(left.updated_at);
  if (updatedDifference !== 0) {
    return updatedDifference;
  }

  return (left.entity_label ?? "").localeCompare(right.entity_label ?? "", "tr");
}

export function recommendationAnalyticsScore(item: ReportDecisionQueueRecommendationAnalyticsItem | null) {
  if (!item) {
    return 0;
  }

  const successWeight = item.item_success_rate ?? 0;
  const applicationWeight = Math.min(item.applied_interactions * 8, 32);
  const confidenceWeight = Math.min(item.tracked_interactions * 2, 12);

  if (item.health_status === "critical") {
    return Math.max(0, successWeight / 4);
  }

  return successWeight + applicationWeight + confidenceWeight;
}

export function buildDecisionQueueRecommendationState(
  filteredItems: ReportDecisionSurfaceQueueItem[],
  recommendationAnalyticsItems: ReportDecisionQueueRecommendationAnalyticsItem[],
) {
  const filteredDeferredItems = filteredItems.filter((item) => item.status === "deferred");
  const deferredWithoutReasonItems = filteredDeferredItems.filter((item) => item.defer_reason_code === null);
  const deferredReasonGroups = Array.from(
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
      priority: deferReasonPriority(group.key),
    }))
    .sort(
      (left, right) =>
        right.priority.rank - left.priority.rank ||
        right.count - left.count ||
        left.label.localeCompare(right.label, "tr"),
    );

  const topPriorityGroups = deferredReasonGroups.filter((group) => group.priority.rank >= 3).slice(0, 3);
  const prioritySelectionCandidates = filteredDeferredItems
    .filter((item) => deferReasonPriority(item.defer_reason_code ?? "none").rank >= 3)
    .sort(compareQueueItems);
  const prioritySelectionKeys = prioritySelectionCandidates.map((item) => queueItemKey(item));
  const missingReasonSelectionKeys = deferredWithoutReasonItems.map((item) => queueItemKey(item));

  const recommendationCandidates: DecisionQueueRecommendation[] = [];

  if (deferredWithoutReasonItems.length > 0) {
    recommendationCandidates.push({
      code: "fix_defer_reason",
      title: "Erteleme nedenini duzelt",
      statusLabel: "Ertelendi",
      statusValue: null,
      variant: "danger",
      helperLabel: "Nedensiz Ertelemeleri Sec",
      helperDescription:
        "Nedensiz ertelemeler en riskli bloklar. Bu kayitlari secip bir erteleme nedeni girerek tekrar kaydedin.",
      targetKeys: missingReasonSelectionKeys,
      targetItems: deferredWithoutReasonItems,
      basePriority: 100,
      staticOrder: 0,
      dominantReasonKey: "none",
    });
  }

  if (topPriorityGroups.some((group) => group.key === "blocked_external_dependency")) {
    recommendationCandidates.push({
      code: "review_external_blockers",
      title: "Once gozden gecir",
      statusLabel: "Gozden Gecirildi",
      statusValue: "reviewed",
      variant: "warning",
      helperLabel: "Once Cozulmelileri Sec",
      helperDescription:
        "Dis bagimlilik bloklari owner atamasi veya takip notu gerektiriyor. Yuzeyleri secip gozden gecirilmis olarak ayirin.",
      targetKeys: prioritySelectionKeys,
      targetItems: prioritySelectionCandidates,
      basePriority: 80,
      staticOrder: 1,
      dominantReasonKey: "blocked_external_dependency",
    });
  }

  if (topPriorityGroups.some((group) => group.key === "waiting_data_validation")) {
    recommendationCandidates.push({
      code: "review_data_validation",
      title: "Veri bloklarini gozden gecir",
      statusLabel: "Gozden Gecirildi",
      statusValue: "reviewed",
      variant: "warning",
      helperLabel: "Once Cozulmelileri Sec",
      helperDescription:
        "Veri dogrulamasi bekleyen bloklar karar akisini durduruyor. Yuzeyleri secip dogrulama sahibiyle birlikte tekrar ele alin.",
      targetKeys: prioritySelectionKeys,
      targetItems: prioritySelectionCandidates,
      basePriority: 78,
      staticOrder: 2,
      dominantReasonKey: "waiting_data_validation",
    });
  }

  if (topPriorityGroups.some((group) => group.key === "priority_window_shifted")) {
    recommendationCandidates.push({
      code: "review_priority_shift",
      title: "Durumu yeniden degerlendir",
      statusLabel: "Gozden Gecirildi",
      statusValue: "reviewed",
      variant: "neutral",
      helperLabel: "Once Cozulmelileri Sec",
      helperDescription:
        "Oncelik penceresi kayan bloklarin yeni takvime gore yeniden siniflanmasi gerekiyor.",
      targetKeys: prioritySelectionKeys,
      targetItems: prioritySelectionCandidates,
      basePriority: 72,
      staticOrder: 3,
      dominantReasonKey: "priority_window_shifted",
    });
  }

  if (prioritySelectionCandidates.length > 0) {
    recommendationCandidates.push({
      code: "complete_priority_blockers",
      title: "Simdi tamamla",
      statusLabel: "Tamamlandi",
      statusValue: "completed",
      variant: "success",
      helperLabel: "Once Cozulmelileri Sec",
      helperDescription:
        "Bu bloklar artik ek bekleme nedeni tasimiyor gibi gorunuyor. Gecerliligini kontrol edip tamamlamaya tasiyin.",
      targetKeys: prioritySelectionKeys,
      targetItems: prioritySelectionCandidates,
      basePriority: 65,
      staticOrder: 4,
      dominantReasonKey: topPriorityGroups[0]?.key ?? null,
    });
  }

  const recommendationAnalyticsIndex = new Map(
    recommendationAnalyticsItems.map((item) => [item.recommendation_code, item]),
  );
  let priorityBulkRecommendation: DecisionQueueRecommendation | null = null;

  if (recommendationCandidates.length > 0) {
    const staticTopCandidate = [...recommendationCandidates].sort(compareRecommendationStaticOrder)[0];
    const rankedCandidates = recommendationCandidates
      .map((candidate) => {
        const analytics = recommendationAnalyticsIndex.get(candidate.code) ?? null;
        const adaptiveScore =
          candidate.code === "fix_defer_reason"
            ? Number.MAX_SAFE_INTEGER
            : candidate.basePriority * 100 + candidate.targetItems.length * 10 + recommendationAnalyticsScore(analytics);

        return {
          ...candidate,
          adaptiveScore,
          analytics: analytics
            ? {
                tracked_interactions: analytics.tracked_interactions,
                selection_only_interactions: analytics.selection_only_interactions,
                applied_interactions: analytics.applied_interactions,
                item_success_rate: analytics.item_success_rate,
                health_status: analytics.health_status,
              }
            : null,
        };
      })
      .sort(compareRecommendationAdaptiveOrder);

    const topCandidate = rankedCandidates[0];

    if (topCandidate.code === "fix_defer_reason") {
      priorityBulkRecommendation = {
        ...topCandidate,
        selectionStrategy: "safety_override",
        selectionSummary: "Nedensiz ertelemeler oldugu icin bu aksiyon guvenlik geregi her zaman once gelir.",
      };
    } else if (
      staticTopCandidate
      && topCandidate.code !== staticTopCandidate.code
      && topCandidate.analytics
      && (topCandidate.analytics.applied_interactions > 0 || topCandidate.analytics.selection_only_interactions > 0)
    ) {
      priorityBulkRecommendation = {
        ...topCandidate,
        selectionStrategy: "analytics_boosted",
        selectionSummary: analyticsBoostSummary(topCandidate),
      };
    } else {
      priorityBulkRecommendation = {
        ...topCandidate,
        selectionStrategy: "static_priority",
        selectionSummary: staticPrioritySummary(topCandidate),
      };
    }
  }

  return {
    filteredDeferredItems,
    deferredWithoutReasonItems,
    deferredReasonGroups,
    topPriorityGroups,
    prioritySelectionCandidates,
    prioritySelectionKeys,
    missingReasonSelectionKeys,
    recommendationCandidates,
    priorityBulkRecommendation,
  };
}

export function getDecisionQueueRecommendationFocus(
  recommendationCode: string | null,
  dominantReasonCode: string | null,
  targetSurfaceKeys: string[],
) {
  let statusFilter: string = "open";
  let reasonFilter: string = "all";

  if (recommendationCode === "fix_defer_reason") {
    statusFilter = "deferred";
    reasonFilter = "none";
  }

  if (recommendationCode === "review_external_blockers") {
    statusFilter = "deferred";
    reasonFilter = "blocked_external_dependency";
  }

  if (recommendationCode === "review_data_validation") {
    statusFilter = "deferred";
    reasonFilter = "waiting_data_validation";
  }

  if (recommendationCode === "review_priority_shift") {
    statusFilter = "deferred";
    reasonFilter = "priority_window_shifted";
  }

  if (recommendationCode === "complete_priority_blockers") {
    statusFilter = "deferred";
    reasonFilter = dominantReasonCode ?? "all";
  }

  return {
    statusFilter,
    reasonFilter,
    surfaceFilter: targetSurfaceKeys.length === 1 ? targetSurfaceKeys[0] : "all",
  };
}

function analyticsBoostSummary(recommendation: DecisionQueueRecommendation) {
  const analytics = recommendation.analytics;

  if (!analytics) {
    return staticPrioritySummary(recommendation);
  }

  return `%${formatRecommendationRate(analytics.item_success_rate)} kayit basarisi ve ${analytics.applied_interactions} uygulama gordugu icin bu aksiyon one alindi.`;
}

function staticPrioritySummary(recommendation: DecisionQueueRecommendation) {
  if (recommendation.dominantReasonKey === "blocked_external_dependency") {
    return "Dis bagimlilik bloklari aktif oldugu icin once gozden gecirme akisina oncelik verildi.";
  }

  if (recommendation.dominantReasonKey === "waiting_data_validation") {
    return "Veri dogrulamasi bekleyen bloklar karar akisini durdurdugu icin once bu akis onerildi.";
  }

  if (recommendation.dominantReasonKey === "priority_window_shifted") {
    return "Oncelik penceresi kayan kayitlar agirlikta oldugu icin yeniden degerlendirme akisina oncelik verildi.";
  }

  return "Mevcut blok dagilimi icinde en yuksek oncelikli grup bu aksiyonla eslesiyor.";
}

function compareRecommendationStaticOrder(left: DecisionQueueRecommendation, right: DecisionQueueRecommendation) {
  return left.staticOrder - right.staticOrder;
}

function compareRecommendationAdaptiveOrder(left: DecisionQueueRecommendation, right: DecisionQueueRecommendation) {
  const scoreDifference = (right.adaptiveScore ?? 0) - (left.adaptiveScore ?? 0);

  if (scoreDifference !== 0) {
    return scoreDifference;
  }

  return compareRecommendationStaticOrder(left, right);
}

function queueItemPriorityScore(item: ReportDecisionSurfaceQueueItem) {
  if (item.status === "deferred") {
    return deferReasonPriority(item.defer_reason_code ?? "none").rank * 10;
  }

  if (item.status === "pending") {
    return 25;
  }

  if (item.status === "reviewed") {
    return 20;
  }

  if (item.status === "completed") {
    return 5;
  }

  return 10;
}

function safeTimestamp(value: string | null) {
  if (!value) {
    return 0;
  }

  const timestamp = Date.parse(value);
  return Number.isNaN(timestamp) ? 0 : timestamp;
}

function formatRecommendationRate(value: number | null) {
  return value === null ? "-" : value.toFixed(1);
}
