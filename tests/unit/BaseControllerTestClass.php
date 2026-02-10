<?php

namespace Tests\Unit;

use AppBundle\Security\ApiToken;
use AppBundle\Security\ApiUser;

class BaseControllerTestClass extends BaseTestClass
{

    protected $role = ApiUser::ROLE_USER;

    protected function createApiRoleUserToken(array $roles = []) :ApiToken
    {
        if (empty($roles)) {
            $roles = [$this->role];
        }

        $pass = bin2hex(random_bytes(5));
        $user = new ApiUser($this->partner, $pass, 2, 1, $roles);
        return new ApiToken(
            $user,
            $this->partner.':'.$pass,
            'secured',
            $user->getRoles()
        );
    }

}