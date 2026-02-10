<?php

namespace Tests\Functional\RewardAvailability;

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

/**
 * @ignore
 */
class DataParser extends \TAccountChecker
{
    use ProxyList;

    const noWarningEmpty = 0;
    const warningEmpty = 1;
    const warningNotEmpty = 2;
    const wrongData = 3;
    const checkDates = 4;
    const wrongMiles = 5;
    const wrongDatesSkip = 6;

    public static $checkState;
    public static $checkParseMode;

    public static function reset(): void
    {
        self::$checkState = null;
        self::$checkParseMode = false;
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
        $this->logger->notice('type: ' . self::$checkState);
        switch (self::$checkState) {
            case self::noWarningEmpty;
                return ['routes' => []];
            case self::checkDates;
                $routes = $this->routesWithCorrectDate($fields);
                if (self::$checkParseMode && $this->AccountFields['ParseMode']==='testParseMode') {
                    return ['routes' => [array_shift($routes)]];
                }
                return ['routes' => $routes];
            case self::warningEmpty;
            {
                $this->SetWarning('no flights');
                return [];
            }
            case self::warningNotEmpty;
            {
                $this->SetWarning('check rate');
                return ['routes' => $this->routes($fields)];
            }
            case self::wrongData;
            {
                $routes = $this->routes($fields);
                foreach ($routes as &$route) {
                    foreach ($route['connections'] as &$connection) {
                        $connection['departure']['date'] = false;
                    }
                }
                return ['routes' => $routes];
            }
            case self::wrongDatesSkip;
            {
                $routes = $this->routes($fields);
                foreach ($routes as &$route) {
                    foreach ($route['connections'] as $num=>&$connection) {
                        if ($num===1){
                            $connection['departure']['airport'] = 'SVO';
                            $connection['arrival']['airport'] = 'LED';
                            $connection['departure']['date'] = date('Y-m-d', strtotime($fields['DepDate']. '-1 day')) . ' 11:30';
                            $connection['arrival']['date'] = date('Y-m-d', strtotime($fields['DepDate']. '-1 day')) . ' 10:30';
                            break 2;
                        }
                    }
                }
                return ['routes' => $routes];
            }
            case self::wrongMiles;
            {
                $routes = $this->routes($fields);
                $routes[0]['redemptions']['miles'] = 0;
                return ['routes' => $routes];
            }
            default:
                throw new \CheckException('bad params', ACCOUNT_ENGINE_ERROR);
        }
    }

    private function routes($fields): array
    {
        // !!! specific routes. don't fix anything. can only be added if needed !!!
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
                            'date' => date('Y-m-d', $fields['DepDate']) . ' 17:30',
                            'airport' => 'CDG',
                            'terminal' => "A",
                        ],
                        'arrival' => [
                            'date' => date('Y-m-d', strtotime("+1 day", $fields['DepDate'])) . ' 06:50',
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
                'redemptions' => ['miles' => 39000, 'program' => 'british'],
                'payments' => ['currency' => 'USD', 'taxes' => null, 'fees' => 35.64],
                'connections' => [
                    [
                        'departure' => [
                            'date' => date('Y-m-d', $fields['DepDate']) . ' 21:00',
                            'dateTime' => 1639170000,
                            'airport' => 'JFK',
                            'terminal' => null,
                        ],
                        'arrival' => [
                            'date' => date('Y-m-d', strtotime('+1 day',$fields['DepDate'])) . ' 08:50',
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
                            'date' => date('Y-m-d', strtotime('+1 day',$fields['DepDate'])) . ' 11:30',
                            'dateTime' => 1639222200,
                            'airport' => 'LHR',
                            'terminal' => null,
                        ],
                        'arrival' => [
                            'date' => date('Y-m-d', strtotime('+1 day',$fields['DepDate'])) . ' 13:50',
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

    private function routesWithCorrectDate($fields): array
    {
        // !!! specific routes. don't fix anything. can only be added if needed !!!
        return [
            [
                'distance' => null,
                'num_stops' => 3,
                'times' => [],
                'redemptions' => [
                    'miles' => 44000,
                    'program' => 'mileageplus',
                ],
                'payments' => [
                    'currency' => 'USD',
                    'taxes' => 47.17,
                    'fees' => null,
                ],
                'connections' => [
                    0 => [
                        'num_stops' => null,
                        'departure' => [
                            'date' => date('Y-m-d', $fields['DepDate']) . ' 09:30',
                            'airport' => 'MNL',
                        ],
                        'arrival' => [
                            'date' => date('Y-m-d', $fields['DepDate']) . ' 15:10',
                            'airport' => 'NRT',
                        ],
                        'cabin' => 'economy',
                        'meal' => 'Meal',
                        'fare_class' => 'X',
                        'flight' => [0 => 'NH820',],
                        'airline' => 'NH',
                        'aircraft' => 'Boeing 787-10 Dreamliner',
                    ],
                    1 => [
                        'num_stops' => null,
                        'departure' => [
                            'date' => date('Y-m-d', $fields['DepDate']) . ' 18:55',
                            'airport' => 'NRT',
                        ],
                        'arrival' => [
                            'date' => date('Y-m-d', $fields['DepDate']) . ' 07:15',
                            'airport' => 'HNL',
                            'dayPlus' => true,
                        ],
                        'cabin' => 'economy',
                        'meal' => 'Dinner',
                        'fare_class' => 'X',
                        'flight' => [0 => 'UA902',],
                        'airline' => 'UA',
                        'aircraft' => 'Boeing 777-200',
                    ],
                    2 => [
                        'num_stops' => null,
                        'departure' => [
                            'date' => date('Y-m-d', $fields['DepDate']) . ' 13:15',
                            'airport' => 'HNL',
                        ],
                        'arrival' => [
                            'date' => date('Y-m-d', $fields['DepDate']) . ' 21:16',
                            'airport' => 'SFO',
                            'dayPlus' => true,
                        ],
                        'cabin' => 'economy',
                        'meal' => 'Meals for purchase',
                        'fare_class' => 'X',
                        'flight' => [0 => 'UA1141',],
                        'airline' => 'UA',
                        'times' => ['layover' => null,],
                        'aircraft' => 'Boeing 777-200',
                    ],
                    3 => [
                        'num_stops' => null,
                        'departure' => [
                            'date' => date('Y-m-d', strtotime('+1 day', $fields['DepDate'])) . ' 08:35',
                            'airport' => 'SFO',
                        ],
                        'arrival' => [
                            'date' => date('Y-m-d', strtotime('+1 day', $fields['DepDate'])) . ' 09:29',
                            'airport' => 'SMF',
                        ],
                        'cabin' => 'economy',
                        'meal' => 'Meals are not offered for this flight',
                        'fare_class' => 'X',
                        'flight' => [0 => 'UA5445',],
                        'airline' => 'UA',
                        'operator' => 'Skywest dba United Express',
                        'times' => ['layover' => null,],
                        'aircraft' => 'Canadair Regional Jet',
                    ],
                ],
                'tickets' => null,
                'award_type' => null,
            ],
            [
                'distance' => null,
                'num_stops' => 2,
                'times' => [],
                'redemptions' => [
                    'miles' => 80000,
                    'program' => 'mileageplus',
                ],
                'payments' => [
                    'currency' => 'USD',
                    'taxes' => 54.47,
                    'fees' => null,
                ],
                'connections' =>
                    [
                        0 => [
                            'num_stops' => null,
                            'departure' => [
                                'date' => date('Y-m-d', $fields['DepDate']) . ' 18:55',
                                'airport' => 'NRT',
                            ],
                            'arrival' => [
                                'date' => date('Y-m-d', $fields['DepDate']) . ' 07:10',
                                'airport' => 'HNL',
                                'dayPlus' => true,
                            ],
                            'cabin' => 'economy',
                            'meal' => 'Dinner',
                            'fare_class' => 'YN',
                            'flight' => [0 => 'UA902',],
                            'airline' => 'UA',
                            'times' => ['layover' => null,],
                            'aircraft' => 'Boeing 777-200',
                        ],
                        1 => [
                            'num_stops' => null,
                            'departure' => [
                                'date' => date('Y-m-d', strtotime('+1 day', $fields['DepDate'])) . ' 13:15',
                                'airport' => 'HNL',
                            ],
                            'arrival' => [
                                'date' => date('Y-m-d', strtotime('+1 day', $fields['DepDate'])) . ' 21:19',
                                'airport' => 'SFO',
                                'dayPlus' => true,
                            ],
                            'cabin' => 'economy',
                            'meal' => 'Meals for purchase',
                            'fare_class' => 'YN',
                            'flight' => [0 => 'UA1141',],
                            'airline' => 'UA',
                            'times' => ['layover' => null,],
                            'aircraft' => 'Boeing 777-200',
                        ],
                        2 =>
                            [
                                'num_stops' => null,
                                'departure' => [
                                    'date' => date('Y-m-d', strtotime('+1 day', $fields['DepDate'])) . ' 09:00',
                                    'airport' => 'SFO',
                                ],
                                'arrival' => [
                                    'date' => date('Y-m-d', strtotime('+1 day', $fields['DepDate'])) . ' 12:03',
                                    'airport' => 'SLC',
                                ],
                                'cabin' => 'economy',
                                'meal' => 'Meals are not offered for this flight',
                                'fare_class' => 'YN',
                                'flight' => [0 => 'UA5872',],
                                'airline' => 'UA',
                                'operator' => 'Skywest dba United Express',
                                'times' => ['layover' => null,],
                                'aircraft' => 'Embraer 175',
                            ],
                    ],
                'tickets' => null,
                'award_type' => null,
            ],
            [
                'distance' => null,
                'num_stops' => 2,
                'times' => [],
                'redemptions' => [
                    'miles' => 80000,
                    'program' => 'mileageplus',
                ],
                'payments' => [
                    'currency' => 'USD',
                    'taxes' => 54.47,
                    'fees' => null,
                ],
                'connections' =>
                    [
                        0 => [
                            'num_stops' => null,
                            'departure' => [
                                'date' => date('Y-m-d', $fields['DepDate']) . ' 18:55',
                                'airport' => 'NRT',
                            ],
                            'arrival' => [
                                'date' => date('Y-m-d', $fields['DepDate']) . ' 07:20',
                                'airport' => 'HNL',
                                'dayPlus' => true,
                            ],
                            'cabin' => 'economy',
                            'meal' => 'Dinner',
                            'fare_class' => 'YN',
                            'flight' => [0 => 'UA902',],
                            'airline' => 'UA',
                            'times' => ['layover' => null,],
                            'aircraft' => 'Boeing 777-200',
                        ],
                        1 => [
                            'num_stops' => null,
                            'departure' => [
                                'date' => date('Y-m-d', strtotime('+1 day', $fields['DepDate'])) . ' 13:00',
                                'airport' => 'HNL',
                            ],
                            'arrival' => [
                                'date' => date('Y-m-d', strtotime('+1 day', $fields['DepDate'])) . ' 21:23',
                                'airport' => 'LAX',
                            ],
                            'cabin' => 'economy',
                            'meal' => 'Meals for purchase',
                            'fare_class' => 'YN',
                            'flight' => [0 => 'UA1231',],
                            'airline' => 'UA',
                            'times' => ['layover' => null,],
                            'aircraft' => 'Boeing 757-300',
                        ],
                        2 => [
                            'num_stops' => null,
                            'departure' => [
                                'date' => date('Y-m-d', strtotime('+1 day', $fields['DepDate'])) . ' 22:55',
                                'airport' => 'LAX',
                            ],
                            'arrival' => [
                                'date' => date('Y-m-d', strtotime('+1 day', $fields['DepDate'])) . ' 07:18',
                                'airport' => 'BOS',
                            ],
                            'cabin' => 'economy',
                            'meal' => 'Snacks for Purchase',
                            'fare_class' => 'YN',
                            'flight' => [0 => 'UA2402',],
                            'airline' => 'UA',
                            'times' => ['layover' => null,],
                            'aircraft' => 'Boeing 737 MAX 9',
                        ],
                    ],
                'tickets' => null,
                'award_type' => null,
            ]
        ];
    }

}