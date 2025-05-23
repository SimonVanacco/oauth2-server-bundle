<?php

declare(strict_types=1);

namespace League\Bundle\OAuth2ServerBundle\Tests\Unit;

use League\Bundle\OAuth2ServerBundle\DependencyInjection\LeagueOAuth2ServerExtension;
use League\Bundle\OAuth2ServerBundle\Manager\InMemory\ScopeManager;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ExtensionTest extends TestCase
{
    /**
     * @dataProvider grantsProvider
     */
    public function testEnablingAndDisablingGrants(string $referenceId, string $grantKey, bool $shouldTheGrantBeEnabled): void
    {
        $container = new ContainerBuilder();

        $this->setupContainer($container);

        $extension = new LeagueOAuth2ServerExtension();

        $extension->load($this->getValidConfiguration([$grantKey => $shouldTheGrantBeEnabled]), $container);

        $authorizationServer = $container->findDefinition(AuthorizationServer::class);
        $methodCalls = $authorizationServer->getMethodCalls();
        $isGrantEnabled = false;

        foreach ($methodCalls as $methodCall) {
            if ('enableGrantType' === $methodCall[0] && $referenceId === (string) $methodCall[1][0]) {
                $isGrantEnabled = true;
                break;
            }
        }

        $this->assertSame($shouldTheGrantBeEnabled, $isGrantEnabled);
    }

    public function grantsProvider(): iterable
    {
        yield 'Client credentials grant can be enabled' => [
            ClientCredentialsGrant::class, 'enable_client_credentials_grant', true,
        ];
        yield 'Client credentials grant can be disabled' => [
            ClientCredentialsGrant::class, 'enable_client_credentials_grant', false,
        ];
        yield 'Password grant can be enabled' => [
            PasswordGrant::class, 'enable_password_grant', true,
        ];
        yield 'Password grant can be disabled' => [
            PasswordGrant::class, 'enable_password_grant', false,
        ];
        yield 'Refresh token grant can be enabled' => [
            RefreshTokenGrant::class, 'enable_refresh_token_grant', true,
        ];
        yield 'Refresh token grant can be disabled' => [
            RefreshTokenGrant::class, 'enable_refresh_token_grant', false,
        ];
    }

    /**
     * @dataProvider requireCodeChallengeForPublicClientsProvider
     */
    public function testAuthCodeGrantDisableRequireCodeChallengeForPublicClientsConfig(
        ?bool $requireCodeChallengeForPublicClients,
        bool $shouldTheRequirementBeDisabled,
    ): void {
        $container = new ContainerBuilder();

        $this->setupContainer($container);

        $extension = new LeagueOAuth2ServerExtension();

        $configuration = $this->getValidConfiguration();
        $configuration[0]['authorization_server']['require_code_challenge_for_public_clients'] = $requireCodeChallengeForPublicClients;

        $extension->load($configuration, $container);

        $authorizationServer = $container->findDefinition(AuthCodeGrant::class);
        $methodCalls = $authorizationServer->getMethodCalls();

        $isRequireCodeChallengeForPublicClientsDisabled = false;

        foreach ($methodCalls as $methodCall) {
            if ('disableRequireCodeChallengeForPublicClients' === $methodCall[0]) {
                $isRequireCodeChallengeForPublicClientsDisabled = true;
                break;
            }
        }

        $this->assertSame($shouldTheRequirementBeDisabled, $isRequireCodeChallengeForPublicClientsDisabled);
    }

    public function requireCodeChallengeForPublicClientsProvider(): iterable
    {
        yield 'when not requiring code challenge for public clients the requirement should be disabled' => [
            false, true,
        ];
        yield 'when code challenge for public clients is required the requirement should not be disabled' => [
            true, false,
        ];
        yield 'with the default value the requirement should not be disabled' => [
            null, false,
        ];
    }

    /**
     * @dataProvider scopeProvider
     */
    public function testDefaultScopeValidation(array $available, array $default, bool $valid): void
    {
        $container = new ContainerBuilder();
        $extension = new LeagueOAuth2ServerExtension();

        $this->setupContainer($container);

        if (!$valid) {
            $this->expectException(\LogicException::class);
        }

        $extension->load($this->getValidConfiguration(['scopes' => ['available' => $available, 'default' => $default]]), $container);

        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider revokeRefreshTokensProvider
     */
    public function testEnablingAndDisablingRevocationOfRefreshTokens(bool $shouldRevokeRefreshTokens): void
    {
        $container = new ContainerBuilder();
        $extension = new LeagueOAuth2ServerExtension();

        $extension->load($this->getValidConfiguration(['revoke_refresh_tokens' => $shouldRevokeRefreshTokens]), $container);

        $authorizationServer = $container->findDefinition(AuthorizationServer::class);
        $methodCalls = $authorizationServer->getMethodCalls();
        $revokeRefreshTokens = null;

        foreach ($methodCalls as $methodCall) {
            if ('revokeRefreshTokens' === $methodCall[0]) {
                $revokeRefreshTokens = $methodCall[1][0];
                break;
            }
        }

        $this->assertSame($shouldRevokeRefreshTokens, $revokeRefreshTokens);
    }

    public function scopeProvider(): iterable
    {
        yield 'when a default scope is part of available scopes' => [
            ['scope_one', 'scope_two'],
            ['scope_one'],
            true,
        ];

        yield 'when a default scope is not part of available scopes' => [
            ['scope_one', 'scope_two'],
            ['unknown_scope'],
            false,
        ];
    }

    private function getValidConfiguration(array $options = []): array
    {
        return [
            [
                'authorization_server' => [
                    'private_key' => 'foo',
                    'encryption_key' => 'foo',
                    'enable_client_credentials_grant' => $options['enable_client_credentials_grant'] ?? true,
                    'enable_password_grant' => $options['enable_password_grant'] ?? true,
                    'enable_refresh_token_grant' => $options['enable_refresh_token_grant'] ?? true,
                    'revoke_refresh_tokens' => $options['revoke_refresh_tokens'] ?? true,
                ],
                'resource_server' => [
                    'public_key' => 'foo',
                ],
                'scopes' => $options['scopes'] ?? [
                    'available' => [
                        'foo',
                        'bar',
                    ],
                    'default' => [
                        'foo',
                    ],
                ],
                // Pick one for valid config:
                // 'persistence' => ['doctrine' => []]
                'persistence' => ['in_memory' => 1],
            ],
        ];
    }

    public function revokeRefreshTokensProvider(): iterable
    {
        yield 'do revoke refresh tokens' => [true];
        yield 'do not revoke refresh tokens' => [false];
    }

    private function setupContainer(ContainerBuilder $container): void
    {
        $container->register(ScopeManager::class);
    }
}
