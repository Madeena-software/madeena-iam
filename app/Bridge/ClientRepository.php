<?php

namespace App\Bridge;

use Laravel\Passport\Bridge\ClientRepository as PassportClientRepository;

class ClientRepository extends PassportClientRepository
{
    /**
     * Validate a client's secret.
     *
     * @param  string  $clientIdentifier
     * @param  string|null  $clientSecret
     * @param  string|null  $grantType
     * @return bool
     */
    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        $record = $this->clients->findActive($clientIdentifier);

        if (! $record || empty($clientSecret)) {
            return false;
        }

        $rawSecret = $record->getRawOriginal('secret');

        try {
            // Attempt to decrypt the raw stored secret
            $decryptedSecret = decrypt($rawSecret);
            return hash_equals($decryptedSecret, $clientSecret);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            // Fall back to standard hash checking for legacy Bcrypt-hashed secrets
            return $this->hasher->check($clientSecret, $rawSecret);
        }
    }
}
