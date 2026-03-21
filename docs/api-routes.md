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
  - `suggested_recipient_groups[]` ile kampanya baglamina uygun onerilen alici gruplarini doner
- `GET /ad-sets/{adSetId}`
- `GET /ads/{adId}`
- `GET /reports`
  - `contact_segment_summary` ve `contact_segments[]` ile kisi etiket segmentlerini doner
  - `recipient_group_catalog_summary` ve `recipient_group_catalog[]` ile kayitli grup + segment + akilli grup katalogunu doner
  - `recipient_presets[].contact_tags`, `resolved_recipients_count` ve `recipient_group_summary` ile kayitli alici gruplarini doner
  - `delivery_profiles[].recipient_group_summary` ile varsayilan alici grubunun kaynagini ozetler
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
- `PUT /reports/recipient-presets/{presetId}` (`settings.manage`)
- `POST /reports/recipient-presets/{presetId}/toggle` (`settings.manage`)
- `DELETE /reports/recipient-presets/{presetId}` (`settings.manage`)
- `PUT /reports/delivery-profiles/{entityType}/{entityId}` (`settings.manage`)
  - `recipient_preset_id`, `recipients` ve/veya `contact_tags[]` ile varsayilan teslim profili kaydedilebilir
- `POST /reports/delivery-profiles/{entityType}/{entityId}/toggle` (`settings.manage`)
- `DELETE /reports/delivery-profiles/{entityType}/{entityId}` (`settings.manage`)
- `POST /reports/delivery-setups` (`settings.manage`)
  - `recipient_preset_id`, `recipients` veya `contact_tags[]` ile alici tanimlanabilir
- `POST /reports/delivery-schedules` (`settings.manage`)
  - `recipient_preset_id`, `recipients` veya `contact_tags[]` ile teslim alicilari tanimlanabilir
- `POST /reports/delivery-schedules/{scheduleId}/toggle` (`settings.manage`)
- `POST /reports/delivery-schedules/{scheduleId}/run-now` (`settings.manage`)
- `POST /reports/delivery-runs/{runId}/retry` (`settings.manage`)
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
  - `suggested_recipient_groups[]` ile hesap baglamina uygun onerilen alici gruplarini doner
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
