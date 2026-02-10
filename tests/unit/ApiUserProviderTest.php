<?php
namespace Tests\Unit;

use AppBundle\Security\ApiUser;
use AppBundle\Security\ApiUserProvider;
use Doctrine\DBAL\Connection;
use Helper\CustomDb;

/**
 * @backupGlobals disabled
 */
class ApiUserProviderTest extends \Codeception\TestCase\Test
{
    /** @var  Connection */
    private $connection;
    /** @var CustomDb */
    private $db;

    public function _before()
    {
        parent::_before();
        $this->connection = $this->getModule('Symfony')->grabService('kernel')->getContainer()->get('database_connection');
        $this->db = $this->getModule('\Helper\CustomDb');
    }

    /**
     * @dataProvider roles
     */
    public function testLoadRoles($fields, $roles)
    {
        $username = 'test_' . bin2hex(random_bytes(5));
        $this->db->haveInDatabase('Partner', array_merge(['Login' => $username, 'LoyaltyAccess' => 1, "Pass" => "xxx"], $fields));

        $provider = new ApiUserProvider($this->connection);
        $user = $provider->loadUserByUsername($username);
        $loadedRoles = $user->getRoles();

        $this->assertEquals(count($roles), count($loadedRoles));
        foreach ($roles as $role) {
            $this->assertEquals(true, in_array($role, $loadedRoles));
        }
    }

    public function roles()
    {
        return [
            [
                [
                    'CanDebug' => 1, 'CanChangePassword' => 1, 'CanCheckRaHotels' => 1, 'LoyaltyReservationsInfo' => 1,
                    'LoyaltyReservationByConfirmation' => 1, 'LoyaltyAccountInfo' => 1, 
                    'LoyaltyAccountHistory' => 1
                ],
                array_merge(ApiUser::ALLOWED_ROLES, [ ApiUser::ROLE_USER ])
            ],
            [
                [
                    'CanDebug' => 0, 'CanChangePassword' => 0, 'CanCheckRaHotels' => 0, 'LoyaltyReservationsInfo' => 0,
                    'LoyaltyReservationByConfirmation' => 0, 'LoyaltyAccountInfo' => 0, 
                    'LoyaltyAccountHistory' => 0
                ],
                [ ApiUser::ROLE_USER ]
            ],
        ];
    }
}