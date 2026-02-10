<?php

namespace AppBundle\Security;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ApiUser implements UserInterface, EquatableInterface {

    const ROLE_ADMIN = 'ROLE_ADMIN';
    const ROLE_USER  = 'ROLE_USER';

    const ROLE_DEBUG = 'ROLE_DEBUG';
    const ROLE_ACCOUNT_INFO = 'ROLE_ACCOUNT_INFO';
    const ROLE_RESERVATIONS_INFO = 'ROLE_RESERVATIONS_INFO';
    const ROLE_RESERVATIONS_CONF_NO = 'ROLE_RESERVATIONS_CONF_NO';
    const ROLE_ACCOUNT_HISTORY = 'ROLE_ACCOUNT_HISTORY';
    const ROLE_CHANGE_PASSWORD = 'ROLE_CHANGE_PASSWORD';
    const ROLE_REWARD_AVAILABILITY = 'ROLE_REWARD_AVAILABILITY';
    const ROLE_REWARD_AVAILABILITY_HOTEL = 'ROLE_REWARD_AVAILABILITY_HOTEL';

    const ALLOWED_ROLES = [
        self::ROLE_DEBUG,
        self::ROLE_ACCOUNT_INFO,
        self::ROLE_ACCOUNT_HISTORY,
        self::ROLE_RESERVATIONS_CONF_NO,
        self::ROLE_RESERVATIONS_INFO,
        self::ROLE_CHANGE_PASSWORD,
        self::ROLE_REWARD_AVAILABILITY,
        self::ROLE_REWARD_AVAILABILITY_HOTEL,
    ];

    private $username;

    private $password;

    private $roles;

    private $userId;

    private $threads;


    public function __construct($username, $password, $userId, $threads, array $roles) {
        $this->username = $username;
        $this->password = $password;
        $this->roles = $roles;
        $this->userId = $userId;
        $this->threads = $threads;
    }

    public function getSalt() {
        return null;
    }

    public function getUsername() {
        return $this->username;
    }

    public function getPassword() {
        return $this->password;
    }

    public function getRoles() {
        return $this->roles;
    }

    public function getThreads(){
        return $this->threads;
    }

    /**
     * @return mixed
     */
    public function getUserId() {
        return $this->userId;
    }

    public function eraseCredentials() {
    }

    public function isEqualTo(UserInterface $user) {
        if (!$user instanceof ApiUser)
            return false;

        if ($this->username !== $user->getUsername() or $this->password !== $user->getPassword())
            return false;

        return false;
    }

}