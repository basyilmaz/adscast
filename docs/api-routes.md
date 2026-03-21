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
- `GET /ad-sets/{adSetId}`
- `GET /ads/{adId}`
- `GET /reports`
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
- `PUT /reports/recipient-presets/{presetId}` (`settings.manage`)
- `POST /reports/recipient-presets/{presetId}/toggle` (`settings.manage`)
- `DELETE /reports/recipient-presets/{presetId}` (`settings.manage`)
- `PUT /reports/delivery-profiles/{entityType}/{entityId}` (`settings.manage`)
- `POST /reports/delivery-profiles/{entityType}/{entityId}/toggle` (`settings.manage`)
- `DELETE /reports/delivery-profiles/{entityType}/{entityId}` (`settings.manage`)
- `POST /reports/delivery-setups` (`settings.manage`)
- `POST /reports/delivery-schedules` (`settings.manage`)
- `POST /reports/delivery-schedules/{scheduleId}/toggle` (`settings.manage`)
- `POST /reports/delivery-schedules/{scheduleId}/run-now` (`settings.manage`)
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
