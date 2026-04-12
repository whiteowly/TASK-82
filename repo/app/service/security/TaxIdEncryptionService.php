<?php
declare(strict_types=1);

namespace app\service\security;

class TaxIdEncryptionService
{
    private const CIPHER = 'aes-256-cbc';
    private ?string $key;

    public function __construct(?string $key = null)
    {
        $this->key = $key;
    }

    private function getKey(): string
    {
        return $this->key ?? bootstrap_config('tax_id_encryption_key', '');
    }

    /**
     * Encrypt a plaintext value using AES-256-CBC.
     * A random IV is generated and prepended to the ciphertext.
     *
     * @param string $plaintext
     * @return string Base64-encoded string containing IV + ciphertext.
     */
    public function encrypt(string $plaintext): string
    {
        $key = $this->getKey();
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLength);

        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv . $ciphertext);
    }

    /**
     * Decrypt a ciphertext value previously encrypted with encrypt().
     * Extracts the prepended IV before decrypting.
     *
     * @param string $ciphertext Base64-encoded string containing IV + ciphertext.
     * @return string The decrypted plaintext.
     */
    public function decrypt(string $ciphertext): string
    {
        $key = $this->getKey();
        $ivLength = openssl_cipher_iv_length(self::CIPHER);

        $decoded = base64_decode($ciphertext, true);
        $iv = substr($decoded, 0, $ivLength);
        $encrypted = substr($decoded, $ivLength);

        return openssl_decrypt($encrypted, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
    }
}
