<?php

namespace AppBundle\Security;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\User\UserInterface;

class ApiToken extends AbstractToken
{

    private $credentials;
    private $providerKey;

    public function __construct($user, $credentials, $providerKey, array $roles = array())
    {
        parent::__construct($roles);

        $this->setUser($user);
        $this->credentials = $credentials;
        $this->providerKey = $providerKey;

        if(!($user instanceof UserInterface))
            return;

        $this->setAuthenticated($user->getPassword() === $this->getPassword() && count($roles) > 0);

    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials()
    {
        return $this->credentials;
    }

    /**
     * {@inheritdoc}
     */
    public function getUsername()
    {
        return $this->getUser()->getUsername();
    }

    /**
     * {@inheritdoc}
     */
    public function getPassword()
    {
        return $this->getUser()->getPassword();
    }

    /**
     * Returns the provider key.
     *
     * @return string The provider key
     */
    public function getProviderKey()
    {
        return $this->providerKey;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize(array($this->credentials, $this->providerKey, parent::serialize()));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        list($this->credentials, $this->providerKey, $parentStr) = unserialize($serialized);
        parent::unserialize($parentStr);
    }

}