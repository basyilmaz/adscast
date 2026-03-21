<?php

namespace App\Domain\Reporting\Mail;

use App\Models\ReportSnapshot;
use App\Models\ReportTemplate;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduledReportDeliveryMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>|null  $shareLink
     * @param  array<string, mixed>  $reportPayload
     */
    public function __construct(
        public readonly Workspace $workspace,
        public readonly ReportTemplate $template,
        public readonly ReportSnapshot $snapshot,
        public readonly array $reportPayload,
        public readonly ?array $shareLink = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $endDate = $this->snapshot->end_date?->format('Y-m-d');
        $subject = trim(sprintf(
            'AdsCast Raporu | %s%s',
            $this->template->name,
            $endDate ? sprintf(' | %s', $endDate) : '',
        ));

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reports.scheduled-delivery',
        );
    }
}
