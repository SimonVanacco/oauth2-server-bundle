<?php

declare(strict_types=1);

namespace League\Bundle\OAuth2ServerBundle\Manager\InMemory;

use League\Bundle\OAuth2ServerBundle\Manager\DeviceCodeManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\DeviceCodeInterface;

final class DeviceCodeManager implements DeviceCodeManagerInterface
{
    /**
     * @var array<string, DeviceCodeInterface>
     */
    private $deviceCodes = [];

    public function find(string $identifier): ?DeviceCodeInterface
    {
        return $this->accessTokens[$identifier] ?? null;
    }

    public function save(DeviceCodeInterface $deviceCode): void
    {
        $this->deviceCodes[$deviceCode->getIdentifier()] = $deviceCode;
    }

    public function clearExpired(): int
    {
        $count = \count($this->deviceCodes);

        $now = new \DateTimeImmutable();
        $this->deviceCodes = array_filter($this->deviceCodes, static function (DeviceCodeInterface $accessToken) use ($now): bool {
            return $accessToken->getExpiry() >= $now;
        });

        return $count - \count($this->deviceCodes);
    }
}
