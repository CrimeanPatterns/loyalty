<?php

namespace AppBundle\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authentication\SimplePreAuthenticatorInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class ApiAuthenticator implements SimplePreAuthenticatorInterface
{

    public function createToken(Request $request, $providerKey)
    {
        $authData = $request->headers->get('X-Authentication', '');

        return new ApiToken(
            'anon.',
            $authData,
            $providerKey
        );
    }

    public function authenticateToken(TokenInterface $token, UserProviderInterface $userProvider, $providerKey)
    {
        if (!$userProvider instanceof ApiUserProvider) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The user provider must be an instance of ApiUserProvider (%s was given).',
                    get_class($userProvider)
                )
            );
        }

        $authData = $token->getCredentials();
        $username = $userProvider->getUsernameForApiKey($authData);

        $user = $userProvider->loadUserByUsername($username);
        return new ApiToken(
            $user,
            $authData,
            $providerKey,
            $user->getRoles()
        );
    }

    public function supportsToken(TokenInterface $token, $providerKey)
    {
        return $token instanceof ApiToken && $token->getProviderKey() === $providerKey;
    }
}