<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\Models\RequestInsurance;

class RequestInsurancePayloadEncryptionTest extends TestCase
{
    public function test_it_can_build_with_a_encrypted_payload(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->payload(['Key' => 'Value'])
            ->encryptPayloadField('Key')
            ->create();

        // Assert

        // An RI should be left decrypted after creation
        $this->assertEquals('Value', $requestInsurance->getJsonDecodedPayload()['Key']);

        $this->assertCount(1, RequestInsurance::all());

        // Disable events, so that we can assert that the header was encrypted
        // since it is auto decrypted by model events
        Event::fake();

        // Extract it RAW from the DB (Since Events are disabled)
        $requestInsurance = RequestInsurance::first();

        // Assert that it is encrypted in DB
        $this->assertNotEquals('Value', $requestInsurance->getJsonDecodedPayload()['Key']);
    }

    public function test_it_only_encrypts_the_requested_payload(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->payload(['Key1' => 'Value1', 'Key2' => 'Value2'])
            ->encryptPayloadField('Key1')
            ->create();

        // Assert

        // Make sure it is not encrypted after creation
        $this->assertEquals('Value1', $requestInsurance->getJsonDecodedPayload()['Key1']);

        $this->assertCount(1, RequestInsurance::all());

        // Disable events, so that we can assert that the header was encrypted
        // since it is auto decrypted by model events
        Event::fake();

        // Extract it RAW from the DB (Since Events are disabled)
        $requestInsurance = RequestInsurance::first();

        // Make sure it is not encrypted in DB
        $this->assertEquals('Value2', $requestInsurance->getJsonDecodedPayload()['Key2']);
    }

    public function test_it_can_use_dot_notation_for_encrypted_payload(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->payload(['Key1' => 'Value1', 'NestedKeyCollection' => ['NestedKey1' => 'NestedValue1', 'NestedKey2' => 'NestedValue2']])
            ->encryptPayloadField('NestedKeyCollection.NestedKey1')
            ->create();

        // Assert

        // An RI should be left decryoted after creation
        $this->assertEquals('NestedValue1', $requestInsurance->getJsonDecodedPayload()['NestedKeyCollection']['NestedKey1']);

        $this->assertCount(1, RequestInsurance::all());

        // Disable events, so that we can assert that the header was encrypted
        // since it is auto decrypted by model events
        Event::fake();

        // Extract it RAW from the DB (Since Events are disabled)
        $requestInsurance = RequestInsurance::first();

        // Assert that it is encrypted in DB
        $this->assertNotEquals('NestedValue1', $requestInsurance->getJsonDecodedPayload()['NestedKeyCollection']['NestedKey1']);
    }

    public function test_it_can_encrypt_arrays(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->payload(['Key1' => 'Value1', 'NestedKeyCollection' => ['NestedKey1' => 'NestedValue1', 'NestedKey2' => 'NestedValue2']])
            ->encryptPayloadField('NestedKeyCollection')
            ->create();

        // Assert

        // An RI should be left decrypted after creation
        $this->assertEquals(['NestedKey1' => 'NestedValue1', 'NestedKey2' => 'NestedValue2'], $requestInsurance->getJsonDecodedPayload()['NestedKeyCollection']);

        $this->assertCount(1, RequestInsurance::all());

        // Disable events, so that we can assert that the header was encrypted
        // since it is auto decrypted by model events
        Event::fake();

        // Extract it RAW from the DB (Since Events are disabled)
        $requestInsurance = RequestInsurance::first();

        // Assert that it is encrypted in DB
        $this->assertNotEquals(['a' => 1, 'b' => 2], $requestInsurance->getJsonDecodedPayload()['NestedKeyCollection']);
        $this->assertIsString($requestInsurance->getJsonDecodedPayload()['NestedKeyCollection']);
    }

    public function test_it_can_build_with_multiple_encrypted_payload_fields(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->payload(['Key1' => 'Value1', 'Key2' => 'Value2'])
            ->encryptPayload(['Key1', 'Key2'])
            ->create();

        // Assert

        // An RI should be left decrypted after creation
        $this->assertEquals('Value1', $requestInsurance->getJsonDecodedPayload()['Key1']);
        $this->assertEquals('Value2', $requestInsurance->getJsonDecodedPayload()['Key2']);

        $this->assertCount(1, RequestInsurance::all());

        // Disable events, so that we can assert that the header was encrypted
        // since it is auto decrypted by model events
        Event::fake();

        // Extract it RAW from the DB (Since Events are disabled)
        $requestInsurance = RequestInsurance::first();

        // Assert that it is encrypted in DB
        $this->assertNotEquals('Value1', $requestInsurance->getJsonDecodedPayload()['Key1']);
        $this->assertNotEquals('Value2', $requestInsurance->getJsonDecodedPayload()['Key2']);
    }

    public function test_it_can_auto_decrypt_after_resolving_from_db(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->payload(['Key1' => 'Value1', 'Key2' => 'Value2'])
            ->encryptPayload(['Key1', 'Key2'])
            ->create();

        // Assert

        // An RI should be left decrypted after creation
        $this->assertEquals('Value1', $requestInsurance->getJsonDecodedPayload()['Key1']);
        $this->assertEquals('Value2', $requestInsurance->getJsonDecodedPayload()['Key2']);

        $this->assertCount(1, RequestInsurance::all());

        // Extract it from the DB
        $requestInsurance = RequestInsurance::first();

        // Assert that it is decrypted after extraction
        $this->assertEquals('Value1', $requestInsurance->getJsonDecodedPayload()['Key1']);
        $this->assertEquals('Value2', $requestInsurance->getJsonDecodedPayload()['Key2']);
    }

    public function test_it_adds_auto_encrypted_payload(): void
    {
        // Arrange
        Config::set('request-insurance.fieldsToAutoEncrypt', [
            'payload' => ['Key1'],
        ]);

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->payload(['Key1' => 'Value1', 'Key2' => 'Value2'])
            ->encryptPayload([])
            ->create();

        // Assert
        $this->assertContains('Key1', json_decode($requestInsurance->encrypted_fields, true, 512, JSON_THROW_ON_ERROR)['payload']);
    }

    public function test_it_can_handle_duplicate_encryption_payload(): void
    {
        // Arrange
        Config::set('request-insurance.fieldsToAutoEncrypt', [
            'headers' => ['Key1'],
        ]);

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->payload(['Key1' => 'Value1', 'Key2' => 'Value2'])
            ->encryptPayload(['Key1'])
            ->create();

        // Assert

        // An RI should be left decrypted after creation
        $this->assertEquals('Value1', $requestInsurance->getJsonDecodedPayload()['Key1']);

        $this->assertCount(1, RequestInsurance::all());

        // Extract it from the DB
        $requestInsurance = RequestInsurance::first();

        // Assert that it is decrypted correctly in DB
        $this->assertEquals('Value1', $requestInsurance->getJsonDecodedPayload()['Key1']);
        $this->assertCount(0, RequestInsurance::query()->where('payload', 'like', '%Value1%')->get());
    }

    public function test_it_can_merge_auto_encryption(): void
    {
        // Arrange
        Config::set('request-insurance.fieldsToAutoEncrypt', [
            'payload' => ['Key2'],
        ]);

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->payload(['Key1' => 'Value1', 'Key2' => 'Value2'])
            ->encryptPayload(['Key1'])
            ->create();

        $encryptedFields = json_decode($requestInsurance->encrypted_fields, true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals([
            'payload' => [
                'Key1',
                'Key2',
            ],
        ], $encryptedFields);
    }

    public function test_it_does_not_double_encrypt_when_saving_multiple_times()
    {
        // Arrange
        Config::set('request-insurance.fieldsToAutoEncrypt', [
            'payload' => ['Key2'],
        ]);

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->payload(['Key1' => 'Value1', 'Key2' => 'Value2'])
            ->encryptPayload(['Key1'])
            ->create();

        // Assert
        $this->assertEquals(['payload' => ['Key1', 'Key2']], json_decode($requestInsurance->encrypted_fields, true, 512, JSON_THROW_ON_ERROR));
        $requestInsurance->save();
        $this->assertEquals(['payload' => ['Key1', 'Key2']], json_decode($requestInsurance->encrypted_fields, true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_it_sends_info_about_encrypted_body_as_header(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->payload(['Key1' => 'Value1', 'Key2' => 'Value2'])
            ->encryptPayload(['Key1', 'Key2'])
            ->create();

        $headers = json_decode($requestInsurance->headers, true);

        // Assert
        $this->assertEquals(array_merge(['Key1', 'Key2'], Config::get('request-insurance.fieldsToAutoEncrypt.payload', [])), json_decode($headers['X-Sensitive-Request-Body-JSON']));
    }
}
