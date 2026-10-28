<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Sends a test email with the live mail settings and shows which settings
 * were used, so a broken mail setup (password resets, backup alerts) can
 * be checked from the server. Never prints the password.
 */
class TestMail extends Command
{
    protected $signature = 'vems:test-mail
        {to : Address to send the test email to}
        {--timeout=15 : Seconds to wait for the mail server}';

    protected $description = 'Send a test email and show the mail settings in use';

    public function handle(): int
    {
        $to = $this->argument('to');

        if (Validator::make(['to' => $to], ['to' => 'email'])->fails()) {
            $this->error("\"{$to}\" is not a valid email address.");

            return self::FAILURE;
        }

        $mailer = config('mail.default');
        $settings = config("mail.mailers.{$mailer}", []);

        $rows = [['Mailer', $mailer]];
        if (($settings['transport'] ?? null) === 'smtp') {
            $rows[] = ['Host', $settings['host'] ?? '-'];
            $rows[] = ['Port', $settings['port'] ?? '-'];
            $rows[] = ['Scheme', $settings['scheme'] ?? 'auto (STARTTLS on 587)'];
            $rows[] = ['Login', filled($settings['username'] ?? null) ? "yes ({$settings['username']})" : 'no (IP-based relay)'];
            $rows[] = ['EHLO domain', $settings['local_domain'] ?? '-'];
        }
        $rows[] = ['From', config('mail.from.name').' <'.config('mail.from.address').'>'];
        $this->table(['Setting', 'Value'], $rows);

        $isReal = ! in_array($mailer, ['log', 'array'], true);

        if (! $isReal) {
            $this->warn("MAIL_MAILER is \"{$mailer}\": emails are not really sent".($mailer === 'log' ? ', only written to storage/logs/laravel.log' : '').'.');
        }

        if (($settings['local_domain'] ?? null) === 'localhost') {
            $this->warn('The EHLO domain is "localhost": set APP_URL (or MAIL_EHLO_DOMAIN) to the real domain, or relays like smtp-relay.gmail.com may refuse the connection.');
        }

        // Don't hang for minutes when the port is blocked
        config(["mail.mailers.{$mailer}.timeout" => (int) $this->option('timeout')]);

        $this->info("Sending a test email to {$to}...");

        try {
            Mail::mailer($mailer)->raw(
                'This is a test email from '.config('app.name').' ('.config('app.url').'), sent at '.now()->format('Y-m-d H:i:s T').'. If you can read this, email sending works.',
                fn ($message) => $message->to($to)->subject(config('app.name').' mail test'),
            );
        } catch (Throwable $e) {
            $this->error('Sending failed: '.$e->getMessage());
            $this->line($this->hint($e->getMessage()));

            return self::FAILURE;
        }

        $this->info($isReal
            ? "Sent: the mail server accepted it. Check the inbox (and the spam folder) of {$to}."
            : 'Not sent: the email was '.($mailer === 'log' ? 'written to storage/logs/laravel.log' : 'kept in memory').'. Set MAIL_MAILER=smtp to really send.');

        return self::SUCCESS;
    }

    /** What a common mail server error usually means */
    private function hint(string $error): string
    {
        return match (true) {
            str_contains($error, 'Invalid credentials for relay') => 'Hint: this server\'s IP is not allowed in Google Admin -> Gmail -> Routing -> SMTP relay service (check it with: curl -4 ifconfig.me).',
            str_contains($error, '421') => 'Hint: the mail server refused the connection; with Google\'s relay this usually means APP_URL / MAIL_EHLO_DOMAIN is not the real domain.',
            str_contains($error, '535') || str_contains($error, 'Authentication') => 'Hint: wrong MAIL_USERNAME / MAIL_PASSWORD (Gmail and Microsoft 365 may need an app password).',
            str_contains($error, 'timed out') || str_contains($error, 'Connection could not be established') => 'Hint: the server cannot reach MAIL_HOST:MAIL_PORT; check the host, the port and the firewall (try: nc -zv HOST PORT).',
            str_contains($error, 'relay') || str_contains($error, 'Sender') => 'Hint: the mail server won\'t send as MAIL_FROM_ADDRESS; use an address in your own domain.',
            default => 'Hint: after changing .env, run `php artisan optimize:clear && php artisan optimize` so the new settings are used.',
        };
    }
}
