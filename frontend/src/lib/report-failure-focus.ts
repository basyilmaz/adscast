import {
  ReportDeliveryProfileSuggestion,
  ReportDeliveryRetryRecommendationItem,
  ReportFailureResolutionActionItem,
  ReportFeaturedFailureResolution,
} from "@/lib/types";

export type ReportDecisionSurfaceKey = "featured_fix" | "retry" | "profile";
export const REPORT_DECISION_SURFACE_FOCUS_EVENT = "report-decision-surface-focus";

export function reasonLabelForCode(value?: string | null): string {
  switch (value) {
    case "smtp_timeout":
      return "SMTP Timeout";
    case "smtp_auth":
      return "SMTP Kimlik Dogrulama";
    case "smtp_connectivity":
      return "SMTP Baglanti Sorunu";
    case "smtp_tls":
      return "SMTP TLS Sorunu";
    case "recipient_rejected":
      return "Alici Reddi";
    case "sender_rejected":
      return "Gonderici Reddi";
    case "share_delivery_failure":
      return "Paylasim Linki Teslim Sorunu";
    case "snapshot_export_failure":
      return "Rapor Export Sorunu";
    case "manual_retry_pending":
      return "Manuel Retry Bekliyor";
    case "invalid_configuration":
      return "Gecersiz Konfigurasyon";
    case "unknown_failure":
      return "Bilinmeyen Hata";
    default:
      return value ?? "-";
  }
}

export function actionLabelForCode(value?: string | null): string {
  switch (value) {
    case "retry_failed_runs":
      return "Basarisiz teslimleri tekrar dene";
    case "review_contact_book":
      return "Alici kisilerini kontrol et";
    case "review_recipient_groups":
      return "Alici grubunu duzelt";
    case "focus_delivery_profile":
      return "Teslim profilini duzelt";
    default:
      return value ?? "-";
  }
}

export function focusSourceLabel(value?: string | null): string {
  switch (value) {
    case "featured_decision":
      return "Featured Karar";
    default:
      return value ?? "Rapor merkezi";
  }
}

export function reportDecisionSurfaceId(key: ReportDecisionSurfaceKey): string {
  return `report-decision-surface-${key}`;
}

export function focusReportDecisionSurface(key: ReportDecisionSurfaceKey) {
  if (typeof window === "undefined" || typeof document === "undefined") {
    return;
  }

  const surfaceId = reportDecisionSurfaceId(key);
  const target = document.getElementById(surfaceId);

  if (!target) {
    return;
  }

  target.scrollIntoView({ behavior: "smooth", block: "start" });
  window.history.replaceState(null, "", `#${surfaceId}`);
  window.dispatchEvent(new CustomEvent(REPORT_DECISION_SURFACE_FOCUS_EVENT, { detail: { surfaceId } }));
}

export function focusedActionExplanation(
  action: ReportFailureResolutionActionItem,
  focusActionCode?: string | null,
  focusReasonCode?: string | null,
  focusSource?: string | null,
): string {
  const reasonLabel = focusReasonCode ? reasonLabelForCode(focusReasonCode) : null;
  const sourceLabel = focusSourceLabel(focusSource);
  const reasonMatch = focusReasonCode ? action.metadata?.affected_reason_codes?.includes(focusReasonCode) : false;
  const actionMatch = focusActionCode ? action.code === focusActionCode : false;

  if (actionMatch && reasonMatch) {
    return `${sourceLabel}, ${reasonLabel} icin bu aksiyonu dogrudan one cikardi. Bu nedenle kart odaga alindi ve once gosteriliyor.`;
  }

  if (actionMatch) {
    return `${sourceLabel}, secili aksiyon olarak bu kaydi one cikardi. Ilgili hata akisini bu karttan yonetebilirsin.`;
  }

  if (reasonMatch) {
    return `${reasonLabel} bu aksiyonun etkiledigi hata nedenleri arasinda. Aksiyon secilmis olmasa da ayni problemi cozen yakin bir secenek olarak odaga alindi.`;
  }

  return "Bu kart rapor merkezinden gelen odaga en yakin duzeltme aksiyonudur.";
}

export function featuredRecommendationExplanation(
  recommendation: ReportFeaturedFailureResolution,
  focusActionCode?: string | null,
  focusReasonCode?: string | null,
  focusSource?: string | null,
): string {
  const sourceLabel = focusSourceLabel(focusSource);
  const reasonLabel = recommendation.reason_label ?? reasonLabelForCode(recommendation.reason_code);
  const actionLabel = recommendation.action_label ?? actionLabelForCode(recommendation.action_code);
  const reasonMatch = focusReasonCode ? recommendation.reason_code === focusReasonCode : false;
  const actionMatch = focusActionCode ? recommendation.action_code === focusActionCode : false;

  if (reasonMatch && actionMatch) {
    return `${sourceLabel}, ${reasonLabel} icin ${actionLabel} aksiyonunu one cikardi. Featured fix ve hizli aksiyon akisi ayni probleme hizalandi.`;
  }

  if (reasonMatch) {
    return `${sourceLabel}, ${reasonLabel} akisini odaga aldi. Bu featured fix ayni hata nedenini cozmek icin one cikiyor.`;
  }

  if (actionMatch) {
    return `${sourceLabel}, secili aksiyon olarak ${actionLabel} yolunu one cikardi. Featured fix alani ayni operator kararini destekliyor.`;
  }

  if (recommendation.analytics_guidance) {
    return `${actionLabel}, ${reasonLabel} icin one cikarildi. ${recommendation.analytics_guidance}`;
  }

  return `${actionLabel}, ${reasonLabel} icin sistemin su anda one cikardigi duzeltme aksiyonudur.`;
}

export function isFocusedRetryRecommendation(
  item: ReportDeliveryRetryRecommendationItem,
  focusActionCode?: string | null,
  focusReasonCode?: string | null,
  featuredRecommendation?: ReportFeaturedFailureResolution | null,
): boolean {
  return Boolean(
    (focusActionCode && item.primary_action_code === focusActionCode)
      || (focusReasonCode && item.reason_code === focusReasonCode)
      || (featuredRecommendation
        && (featuredRecommendation.reason_code === item.reason_code
          || featuredRecommendation.action_code === item.primary_action_code)),
  );
}

export function focusedRetryExplanation(
  item: ReportDeliveryRetryRecommendationItem,
  focusActionCode?: string | null,
  focusReasonCode?: string | null,
  focusSource?: string | null,
  featuredRecommendation?: ReportFeaturedFailureResolution | null,
): string {
  const sourceLabel = focusSourceLabel(focusSource);
  const reasonLabel = reasonLabelForCode(item.reason_code);
  const actionLabel = actionLabelForCode(item.primary_action_code);
  const reasonMatch = focusReasonCode ? item.reason_code === focusReasonCode : false;
  const actionMatch = focusActionCode ? item.primary_action_code === focusActionCode : false;
  const featuredReasonMatch = featuredRecommendation ? featuredRecommendation.reason_code === item.reason_code : false;
  const featuredActionMatch = featuredRecommendation ? featuredRecommendation.action_code === item.primary_action_code : false;

  if (reasonMatch && actionMatch) {
    return `${sourceLabel}, ${reasonLabel} icin ${actionLabel} yolunu odaga aldi. Retry rehberi bu satiri ayni karar akisi icin one tasiyor.`;
  }

  if (reasonMatch) {
    return `${sourceLabel}, ${reasonLabel} hatasini odaga aldi. Bu satir ayni hata tipi icin uygulanacak retry politikasini netlestiriyor.`;
  }

  if (actionMatch) {
    return `${sourceLabel}, secili aksiyon olarak ${actionLabel} yolunu one cikardi. Retry rehberi bu aksiyonun hangi sartlarda gecerli oldugunu gosteriyor.`;
  }

  if (featuredReasonMatch || featuredActionMatch) {
    return `Featured fix, ${reasonLabel} icin bu retry politikasini destekliyor. Hizli aksiyon ve retry rehberi ayni operasyonel dili kullaniyor.`;
  }

  return "Bu satir mevcut odaga en yakin retry kararidir.";
}

export function prioritizeFocusedRetryRecommendations(
  items: ReportDeliveryRetryRecommendationItem[],
  focusActionCode?: string | null,
  focusReasonCode?: string | null,
  featuredRecommendation?: ReportFeaturedFailureResolution | null,
) {
  return [...items].sort((left, right) => {
    const leftFocused = isFocusedRetryRecommendation(left, focusActionCode, focusReasonCode, featuredRecommendation);
    const rightFocused = isFocusedRetryRecommendation(right, focusActionCode, focusReasonCode, featuredRecommendation);

    if (leftFocused === rightFocused) {
      return 0;
    }

    return leftFocused ? -1 : 1;
  });
}

const DELIVERY_PROFILE_RELEVANT_CHANGES_BY_REASON: Record<string, string[]> = {
  smtp_auth: ["recipient_group", "share_delivery"],
  smtp_tls: ["send_time", "share_delivery"],
  sender_rejected: ["recipient_group", "share_delivery"],
  invalid_configuration: ["recipient_group", "cadence", "schedule_slot", "send_time", "range", "layout", "share_delivery"],
  share_delivery_failure: ["share_delivery"],
  snapshot_export_failure: ["layout", "range", "share_delivery"],
  recipient_rejected: ["recipient_group", "contact_tags"],
};

export function isFocusedDeliveryProfileSuggestion(
  suggestion: ReportDeliveryProfileSuggestion,
  focusActionCode?: string | null,
  focusReasonCode?: string | null,
  featuredRecommendation?: ReportFeaturedFailureResolution | null,
): boolean {
  if (focusActionCode === "focus_delivery_profile" || featuredRecommendation?.action_code === "focus_delivery_profile") {
    return true;
  }

  if (!focusReasonCode) {
    return false;
  }

  const relevantChanges = DELIVERY_PROFILE_RELEVANT_CHANGES_BY_REASON[focusReasonCode] ?? [];
  return suggestion.changes.some((change) => relevantChanges.includes(change));
}

export function deliveryProfileSuggestionExplanation(
  suggestion: ReportDeliveryProfileSuggestion,
  focusActionCode?: string | null,
  focusReasonCode?: string | null,
  focusSource?: string | null,
  featuredRecommendation?: ReportFeaturedFailureResolution | null,
): string {
  const sourceLabel = focusSourceLabel(focusSource);
  const reasonLabel = focusReasonCode ? reasonLabelForCode(focusReasonCode) : null;
  const actionMatches = focusActionCode === "focus_delivery_profile";
  const featuredMatches = featuredRecommendation?.action_code === "focus_delivery_profile";
  const relevantChanges = focusReasonCode ? DELIVERY_PROFILE_RELEVANT_CHANGES_BY_REASON[focusReasonCode] ?? [] : [];
  const reasonMatches = focusReasonCode ? suggestion.changes.some((change) => relevantChanges.includes(change)) : false;

  if (actionMatches && reasonMatches && reasonLabel) {
    return `${sourceLabel}, ${reasonLabel} akisi icin teslim profilini duzeltmeyi odaga aldi. Bu onerilen profil ayni problemi recipient grubu, zamanlama veya paylasim ayari uzerinden toparlamak icin one cikiyor.`;
  }

  if (actionMatches) {
    return `${sourceLabel}, secili aksiyon olarak teslim profilini duzeltmeyi one cikardi. Bu kart profili tek adimda yeni odaga hizalamak icin gosteriliyor.`;
  }

  if (featuredMatches && reasonMatches && reasonLabel) {
    return `Featured fix, ${reasonLabel} icin teslim profiline mudahaleyi destekliyor. Bu onerilen profil ayni hata akisini kuralli bir profil degisikligiyle kapatmayi hedefliyor.`;
  }

  if (reasonMatches && reasonLabel) {
    return `${reasonLabel} icin secili odak, teslim profili degisiklikleriyle de iyilesebiliyor. Bu oneride ayni problemi etkileyen profil farklari one cikarildi.`;
  }

  if (featuredMatches) {
    return "Featured fix akisi teslim profili duzeltmesini destekliyor. Bu onerilen profil, mevcut karar yuzeyleriyle ayni operasyonel yone hizalaniyor.";
  }

  return "Bu onerilen profil, mevcut teslim ayarlarini daha guvenli ve tutarli bir profile cekmek icin sistemin onerdigi kural setidir.";
}
