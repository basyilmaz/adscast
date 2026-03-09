<?php

namespace App\Domain\Reporting\Http\Controllers;

use App\Domain\Reporting\Services\CampaignQueryService;
use App\Domain\Tenants\Support\WorkspaceContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController
{
    public function __construct(
        private readonly CampaignQueryService $campaignQueryService,
    ) {
    }

    public function campaignsCsv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : now()->subDays(6);
        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])
            : now();

        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();
        $rows = $this->campaignQueryService->list($workspaceId, $startDate, $endDate)['items'];

        $fileName = 'campaign-performance-'.$startDate->toDateString().'-'.$endDate->toDateString().'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['campaign_id', 'name', 'objective', 'status', 'spend', 'results', 'cpa_cpl', 'ctr', 'cpm']);

            foreach ($rows as $row) {
                fputcsv($stream, [
                    $row['id'],
                    $row['name'],
                    $row['objective'],
                    $row['status'],
                    $row['spend'],
                    $row['results'],
                    $row['cpa_cpl'],
                    $row['ctr'],
                    $row['cpm'],
                ]);
            }

            fclose($stream);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
