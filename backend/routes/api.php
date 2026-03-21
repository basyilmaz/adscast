<?php

use Illuminate\Support\Facades\Route;
use App\Domain\AI\Http\Controllers\RecommendationController;
use App\Domain\Approvals\Http\Controllers\ApprovalController;
use App\Domain\Audit\Http\Controllers\AuditLogController;
use App\Domain\Auth\Http\Controllers\AuthController;
use App\Domain\Drafts\Http\Controllers\CampaignDraftController;
use App\Domain\Meta\Http\Controllers\MetaConnectionController;
use App\Domain\Meta\Http\Controllers\MetaOAuthController;
use App\Domain\Meta\Http\Controllers\MetaSyncController;
use App\Domain\Meta\Http\Controllers\MetaWebhookController;
use App\Domain\Reporting\Http\Controllers\AdController;
use App\Domain\Reporting\Http\Controllers\AdSetController;
use App\Domain\Reporting\Http\Controllers\CampaignController;
use App\Domain\Reporting\Http\Controllers\DashboardController;
use App\Domain\Reporting\Http\Controllers\ExportController;
use App\Domain\Reporting\Http\Controllers\PublicReportShareController;
use App\Domain\Reporting\Http\Controllers\ReportController;
use App\Domain\Rules\Http\Controllers\AlertController;
use App\Domain\Settings\Http\Controllers\SettingController;
use App\Domain\Tenants\Http\Controllers\WorkspaceController;

Route::prefix('v1')->group(function (): void {
    Route::get('/public/report-shares/{token}', [PublicReportShareController::class, 'show']);
    Route::get('/public/report-shares/{token}/export.csv', [PublicReportShareController::class, 'exportCsv']);

    Route::get('/meta/webhook', [MetaWebhookController::class, 'verify']);
    Route::post('/meta/webhook', [MetaWebhookController::class, 'ingest']);

    Route::prefix('auth')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/workspaces', [WorkspaceController::class, 'index']);
        Route::post('/workspaces/switch', [WorkspaceController::class, 'switch']);

        Route::middleware(['workspace.resolve', 'workspace.member'])->group(function (): void {
            Route::get('/workspaces/current', [WorkspaceController::class, 'current']);

            Route::get('/dashboard/overview', [DashboardController::class, 'overview'])
                ->middleware('workspace.permission:reporting.view');

            Route::get('/campaigns', [CampaignController::class, 'index'])
                ->middleware('workspace.permission:reporting.view');
            Route::get('/campaigns/{campaignId}', [CampaignController::class, 'show'])
                ->middleware('workspace.permission:reporting.view');
            Route::get('/ad-sets/{adSetId}', [AdSetController::class, 'show'])
                ->middleware('workspace.permission:reporting.view');
            Route::get('/ads/{adId}', [AdController::class, 'show'])
                ->middleware('workspace.permission:reporting.view');
            Route::get('/reports', [ReportController::class, 'index'])
                ->middleware('workspace.permission:reporting.view');
            Route::get('/reports/account/{adAccountId}', [ReportController::class, 'account'])
                ->middleware('workspace.permission:reporting.view');
            Route::get('/reports/campaign/{campaignId}', [ReportController::class, 'campaign'])
                ->middleware('workspace.permission:reporting.view');
            Route::post('/reports/snapshots', [ReportController::class, 'storeSnapshot'])
                ->middleware('workspace.permission:reporting.view');
            Route::post('/reports/snapshots/{snapshotId}/share-links', [ReportController::class, 'storeShareLink'])
                ->middleware('workspace.permission:settings.manage');
            Route::post('/reports/templates', [ReportController::class, 'storeTemplate'])
                ->middleware('workspace.permission:settings.manage');
            Route::post('/reports/recipient-presets', [ReportController::class, 'storeRecipientPreset'])
                ->middleware('workspace.permission:settings.manage');
            Route::put('/reports/recipient-presets/{presetId}', [ReportController::class, 'updateRecipientPreset'])
                ->middleware('workspace.permission:settings.manage');
            Route::post('/reports/recipient-presets/{presetId}/toggle', [ReportController::class, 'toggleRecipientPreset'])
                ->middleware('workspace.permission:settings.manage');
            Route::delete('/reports/recipient-presets/{presetId}', [ReportController::class, 'deleteRecipientPreset'])
                ->middleware('workspace.permission:settings.manage');
            Route::put('/reports/delivery-profiles/{entityType}/{entityId}', [ReportController::class, 'upsertDeliveryProfile'])
                ->middleware('workspace.permission:settings.manage');
            Route::post('/reports/delivery-profiles/{entityType}/{entityId}/toggle', [ReportController::class, 'toggleDeliveryProfile'])
                ->middleware('workspace.permission:settings.manage');
            Route::delete('/reports/delivery-profiles/{entityType}/{entityId}', [ReportController::class, 'deleteDeliveryProfile'])
                ->middleware('workspace.permission:settings.manage');
            Route::post('/reports/delivery-setups', [ReportController::class, 'storeDeliverySetup'])
                ->middleware('workspace.permission:settings.manage');
            Route::post('/reports/delivery-schedules', [ReportController::class, 'storeDeliverySchedule'])
                ->middleware('workspace.permission:settings.manage');
            Route::post('/reports/delivery-schedules/{scheduleId}/toggle', [ReportController::class, 'toggleDeliverySchedule'])
                ->middleware('workspace.permission:settings.manage');
            Route::post('/reports/delivery-schedules/{scheduleId}/run-now', [ReportController::class, 'runDeliveryScheduleNow'])
                ->middleware('workspace.permission:settings.manage');
            Route::post('/reports/share-links/{shareLinkId}/revoke', [ReportController::class, 'revokeShareLink'])
                ->middleware('workspace.permission:settings.manage');
            Route::get('/reports/snapshots/{snapshotId}', [ReportController::class, 'showSnapshot'])
                ->middleware('workspace.permission:reporting.view');
            Route::get('/reports/account/{adAccountId}/export.csv', [ReportController::class, 'exportAccountCsv'])
                ->middleware('workspace.permission:reporting.view');
            Route::get('/reports/campaign/{campaignId}/export.csv', [ReportController::class, 'exportCampaignCsv'])
                ->middleware('workspace.permission:reporting.view');
            Route::get('/reports/snapshots/{snapshotId}/export.csv', [ReportController::class, 'exportSnapshotCsv'])
                ->middleware('workspace.permission:reporting.view');
            Route::get('/exports/campaigns.csv', [ExportController::class, 'campaignsCsv'])
                ->middleware('workspace.permission:reporting.view');

            Route::prefix('meta')->group(function (): void {
                Route::get('/connector-status', [MetaConnectionController::class, 'connectorStatus'])
                    ->middleware('workspace.permission:meta.manage');
                Route::get('/oauth/start', [MetaOAuthController::class, 'start'])
                    ->middleware('workspace.permission:meta.manage');
                Route::post('/oauth/exchange', [MetaOAuthController::class, 'exchange'])
                    ->middleware('workspace.permission:meta.manage');
                Route::get('/connections', [MetaConnectionController::class, 'index'])
                    ->middleware('workspace.permission:meta.manage');
                Route::get('/ad-accounts', [MetaConnectionController::class, 'adAccounts'])
                    ->middleware('workspace.permission:reporting.view');
                Route::get('/ad-accounts/{adAccountId}', [MetaConnectionController::class, 'showAdAccount'])
                    ->middleware('workspace.permission:reporting.view');
                Route::post('/connections', [MetaConnectionController::class, 'store'])
                    ->middleware('workspace.permission:meta.manage');
                Route::post('/connections/{connectionId}/revoke', [MetaConnectionController::class, 'revoke'])
                    ->middleware('workspace.permission:meta.manage');

                Route::post('/connections/{connectionId}/sync-assets', [MetaSyncController::class, 'syncAssets'])
                    ->middleware('workspace.permission:meta.manage');
                Route::post('/connections/{connectionId}/sync-insights', [MetaSyncController::class, 'syncInsights'])
                    ->middleware('workspace.permission:meta.manage');
            });

            Route::get('/alerts', [AlertController::class, 'index'])
                ->middleware('workspace.permission:alerts.view');
            Route::post('/alerts/evaluate', [AlertController::class, 'evaluate'])
                ->middleware('workspace.permission:alerts.manage');

            Route::get('/recommendations', [RecommendationController::class, 'index'])
                ->middleware('workspace.permission:recommendations.view');
            Route::post('/recommendations/generate', [RecommendationController::class, 'generate'])
                ->middleware('workspace.permission:recommendations.generate');

            Route::get('/drafts', [CampaignDraftController::class, 'index'])
                ->middleware('workspace.permission:drafts.view');
            Route::post('/drafts', [CampaignDraftController::class, 'store'])
                ->middleware('workspace.permission:drafts.manage');
            Route::get('/drafts/{draftId}', [CampaignDraftController::class, 'show'])
                ->middleware('workspace.permission:drafts.view');
            Route::post('/drafts/{draftId}/submit-review', [CampaignDraftController::class, 'submitForReview'])
                ->middleware('workspace.permission:drafts.manage');

            Route::get('/approvals', [ApprovalController::class, 'index'])
                ->middleware('workspace.permission:approvals.view');
            Route::post('/approvals/{approvalId}/approve', [ApprovalController::class, 'approve'])
                ->middleware('workspace.permission:approvals.review');
            Route::post('/approvals/{approvalId}/reject', [ApprovalController::class, 'reject'])
                ->middleware('workspace.permission:approvals.review');
            Route::post('/approvals/{approvalId}/publish', [ApprovalController::class, 'publish'])
                ->middleware('workspace.permission:approvals.publish');

            Route::get('/audit-logs', [AuditLogController::class, 'index'])
                ->middleware('workspace.permission:audit.view');

            Route::get('/settings', [SettingController::class, 'index'])
                ->middleware('workspace.permission:settings.view');
            Route::post('/settings', [SettingController::class, 'upsert'])
                ->middleware('workspace.permission:settings.manage');
        });
    });
});
