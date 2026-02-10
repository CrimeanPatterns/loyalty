<?php

namespace Tests\Functional\RewardAvailability;

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

/**
 * @ignore
 */
class FlightStatsParser extends \TAccountChecker
{
    use ProxyList;

    const zero = 0;
    const all = 1;
    const some = 2;

    public static $aircrafts;

    public static function reset(): void
    {
        self::$aircrafts = null;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setRandomUserAgent();
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function ParseRewardAvailability(array $fields)
    {
        $routes = $this->routesWithAirports();
        switch (self::$aircrafts) {
            case self::all;
                return ['routes'=>$routes];
            case self::some;
            {
                foreach ($routes as &$route) {
                    $last = count($route['connections']) - 1;
                    unset($route['connections'][$last]['aircraft']);
                }
                return ['routes'=>$routes];
            }
            case self::zero;
            {
                foreach ($routes as &$route) {
                    foreach ($route['connections'] as &$connection) {
                        unset($connection['aircraft']);
                    }
                }
                return ['routes'=>$routes];
            }
            default:
                throw new \CheckException('bad params', ACCOUNT_ENGINE_ERROR);
        }
    }

    private function routesWithAirports(): array
    {
        return [
            [
                'distance' => null,
                'num_stops' => 0,
                'times' => ['flight' => '11:20', 'layover' => '00:00'],
                'redemptions' => ['miles' => 55000, 'program' => 'british'],
                'payments' => ['currency' => 'USD', 'taxes' => 5.6, 'fees' => null],
                'connections' => [
                    [
                        'departure' => [
                            'date' => '2021-12-10 17:30',
                            'airport' => 'CDG',
                            'terminal' => "A",
                        ],
                        'arrival' => [
                            'date' => '2021-12-11 06:50',
                            'airport' => 'JFK',
                            'terminal' => 1,
                        ],
                        'meal' => 'Dinner',
                        'cabin' => 'business',
                        'fare_class' => 'HN',
                        'flight' => ['BA0269'],
                        'airline' => 'BA',
                        'operator' => 'BA',
                        'distance' => null,
                        'aircraft' => 'Boeing 787 jet',
                        'times' => ['flight' => '11:20', 'layover' => '00:00'],
                        'num_stops' => 0,
                    ],
                ],
                'tickets' => null,
                'award_type' => 'Standard Reward',
            ],
            [
                'distance' => null,
                'num_stops' => 1,
                'times' => ['flight' => '15:56', 'layover' => null],
                'redemptions' => ['miles' => null, 'program' => 'british'],
                'payments' => ['currency' => 'USD', 'taxes' => null, 'fees' => 35.64],
                'connections' => [
                    [
                        'departure' => [
                            'date' => '2021-12-10 21:00',
                            'dateTime' => 1639170000,
                            'airport' => 'JFK',
                            'terminal' => null,
                        ],
                        'arrival' => [
                            'date' => '2021-12-11 08:50',
                            'dateTime' => 1639212600,
                            'airport' => 'LHR',
                            'terminal' => null,
                        ],
                        'meal' => 'Dinner',
                        'cabin' => 'business',
                        'fare_class' => 'HN',
                        'flight' => ['BA0297'],
                        'airline' => 'BA',
                        'operator' => 'BA',
                        'distance' => null,
                        'aircraft' => 'Boeing 787 jet',
                        'times' => ['flight' => '08:55', 'layover' => '00:00'],
                        'tickets' => 6,
                    ],
                    [
                        'departure' => [
                            'date' => '2021-12-11 11:30',
                            'dateTime' => 1639222200,
                            'airport' => 'LHR',
                            'terminal' => null,
                        ],
                        'arrival' => [
                            'date' => '2021-12-11 13:50',
                            'dateTime' => 1639230600,
                            'airport' => 'CDG',
                            'terminal' => '3',
                        ],
                        'meal' => 'Dinner',
                        'cabin' => 'business',
                        'fare_class' => 'HN',
                        'flight' => ['AA2715'],
                        'airline' => 'AA',
                        'operator' => 'AA',
                        'distance' => null,
                        'aircraft' => 'Airbus A321 jet',
                        'times' => ['flight' => '04:41', 'layover' => '00:00'],
                        'tickets' => 7,
                    ],
                ],
                'tickets' => '6',
                'award_type' => 'Latitude Reward',
            ],
        ];
    }
}