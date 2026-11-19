<?php

namespace App\Mail;

use App\Services\SimplePdfReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class FinancialReportMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public readonly string $periodLabel;

    /**
     * @var array<int, string>
     */
    public readonly array $lines;

    /**
     * @param  array<int, string>  $lines
     */
    public function __construct(string $periodLabel, array $lines)
    {
        $this->periodLabel = $periodLabel;
        $this->lines = $lines;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Student Finance report - '.$this->periodLabel);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.financial-report', with: ['periodLabel' => $this->periodLabel]);
    }

    public function attachments(): array
    {
        $pdf = app(SimplePdfReport::class)->render($this->lines);

        return [
            Attachment::fromData(fn (): string => $pdf, 'financial-report.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
