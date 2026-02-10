<?php


namespace AppBundle\Extension;


use AppBundle\Document\RewardAvailability;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityFlights;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityResponse;
use AppBundle\Model\Resources\RewardAvailability\Route;
use AppBundle\Model\Resources\RewardAvailability\Segment;
use AwardWallet\Common\DateTimeUtils;
use Doctrine\DBAL\Connection;
use JMS\Serializer\Serializer;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Psr\Log\LoggerInterface;

class SendToAW
{
    const QUEUE_NAME = 'send_to_aw';

    private const HOME_CARRIER = 1;
    private const PARTNER_CARRIER = 2;
    private const MIX_CARRIER = 3;

    /** Connecton $connection */
    private $connection;

    /** @var AMQPChannel $mqChanel */
    private $mqChanel;

    /** @var Serializer $serializer */
    private $serializer;

    private $stats;

    public function __construct(
        LoggerInterface $logger,
        Connection $connection,
        AMQPChannel $mqChanel,
        Serializer $serializer
    ) {
        $this->logger = $logger;
        $this->connection = $connection;
        $this->mqChanel = $mqChanel;
        $this->serializer = $serializer;

        $mqpTable = new AMQPTable(["x-message-ttl" => DateTimeUtils::SECONDS_PER_HOUR * 2 * 1000]);
        $this->mqChanel->queue_declare(self::QUEUE_NAME, false, true, false, false, false, $mqpTable);
    }

    /**
     * Sending messages to send_to_aw queue
     * @param RewardAvailability $row
     */
    public function sendMessage(RewardAvailability $row)
    {
        if (!$row->getResponse() || !$row->getRequest()) {
            return;
        }
        $this->stats = [];
        $this->logger->debug('sendMessage to send_to_aw: start prepare', ['requestId' => $row->getId()]);
        $startTime = microtime(true);
        $msg = $this->prepareResult(
            $row->getResponse(),
            $row->getRequest()->getProvider(),
            $row->getRequest()->getPassengers()->getAdults(),
            $row->getId(),
            $row->getRequest()->getDeparture()->getAirportCode(),
            $row->getRequest()->getArrival(),
            $row->getRequest()->getCabin(),
            $row->getPartner()
        );
        $this->logger->info('sendMessage to send_to_aw: prepared',
            ['requestId' => $row->getId(), 'duration' => microtime(true) - $startTime]);
        if (null === $msg) {
            return;
        }
        $message = new AMQPMessage(
            $this->serializer->serialize($msg, 'json'),
            array('content_type' => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT)
        );
        $this->mqChanel->basic_publish($message, '', self::QUEUE_NAME);
    }

    private function prepareResult(
        RewardAvailabilityResponse $response,
        string $provider,
        int $passengers,
        string $requestId,
        string $depCode,
        string $arrCode,
        string $cabin,
        string $partner
    ): ?RewardAvailabilityFlights {
        $routes = $response->getRoutesToSerialize();
        $providerAirline = $this->providerAirline($provider);

        $fasted = $this->findFastest($routes, $providerAirline);

        $routeByCabin = $this->divideByCabinType($routes, $provider);
//        $fastedCabin = $this->findFastestCabin($routeByCabin, $providerAirline);

        $dividedRoutes = $this->divideByAirlinesEts($routeByCabin, $providerAirline);

        $resultRoutes = $cheapest = $this->findCheapest($dividedRoutes, $providerAirline, $fasted);

        $cheapestHasFast = false;
        foreach ($cheapest as $flightType => $routes) {
            foreach ($routes as $r) {
                if ($r->isFastest()) {
                    $cheapestHasFast = true;
                }
            }
        }

        if (!$cheapestHasFast) {
            foreach ($fasted as $flightType => $r) {
                if (isset($resultRoutes[$flightType])) {
                    foreach ($r as $route) {
                        $resultRoutes[$flightType][] = $route;
                    }
                } else {
                    $resultRoutes[$flightType] = $r;
                }
            }
        }
        /*
         * $resultRoutes = [
         *    HOME_CARRIER    => Route[],
         *    PARTNER_CARRIER => Route[],
         *    MIX_CARRIER     => Route[],
         * ];
         * */
        // sending even empty ones (for search statistics)
/*        if (empty($resultRoutes)) {
            return null;
        }*/
        $this->stats = array_unique($this->stats, SORT_REGULAR);
        return new RewardAvailabilityFlights($requestId, $provider, $passengers, $resultRoutes, $this->stats, $response->getRequestdate(), $depCode, $arrCode, $cabin, $partner);
    }

    private function providerAirline($providerCode): ?string
    {
        if ($providerCode === 'emiratesky') {
            $providerCode = 'skywards';
        }
        $sql = <<<SQL
            SELECT IATACode
            FROM Provider
            WHERE CanCheckRewardAvailability <> 0 AND Code = :CODE
        SQL;
        $row = $this->connection->executeQuery($sql, [':CODE' => $providerCode])->fetch();

        return $row['IATACode'] ?? null;
    }

    private function divideByCabinType(array $routes, string $provider): array
    {
        $routeByCabin = [];
        $airlines = [];
        /** @var Route $route */
        foreach ($routes as $route) {
            /** @var Segment $segment */
            $segments = $route->getSegmentsToSerialize();

            // for #21813
            $curAirlines = array_map(function ($s) {
                return $s->getAirlineCode();
            }, $segments);
            $airports = array_map(function ($s) {
                return $s->getDepartAirport();
            }, $segments);
            /** @var Segment $lastSegment */
            $lastSegment = end($segments);
            $airports[] = $lastSegment->getArrivalAirport();
            $airlines = array_unique($curAirlines);

            list($cabin, $cabinPercentage) = $this->checkCabinType($segments, $route->getTimes()->getFlightMinutes());
            $route->setCabinType($cabin);
            $route->setCabinPercentage($cabinPercentage);
            $routeByCabin[$route->getCabinType()][] = $route;
            $airlines = array_values($airlines);
            foreach ($airlines as $airline) {
                $this->stats[] = ['provider' => $provider, 'carrier' => $airline, 'route' => $airports];
            }
        }
        return $routeByCabin;
    }

    private function checkCabinType($segments, $ftt): array
    {
        // order matter
        $cabinTime = ['firstClass' => 0.0, 'business' => 0.0, 'premiumEconomy' => 0.0, 'economy' => 0.0];
        /** @var Segment $segment */
        foreach ($segments as $segment) {
            $cabinTime[$segment->getCabin()] += $segment->getTimes()->getFlightMinutes();
        }
        $thrift = 0.0;
        foreach ($cabinTime as $cabin => $time) {
            $cabinPercentage = round(($cabinTime[$cabin] + $thrift) / $ftt, 2);
            if ($cabinPercentage >= 0.75) {
                break;
            }
            $thrift += $cabinTime[$cabin];
        }
        $resCabin = $cabin;
        $resPercentage = $cabinPercentage ?? 1;
        return [$resCabin, $resPercentage];
    }

    private function divideByAirlinesEts($routeByCabin, $providerAirline): array
    {
        $result = [];
        foreach ($routeByCabin as $cabin => $routes) {
            $res = [];
            /** @var Route $route */
            foreach ($routes as $route) {
                $segments = $route->getSegmentsToSerialize();

                $ttt = $route->getTimes()->getFlightMinutes() + $route->getTimes()->getLayoverMinutes();
                $flightType = $this->checkFlightType($segments, $providerAirline);
                $path = $this->getPath($route);

                $res[$flightType][$path][$route->getMileCost()->getMiles()][$ttt][(string)$route->getCabinPercentage()][$route->getNumberOfStops()][] = $route;
            }
            $result[$cabin] = $res;
        }
        return $result;
    }

    private function findCheapest($dividedRoutes, $providerAirline, $fasted): array
    {
        $resultRoutes = [];
        $fastestPathTTT = [];
        foreach ($fasted as $flightType => $routes) {
            foreach ($routes as $route) {
                $path = $this->getPath($route);
                $fastestPathTTT[$path] = $route->getTimes()->getFlightMinutes() + $route->getTimes()->getLayoverMinutes();
            }
        }
        foreach ($dividedRoutes as $cabinType => $data) {
            foreach ($data as $flightType => $byPath) {
                foreach ($byPath as $path => $res) {
                    $minMiles = min(array_keys($res));
                    $minTTT = min(array_keys($res[$minMiles]));
                    $maxPercentage = (string)(max(array_map(function ($s) {
                        return (float)$s;
                    }, array_keys($res[$minMiles][$minTTT]))));
                    $minStops = min(array_keys($res[$minMiles][$minTTT][$maxPercentage]));
                    $resRoutes = $res[$minMiles][$minTTT][$maxPercentage][$minStops];

                    /** @var Route $resRoute */
                    $resRoute = array_shift($resRoutes);
                    $segments = $resRoute->getSegmentsToSerialize();
                    $flightType = $this->checkFlightType($segments, $providerAirline);
                    $resRoute->setIsCheapest(true);
                    if (isset($fastestPathTTT[$path])) {
                        $ttt = $resRoute->getTimes()->getFlightMinutes() + $resRoute->getTimes()->getLayoverMinutes();
                        if ($fastestPathTTT[$path] === $ttt) {
                            $resRoute->setIsFastest(true);
                        }
                    }
                    $resultRoutes[$flightType][] = $resRoute;
//                    $keyCheck = implode('-', [$path, $cabinType, $minTTT, $minMiles]);
//                    $resultRoutes[$flightType][$keyCheck][] = $resRoute;
                }
            }
        }
        return $resultRoutes;
    }

    private function findFastest($routes, $providerAirline): array
    {
        $resultRoutes = [];
        $resPath = [];

        /** @var Route $route */
        foreach ($routes as $route) {
            $path = $this->getPath($route);

            $ttt = $route->getTimes()->getFlightMinutes() + $route->getTimes()->getLayoverMinutes();
            $mileCost = $route->getMileCost()->getMiles();
            $taxes = $route->getCashCost()->getFees() ?? 0.0 + $route->getCashCost()->getTaxes() ?? 0.0;
            $resPath[$path][$ttt][$route->getNumberOfStops()][$mileCost][(string)$taxes][] = $route;
        }

        foreach ($resPath as $path => $res) {
            $minTTT = min(array_keys($res));
            $minStops = min(array_keys($res[$minTTT]));
            $minMiles = min(array_keys($res[$minTTT][$minStops]));
            $minTaxes = (string)(min(array_map(function ($s) {
                return (float)$s;
            }, array_keys($res[$minTTT][$minStops][$minMiles]))));

            $resRoutes = $res[$minTTT][$minStops][$minMiles][$minTaxes];
            /** @var Route $resRoute */
            $resRoute = array_shift($resRoutes);

            $segments = $resRoute->getSegmentsToSerialize();
            $flightType = $this->checkFlightType($segments, $providerAirline);
            list($cabin, $cabinPercentage) = $this->checkCabinType($segments, $route->getTimes()->getFlightMinutes());

            $resRoute->setIsFastest(true);
            $resRoute->setCabinType($cabin);
            $resRoute->setCabinPercentage($cabinPercentage);
            $resultRoutes[$flightType][] = $resRoute;
        }
        return $resultRoutes;
    }

    private function findFastestCabin($routeByCabin, $providerAirline): array
    {
        $resultRoutes = [];
        foreach ($routeByCabin as $cabin => $routes) {
            $resPath = [];

            /** @var Route $route */
            foreach ($routes as $route) {
                $path = $this->getPath($route);

                $ttt = $route->getTimes()->getFlightMinutes() + $route->getTimes()->getLayoverMinutes();
                $mileCost = $route->getMileCost()->getMiles();
                $taxes = $route->getCashCost()->getFees() ?? 0.0 + $route->getCashCost()->getTaxes() ?? 0.0;
                $resPath[$path][$ttt][(string)$route->getCabinPercentage()][$route->getNumberOfStops()][$mileCost][(string)$taxes][] = $route;
            }

            foreach ($resPath as $path => $res) {
                $minTTT = min(array_keys($res));
                $maxPercentage = (string)(max(array_map(function ($s) {
                    return (float)$s;
                }, array_keys($res[$minTTT]))));
                $minStops = min(array_keys($res[$minTTT][$maxPercentage]));
                $minMiles = min(array_keys($res[$minTTT][$maxPercentage][$minStops]));
                $minTaxes = (string)(min(array_map(function ($s) {
                    return (float)$s;
                }, array_keys($res[$minTTT][$maxPercentage][$minStops][$minMiles]))));

                $resRoutes = $res[$minTTT][$maxPercentage][$minStops][$minMiles][$minTaxes];
                /** @var Route $resRoute */
                $resRoute = array_shift($resRoutes);

                $segments = $resRoute->getSegmentsToSerialize();
                $flightType = $this->checkFlightType($segments, $providerAirline);

                $r = clone $resRoute;
                $r->setIsFastest(true);
                $resultRoutes[$flightType][] = $r;
            }
        }
        return $resultRoutes;
    }

    private function checkFlightType($segments, $providerAirline): int
    {
        $airlines = array_values(array_unique(array_map(function ($s) {
            return $s->getAirlineCode();
        }, $segments)));
        if (count($airlines) === 1 && $airlines[0] === $providerAirline) {
            return self::HOME_CARRIER;
        }
        if (in_array($providerAirline, $airlines)) {
            return self::MIX_CARRIER;
        }
        return self::PARTNER_CARRIER;
    }

    private function getPath($route): string
    {
        $segments = $route->getSegmentsToSerialize();
        $depPort = $arrPort = null;
        /** @var Segment $segment */
        foreach ($segments as $segment) {
            if (!isset($depPort)) {
                $depPort = $segment->getDepartAirport();
            }
            $arrPort = $segment->getArrivalAirport();
        }
        return $depPort . '-' . $arrPort;
    }

}