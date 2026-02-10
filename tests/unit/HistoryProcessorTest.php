<?php

namespace Tests\Unit;

use AppBundle\Extension\CheckerFactory;
use AppBundle\Extension\HistoryProcessor;
use AppBundle\Model\Resources\History;
use AppBundle\Model\Resources\RequestItemHistory;
use AppBundle\Worker\CheckExecutor\CheckerHistoryOptionsConverter;
use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;

/**
 * @backupGlobals disabled
 */
class HistoryProcessorTest extends BaseTestClass
{

    public function testMainAccountPostingdateExist()
    {
        $provider = 'testprovider';

        /** @var CheckerFactory $factory */
        $factory = $this->container->get("aw.checker_factory");
        $checker = $factory->getAccountChecker($provider, ['Login' => 'History.PostingDateError', 'ProviderCode' => $provider,]);

        $connection = $this->getCustomMock(Connection::class);
        $connection->method('executeQuery')->willReturnCallback(function($query, $params = [], $types = [], $qcp = null) {
            return $this->connection->executeQuery("SELECT 1 AS CacheVersion, 1 AS CanCheckHistory");
        });

        /** @var RequestItemHistory $history */
        $history = (new RequestItemHistory())
                    ->setRange(History::HISTORY_COMPLETE);

        $processor = new HistoryProcessor($this->serializer, $connection, 'testkey', new NullLogger());
        CheckerHistoryOptionsConverter::setCheckerHistoryOptions($processor->getParseHistoryOptions($history, $provider), $checker);
        $this->assertEquals(true, $checker->WantHistory);
        $checker->Check();
        $result = $processor->prepareHistoryResults($checker);

        $this->assertInstanceOf(History::class, $result);
        $this->assertEquals(6, count($checker->History));
        $this->assertEquals(4, count($result->getRows()));
    }

}