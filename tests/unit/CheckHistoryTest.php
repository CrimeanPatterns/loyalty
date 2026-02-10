<?php

namespace Tests\Unit;
use AppBundle\Document\CheckAccount;
use AppBundle\Extension\HistoryProcessor;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AppBundle\Model\Resources\History;
use AppBundle\Model\Resources\HistoryField;
use AppBundle\Model\Resources\HistoryState;
use AppBundle\Model\Resources\HistoryState\StructureVersion1;
use AppBundle\Model\Resources\RequestItemHistory;
use AppBundle\Model\Resources\SubAccount;
use AppBundle\Worker\CheckExecutor\CheckAccountExecutor;
use Doctrine\DBAL\Connection;
use Helper\Aw;
use Psr\Log\NullLogger;

/**
 * @backupGlobals disabled
 */
class CheckHistoryTest extends \Tests\Unit\BaseWorkerTestClass
{

    const TEST_AES_KEY = 'qwerty12345';
    const TEST_CACHE_VERSION = 1;


    protected function getHistoryRequest()
    {
        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
                ->setUserId('SomeID')
                ->setPassword('g5f4' . rand(1000, 9999) . '_q')
                ->setLogin('history');
        return $request;
    }

    public function testParseHistory() {
        $history = (new RequestItemHistory())->setRange(History::HISTORY_COMPLETE);
        $request = $this->getHistoryRequest();
        $request->setHistory($history);

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertEquals(10, count($response->getHistory()->getRows()));
    }

    public function testParseHistoryNotArray() {
        $providerCode = "pc" . bin2hex(random_bytes(8));
        /** @var Aw $aw */
        $aw = $this->getModule('\\' . Aw::class);
        $aw->createAwProvider(null, $providerCode, ['CanCheckHistory' => 1], [
            "GetHistoryColumns" => function() {
                return [
                    "Type"            => "Info",
                    "Post Date"       => "PostingDate",
                    "Bonus"           => "Bonus",
                    "Total Points"    => "Miles",
                ];
            },
            "Parse" => function () {
                $this->SetBalance(10);
                $this->AddSubAccount([
                    "Balance"     => 1,
                    "Code"        => "SubAcc1",
                    "DisplayName" => "SubAccount 1",
                    "HistoryRows" => [
                        [
                            'Post Date'       => strtotime("2020-01-01"),
                            'Type'            => 'Bonus',
                            'Bonus'           => '+1000',
                        ],
                    ]
                ]);
            },
            "ParseHistory" => function($startDate = NULL) {
                return false;
            }
        ]);

        $history = (new RequestItemHistory())->setRange(History::HISTORY_COMPLETE);

        $request = new CheckAccountRequest();
        $request
            ->setProvider($providerCode)
            ->setUserId('SomeID')
            ->setPassword('p' . random_int(1000, 9999) . '_q')
            ->setLogin('history')
            ->setHistory($history);

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        /** @var CheckAccountExecutor $worker */
        $worker = $this->getCheckAccountWorker();

        $worker->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());

        /** @var History $history */
        $history = $response->getHistory();
        $this->assertNotNull($history);
        $subAccsHistory = $history->getSubAccounts();
        $this->assertNull($history->getRows());
        // unnecessary tests
        $state = $history->getState();
        // for debug
//        $stateData = json_encode(json_decode(AESDecode(base64_decode($state), self::TEST_AES_KEY)), JSON_PRETTY_PRINT);
        $this->assertNotNull($state);
        $this->assertEquals(1, count($subAccsHistory));
        $this->assertEquals(1, count($subAccsHistory[0]->getRows()));

    }

    public function testInvalidCacheVersion() {
        $hVersion = 2;
        $stateObj = (new StructureVersion1())->setLastDate(new \DateTime('2012-01-06'))->setCacheVersion($hVersion);
        $state = base64_encode(AESEncode($this->serializer->serialize($stateObj, 'json'), self::TEST_AES_KEY));
        $history = (new RequestItemHistory())->setRange(History::HISTORY_INCREMENTAL)->setState($state);
        $request = $this->getHistoryRequest();
        $request->setHistory($history);

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertEquals(10, count($response->getHistory()->getRows()));
        $this->assertEquals(History::HISTORY_COMPLETE, $response->getHistory()->getRange());
    }

    public function testValidCacheVersion() {
        $hVersion = 1;
        $stateObj = (new StructureVersion1())->setLastDate(new \DateTime('2012-01-06'))->setCacheVersion($hVersion);
        $state = base64_encode(AESEncode($this->serializer->serialize($stateObj, 'json'), self::TEST_AES_KEY));
        $history = (new RequestItemHistory())->setRange(History::HISTORY_INCREMENTAL)->setState($state);
        $request = $this->getHistoryRequest();
        $request->setHistory($history);

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertEquals(5, count($response->getHistory()->getRows()));
        $this->assertEquals(History::HISTORY_INCREMENTAL, $response->getHistory()->getRange());
    }

    private function getHistoryProcessor()
    {
        $connection = $this->getCustomMock(Connection::class);
        $connection->method('executeQuery')->willReturnCallback(function() {
            return $this->connection->executeQuery("SELECT ".self::TEST_CACHE_VERSION." As CacheVersion, 1 as CanCheckHistory");
        });

        return new HistoryProcessor($this->serializer, $connection, self::TEST_AES_KEY, new NullLogger());
    }

    public function testSubAccountHistoryParserErrors()
    {
        $history = (new RequestItemHistory())->setRange(History::HISTORY_COMPLETE);

        $request = $this->getHistoryRequest()
                        ->setLogin('History.SubAccounts')
                        ->setLogin2('invalid.parsing')
                        ->setHistory($history);

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        /** @var CheckAccountExecutor $worker */
        $worker = $this->getCheckAccountWorker();

        $worker->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());

        $subAccsHistory = $response->getHistory()->getSubAccounts();
        $testItem = $subAccsHistory[0];
        $this->assertEquals('testproviderSubAcc1', $testItem->getCode());
        $rows = $testItem->getRows();
        $this->assertEquals(1, count($rows));
        $this->assertEquals(5, count($rows[0]->getFields()));
    }

    public function testSubAccountHistoryComplete()
    {
        $history = (new RequestItemHistory())->setRange(History::HISTORY_COMPLETE);

        $request = $this->getHistoryRequest()
                        ->setLogin('History.SubAccounts')
                        ->setHistory($history);

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        /** @var CheckAccountExecutor $worker */
        $worker = $this->getCheckAccountWorker();

        $worker->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());

        $testResults = file_get_contents(__DIR__.'/../_data/historyStateComplete.json');

        /** @var History $history */
        $history = $response->getHistory();
        $subAccsHistory = $history->getSubAccounts();
        $this->assertEquals(3, count($history->getRows()));
        $this->assertEquals(2, count($subAccsHistory));
        $this->assertEquals(2, count($subAccsHistory[0]->getRows()));
        $this->assertEquals(2, count($subAccsHistory[1]->getRows()));
        $this->assertEquals($testResults, json_encode(json_decode(AESDecode(base64_decode($history->getState()), self::TEST_AES_KEY)), JSON_PRETTY_PRINT));
    }

    public function testSubAccountMissed(){
        // ref 16900#note-2
        // #1
        $history = (new RequestItemHistory())->setRange(History::HISTORY_COMPLETE);

        $request = $this->getHistoryRequest()
            ->setLogin('History.SubAccounts')
            ->setLogin2('missed1')
            ->setHistory($history);

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));

        /** @var CheckAccountExecutor $worker */
        $worker = $this->getCheckAccountWorker();

        $worker->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());

        /** @var History $history */
        $history = $response->getHistory();
        $subAccsHistory = $history->getSubAccounts();
        $state = $history->getState();
        // for debug
//        $stateData = json_encode(json_decode(AESDecode(base64_decode($state), self::TEST_AES_KEY)), JSON_PRETTY_PRINT);
        $this->assertNotNull($state);
        $this->assertEquals(1, count($subAccsHistory));
        $this->assertEquals(2, count($subAccsHistory[0]->getRows()));


        // #2
        $history = (new RequestItemHistory())->setRange(History::HISTORY_INCREMENTAL)->setState($state);

        $request = $this->getHistoryRequest()
            ->setLogin('History.SubAccounts')
            ->setLogin2('missed2')
            ->setHistory($history);

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));

        $worker->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());

        /** @var History $history */
        $history = $response->getHistory();
        $subAccsHistory = $history->getSubAccounts();
        $state = $history->getState();
//        $stateData = json_encode(json_decode(AESDecode(base64_decode($state), self::TEST_AES_KEY)), JSON_PRETTY_PRINT);
        $this->assertNotNull($state);
        $this->assertEquals(History::HISTORY_INCREMENTAL,$history->getRange());
        $this->assertEquals(1, count($history->getRows()));
        $this->assertEquals(1, count($subAccsHistory));
        $this->assertEquals(2, count($subAccsHistory[0]->getRows()));

        // #3

        $stateOld = $state;
        $history = (new RequestItemHistory())->setRange(History::HISTORY_INCREMENTAL)->setState($state);

        $request = $this->getHistoryRequest()
            ->setLogin('History.SubAccounts')
            ->setLogin2('missed3')
            ->setHistory($history);

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));

        $worker->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());

        /** @var History $history */
        $history = $response->getHistory();
        $subAccsHistory = $history->getSubAccounts();
        $state = $history->getState();
//        $stateData = json_encode(json_decode(AESDecode(base64_decode($state), self::TEST_AES_KEY)), JSON_PRETTY_PRINT);
        $this->assertNotEquals($state, $stateOld);
        $this->assertEquals(1, count($history->getRows()));
        $this->assertEquals(History::HISTORY_INCREMENTAL, $history->getRange());
        $this->assertNull($subAccsHistory);
        $state = $history->getState();

        // #4

        $history = (new RequestItemHistory())->setRange(History::HISTORY_INCREMENTAL)->setState($state);

        $request = $this->getHistoryRequest()
            ->setLogin('History.SubAccounts')
            ->setLogin2('missed4')
            ->setHistory($history);

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));

        $worker->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());

        /** @var History $history */
        $history = $response->getHistory();
        $subAccsHistory = $history->getSubAccounts();
        $state = $history->getState();
//        $stateData = json_encode(json_decode(AESDecode(base64_decode($state), self::TEST_AES_KEY)), JSON_PRETTY_PRINT);
        $this->assertNotNull($state);
        $this->assertEquals(History::HISTORY_INCREMENTAL,$history->getRange());
        $this->assertEquals(2, count($history->getRows()));
        $this->assertEquals(1, count($subAccsHistory));
        $this->assertEquals(5, count($subAccsHistory[0]->getRows()));// TODO: ????
    }

    public function testHiddenHistoryFields()
    {
        $history = (new RequestItemHistory())->setRange(History::HISTORY_COMPLETE);

        $request = $this->getHistoryRequest()
                        ->setLogin('History.HiddenFields')
                        ->setUserData(json_encode(['accountId' => 'someId']))
                        ->setHistory($history);

        $responseAw = new CheckAccountResponse();
        $responseAw->setRequestid(bin2hex(random_bytes(10)));
        /** @var CheckAccountExecutor $worker */
        $worker = $this->getCheckAccountWorker();
        $worker->processRequest($request, $responseAw, (new CheckAccount())->setPartner("awardwallet"));
        $this->assertEquals(ACCOUNT_CHECKED, $responseAw->getState());

        $responsePartner = new CheckAccountResponse();
        $responsePartner->setRequestid(bin2hex(random_bytes(10)));
        $worker->processRequest($request, $responsePartner, (new CheckAccount())->setPartner("test"));
        $this->assertEquals(ACCOUNT_CHECKED, $responsePartner->getState());

        $subAccsResult = [
            "awardwallet" => $responseAw->getHistory()->getSubAccounts(),
            "test" => $responsePartner->getHistory()->getSubAccounts()
        ];
        $this->assertNotEquals($subAccsResult["awardwallet"], $subAccsResult["test"]);

        /** @var SubAccount[] $subAccs */
        foreach ($subAccsResult as $partner => $subAccs) {
            $fields = $subAccs[0]->getRows()[1]->getFields();
            $exists = false;
            /** @var HistoryField $field */
            foreach ($fields as $field)
                if($field->getName() === "Transaction Description")
                    $exists = true;

            $expect = $partner === "awardwallet" ? true : false;
            $this->assertEquals($expect, $exists);
        }
    }

    /**
     * @dataProvider dataStateIncremental
     */
    public function testSubAccountHistoryIncremental($json, $mainRowsCount, $hasBadValue)
    {
        $state = base64_encode(AESEncode(file_get_contents($json), self::TEST_AES_KEY));
        $history = (new RequestItemHistory())->setRange(History::HISTORY_INCREMENTAL)->setState($state);

        $request = $this->getHistoryRequest()
                        ->setLogin('History.SubAccounts')
                        ->setHistory($history);

        if ($hasBadValue) {
            $request->setLogin2('badSubAcc');
        }

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        /** @var CheckAccountExecutor $worker */
        $worker = $this->getCheckAccountWorker();

        $worker->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());

        $testResults = file_get_contents(__DIR__.'/../_data/historyStateComplete.json');

        /** @var History $history */
        $history = $response->getHistory();
        $subAccsHistory = $history->getSubAccounts();
        $this->assertEquals(History::HISTORY_INCREMENTAL, $history->getRange());
        $this->assertEquals($mainRowsCount, count($history->getRows()));
        $stateData = json_encode(json_decode(AESDecode(base64_decode($history->getState()), self::TEST_AES_KEY)), JSON_PRETTY_PRINT);
        if ($hasBadValue) {
            $this->assertEquals(1, count($subAccsHistory));
            $this->assertEquals(2, count($subAccsHistory[0]->getRows()));
//            $this->assertEquals($testResults,$stateData);//TODO??? надо поменять $testResults
        } else {
            $this->assertEquals(2, count($subAccsHistory));
            $this->assertEquals(2, count($subAccsHistory[0]->getRows()));
            $this->assertEquals(2, count($subAccsHistory[1]->getRows()));
            $this->assertEquals($testResults, $stateData);
        }
    }

    public function dataStateIncremental()
    {
        return [
            [__DIR__.'/../_data/historyStateIncremental.json', 1, false],
            [__DIR__.'/../_data/historyStateIncremental.json', 1, true],
            [__DIR__.'/../_data/historyStateIncrementalNullMain.json', 3, false]
        ];
    }

    /**
     * @dataProvider pastIncrementalDataProvider
     */
    public function testPastIncremental(string $lastDate, string $requestedHistoryRange, string $expectedHistoryRange, $expectedRowCount)
    {
        $providerCode = "pc" . bin2hex(random_bytes(8));
        /** @var Aw $aw */
        $aw = $this->getModule('\\' . Aw::class);
        $aw->createAwProvider(null, $providerCode, [], [
            "GetHistoryColumns" => function() {
                return [
                    "Type"            => "Info",
                    "Eligible Nights" => "Info",
                    "Post Date"       => "PostingDate",
                    "Description"     => "Description",
                    "Starpoints"      => "Miles",
                    "Bonus"           => "Bonus",
                ];
            },
            "Parse" => function () {
                $this->SetBalance(10);
                $this->AddSubAccount([
                    "Balance"     => 1,
                    "Code"        => "SubAcc1",
                    "DisplayName" => "SubAccount 1",
                    "HistoryRows" => [
                        [
                            'Post Date'       => strtotime("2020-01-03"),
                            'Type'            => 'Bonus',
                            'Eligible Nights' => '-',
                            'Bonus'           => '+3,000',
                            'Description'     => 'Subacc1 hist 3',
                        ],
                        [
                            'Post Date'       => strtotime("2020-01-02"),
                            'Type'            => 'Bonus',
                            'Eligible Nights' => '-',
                            'Bonus'           => '+2,000',
                            'Description'     => 'Subacc1 hist 2',
                        ],
                        [
                            'Post Date'       => strtotime("2020-01-01"),
                            'Type'            => 'Bonus',
                            'Eligible Nights' => '-',
                            'Bonus'           => '+1,000',
                            'Description'     => 'Subacc1 hist 1',
                        ],
                    ]
                ]);
            },
            "ParseHistory" => function($startDate = null) {
                return [
                    [
                        'Post Date'       => strtotime("2020-01-03"),
                        'Type'            => 'Bonus',
                        'Eligible Nights' => '-',
                        'Bonus'           => '+3,000',
                        'Description'     => 'Main acc hist 3',
                    ],
                    [
                        'Post Date'       => strtotime("2020-01-02"),
                        'Type'            => 'Bonus',
                        'Eligible Nights' => '-',
                        'Bonus'           => '+2,000',
                        'Description'     => 'Main acc hist 2',
                    ],
                    [
                        'Post Date'       => strtotime("2020-01-01"),
                        'Type'            => 'Bonus',
                        'Eligible Nights' => '-',
                        'Bonus'           => '+1,000',
                        'Description'     => 'Main acc hist 1',
                    ],
                ];
            }
        ]);

        $json = json_encode([
            "structureVersion" => 1,
            "lastDate" => $lastDate,
            "cacheVersion" => 1,
            "subAccountLastDates" => [
                $providerCode . "SubAcc1" => $lastDate,
            ]
        ]);
        $state = base64_encode(AESEncode($json, self::TEST_AES_KEY));
        $history = (new RequestItemHistory())->setRange($requestedHistoryRange)->setState($state);


        $request = new CheckAccountRequest();
        $request
            ->setProvider($providerCode)
            ->setUserId('SomeID')
            ->setPassword('p' . random_int(1000, 9999) . '_q')
            ->setLogin('history')
            ->setHistory($history);

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        /** @var CheckAccountExecutor $worker */
        $worker = $this->getCheckAccountWorker();

        $worker->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());

        /** @var History $history */
        $history = $response->getHistory();
        $subAccsHistory = $history->getSubAccounts();
        $this->assertEquals($expectedHistoryRange, $history->getRange());
        $this->assertEquals($expectedRowCount, count($history->getRows()));
        $this->assertEquals($expectedRowCount, count($subAccsHistory[0]->getRows()));
    }

    public function pastIncrementalDataProvider() : array
    {
        return [
            // string $lastDate, string $requestedHistoryRange, string $expectedHistoryRange, $expectedRowCount
            ["2020-01-01", "incremental", "incremental", 3],
            ["2020-01-02", "incremental", "incremental", 2],
            ["2020-01-03", "incremental", "incremental", 1],

            ["2020-01-01", "incremental2", "incremental2", 3],
            ["2020-01-02", "incremental2", "incremental2", 3],
            ["2020-01-03", "incremental2", "incremental2", 3],
        ];
    }

    protected function getCheckAccountWorker()
    {
        $worker = parent::getCheckAccountWorker();
        $worker->setHistoryProcessor($this->getHistoryProcessor());
        return $worker;
    }

    /**
     * @dataProvider dataBonus
     */
    public function testBonusFormat(string $parseSubHistoryBonus, string $parseHistoryBonus, bool $isValidSub, bool $isValid)
    {
        $providerCode = "pc" . bin2hex(random_bytes(8));
        /** @var Aw $aw */
        $aw = $this->getModule('\\' . Aw::class);
        $aw->createAwProvider(null, $providerCode, ['CanCheckHistory' => 1], [
            "GetHistoryColumns" => function() {
                return [
                    "Type"            => "Info",
                    "Post Date"       => "PostingDate",
                    "Bonus"           => "Bonus",
                    "Total Points"    => "Miles",
                ];
            },
            "Parse" => function () use ($parseSubHistoryBonus) {
                $this->SetBalance(10);
                $this->AddSubAccount([
                    "Balance"     => 1,
                    "Code"        => "SubAcc1",
                    "DisplayName" => "SubAccount 1",
                    "HistoryRows" => [
                        [
                            'Post Date'       => strtotime("2020-01-01"),
                            'Type'            => 'Bonus',
                            'Bonus'           => $parseSubHistoryBonus,
                        ],
                    ]
                ]);
            },
            "ParseHistory" => function($startDate = NULL) use ($parseHistoryBonus) {
                return [
                    [
                        'Post Date'       => strtotime("2020-01-01"),
                        'Type'            => 'Bonus',
                        'Total Points'    => $parseHistoryBonus,
                    ],
                ];
            }
        ]);

        $history = (new RequestItemHistory())->setRange(History::HISTORY_COMPLETE);

        $request = new CheckAccountRequest();
        $request
            ->setProvider($providerCode)
            ->setUserId('SomeID')
            ->setPassword('p' . random_int(1000, 9999) . '_q')
            ->setLogin('history')
            ->setHistory($history);

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        /** @var CheckAccountExecutor $worker */
        $worker = $this->getCheckAccountWorker();

        $worker->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());

        /** @var History $history */
        $history = $response->getHistory();
        $subAccsHistory = $history->getSubAccounts();
        if ($isValidSub) {
            $this->assertEquals(1, count($subAccsHistory[0]->getRows()));
        } else {
            $this->assertNull($subAccsHistory);
        }
        if ($isValid) {
            $this->assertEquals(1, count($history->getRows()));
        } else {
            $this->assertNull($history->getRows());
        }

    }

    public function dataBonus() : array
    {
        return [
            // string $parseSubHistoryBonus, string $parseHistoryBonus, bool $isValidSub, bool $isValid
            ["Hotel Stay - 07 Oct 2021 to 09 Oct 2021 - Radisson Blu Hotel, Glasgow", "-110.5", false, true],
            ["-110.5", "Hotel Stay - 07 Oct 2021 to 09 Oct 2021 - Radisson Blu Hotel, Glasgow", true, false],
            ["+1000", "-110.5", true, true],
            ["+1000", "-110.5", true, true],
        ];
    }

}