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
- `POST /approvals/{approvalId}/approve`
- `POST /approvals/{approvalId}/reject`
- `POST /approvals/{approvalId}/publish`

## Audit / Settings

- `GET /audit-logs`
- `GET /settings`
- `POST /settings`
