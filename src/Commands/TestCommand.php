<?php

namespace FizWatch\Commands;

use FizWatch\FizWatch;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'fizwatch:test';

    protected $description = 'Send a test exception to FizWatch to verify your integration';

    public function handle(FizWatch $fizwatch): int
    {
        if (! $fizwatch->isConfigured()) {
            $this->error('FizWatch is not configured.');
            $this->line('');
            $this->line('Add the following to your <comment>.env</comment> file:');
            $this->line('  <info>FIZWATCH_URL</info>=https://your-fizwatch-instance.com');
            $this->line('  <info>FIZWATCH_KEY</info>=your-project-api-key');

            return self::FAILURE;
        }

        $this->info('Sending test exception to FizWatch...');

        try {
            $exception = new \RuntimeException(
                'FizWatch test — this is a test exception to verify your integration is working correctly.'
            );

            $result = $fizwatch->sendTest($exception);

            if ($result['status'] === 202) {
                $this->info('Test exception sent successfully! Check your FizWatch dashboard.');

                return self::SUCCESS;
            }

            $this->error("FizWatch responded with HTTP {$result['status']}.");

            if (is_array($result['body'])) {
                $this->line(json_encode($result['body'], JSON_PRETTY_PRINT));
            } else {
                $this->line((string) $result['body']);
            }

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Failed to send test exception: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
