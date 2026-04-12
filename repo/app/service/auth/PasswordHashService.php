<?php
declare(strict_types=1);

namespace app\service\auth;

class PasswordHashService
{
    /**
     * Hash a password using Argon2id.
     *
     * @param string $password
     * @return string The hashed password.
     */
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    /**
     * Verify a password against a hash.
     *
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check whether the given hash needs to be rehashed (e.g. algorithm or cost change).
     *
     * @param string $hash
     * @return bool
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }
}
