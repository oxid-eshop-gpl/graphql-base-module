<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\GraphQL\Base\Service;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\UnencryptedToken;
use OxidEsales\GraphQL\Base\DataType\User;
use OxidEsales\GraphQL\Base\Event\BeforeTokenCreation;
use OxidEsales\GraphQL\Base\Exception\InvalidLogin;
use OxidEsales\GraphQL\Base\Exception\InvalidToken;
use OxidEsales\GraphQL\Base\Infrastructure\Legacy as LegacyService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use TheCodingMachine\GraphQLite\Security\AuthenticationServiceInterface;

class Authentication implements AuthenticationServiceInterface
{
    public const CLAIM_SHOPID   = 'shopid';

    public const CLAIM_USERNAME = 'username';

    public const CLAIM_USERID   = 'userid';

    public const CLAIM_USER_ANONYMOUS   = 'useranonymous';

    /** @var KeyRegistry */
    private $keyRegistry;

    /** @var LegacyService */
    private $legacyService;

    /** @var Token */
    private $tokenService;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    public function __construct(
        KeyRegistry $keyRegistry,
        LegacyService $legacyService,
        Token $tokenService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->keyRegistry     = $keyRegistry;
        $this->legacyService   = $legacyService;
        $this->tokenService    = $tokenService;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws InvalidToken
     */
    public function isLogged(): bool
    {
        if ($this->getUser()->isAnonymous()) {
            return false;
        }

        $groups = $this->legacyService->getUserGroupIds(
            $this->tokenService->getTokenClaim(self::CLAIM_USERID)
        );

        if (in_array('oxidblocked', $groups)) {
            throw InvalidToken::userBlocked();
        }

        if (!$this->isValidToken($this->tokenService->getToken())) {
            throw InvalidToken::invalidToken();
        }

        return true;
    }

    /**
     * @throws InvalidLogin
     */
    public function createToken(?string $username = null, ?string $password = null): UnencryptedToken
    {
        $userData  = $this->legacyService->login($username, $password);
        $time      = new DateTimeImmutable('now');
        $expire    = new DateTimeImmutable('+8 hours');
        $config    = $this->getConfig();

        $builder = $config->builder()
            ->issuedBy($this->legacyService->getShopUrl())
            ->withHeader('iss', $this->legacyService->getShopUrl())
            ->permittedFor($this->legacyService->getShopUrl())
            ->issuedAt($time)
            ->canOnlyBeUsedAfter($time)
            ->expiresAt($expire)
            ->withClaim(self::CLAIM_SHOPID, $this->legacyService->getShopId())
            ->withClaim(self::CLAIM_USERNAME, $username)
            ->withClaim(self::CLAIM_USERID, $userData->getUserId())
            ->withClaim(self::CLAIM_USER_ANONYMOUS, $userData->isAnonymous());

        $event = new BeforeTokenCreation($builder, $userData);
        $this->eventDispatcher->dispatch(
            BeforeTokenCreation::NAME,
            $event
        );

        return $event->getBuilder()->getToken(
            $config->signer(),
            $config->signingKey()
        );
    }

    /**
     * @throws InvalidToken
     */
    public function getUserName(): string
    {
        if (!$this->isLogged()) {
            throw InvalidToken::invalidToken();
        }

        return (string) $this->tokenService->getTokenClaim(self::CLAIM_USERNAME);
    }

    protected function getConfig(): Configuration
    {
        $configBuilder = new JwtConfigurationBuilder(
            $this->keyRegistry,
            $this->legacyService
        );

        return $configBuilder->getConfiguration();
    }

    public function getUser(): User
    {
        return new User(
            $this->legacyService->getUserModel($this->tokenService->getTokenClaim(self::CLAIM_USERID)),
            $this->tokenService->getTokenClaim(self::CLAIM_USER_ANONYMOUS, true)
        );
    }

    /**
     * Checks if given token is valid:
     * - has valid signature
     * - has valid issuer and audience
     * - has valid shop claim
     *
     * @internal
     */
    private function isValidToken(UnencryptedToken $token): bool
    {
        $config    = $this->getConfig();
        $validator = $config->validator();

        if (!$validator->validate($token, ...$config->validationConstraints())) {
            return false;
        }

        if (!$this->tokenService->checkTokenHasClaim(self::CLAIM_SHOPID)) {
            return false;
        }

        if ($this->tokenService->getTokenClaim(self::CLAIM_SHOPID) !== $this->legacyService->getShopId()) {
            return false;
        }

        return true;
    }
}
