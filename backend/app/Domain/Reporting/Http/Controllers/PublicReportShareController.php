<?php

namespace App\Domain\Reporting\Http\Controllers;

use App\Domain\Reporting\Services\ReportShareLinkService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicReportShareController
{
    public function __construct(
        private readonly ReportShareLinkService $reportShareLinkService,
    ) {
    }

    public function show(string $token): JsonResponse
    {
        $resolved = $this->reportShareLinkService->resolvePublicPayload($token);

        return new JsonResponse([
            'data' => $resolved['report'],
        ]);
    }

    public function exportCsv(string $token): StreamedResponse
    {
        $rows = $this->reportShareLinkService->resolvePublicExportRows($token);

        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fputcsv($stream, $row);
            }

            fclose($stream);
        }, sprintf('shared-report-%s.csv', now()->format('YmdHis')), [
            'Content-Type' => 'text/csv',
        ]);
    }
}
