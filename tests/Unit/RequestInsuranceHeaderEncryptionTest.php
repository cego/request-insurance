<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\Models\RequestInsurance;

class RequestInsuranceHeaderEncryptionTest extends TestCase
{
    /** @test */
    public function it_can_build_with_a_encrypted_header(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->headers(['Content-Type' => 'application/json'])
            ->encryptHeader('Content-Type')
            ->create();

        // Assert

        // An RI should be left decrypted after creation
        $this->assertEquals('application/json', $requestInsurance->getHeadersCastToArray()['Content-Type']);

        $this->assertCount(1, RequestInsurance::all());

        // Disable events, so that we can assert that the header was encrypted
        // since it is auto decrypted by model events
        Event::fake();

        // Extract it RAW from the DB (Since Events are disabled)
        $requestInsurance = RequestInsurance::first();

        // Assert that it is encrypted in DB
        $this->assertNotEquals('application/json', $requestInsurance->getHeadersCastToArray()['Content-Type']);
    }

    /** @test */
    public function it_only_encrypts_the_requested_headers(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->headers(['Content-Type' => 'application/json', 'X-test' => 'CEGO'])
            ->encryptHeader('Content-Type')
            ->create();

        // Assert

        // Make sure it is not encrypted after creation
        $this->assertEquals('CEGO', $requestInsurance->getHeadersCastToArray()['X-test']);

        $this->assertCount(1, RequestInsurance::all());

        // Disable events, so that we can assert that the header was encrypted
        // since it is auto decrypted by model events
        Event::fake();

        // Extract it RAW from the DB (Since Events are disabled)
        $requestInsurance = RequestInsurance::first();

        // Make sure it is not encrypted in DB
        $this->assertEquals('CEGO', $requestInsurance->getHeadersCastToArray()['X-test']);
    }

    /** @test */
    public function it_can_use_dot_notation_for_encrypted_headers(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->headers(['Content-Type' => 'application/json', 'x-test' => ['a' => 1, 'b' => 2]])
            ->encryptHeader('x-test.a')
            ->create();

        // Assert

        // An RI should be left decryoted after creation
        $this->assertEquals(1, $requestInsurance->getHeadersCastToArray()['x-test']['a']);

        $this->assertCount(1, RequestInsurance::all());

        // Disable events, so that we can assert that the header was encrypted
        // since it is auto decrypted by model events
        Event::fake();

        // Extract it RAW from the DB (Since Events are disabled)
        $requestInsurance = RequestInsurance::first();

        // Assert that it is encrypted in DB
        $this->assertNotEquals(1, $requestInsurance->getHeadersCastToArray()['x-test']['a']);
    }

    /** @test */
    public function it_can_encrypt_arrays(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->headers(['Content-Type' => 'application/json', 'x-test' => ['a' => 1, 'b' => 2]])
            ->encryptHeader('x-test')
            ->create();

        // Assert

        // An RI should be left decryoted after creation
        $this->assertEquals(['a' => 1, 'b' => 2], $requestInsurance->getHeadersCastToArray()['x-test']);

        $this->assertCount(1, RequestInsurance::all());

        // Disable events, so that we can assert that the header was encrypted
        // since it is auto decrypted by model events
        Event::fake();

        // Extract it RAW from the DB (Since Events are disabled)
        $requestInsurance = RequestInsurance::first();

        // Assert that it is encrypted in DB
        $this->assertNotEquals(['a' => 1, 'b' => 2], $requestInsurance->getHeadersCastToArray()['x-test']);
        $this->assertIsString($requestInsurance->getHeadersCastToArray()['x-test']);
    }

    /** @test */
    public function it_can_build_with_multiple_encrypted_headers(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->headers(['Content-Type' => 'application/json', 'x-test' => 'abc'])
            ->encryptHeaders(['Content-Type', 'x-test'])
            ->create();

        // Assert

        // An RI should be left decryoted after creation
        $this->assertEquals('application/json', $requestInsurance->getHeadersCastToArray()['Content-Type']);
        $this->assertEquals('abc', $requestInsurance->getHeadersCastToArray()['x-test']);

        $this->assertCount(1, RequestInsurance::all());

        // Disable events, so that we can assert that the header was encrypted
        // since it is auto decrypted by model events
        Event::fake();

        // Extract it RAW from the DB (Since Events are disabled)
        $requestInsurance = RequestInsurance::first();

        // Assert that it is encrypted in DB
        $this->assertNotEquals('application/json', $requestInsurance->getHeadersCastToArray()['Content-Type']);
        $this->assertNotEquals('abc', $requestInsurance->getHeadersCastToArray()['x-test']);
    }

    /** @test */
    public function it_can_auto_decrypt_after_resolving_from_db(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->headers(['Content-Type' => 'application/json', 'x-test' => 'abc'])
            ->encryptHeaders(['Content-Type', 'x-test'])
            ->create();

        // Assert

        // An RI should be left decryoted after creation
        $this->assertEquals('application/json', $requestInsurance->getHeadersCastToArray()['Content-Type']);
        $this->assertEquals('abc', $requestInsurance->getHeadersCastToArray()['x-test']);

        $this->assertCount(1, RequestInsurance::all());

        // Extract it RAW from the DB (Since Events are disabled)
        $requestInsurance = RequestInsurance::first();

        // Assert that it is encrypted in DB
        $this->assertEquals('application/json', $requestInsurance->getHeadersCastToArray()['Content-Type']);
        $this->assertEquals('abc', $requestInsurance->getHeadersCastToArray()['x-test']);
    }

    /** @test */
    public function it_adds_auto_encrypted_headers(): void
    {
        // Arrange
        Config::set('request-insurance.fieldsToAutoEncrypt', [
            'headers' => ['x-test'],
        ]);

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->headers(['Content-Type' => 'application/json', 'x-test' => 'abc'])
            ->encryptHeaders([])
            ->create();

        // Assert
        $this->assertContains('x-test', json_decode($requestInsurance->encrypted_fields, true, 512, JSON_THROW_ON_ERROR)['headers']);
    }

    /** @test */
    public function it_can_handle_duplicate_encryption_headers(): void
    {
        // Arrange
        Config::set('request-insurance.fieldsToAutoEncrypt', [
            'headers' => ['x-test'],
        ]);

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->headers(['Content-Type' => 'application/json', 'x-test' => 'abc'])
            ->encryptHeader('x-test')
            ->create();

        // Assert

        // An RI should be left decrypted after creation
        $this->assertEquals('abc', $requestInsurance->getHeadersCastToArray()['x-test']);

        $this->assertCount(1, RequestInsurance::all());

        // Extract it from the DB
        $requestInsurance = RequestInsurance::first();

        // Assert that it is decrypted correctly in DB
        $this->assertEquals('abc', $requestInsurance->getHeadersCastToArray()['x-test']);
        $this->assertCount(0, RequestInsurance::query()->where('headers', 'like', '%abc%')->get());
    }

    /** @test */
    public function it_can_merge_auto_encryption(): void
    {
        // Arrange
        Config::set('request-insurance.fieldsToAutoEncrypt', [
            'headers' => ['x-test'],
        ]);

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->headers(['Content-Type' => 'application/json', 'x-test' => 'abc'])
            ->encryptHeader('Content-Type')
            ->create();

        $encryptedFields = json_decode($requestInsurance->encrypted_fields, true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals([
            'headers' => [
                'Content-Type',
                'x-test',
            ],
        ], $encryptedFields);
    }

    /** @test */
    public function it_does_not_double_encrypt_when_saving_multiple_times()
    {
        // Arrange
        Config::set('request-insurance.fieldsToAutoEncrypt', [
            'headers' => ['x-test'],
        ]);

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->headers(['Content-Type' => 'application/json', 'x-test' => 'abc'])
            ->encryptHeader('Content-Type')
            ->create();

        // Assert
        $this->assertEquals(['headers' => ['Content-Type', 'x-test']], json_decode($requestInsurance->encrypted_fields, true, 512, JSON_THROW_ON_ERROR));
        $requestInsurance->save();
        $this->assertEquals(['headers' => ['Content-Type', 'x-test']], json_decode($requestInsurance->encrypted_fields, true, 512, JSON_THROW_ON_ERROR));
    }

    /** @test */
    public function it_sends_info_about_encrypted_headers_as_header(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->headers(['Content-Type' => 'application/json'])
            ->encryptHeader('Content-Type')
            ->create();

        $headers = json_decode($requestInsurance->headers, true);

        // Assert
        $this->assertEquals(array_merge(['Content-Type'], Config::get('request-insurance.fieldsToAutoEncrypt.headers', [])), json_decode($headers['X-Sensitive-Request-Headers-JSON']));
    }
}
