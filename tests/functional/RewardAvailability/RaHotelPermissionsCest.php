<?php


namespace Tests\Functional\RewardAvailability;

use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelRequest;
use Codeception\Example;
use JMS\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Response;

/**
 * @backupGlobals disabled
 */
class RaHotelPermissionsCest
{
    private const RaHotel_Search = '/v1/search';
    private const RaHotel_List = '/v1/providers/list';

    private $key;
    private $connection;
    private $PartnerId;

    public function _before(\FunctionalTester $I)
    {
        $partnerData = ['Login' => uniqid(), 'Pass' => 'xxxx', 'LoyaltyAccess' => '1'];
        $this->PartnerId = $I->haveInDatabase('Partner', $partnerData);
        $I->haveInDatabase('PartnerApiKey', [
            'PartnerID' => $this->PartnerId,
            'ApiKey' => ($key = sprintf('%s:%s', $partnerData['Login'], $partnerData['Pass'])),
            'Enabled' => '1'
        ]);
        $this->key = $key;

        $host = $I->grabParameter('reward_availability_hotel_host');
        $I->setHost($host);
        $this->connection = $I->grabService('database_connection');
    }

    public function testUnAuthorized(\FunctionalTester $I)
    {
        $I->setServerParameters(['HTTP_HOST' => $I->grabParameter('host')]);
        $I->sendGET(self::RaHotel_List, []);
        $I->seeResponseCodeIs(Response::HTTP_UNAUTHORIZED);

        $I->haveHttpHeader("X-Authentication", "baduser:badpassword");
        $I->sendGET(self::RaHotel_List);
        $I->seeResponseCodeIs(Response::HTTP_UNAUTHORIZED);
        $I->seeResponseMatchesJsonType(['message' => 'string', 'logref' => 'string']);
        $I->seeResponseContainsJson(['message' => 'Unauthorized']);
    }

    public function testAuthorized(\FunctionalTester $I)
    {
        $I->setServerParameters(['HTTP_HOST' => $I->grabParameter('host')]);
        $I->haveHttpHeader("X-Authentication", $this->key);
        $I->sendGET(self::RaHotel_List);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseIsJson();
    }

    /**
     * @dataProvider getData
     * @param \FunctionalTester $I
     * @param Example $example
     */
    public function testProvider(\FunctionalTester $I, Example $example)
    {
        $I->wantToTest($example['message']);

        $this->connection->executeUpdate("UPDATE `Partner` SET CanCheckRaHotels = {$example['partnerCanCheckRaHotels']} WHERE `PartnerID` = " . $this->PartnerId);

        if ($example['CanCheckRaHotel'] === null) {
            $providerCode = 'someprovider';
            $example['text'] = 'Provider code (someprovider) does not exist';
        } else {
            $providerCode = $example['provider'];
            $I->createAwProvider(null, $providerCode, ['CanCheckRaHotel' => $example['CanCheckRaHotel']]);
        }
        $params = $this->buildParams($providerCode, $I);
        $I->haveHttpHeader("X-Authentication", $this->key);
        $I->sendPOST(self::RaHotel_Search, $params);
        $I->seeResponseCodeIs($example['response']);
        $I->seeResponseContains($example['text']);
        if ($example['response'] === Response::HTTP_OK) {
            $I->seeResponseContains($example['text']);
        } else {
            $I->seeResponseMatchesJsonType(['message' => 'string', 'logref' => 'string']);
        }
    }

    protected function getData()
    {
        $providerCode = "test" . bin2hex(random_bytes(8));
        return [
            [
                'message' => 'no provider in DB => bad request',
                'provider' => $providerCode,
                'CanCheckRaHotel' => null,
                'response' => Response::HTTP_BAD_REQUEST,
                'text' => 'Provider code (' . $providerCode. ') does not exist',
                'partnerCanCheckRaHotels' => 1
            ],
            [
                'message' => 'provider in DB, CanCheckRaHotel turn off => bad request',
                'provider' => $providerCode,
                'CanCheckRaHotel' => 0,
                'response' => Response::HTTP_BAD_REQUEST,
                'text' => 'Provider code (' . $providerCode. ') does not exist',
                'partnerCanCheckRaHotels' => 1
            ],
            [
                'message' => 'provider in DB, CanCheckRaHotel turn on => ok',
                'provider' => $providerCode,
                'CanCheckRaHotel' => 1,
                'response' => Response::HTTP_OK,
                'text' => 'requestId',
                'partnerCanCheckRaHotels' => 1
            ],
            [
                'message' => 'provider in DB, CanCheckRaHotel turn on, partner  can\'t check raHotel => bad request',
                'provider' => $providerCode,
                'CanCheckRaHotel' => 1,
                'response' => Response::HTTP_FORBIDDEN,
                'text' => 'API Access error',
                'partnerCanCheckRaHotels' => 0
            ],
        ];
    }

    private function buildParams($provider, \FunctionalTester $I)
    {
        $this->checkIn = date("Y-m-d", strtotime('+1 month'));
        $this->checkOut = date("Y-m-d", strtotime('+1 day', strtotime($this->checkIn)));
        /** @var Serializer $serializer */
        $request = (new RaHotelRequest())
            ->setProvider($provider)
            ->setCheckInDate(new \DateTime($this->checkIn))
            ->setCheckOutDate(new \DateTime($this->checkOut))
            ->setDestination('New York')
            ->setNumberOfAdults(2)
            ->setNumberOfKids(1)
            ->setNumberOfRooms(2)
            ->setPriority(7);
        return $I->grabService("jms_serializer")->serialize($request, 'json');
    }
}