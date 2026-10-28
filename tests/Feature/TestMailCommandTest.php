<?php

use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;

it('sends a test email with the configured mailer', function () {
    config([
        'mail.default' => 'array',
        'mail.from.address' => 'rsc.admin@rsc-bd.org',
    ]);

    $this->artisan('vems:test-mail', ['to' => 'someone@rsc-bd.org'])
        ->expectsOutputToContain('rsc.admin@rsc-bd.org')
        ->expectsOutputToContain('emails are not really sent')
        ->expectsOutputToContain('Not sent: the email was kept in memory')
        ->assertSuccessful();

    /** @var ArrayTransport $transport */
    $transport = Mail::mailer('array')->getSymfonyTransport();
    $sent = $transport->messages()->first()->getOriginalMessage();

    expect($transport->messages())->toHaveCount(1)
        ->and($sent->getTo()[0]->getAddress())->toBe('someone@rsc-bd.org');
});

it('shows the smtp settings without the password and reports a mail server it cannot reach, with a hint', function () {
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 1,
        'mail.mailers.smtp.username' => 'relay-user',
        'mail.mailers.smtp.password' => 'super-secret',
    ]);

    $this->artisan('vems:test-mail', ['to' => 'someone@rsc-bd.org', '--timeout' => 2])
        ->expectsOutputToContain('yes (relay-user)')
        ->doesntExpectOutputToContain('super-secret')
        ->expectsOutputToContain('Sending failed')
        ->expectsOutputToContain('Hint: the server cannot reach MAIL_HOST:MAIL_PORT')
        ->assertFailed();
});

it('rejects an invalid address', function () {
    $this->artisan('vems:test-mail', ['to' => 'not-an-email'])
        ->expectsOutputToContain('is not a valid email address')
        ->assertFailed();
});
