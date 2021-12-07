<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\GraphQL\Base\Tests\Unit\Service;

use OxidEsales\GraphQL\Base\DataType\Filter\IDFilter;
use OxidEsales\GraphQL\Base\DataType\Pagination\Pagination;
use OxidEsales\GraphQL\Base\DataType\Sorting\TokenSorting;
use OxidEsales\GraphQL\Base\DataType\TokenFilterList;
use OxidEsales\GraphQL\Base\DataType\User as UserDataType;
use OxidEsales\GraphQL\Base\Exception\InvalidLogin;
use OxidEsales\GraphQL\Base\Infrastructure\Repository as BaseRepository;
use OxidEsales\GraphQL\Base\Infrastructure\Token as TokenInfrastructure;
use OxidEsales\GraphQL\Base\Service\Authentication;
use OxidEsales\GraphQL\Base\Service\Authorization;
use OxidEsales\GraphQL\Base\Service\TokenAdministration;
use OxidEsales\GraphQL\Base\Tests\Unit\BaseTestCase;
use TheCodingMachine\GraphQLite\Types\ID;

class TokenAdministrationTest extends BaseTestCase
{
    public function testQueryTokensNotAuthorizedFilterOnNotOwnUserId(): void
    {
        $repository = $this->createPartialMock(BaseRepository::class, ['getList']);
        $repository->method('getList')->willReturn([]);

        $authorizationService = $this->createPartialMock(Authorization::class, ['isAllowed']);
        $authorizationService->method('isAllowed')->willReturn(false);

        $userDataType          = new UserDataType($this->getUserModelStub('_testuserid'));
        $authenticationService = $this->createPartialMock(Authentication::class, ['getUser']);
        $authenticationService->method('getUser')->willReturn($userDataType);

        $tokenInfrastructure = $this->createPartialMock(TokenInfrastructure::class, []);

        $tokenAdministration = new TokenAdministration($repository, $authorizationService, $authenticationService, $tokenInfrastructure);
        $filterList          = new TokenFilterList(new IDFilter(new ID('unknown')));
        $sort                = TokenSorting::fromUserInput();
        $pagination          = new Pagination();

        $this->expectException(InvalidLogin::class);
        $tokenAdministration->tokens($filterList, $pagination, $sort);
    }

    public function testQueryTokensNotAuthorizedFilterOnOwnUserId(): void
    {
        $repository = $this->createPartialMock(BaseRepository::class, ['getList']);
        $repository->method('getList')->willReturn([]);

        $authorizationService = $this->createPartialMock(Authorization::class, ['isAllowed']);
        $authorizationService->method('isAllowed')->willReturn(false);

        $userDataType          = new UserDataType($this->getUserModelStub('_testuserid'));
        $authenticationService = $this->createPartialMock(Authentication::class, ['getUser']);
        $authenticationService->method('getUser')->willReturn($userDataType);

        $tokenInfrastructure = $this->createPartialMock(TokenInfrastructure::class, []);

        $tokenAdministration = new TokenAdministration($repository, $authorizationService, $authenticationService, $tokenInfrastructure);
        $filterList          = new TokenFilterList(new IDFilter(new ID('_testuserid')));
        $sort                = TokenSorting::fromUserInput();
        $pagination          = new Pagination();

        $tokenAdministration->tokens($filterList, $pagination, $sort);
    }

    public function testCustomerTokensDeleteOwnByDefault(): void
    {
        $userDataType = new UserDataType($this->getUserModelStub('_testuserid'));

        $repository = $this->createPartialMock(BaseRepository::class, ['getById']);
        $repository->method('getById')->willReturn($userDataType);

        $authorizationService = $this->createPartialMock(Authorization::class, ['isAllowed']);
        $authorizationService->method('isAllowed')->willReturn(false);

        $authenticationService = $this->createPartialMock(Authentication::class, ['getUser']);
        $authenticationService->method('getUser')->willReturn($userDataType);

        $tokenInfrastructure = $this->createPartialMock(TokenInfrastructure::class, ['tokenDelete']);
        $tokenInfrastructure->method('tokenDelete')->with($userDataType)->willReturn(5);

        $tokenAdministration = new TokenAdministration($repository, $authorizationService, $authenticationService, $tokenInfrastructure);

        $this->assertEquals(5, $tokenAdministration->customerTokensDelete(null));
    }

    public function testCustomerTokensDeleteOwnWithId(): void
    {
        $userDataType = new UserDataType($this->getUserModelStub('_testuserid'));

        $repository = $this->createPartialMock(BaseRepository::class, ['getById']);
        $repository->method('getById')->willReturn($userDataType);

        $authorizationService = $this->createPartialMock(Authorization::class, ['isAllowed']);
        $authorizationService->method('isAllowed')->willReturn(false);

        $authenticationService = $this->createPartialMock(Authentication::class, ['getUser']);
        $authenticationService->method('getUser')->willReturn($userDataType);

        $tokenInfrastructure = $this->createPartialMock(TokenInfrastructure::class, ['tokenDelete']);
        $tokenInfrastructure->method('tokenDelete')->with($userDataType)->willReturn(5);

        $tokenAdministration = new TokenAdministration($repository, $authorizationService, $authenticationService, $tokenInfrastructure);

        $this->assertEquals(5, $tokenAdministration->customerTokensDelete(new ID('_testuserid')));
    }

    public function testCustomerTokensDeleteOtherUserFails(): void
    {
        $repository = $this->createPartialMock(BaseRepository::class, []);

        $authorizationService = $this->createPartialMock(Authorization::class, ['isAllowed']);
        $authorizationService->method('isAllowed')->willReturn(false);

        $userDataType          = new UserDataType($this->getUserModelStub('_testuserid'));
        $authenticationService = $this->createPartialMock(Authentication::class, ['getUser']);
        $authenticationService->method('getUser')->willReturn($userDataType);

        $tokenInfrastructure = $this->createPartialMock(TokenInfrastructure::class, []);

        $tokenAdministration = new TokenAdministration($repository, $authorizationService, $authenticationService, $tokenInfrastructure);

        $this->expectException(InvalidLogin::class);
        $tokenAdministration->customerTokensDelete(new ID('_otheruserid'));
    }

    public function testCustomerTokensDeleteOtherUserAdmin(): void
    {
        $userDataType = new UserDataType($this->getUserModelStub('_testuserid'));

        $repository = $this->createPartialMock(BaseRepository::class, ['getById']);
        $repository->method('getById')->willReturn($userDataType);

        $authorizationService = $this->createPartialMock(Authorization::class, ['isAllowed']);
        $authorizationService->method('isAllowed')->willReturn(true);

        $authenticationService = $this->createPartialMock(Authentication::class, ['getUser']);
        $authenticationService->method('getUser')->willReturn($userDataType);

        $tokenInfrastructure = $this->createPartialMock(TokenInfrastructure::class, ['tokenDelete']);
        $tokenInfrastructure->method('tokenDelete')->with($userDataType)->willReturn(5);

        $tokenAdministration = new TokenAdministration($repository, $authorizationService, $authenticationService, $tokenInfrastructure);

        $this->assertEquals(5, $tokenAdministration->customerTokensDelete(new ID('_otheruserid')));
    }
}
