<?php


namespace Tests\Unit\Filters;


use AwardWallet\Common\Geo\Google\GoogleApi;
use AwardWallet\Common\Geo\Google\GoogleRequestLimitReachedException;
use AwardWallet\Common\Geo\Google\Place;
use AwardWallet\Common\Geo\Google\PlaceDetailsResponse;
use AwardWallet\Common\Geo\Google\PlaceDetailsToAddressConverter;
use AwardWallet\Common\Geo\Google\PlaceDetails;
use AwardWallet\Common\Geo\Google\PlaceSearchResponse;
use AwardWallet\Common\Itineraries\Address;
use AwardWallet\Common\Itineraries\Event;
use AwardWallet\Common\Itineraries\HotelReservation;
use AwardWallet\Common\Parsing\Filter\Google\HotelAddressByNameFilter;
use Codeception\Module\Symfony;
use Codeception\Specify;
use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Codeception\Util\Stub;
use JMS\Serializer\Serializer;
use Psr\Log\LoggerInterface;

/**
 * @backupGlobals disabled
 */
class HotelAddressByNameFilterTest extends Unit
{
    use Specify;

    /**
     * @var HotelAddressByNameFilter
     */
    private $filter;

    /**
     * @var GoogleApi
     */
    private $googleApi;

    /**
     * @var Address
     */
    private $address;

    /**
     * @var PlaceDetailsToAddressConverter
     */
    private $addressConverter;

    /**
     * @var Place[]
     */
    private $textSearchPlaces;

    /**
     * @var PlaceDetails
     */
    private $placeDetails;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function _before()
    {
        parent::_before();
        $this->logger = Stub::makeEmpty(LoggerInterface::class);
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        /** @var Serializer $serializer */
        $serializer = $symfony->grabService('jms_serializer');
        $this->textSearchPlaces = $serializer->deserialize(file_get_contents(__DIR__ . '/../../_data/googleTextSearch.json'), PlaceSearchResponse::class, 'json');
        $this->placeDetails = $serializer->deserialize(file_get_contents(__DIR__ . '/../../_data/googlePlaceDetails.json'), PlaceDetailsResponse::class, 'json');
        $this->googleApi = Stub::makeEmpty(GoogleApi::class, ['placeTextSearch' => $this->textSearchPlaces, 'placeDetails' => $this->placeDetails]);
        $this->address = new Address($this->logger);
        $this->address->lng = '37.5960691';
        $this->address->lat = '55.7521942';
        $this->address->timezone = '10800';
        $this->address->postalCode = '111141';
        $this->address->addressLine = 'New Arbat Avenue, 11';
        $this->address->countryName = 'Russia';
        $this->address->text = 'New Arbat Ave, 11, Moskva, Russia, 111141';
        $this->address->city = 'Moskva';
        $this->address->stateName = 'Moscow';
        $this->addressConverter = Stub::makeEmpty(PlaceDetailsToAddressConverter::class, ['convert' => $this->address]);
        $this->filter = new HotelAddressByNameFilter($this->googleApi, $this->addressConverter, $this->logger);
    }

    public function testFilter()
    {
        $this->specify('Empty address, should be filled in', function () {
            $hotelReservation = new HotelReservation($this->logger);
            $hotelReservation->hotelName = "TEST";
            $hotelReservation->address = new Address($this->logger);
            $this->filter->filter($hotelReservation);
            verify($hotelReservation->address)->equals($this->address);
        });

        $this->specify('Empty address, but no name, should do nothing', function () {
            $hotelReservation = new HotelReservation($this->logger);
            $hotelReservation->address = new Address($this->logger);
            $this->filter->filter($hotelReservation);
            verify($hotelReservation->address->text)->isEmpty();
        });

        $this->specify('Address is not empty, should do nothing', function () {
            $hotelReservation = new HotelReservation($this->logger);
            $hotelReservation->hotelName = 'TEST';
            $oldAddress = new Address($this->logger);
            $oldAddress->text = 'have some address here';
            $hotelReservation->address = $oldAddress;
            $this->filter->filter($hotelReservation);
            verify($hotelReservation->address)->same($oldAddress);
        });

        $this->specify('Not a hotel, should do nothing', function () {
            $event = new Event($this->logger);;
            $event->address = new Address($this->logger);
            $this->filter->filter($event);
            verify($event->address->text)->isEmpty();
        });

        $this->specify('Geo locator throws exception, should log it', function () {
            $hotelReservation = new HotelReservation($this->logger);
            $hotelReservation->hotelName = "TEST";
            $hotelReservation->address = new Address($this->logger);
            $this->logger = Stub::makeEmpty(LoggerInterface::class, ['warning' => Expected::once()]);
            $this->googleApi = Stub::makeEmpty(GoogleApi::class, ['placeTextSearch' => function () {
                throw new GoogleRequestLimitReachedException();
            }]);
            $this->filter = new HotelAddressByNameFilter($this->googleApi, $this->addressConverter, $this->logger);
            $this->filter->filter($hotelReservation);
        });
    }
}