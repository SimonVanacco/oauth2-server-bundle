<?php

declare(strict_types=1);

namespace League\Bundle\OAuth2ServerBundle\Repository;

use League\Bundle\OAuth2ServerBundle\Converter\ScopeConverterInterface;
use League\Bundle\OAuth2ServerBundle\Entity\DeviceCode as DeviceCodeEntity;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\DeviceCodeManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\AbstractClient;
use League\Bundle\OAuth2ServerBundle\Model\DeviceCode as DeviceCodeModel;
use League\Bundle\OAuth2ServerBundle\Model\DeviceCodeInterface;
use League\OAuth2\Server\Entities\DeviceCodeEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\DeviceCodeRepositoryInterface;

final class DeviceCodeRepository implements DeviceCodeRepositoryInterface
{
    /**
     * @var DeviceCodeManagerInterface
     */
    private $deviceCodeManager;

    /**
     * @var ClientManagerInterface
     */
    private $clientManager;

    /**
     * @var ScopeConverterInterface
     */
    private $scopeConverter;

    /**
     * @var ClientRepositoryInterface
     */
    private $clientRepository;

    public function __construct(
        DeviceCodeManagerInterface $deviceCodeManager,
        ClientManagerInterface $clientManager,
        ScopeConverterInterface $scopeConverter,
        ClientRepositoryInterface $clientRepository
    ) {
        $this->deviceCodeManager = $deviceCodeManager;
        $this->clientManager     = $clientManager;
        $this->scopeConverter    = $scopeConverter;
        $this->clientRepository  = $clientRepository;
    }

    public function getNewDeviceCode(): DeviceCodeEntityInterface
    {
        return new DeviceCodeEntity();
    }

    public function persistDeviceCode(DeviceCodeEntityInterface $deviceCodeEntity): void
    {
        $deviceCode = $this->deviceCodeManager->find($deviceCodeEntity->getIdentifier());

        if (null !== $deviceCode) {
            throw UniqueTokenIdentifierConstraintViolationException::create();
        }

        $deviceCode = $this->buildDeviceCodeModel($deviceCodeEntity);

        $this->deviceCodeManager->save($deviceCode);
    }

    public function getDeviceCodeEntityByDeviceCode(string $deviceCodeEntity): ?DeviceCodeEntityInterface
    {
        $deviceCode = $this->deviceCodeManager->findByCode($deviceCodeEntity);
        if (null === $deviceCode) {
            return null;
        }

        return $this->buildDeviceCodeEntity($deviceCode);
    }

    public function revokeDeviceCode(string $codeId): void
    {
        $deviceCode = $this->deviceCodeManager->find($codeId);

        if (null === $deviceCode) {
            return;
        }

        $deviceCode->revoke();

        $this->deviceCodeManager->save($deviceCode);
    }

    public function isDeviceCodeRevoked(string $codeId): bool
    {
        $deviceCode = $this->deviceCodeManager->find($codeId);

        if (null === $deviceCode) {
            return true;
        }

        return $deviceCode->isRevoked();
    }

    private function buildDeviceCodeEntity(DeviceCodeInterface $deviceCode): DeviceCodeEntity
    {
        $deviceCodeEntity = new DeviceCodeEntity();
        $deviceCodeEntity->setIdentifier($deviceCode->getIdentifier());
        $deviceCodeEntity->setExpiryDateTime($deviceCode->getExpiry());
        $deviceCodeEntity->setClient(
            $this->clientRepository->buildClientEntity($deviceCode->getClient())
        );
        $deviceCodeEntity->setUserIdentifier($deviceCode->getUserIdentifier());
        $deviceCodeEntity->setUserCode($deviceCode->getUserCode());
        $deviceCodeEntity->setUserApproved($deviceCode->getUserApproved());
        $deviceCodeEntity->setVerificationUriCompleteInAuthResponse($deviceCode->getIncludeVerificationUriComplete());
        $deviceCodeEntity->setVerificationUri($deviceCode->getVerificationUri());
        $deviceCodeEntity->setLastPolledAt($deviceCode->getLastPolledAt());
        $deviceCodeEntity->setInterval($deviceCode->getInterval());

        foreach ($deviceCode->getScopes() as $scope) {
            $deviceCodeEntity->addScope($this->scopeConverter->toLeague($scope));
        }

        return $deviceCodeEntity;
    }


    private function buildDeviceCodeModel(DeviceCodeEntityInterface $deviceCodeEntity): DeviceCodeModel
    {
        /** @var AbstractClient $client */
        $client = $this->clientManager->find($deviceCodeEntity->getClient()->getIdentifier());

        $userIdentifier = $deviceCodeEntity->getUserIdentifier();

        return new DeviceCodeModel(
            $deviceCodeEntity->getIdentifier(),
            $deviceCodeEntity->getExpiryDateTime(),
            $client,
            $userIdentifier,
            $this->scopeConverter->toDomainArray(array_values($deviceCodeEntity->getScopes())),
            $deviceCodeEntity->getUserCode(),
            $deviceCodeEntity->getUserApproved(),
            $deviceCodeEntity->getVerificationUriCompleteInAuthResponse(),
            $deviceCodeEntity->getVerificationUri(),
            $deviceCodeEntity->getLastPolledAt(),
            $deviceCodeEntity->getInterval()
        );
    }

}
