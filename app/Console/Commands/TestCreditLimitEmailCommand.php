<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Erp\CreditLimitIncreaseRequestMail;
use App\Models\BuyerCreditLimitRequest;
use App\Models\Company;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

final class TestCreditLimitEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:credit-limit-email 
                            {--buyer-id= : Specific buyer ID to use}
                            {--email= : Email address to send test email to}
                            {--create : Create a new test request instead of using existing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test credit limit increase request email';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Check Mailpit connection before proceeding
        $mailpitHost = config('mail.mailers.mailpit.host');
        $mailpitPort = config('mail.mailers.mailpit.port');

        if ($mailpitHost && $mailpitPort) {
            $this->info("Using Mailpit at {$mailpitHost}:{$mailpitPort}");
        }
        $email = $this->option('email') ?? $this->ask('Enter email address to send test email to');

        if (! $email) {
            $this->error('Email address is required');

            return self::FAILURE;
        }

        $request = null;

        if ($this->option('create')) {
            $request = $this->createTestRequest();
        } else {
            $buyerId = $this->option('buyer-id');
            $request = $this->getOrCreateRequest($buyerId);
        }

        if (! $request instanceof \App\Models\BuyerCreditLimitRequest) {
            $this->error('Could not find or create a credit limit request');

            return self::FAILURE;
        }

        $this->info("Sending test email to: {$email}");
        $this->info("Using request ID: {$request->id}");
        $this->info("Buyer: {$request->buyer->name}");

        try {
            Mail::to($email)->send(new CreditLimitIncreaseRequestMail($request));

            $this->info('✅ Test email sent successfully!');
            $this->info('Check Mailpit at http://mailpit.test/ to view the email.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to send email: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function getOrCreateRequest(?int $buyerId = null): ?BuyerCreditLimitRequest
    {
        if ($buyerId) {
            $buyer = Company::find($buyerId);
            if (! $buyer || ! $buyer->is_buyer) {
                $this->error("Buyer with ID {$buyerId} not found or is not a buyer");

                return null;
            }

            $request = BuyerCreditLimitRequest::where('buyer_id', $buyer->id)->first();

            if ($request) {
                return $request;
            }

            return $this->createRequestForBuyer($buyer);
        }

        $request = BuyerCreditLimitRequest::first();

        if ($request) {
            return $request;
        }

        return $this->createTestRequest();
    }

    private function createTestRequest(): ?BuyerCreditLimitRequest
    {
        $team = Team::first();

        if (! $team) {
            $this->error('No team found. Please create a team first.');

            return null;
        }

        $buyer = Company::where('is_buyer', true)->where('team_id', $team->id)->first();

        if (! $buyer) {
            $this->error('No buyer found. Please create a buyer first.');

            return null;
        }

        return $this->createRequestForBuyer($buyer);
    }

    private function createRequestForBuyer(Company $buyer): BuyerCreditLimitRequest
    {
        $currentLimit = (float) ($buyer->credit_limit ?? 1000);
        $requestedLimit = $currentLimit + 1000;

        return BuyerCreditLimitRequest::create([
            'team_id' => $buyer->team_id,
            'buyer_id' => $buyer->id,
            'current_limit' => (string) $currentLimit,
            'requested_limit' => (string) $requestedLimit,
            'status' => \App\Enums\CreditLimitRequestStatus::PENDING,
            'requested_by_id' => auth()->id() ?? 1,
        ]);
    }
}
