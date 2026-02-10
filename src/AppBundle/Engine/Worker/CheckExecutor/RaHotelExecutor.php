<?php


namespace AppBundle\Worker\CheckExecutor;


use AppBundle\Document\BaseDocument;
use AppBundle\Document\RaHotel;
use AppBundle\Document\RewardAvailability;
use AppBundle\Extension\CheckerFactory;
use AppBundle\Extension\Loader;
use AppBundle\Extension\MQSender;
use AppBundle\Extension\S3Custom;
use AppBundle\Extension\SendToAW;
use AppBundle\Extension\Watchdog;
use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\DetailedAddress;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\Hotel;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelResponse;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityResponse;
use AppBundle\Service\ApiValidator;
use AppBundle\Service\BrowserStateFactory;
use AppBundle\Service\Otc\Cache;
use AwardWallet\Common\Airport\AirportTime;
use AwardWallet\Common\AWS\Util;
use AwardWallet\Common\CurrencyConverter\CurrencyConverter;
use AwardWallet\Common\Parsing\Filter\ItinerariesFilter;
use AwardWallet\Common\Parsing\Solver\Helper\FlightHelper;
use AwardWallet\Common\Parsing\Solver\Helper\FSHelper;
use AwardWallet\Common\Parsing\Solver\MasterSolver;
use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\SerializerInterface;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class RaHotelExecutor extends RewardAvailabilityExecutor
{

    protected $RepoKey = RaHotel::METHOD_KEY;

    public function processRequest($request, $response, $row)
    {
        BaseExecutor::processRequest($request, $response, $row);
    }

    protected function buildChecker($request, BaseDocument $row): \TAccountChecker
    {
        return parent::buildChecker($request, $row);
    }

    public function processChecker(\TAccountChecker $checker, $request, BaseDocument $row, $fresh = true)
    {
        parent::processChecker($checker, $request, $row, $fresh);
    }

    protected function fillRequestFields($request)
    {
        $checkIn = $request->getCheckInDate();
        $checkOut = $request->getCheckOutDate();
        return [
            'Destination' => trim($request->getDestination()),
            'Adults' => $request->getNumberOfAdults(),
            'Kids' => $request->getNumberOfKids(),
            'Rooms' => $request->getNumberOfRooms(),
            'CheckIn' => $checkIn instanceof \DateTime ? $checkIn->getTimestamp() : time(),
            'CheckOut' => $checkOut instanceof \DateTime ? $checkOut->getTimestamp() : time(),
            'DownloadPreview' => $request->isDownloadPreview()
        ];
    }

    private function getRate($result)
    {
        if (!isset($result['hotels']) || count($result['hotels']) === 0) {
            return 1;
        }
        $currencyOut = $result['hotels'][0]['currency'] ?? 'USD';
        if ($currencyOut === 'USD') {
            return 1;
        }
        return $this->currencyConverter->getExchangeRate($currencyOut, 'USD');
    }

    /**
     * @param RaHotel $row
     */
    protected function processResult(BaseDocument $row, $result, \TAccountChecker $checker, string $provider)
    {
        if (empty($result['hotels']) && $checker->ErrorCode === ACCOUNT_CHECKED) {
            $checker->ErrorCode = ACCOUNT_ENGINE_ERROR;
            $checker->ErrorMessage = "Empty result cannot have status equal to 1";
            return;
        }

        $rate = $this->getRate($result);
        if (null === $rate) {
            $checker->ErrorCode = ACCOUNT_WARNING;
            $errorMessages = [];
            if ($checker->ErrorMessage !== parent::UNKNOWN_ERROR) {
                $errorMessages[] = $checker->ErrorMessage;
            }
            $errorMessages[] = 'Result price in' . $result['hotels'][0]['currency'];
            $checker->ErrorMessage = implode('; ', $errorMessages);
            $rate = 1;
        }

        $parseErrors = [];
        $parseNotices = [];

        $hotels = array_map(function (array $hotel, int $numHotel) use (&$parseErrors, &$parseNotices, $rate) {
            if (!isset($hotel['checkInDate']) || !is_string($hotel['checkInDate'])
                || ($checkInDate = strtotime($hotel['checkInDate'])) === false
            ) {
                $parseErrors[] = [
                    "property" => "hotels[{$numHotel}].checkInDate",
                    "message" => "not date format"
                ];
                return null;
            }
            if (!isset($hotel['checkOutDate']) || !is_string($hotel['checkOutDate'])
                || ($checkOutDate = strtotime($hotel['checkOutDate'])) === false
            ) {
                $parseErrors[] = [
                    "property" => "hotels[{$numHotel}].checkOutDate",
                    "message" => "not date format"
                ];
                return null;
            }
            // check points
            if (!isset($hotel['pointsPerNight']) or empty($hotel['pointsPerNight'])) {
                $parseErrors[] = [
                    "property" => "hotels[{$numHotel}].pointsPerNight",
                    "message" => "empty"
                ];
            }
            // checking and adding numberOfNights
            $in = new \DateTime(date('Y-m-d', $checkInDate));// ignore time
            $out = new \DateTime(date('Y-m-d', $checkOutDate));// ignore time
            $numberOfNights = (int)$out->diff($in)->format("%a");
            if (isset($hotel['numberOfNights']) && $numberOfNights > 0
                && $numberOfNights !== (int)($hotel['numberOfNights'] ?? null)
            ) {
                $parseErrors[] = [
                    "property" => "hotels[{$numHotel}].numberOfNights",
                    "message" => "not equal difference between in and out"
                ];
            } else {
                $numberOfNights = !empty($numberOfNights) ? $numberOfNights : ($hotel['numberOfNights'] ?? null);
            }
            $cashPerNight = isset($hotel['cashPerNight']) ? (float)$hotel['cashPerNight'] : null;
            if (isset($cashPerNight)) {
                $cashPerNight = round($rate * $cashPerNight, 2);
            } else {
                $parseErrors[] = [
                    "property" => "hotels[{$numHotel}].cashPerNight",
                    "message" => "empty"
                ];
            }
            if ($rate === 1 && isset($hotel['currency']) && $hotel['currency'] !== 'USD') {
                $parseNotices[] = [
                    "property" => "hotels[{$numHotel}].currency",
                    "message" => "it looks like collecting results from different currencies"
                ];
            }

            return new Hotel(
                $hotel['name'] ?? null,
                $hotel['checkInDate'] ?? null,
                $hotel['checkOutDate'] ?? null,
                $hotel['roomType'] ?? null,
                $hotel['hotelDescription'] ?? null,
                $numberOfNights,
                isset($hotel['pointsPerNight']) ? (int)$hotel['pointsPerNight'] : null,
                $cashPerNight,
                $hotel['currency'] ?? null,
                $rate === 1 ? null : $rate,
                isset($hotel['distance']) ? (float)$hotel['distance'] : null,
                isset($hotel['rating']) ? (float)$hotel['rating'] : null,
                isset($hotel['numberOfReviews']) ? (int)$hotel['numberOfReviews'] : null,
                $hotel['phone'] ?? null,
                $hotel['url'] ?? null,
                $hotel['preview'] ?? null,
                $this->getDetailedAddress($hotel['address'] ?? null, $hotel['detailedAddress'] ?? [])
            );
        }, $result['hotels'] ?? [], array_keys($result['hotels'] ?? []));

        /** @var RaHotelResponse $response */
        $response = clone $row->getResponse();
        $response->setHotels($hotels);

        // validation
        $responseSerialized = $this->serializer->serialize($response, 'json');
        $errors = $this->validator->validate(
            json_decode($responseSerialized, false),
            basename(str_replace('\\', '/', get_class($response))),
            $row->getApiVersion(),
            true,
            true
        );

        if (!empty($parseErrors)) {
            $checker->logger->error(
                'Parse errors: ' . print_r(array_map(function (array $error) {
                    return [$error['property'], $error['message']];
                }, $parseErrors), true)
            );
            $this->logger->notice(
                'Reward availability has parse errors',
                ['component' => 'parser']
            );
            $checker->ErrorCode = ACCOUNT_ENGINE_ERROR;
        }
        if (!empty($parseNotices)) {
            $checker->logger->notice(
                'Parse notice: ' . print_r(array_map(function (array $notice) {
                    return [$notice['property'], $notice['message']];
                }, $parseNotices), true)
            );
//            $this->logger->notice('Reward availability has parse notice', ['component' => 'parser']);
        }
        if (!empty($errors)) {
            $checker->logger->error(
                'Validation errors: ' . print_r(array_map(function (array $error) {
                    return [$error['property'], $error['message']];
                }, $errors), true)
            );
            $this->logger->notice('Reward availability has validation errors', ['component' => 'parser']);
            $checker->ErrorCode = ACCOUNT_ENGINE_ERROR;
        }
        if (!empty($errors) || !empty($parseErrors)) {
            return;
        }

        $response
            ->setState($checker->ErrorCode)
            ->setMessage($checker->ErrorCode === ACCOUNT_CHECKED ? '' : $checker->ErrorMessage);

        if ($this->partnerCanDebug($row->getPartner())) {
            $response->setDebugInfo($checker->DebugInfo);
        }

        $row->setResponse($response);
        $this->manager->persist($row);
        $this->manager->flush();
    }

    private function getDetailedAddress(?string $text, array $detailedAddress): DetailedAddress
    {
        // TODO calc fields of $detailedAddress by $text, maybe hotelName & googleGeo
        return new DetailedAddress($text, $detailedAddress['addressLine'] ?? null, $detailedAddress['city'] ?? null,
            $detailedAddress['stateName'] ?? null, $detailedAddress['countryName'] ?? null,
            $detailedAddress['postalCode'] ?? null, $detailedAddress['lat'] ?? null, $detailedAddress['lng'] ?? null,
            $detailedAddress['timezone'] ?? null);
    }

    public function getMongoDocumentClass(): string
    {
        return RaHotel::class;
    }

    public function getMethodKey() : string
    {
        return RaHotel::METHOD_KEY;
    }

}