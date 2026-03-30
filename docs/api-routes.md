# AdsCast API Rotalari (MVP)

Base path: `/api/v1`

## Auth

- `POST /auth/login`
- `GET /auth/me`
- `POST /auth/logout`

## Workspace

- `GET /workspaces`
- `POST /workspaces/switch`
- `GET /workspaces/current` (`X-Workspace-Id` zorunlu)

## Dashboard / Reporting

- `GET /dashboard/overview`
- `GET /campaigns`
  - desteklenen query parametreleri: `start_date`, `end_date`, `ad_account_id`, `objective`, `status`
- `GET /campaigns/{campaignId}`
  - `suggested_recipient_groups[]` ile kampanya baglamina uygun onerilen alici gruplarini doner; sirket/marka eslesmesi olan akilli gruplar dahil edilir
  - `suggested_delivery_profile` ile rule-managed template kaynakli varsayilan teslim profili onerisi doner
  - `failure_resolution_effectiveness_summary` ve `failure_resolution_effectiveness[]` ile kampanya scope'unda hangi duzeltmenin gercekten ise yaradigini doner
  - `featured_failure_resolution` ile entity bazinda otomatik olarak one cikarilan duzeltme aksiyonunu doner
  - `decision_surface_status_summary` ve `decision_surface_statuses[]` ile report sekmesindeki featured fix / retry / profil onerisi yuzeylerinin operator takip durumunu doner
  - `retry_recommendation_summary` ve `retry_recommendations[]` ile provider/asama bazli retry politikasini doner
  - `recipient_group_failure_alignment_summary` ve `recipient_group_failure_alignment[]` ile kampanya scope'unda failure reason dagiliminin onerilen grup mu yoksa override secimi mi tarafinda biriktigini doner
  - `failure_resolution_summary` ve `failure_resolution_actions[]` ile tek tik duzeltme aksiyonlarini doner
  - `failure_resolution_actions[]` route tabanli `review_contact_book` ve `review_recipient_groups` aksiyonlari icin etkilenen alici/grup metadata'si tasiyabilir
  - `recipient_group_failure_reason_summary` ve `recipient_group_failure_reasons[]` ile kampanya scope'unda teslim hata siniflarini doner
- `GET /ad-sets/{adSetId}`
- `GET /ads/{adId}`
- `GET /reports`
  - `contact_segment_summary` ve `contact_segments[]` ile kisi etiket segmentlerini doner
  - `recipient_group_catalog_summary` ve `recipient_group_catalog[]` ile kayitli grup + segment + primary/sirket bazli akilli grup katalogunu doner
  - `recipient_group_analytics_summary` ve `recipient_group_analytics[]` ile alici grubu kullanim, teslim basarisi ve entity yayilimini doner
  - `recipient_group_alignment_summary` ve `recipient_group_alignment[]` ile onerilen grup ile operator secimi arasindaki sapmayi doner
  - `recipient_group_failure_alignment_summary` ve `recipient_group_failure_alignment[]` ile failure reason dagiliminin onerilen grup mu yoksa override secimi mi tarafinda biriktigini doner
  - `recipient_group_correlation_summary` ve `recipient_group_correlation[]` ile onerilen grup uyumu ile gercek teslim basarisi arasindaki korelasyonu doner
  - `recipient_group_failure_reason_summary` ve `recipient_group_failure_reasons[]` ile recipient group bazli teslim hata tiplerini, etkiledigi grup/entity yayilimini ve onerilen aksiyonu doner
  - `failure_resolution_action_analytics_summary` ve `failure_resolution_action_analytics[]` ile hizli duzeltme aksiyonlarinin kullanim ve sonuc verisini doner
  - `failure_resolution_effectiveness_summary` ve `failure_resolution_effectiveness[]` ile hata nedeni bazinda onerilen fix'in gercekten ise yarayip yaramadigini doner
  - `featured_failure_resolution_analytics_summary` ve `featured_failure_resolution_analytics[]` ile sistemin one cikardigi duzeltmenin takip edilip edilmedigini ve override/featured sonuc farkini doner
  - `featured_failure_resolution_decision_summary` ve `featured_failure_resolution_decisions[]` ile sistemin hangi fix'i neden one cikardigini aciklanabilir karar katmani olarak doner; item bazinda `primary_entity.route` ile operator ilgili account/campaign detail ekranina gidebilir
  - `decision_surface_queue_summary` ve `decision_surface_queue[]` ile detail ekranlarinda isaretlenen featured fix / retry / profil onerisi durumlarini workspace genelinde operasyon kuyrugu olarak doner
  - `decision_queue_recommendation_analytics_summary` ve `decision_queue_recommendation_analytics[]` ile operasyon kuyrugunun onerilen bulk aksiyonlarinin ne kadar kullanildigini ve ne kadar is kapattigini doner
  - her failure reason item'i provider (`provider_label`) ve teslim asamasi (`delivery_stage_label`) metadata'si da tasir
  - `recipient_preset_summary.managed_templates` ve `recipient_preset_summary.recommended_default_presets` ile kural yonetilen grup sablonlarini ozetler
  - `recipient_presets[].contact_tags`, `resolved_recipients_count` ve `recipient_group_summary` ile kayitli alici gruplarini doner
  - `recipient_presets[].template_profile` ve `template_rule_summary` ile grup sablon kurallarini doner
  - `delivery_profiles[].recipient_group_summary` ile varsayilan alici grubunun kaynagini ozetler
- `GET /reports/recipient-group-suggestions`
  - `entity_type` + `entity_id` icin operator akislarinda kullanilacak onerilen alici gruplarini doner
  - quick delivery, schedule ve detail delivery profile editor'u bu endpoint'i ana secim akisi olarak kullanir
- `GET /reports/account/{adAccountId}`
- `GET /reports/campaign/{campaignId}`
- `POST /reports/snapshots`
- `POST /reports/snapshots/{snapshotId}/share-links` (`settings.manage`)
- `POST /reports/templates` (`settings.manage`)
- `POST /reports/contacts` (`settings.manage`)
- `PUT /reports/contacts/{contactId}` (`settings.manage`)
- `POST /reports/contacts/{contactId}/toggle` (`settings.manage`)
- `DELETE /reports/contacts/{contactId}` (`settings.manage`)
- `POST /reports/recipient-presets` (`settings.manage`)
  - `recipients[]` ve/veya `contact_tags[]` ile statik + segment destekli alici grubu tanimlanabilir
  - `template_kind`, `target_entity_types[]`, `matching_companies[]`, `priority`, `is_recommended_default` ile kural yonetilen grup sablonu tanimlanabilir
- `PUT /reports/recipient-presets/{presetId}` (`settings.manage`)
  - ayni kural alanlariyla kayitli grup sablonu guncellenebilir
- `POST /reports/recipient-presets/{presetId}/toggle` (`settings.manage`)
- `DELETE /reports/recipient-presets/{presetId}` (`settings.manage`)
- `PUT /reports/delivery-profiles/{entityType}/{entityId}` (`settings.manage`)
  - `recipient_preset_id`, `recipients` ve/veya `contact_tags[]` ile varsayilan teslim profili kaydedilebilir
- `PUT /reports/decision-surface-statuses/{entityType}/{entityId}/{surfaceKey}` (`settings.manage`)
  - `status`: `pending|reviewed|completed|deferred`
  - opsiyonel `operator_note` ve `defer_reason_code` (`waiting_client_feedback|waiting_data_validation|scheduled_followup|blocked_external_dependency|priority_window_shifted`) alanlarini kabul eder
  - report sekmesindeki `featured_fix`, `retry` veya `profile` yuzeyi icin operator takip durumunu kaydeder
- `POST /reports/decision-surface-queue/recommendations/track` (`settings.manage`)
  - queue icindeki onerilen bulk aksiyon secimlerini ve uygulama sonucunu analytics icin izler
  - `execution_mode`: `selection_only|bulk_status_applied`
- `POST /reports/delivery-profiles/{entityType}/{entityId}/toggle` (`settings.manage`)
- `DELETE /reports/delivery-profiles/{entityType}/{entityId}` (`settings.manage`)
- `POST /reports/delivery-setups` (`settings.manage`)
  - `recipient_preset_id`, `recipients`, `contact_tags[]`, `recipient_group_selection` ve `recommended_recipient_group` ile alici ve karar metadata'si tanimlanabilir
- `POST /reports/delivery-schedules` (`settings.manage`)
  - `recipient_preset_id`, `recipients`, `contact_tags[]`, `recipient_group_selection` ve `recommended_recipient_group` ile teslim alicilari ve karar metadata'si tanimlanabilir
- `POST /reports/delivery-schedules/{scheduleId}/toggle` (`settings.manage`)
- `POST /reports/delivery-schedules/{scheduleId}/run-now` (`settings.manage`)
- `POST /reports/delivery-runs/{runId}/retry` (`settings.manage`)
- `POST /reports/failure-resolution-actions/{entityType}/{entityId}/{actionCode}/track` (`settings.manage`)
  - route, focus ve api tabanli failure resolution aksiyonlarini analytics icin izler
- `POST /reports/failure-resolution-actions/{entityType}/{entityId}/{actionCode}` (`settings.manage`)
  - Ilk desteklenen aksiyon: `retry_failed_runs`
- `POST /reports/share-links/{shareLinkId}/revoke` (`settings.manage`)
- `GET /reports/snapshots/{snapshotId}`
- `GET /reports/account/{adAccountId}/export.csv`
- `GET /reports/campaign/{campaignId}/export.csv`
- `GET /reports/snapshots/{snapshotId}/export.csv`
- `GET /public/report-shares/{token}`
- `GET /public/report-shares/{token}/export.csv`
- `GET /exports/campaigns.csv`

## Meta Connector

- `GET /meta/connector-status`
- `GET /meta/oauth/start`
- `POST /meta/oauth/exchange`
- `GET /meta/connections`
- `POST /meta/connections`
- `POST /meta/connections/{connectionId}/revoke`
- `GET /meta/ad-accounts`
- `GET /meta/ad-accounts/{adAccountId}`
  - `suggested_recipient_groups[]` ile hesap baglamina uygun onerilen alici gruplarini doner; sirket/marka eslesmesi olan akilli gruplar dahil edilir
  - `suggested_delivery_profile` ile rule-managed template kaynakli varsayilan teslim profili onerisi doner
  - `failure_resolution_effectiveness_summary` ve `failure_resolution_effectiveness[]` ile hesap scope'unda hangi duzeltmenin gercekten ise yaradigini doner
  - `featured_failure_resolution` ile entity bazinda otomatik olarak one cikarilan duzeltme aksiyonunu doner
  - `decision_surface_status_summary` ve `decision_surface_statuses[]` ile report sekmesindeki featured fix / retry / profil onerisi yuzeylerinin operator takip durumunu doner
  - `retry_recommendation_summary` ve `retry_recommendations[]` ile provider/asama bazli retry politikasini doner
  - `recipient_group_failure_alignment_summary` ve `recipient_group_failure_alignment[]` ile hesap scope'unda failure reason dagiliminin onerilen grup mu yoksa override secimi mi tarafinda biriktigini doner
  - `failure_resolution_summary` ve `failure_resolution_actions[]` ile tek tik duzeltme aksiyonlarini doner
  - `failure_resolution_actions[]` route tabanli `review_contact_book` ve `review_recipient_groups` aksiyonlari icin etkilenen alici/grup metadata'si tasiyabilir
  - `recipient_group_failure_reason_summary` ve `recipient_group_failure_reasons[]` ile hesap scope'unda teslim hata siniflarini doner
- `POST /meta/connections/{connectionId}/sync-assets`
- `POST /meta/connections/{connectionId}/sync-insights`

## Rules / Alerts

- `GET /alerts`
- `POST /alerts/evaluate`

## Recommendations / AI

- `GET /recommendations`
- `POST /recommendations/generate`

## Drafts / Approvals

- `GET /drafts`
- `POST /drafts`
- `GET /drafts/{draftId}`
- `POST /drafts/{draftId}/submit-review`
- `GET /approvals`
  - opsiyonel filtreler: `status`, `cleanup_state` (`failed|successful|not_attempted`), `manual_check_state` (`required|completed|not_required`), `recommended_action_code`
  - approvals index item'lari `publish_state.manual_check_completed`, `manual_check_completed_at` ve `manual_check_note` alanlarini da doner
- `GET /approvals/remediation-analytics`
  - opsiyonel `window_days=7|30|90`
  - publish failed remediation cluster'larini `manual-check-required`, `retry-ready`, `cleanup-recovered`, `review-error` ekseninde ozetler
  - `summary` altinda takip edilen cluster, takip edilen source, manuel kontrol, publish denemesi, secili analytics penceresi ve en etkili remediation cluster ozetini doner
  - `summary.top_route_key`, `top_route_label`, `top_route_source_label`, `top_route_publish_success_rate` ve `top_route_advantage` ile approvals-native vs draft detail route ailesi bazinda hangi remediation yolunun one ciktigini gosterir
  - `summary.top_draft_detail_cluster_label` ile draft detail uzerinde en iyi publish toparlayan remediation cluster gorulebilir
  - `summary.long_term_window_days`, `summary.top_long_term_stable_cluster_label` ve `summary.top_long_term_stable_cluster_score` ile current-window veri sparse olsa bile uzun vade stabil remediation cluster gorulebilir
  - `summary.top_route_series_status`, `summary.top_route_series_status_label`, `summary.top_route_series_reason`, `summary.top_route_series_window_days`, `summary.top_route_series_route_key`, `summary.top_route_series_route_label`, `summary.top_route_outcome_status`, `summary.top_route_outcome_status_label`, `summary.top_route_outcome_reason`, `summary.top_route_outcome_recommended_action_mode` ile route outcome spotlight ve guidance ozeti okunur
  - `featured_recommendation.decision_status=long_term_preferred` olursa current-window veri zayifken uzun vade 90 gunluk veri daha stabil oldugu icin featured karar long-term cluster'a kaydirilmis olur
  - `featured_recommendation.decision_context_window_days`, `decision_context_success_rate`, `decision_context_baseline_success_rate` ve `decision_context_advantage` ile long-term prefer kararinin current-window bazini aciklar
  - `long_term_approvals_native_outcome_summary` ve `long_term_draft_detail_outcome_summary` ile 90 gunluk long-term outcome bazini current-window summary'lerden ayri olarak gorunur kilar
  - `interaction_sources[]` altinda telemetry kaynagi bazinda `tracked_interactions`, `followed_featured_interactions`, `override_interactions`, `manual_check_completions`, `publish_retry_actions`, `bulk_retry_actions`, `publish_attempts`, `successful_publishes`, `failed_publishes`, `publish_success_rate` alanlarini doner
  - `summary.top_route_*` ve `summary.top_long_term_route_*` alanlari ile current-window ve long-term tarafinda hangi route ailesinin one ciktigi ozet seviyede okunabilir
  - `route_trends[]` ve `long_term_route_trends[]` altinda `draft_detail`, `approvals` ve gerekiyorsa `other` route ailesi bazinda `tracked_interactions`, `publish_attempts`, `successful_publishes`, `failed_publishes`, `publish_success_rate`, `top_source_key`, `top_source_label` alanlarini doner
  - `route_window_series[]` altinda `7/30/90` gunluk render-ready route snapshot'lari doner: `label`, `preferred_flow`, `confidence`, `current_route_*`, `top_route_*`, `summary_label`, `reason`, `route_trends[]`
  - `outcome_chain_summary` altinda tum telemetry akisinin `manual_check_completions`, `total_retry_actions`, `publish_attempts`, `successful_publishes`, `failed_publishes`, `publish_success_rate` ozetini doner
  - `approvals_native_outcome_summary` ile approvals ekranindan dogrudan gelen remediation akislarinin toplu outcome ozeti doner
  - `draft_detail_outcome_summary` ile draft detail kaynakli remediation akislarinin toplu outcome ozeti ve top source bilgisi doner
  - `long_term_approvals_native_outcome_summary` ve `long_term_draft_detail_outcome_summary` ile ayni source/outcome karsilastirmasinin 90 gunluk uzun donem ozeti de okunabilir
  - `summary.top_route_series_*` alanlari current-window route karari ile route-series spotlight ozeti arasindaki baglantiyi one cikarir
  - `featured_recommendation` altinda sistemin su an one cikardigi remediation cluster'ini, karar nedenini, onerilen aksiyon modunu, effectiveness bilgisini, `source_breakdown[]`, `route_trends[]`, `route_series_spotlight`, `route_outcome_spotlight`, `outcome_chain_summary`, `draft_detail_outcome_summary` ve gerekiyorsa `decision_context_*` alanlarini doner
  - `featured_recommendation.primary_action` altinda additive bir operasyon karari doner: `mode` (`bulk_retry_publish|focus_cluster|jump_to_item`), `route`, `route_key`, `route_label`, `source_key`, `source_label`, `publish_attempts`, `publish_success_rate`, `tracked_interactions`, `successful_publishes`, `failed_publishes`, `followed_featured_interactions`, `preferred_flow`, `confidence_status`, `confidence_label`, `trend_status`, `trend_reason`, `route_series[]`, `alternative_route_key`, `alternative_route_label`, `alternative_publish_success_rate`, `advantage_vs_alternative_route`, `reason`
  - `featured_recommendation.route_series_spotlight` ile route bazli 7/30/90 pencere ozeti, support status ve operator guidance tek blokta render-ready gelir
  - `featured_recommendation.route_outcome_spotlight` ile aynı veri, guidance status, recommended action mode ve decision context ile birlikte render-ready gelir
  - `featured_recommendation.decision_context_route_outcome_*` alanlari featured kararinin outcome-guidance dayanaklarini aciklar
  - `route_outcome_spotlight` ayni guidance katmanini top-level olarak da tekrarlar; `guidance_status`, `guidance_label`, `guidance_reason`, `recommended_action_mode` ve `recommended_action_label` alanlari ile CTA karari neden guvenli/temkinli oldugunu aciklar
  - `featured_recommendation.action_mode` ve `items[].action_mode` route-series spotlight `softening|sparse` sinyali geldiginde guvenli tarafta kalip `focus_cluster` moduna geri cekilebilir
  - `featured_recommendation.decision_context_route_series_*` alanlari featured kararinin hangi pencere dayanaklariyla secildigini aciklar
  - featured remediation kararinda `decision_status=draft_detail_preferred` olursa draft detail uzerinden daha iyi sonuc veren remediation cluster analytics destekli olarak one cikarilmis olur
  - `featured_recommendation.retry_guidance_status`, `retry_guidance_label`, `retry_guidance_reason` ve `safe_bulk_retry` ile cluster bazli toplu retry guvenligini aciklar
  - `summary` ve `featured_recommendation` featured takip / override / publish basarisi metriklerini de doner
  - `items[]` altinda `current_items`, `manual_check_completions`, `publish_attempts`, `successful_publishes`, `publish_success_rate`, `effectiveness_score`, `effectiveness_status`, `health_status`, `route`, `top_interaction_source_label`, `source_breakdown[]`, `outcome_chain_summary`, `retry_guidance_status`, `retry_guidance_label`, `retry_guidance_reason`, `safe_bulk_retry` alanlarini doner
  - `items[]` ayri olarak `long_term_current_items`, `long_term_manual_check_completions`, `long_term_publish_attempts`, `long_term_successful_publishes`, `long_term_failed_publishes`, `long_term_publish_success_rate`, `long_term_effectiveness_score`, `long_term_effectiveness_status`, `long_term_health_status`, `long_term_health_summary`, `long_term_route`, `long_term_top_interaction_source_label`, `long_term_source_breakdown[]`, `long_term_outcome_chain_summary`, `long_term_draft_detail_outcome_summary`, `long_term_retry_guidance_status`, `long_term_retry_guidance_label`, `long_term_retry_guidance_reason`, `long_term_safe_bulk_retry` ve ilgili long-term source alanlarini tasir
- `POST /approvals/remediation-analytics/track`
  - featured remediation karti veya cluster aksiyonlari kullanildiginda takip / override ve publish sonucu telemetrisi kaydeder
  - `featured_cluster_key`, `acted_cluster_key`, `interaction_type`, `followed_featured`, `attempted_count`, `success_count`, `failure_count` alanlarini kabul eder
  - opsiyonel `interaction_source` ile telemetry kaynaginin approvals, featured, cluster veya draft detail odagindan gelip gelmedigi kaydedilebilir
- `POST /approvals/{approvalId}/approve`
- `POST /approvals/{approvalId}/reject`
- `POST /approvals/{approvalId}/manual-check-completed`
  - opsiyonel `note` alir
  - partial publish + cleanup failed approval'larinda manuel kontrolun tamamlandigini isaretler
- `POST /approvals/{approvalId}/publish`

## Audit / Settings

- `GET /audit-logs`
- `GET /settings`
- `POST /settings`
