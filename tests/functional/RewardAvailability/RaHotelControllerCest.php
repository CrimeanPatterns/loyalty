<?php

namespace Tests\Functional\RewardAvailability;


use AppBundle\Model\Resources\PostCheckResponse;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelRequest;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelResponse;
use AppBundle\Security\ApiUser;
use Codeception\Util\Stub;
use JMS\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class RaHotelControllerCest
{
    private const RaHotel_Search = '/v1/search';
    private const RaHotel_Result = '/v1/getResults/%s';
    private const RaHotel_List = '/v1/providers/list';

    protected $userData;
    protected $partner;

    public function _before(\FunctionalTester $I)
    {
        $this->partner = 'test_' . bin2hex(random_bytes(5));
        $this->userData = 'userdata_' . bin2hex(random_bytes(5));

        $tokenStorage = Stub::make(TokenStorage::class, [
            'getToken' => $I->createApiRoleUserToken($this->partner,
                [ApiUser::ROLE_USER, ApiUser::ROLE_REWARD_AVAILABILITY_HOTEL])
        ]);
        $I->mockService('security.token_storage', $tokenStorage);
    }

    private function buildRequest()
    {
        $this->checkIn = date("Y-m-d", strtotime('+1 month'));
        $this->checkOut = date("Y-m-d", strtotime('+1 day', strtotime($this->checkIn)));
        return (new RaHotelRequest())
            ->setProvider('testprovider')
            ->setCheckInDate(new \DateTime($this->checkIn))
            ->setCheckOutDate(new \DateTime($this->checkOut))
            ->setDestination('New York')
            ->setNumberOfAdults(2)
            ->setNumberOfKids(1)
            ->setNumberOfRooms(2)
            ->setPriority(7)
            ->setDownloadPreview(true)
            ->setUserData($this->userData);
    }

    public function testRaHotel(\FunctionalTester $I)
    {
        $I->sendGET(self::RaHotel_List);
        $I->seeResponseCodeIs(Response::HTTP_NOT_FOUND);
        $host = $I->grabParameter('reward_availability_hotel_host');
        $I->setHost($host);

        $I->sendGET(self::RaHotel_List);
        $I->seeResponseCodeIs(Response::HTTP_OK);

        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");
        $d = $serializer->serialize($this->buildRequest(), 'json');
        $I->sendPOST(self::RaHotel_Search, $d);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseMatchesJsonType(['requestId' => 'string']);
        /** @var PostCheckResponse $postResponse */
        $postResponse = $serializer->deserialize($I->grabResponse(), PostCheckResponse::class, 'json');

        $I->sendGET(sprintf(self::RaHotel_Result, $postResponse->getRequestid()));
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseMatchesJsonType(['state' => 'string']);
        /** @var RaHotelResponse $checkResponse */
        $checkResponse = $serializer->deserialize($I->grabResponse(), RaHotelResponse::class, 'json');
        $I->assertEquals($postResponse->getRequestid(), $checkResponse->getRequestId());
    }


}