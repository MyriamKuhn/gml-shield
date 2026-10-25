<?php

namespace Tests\Unit;

use App\Services\SecretCodeEncryptionService;
use Illuminate\Contracts\Encryption\DecryptException;
use Tests\TestCase;

class SecretCodeEncryptionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'security.secret_code_key',
            base64_encode(random_bytes(32))
        );
    }

    public function test_secret_can_be_encrypted_and_decrypted(): void
    {
        $service = new SecretCodeEncryptionService();

        $secret = 'WAREHOUSE-1234';
        $encrypted = $service->encrypt($secret);

        $this->assertNotSame($secret, $encrypted);
        $this->assertStringStartsWith('v1:', $encrypted);
        $this->assertSame($secret, $service->decrypt($encrypted));
    }

    public function test_encryption_uses_a_fresh_nonce(): void
    {
        $service = new SecretCodeEncryptionService();

        $first = $service->encrypt('WAREHOUSE-1234');
        $second = $service->encrypt('WAREHOUSE-1234');

        $this->assertNotSame($first, $second);
    }

    public function test_tampered_ciphertext_is_rejected(): void
    {
        $service = new SecretCodeEncryptionService();

        $encrypted = $service->encrypt('WAREHOUSE-1234');
        $parts = explode(':', $encrypted);

        $ciphertext = base64_decode($parts[3], true);
        $ciphertext[0] = chr(ord($ciphertext[0]) ^ 1);
        $parts[3] = base64_encode($ciphertext);

        $this->expectException(DecryptException::class);

        $service->decrypt(implode(':', $parts));
    }

    public function test_missing_encryption_key_is_rejected(): void
    {
        config()->set('security.secret_code_key', null);

        $service = new SecretCodeEncryptionService();

        $this->expectException(\RuntimeException::class);

        $service->encrypt('WAREHOUSE-1234');
    }
}
