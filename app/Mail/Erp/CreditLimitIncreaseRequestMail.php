<?php

declare(strict_types=1);

namespace App\Mail\Erp;

use App\Models\BuyerCreditLimitRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class CreditLimitIncreaseRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly BuyerCreditLimitRequest $request
    ) {}

    public function envelope(): Envelope
    {
        $buyerName = $this->request->buyer->name ?? 'Buyer';

        return new Envelope(
            subject: "Credit Limit Increase Request: {$buyerName}",
        );
    }

    public function content(): Content
    {
        $buyer = $this->request->buyer;
        $requester = $this->request->requestedBy;
        $team = $this->request->team;
        $currency = $team->getBaseCurrency();

        $currentLimit = $currency
            ? $currency->format((float) $this->request->current_limit)
            : number_format((float) $this->request->current_limit, 2);

        $requestedLimit = $currency
            ? $currency->format((float) $this->request->requested_limit)
            : number_format((float) $this->request->requested_limit, 2);

        $increaseAmount = $currency
            ? $currency->format((float) $this->request->requested_limit - (float) $this->request->current_limit)
            : number_format((float) $this->request->requested_limit - (float) $this->request->current_limit, 2);

        return new Content(
            view: 'emails.credit-limit-increase-request',
            with: [
                'request' => $this->request,
                'buyer' => $buyer,
                'requester' => $requester,
                'team' => $team,
                'currentLimit' => $currentLimit,
                'requestedLimit' => $requestedLimit,
                'increaseAmount' => $increaseAmount,
            ],
        );
    }
}
