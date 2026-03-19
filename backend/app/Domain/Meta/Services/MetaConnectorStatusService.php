<?php

namespace App\Domain\Meta\Services;

class MetaConnectorStatusService
{
    /**
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        $appIdConfigured = filled(config('services.meta.app_id'));
        $appSecretConfigured = filled(config('services.meta.app_secret'));
        $redirectUriConfigured = filled(config('services.meta.redirect_uri'));

        return [
            'mode' => config('services.meta.mode', 'stub'),
            'oauth_ready' => $appIdConfigured && $appSecretConfigured && $redirectUriConfigured,
            'app_id_configured' => $appIdConfigured,
            'app_secret_configured' => $appSecretConfigured,
            'redirect_uri_configured' => $redirectUriConfigured,
            'default_api_version' => config('services.meta.default_api_version', 'v20.0'),
            'supported_api_versions' => ['v20.0'],
            'raw_payload_retention_days' => (int) config('services.meta.raw_payload_retention_days', 90),
            'sync_cadence' => [
                'assets' => 'Saatlik veya manuel tetikleme',
                'insights_daily' => 'Gunluk ve son 7 gunluk duzeltme penceresi',
                'stale_connection_check' => 'Saatlik saglik kontrolu',
            ],
            'business_verification_dependency' => [
                'mode' => 'soft',
                'read_sync' => true,
                'publish' => 'kismi kisit olasiligi',
            ],
            'mvp_features' => [
                'manual_access_token_connection',
                'oauth_preflight_readiness',
                'asset_sync',
                'daily_insights_sync',
                'raw_payload_retention',
                'approval_gated_publish_scaffold',
            ],
            'later_phase_features' => [
                'full_oauth_callback_flow',
                'lead_webhook_processing',
                'offline_conversions',
                'advanced_breakdowns',
            ],
        ];
    }
}
