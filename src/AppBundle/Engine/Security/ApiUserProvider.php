<?php

namespace AppBundle\Security;

use AppBundle\Service\AccessChecker;
use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class ApiUserProvider implements UserProviderInterface {

    /** @var Connection */
    private $connection;

    public function __construct($connection) {
        $this->connection = $connection;
    }

    public function getUsernameForApiKey($apiKey)
    {
        $sql = <<<SQL
            SELECT p.Login 
            FROM Partner p JOIN PartnerApiKey pak on p.PartnerID = pak.PartnerID
            WHERE pak.ApiKey = :APIKEY
            AND pak.Enabled = 1
SQL;

        $result = $this->connection->executeQuery($sql, [':APIKEY' => $apiKey])->fetch();
        $username = $result['Login'] ?? null;
        if(!$username)
            throw new UsernameNotFoundException('Invalid Username');

        return $username;
    }

    public function loadUserByUsername($username)
    {
        $sql = <<<SQL
            SELECT 
                PartnerID, Login, Pass, CanDebug, Threads, CanChangePassword, CanCheckRaHotels,
                LoyaltyReservationsInfo, LoyaltyReservationByConfirmation,  
                LoyaltyAccountInfo, LoyaltyAccountHistory 
            FROM Partner
            WHERE Login = :USERNAME
            AND LoyaltyAccess = 1
SQL;
        $result = $this->connection->executeQuery($sql, [':USERNAME' => $username])->fetch();
        if(!$result)
            throw new UsernameNotFoundException('Invalid Username');

        $roles = [ApiUser::ROLE_USER];
        foreach (ApiUser::ALLOWED_ROLES as $role) {
            if ((int)$result[AccessChecker::getRoleDbMappedField($role)] === 1) {
                $roles[] = $role;
            }
        }

        if ($result['Login'] === 'awardwallet') {
            $roles[] = ApiUser::ROLE_ADMIN;
        }

        return new ApiUser(
            $result['Login'],
            $result['Pass'],
            $result['PartnerID'],
            intval($result['Threads']),
            // the roles for the user - you may choose to determine
            // these dynamically somehow based on the user
            $roles
        );
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof ApiUser)
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        return $this->loadUserByUsername($user->getUsername().':'.$user->getPassword());
    }

    public function supportsClass($class)
    {
        return ApiUser::class === $class;
    }
}