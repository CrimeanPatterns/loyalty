<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 14/02/2018
 * Time: 15:37
 */

namespace AppBundle\Extension;


use AppBundle\Document\RegisterAccount;
use AppBundle\Security\ApiUser;
use Doctrine\DBAL\Connection;

class ProvidersHelper
{

    const DO_NOT_SUPPORT_ITINERARIES_WARNING = "At the moment we don't support gathering of itineraries from this provider's online accounts, if you believe it is possible, please contact our support and we may develop this functionality.";


    /**
     * @var Connection
     */
    private $connection;

    /**
     * ProvidersHelper constructor.
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param $type
     * @param ApiUser $user
     * @return string
     * @throws \Exception
     */
    public function AvailableProvidersQuery($type, ApiUser $user){
        if(!in_array($type, ['list', 'info', 'check', 'confirmation', 'reward-availability', 'reward-availability-hotel']))
            throw new \Exception("Unknown query type '{$type}'");

        $userName = $user->getUsername();
        $additionalPartnerAccess = [
            'aeroplan'      => ["tripitprod", "tripittest", "traxo", "awardwallet", "test"], // ???
//            'aa'            => ["awardwallet", "test", "obex", "accountaccessproxy"],
            'rapidrewards'  => ["accountaccessproxy"],
            'delta' => ['accountaccessproxy'],
            'mileageplus' => ['accountaccessproxy'],
        ];
        $additionalPartnerAccess['aa'] = $this->connection->executeQuery('select Login from Partner where CanParseAAWeb = 1')->fetchAll(\PDO::FETCH_COLUMN);


        $addQuery = '';
        if (!in_array($userName, ["awardwallet", "test", "awardwallet-dev-desktop"])) {
            $addQuery .= "AND (WSDL = 1";
            foreach($additionalPartnerAccess as $provider => $partners){
                if(in_array($userName, $partners))
                    $addQuery .= " OR Code = '{$provider}'";
            }
            $addQuery .= ")";
        }

        $addCode = in_array($type, ['info', 'check', 'confirmation']) ? 'AND Code = :CODE' : '';
        $addDebug = '';
        if(in_array($type, ['check', 'confirmation', 'info']) && in_array(ApiUser::ROLE_DEBUG, $user->getRoles()))
            $addDebug = 'OR State = '.PROVIDER_TEST;

        $available = "AND State <> ".PROVIDER_CHECKING_EXTENSION_ONLY;
        if ('confirmation' === $type) {
            $available = "AND CanCheckConfirmation IN (".CAN_CHECK_CONFIRMATION_YES_SERVER.", ".CAN_CHECK_CONFIRMATION_YES_EXTENSION_AND_SERVER.")";
        }

        if ('reward-availability-hotel' === $type) {
            $available = "AND CanCheckRaHotel = 1";
            $addQuery = '';
        }

        if ('reward-availability' === $type) {
            $available = "AND CanCheckRewardAvailability <> 0";
            $addQuery = '';
        }

        $sql = "
            SELECT * FROM Provider
			WHERE (State >= :PROVIDER_ENABLED OR State = :PROVIDER_WSDL_ONLY {$addDebug})
			{$available}
			{$addQuery}
			{$addCode}
			ORDER BY Code
        ";

        return $sql;
    }

}