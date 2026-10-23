<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class TesEmailCommandTest extends TestCase
{
    public function test_success_is_reported(): void
    {
        config(['mail.default' => 'array']);
        Mail::swap(app('mail.manager'));

        $this->artisan('semadiksi:tes-email', ['alamat' => 'tujuan@gmail.com'])
            ->expectsOutputToContain('Berhasil')
            ->assertSuccessful();
    }

    public function test_wrong_credentials_are_explained_without_printing_secrets(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.password' => 'sandi-rahasia-123']);
        Mail::shouldReceive('raw')->andThrow(new TransportException('Expected response code "250" but got code "535", with message "535-5.7.8 Username and Password not accepted."'));

        $this->artisan('semadiksi:tes-email', ['alamat' => 'tujuan@gmail.com'])
            ->expectsOutputToContain('Kredensial salah')
            ->doesntExpectOutputToContain('sandi-rahasia-123')
            ->assertFailed();
    }

    public function test_connection_failure_is_explained(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::shouldReceive('raw')->andThrow(new TransportException('Connection could not be established with host "smtp.gmail.com:587": timed out'));

        $this->artisan('semadiksi:tes-email', ['alamat' => 'tujuan@gmail.com'])
            ->expectsOutputToContain('Koneksi gagal atau diblokir jaringan')
            ->assertFailed();
    }

    public function test_invalid_address_is_rejected(): void
    {
        $this->artisan('semadiksi:tes-email', ['alamat' => 'bukan-email'])
            ->expectsOutputToContain('tidak valid')
            ->assertFailed();
    }
}
