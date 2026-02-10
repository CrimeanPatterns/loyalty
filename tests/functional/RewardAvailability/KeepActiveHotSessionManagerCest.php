<?php

namespace Tests\Functional\RewardAvailability;

use AwardWallet\Common\Document\HotSession;
use AwardWallet\Common\Repository\HotSessionRepository;
use AwardWallet\Common\Selenium\HotSession\HotPoolManager;
use AwardWallet\Common\Selenium\HotSession\KeepActiveHotSessionManager;
use Codeception\Module\Symfony;
use Codeception\Stub;
use Codeception\Stub\Expected;
use Doctrine\ODM\MongoDB\DocumentManager;
use Monolog\Logger;

/**
 * @backupGlobals disabled
 */
class KeepActiveHotSessionManagerCest
{

    private $providerCode;

    public function _before(\FunctionalTester $I)
    {
        $this->providerCode = "test" . bin2hex(random_bytes(8));
        $I->createAwProvider(null, $this->providerCode);

        eval("namespace AwardWallet\\Engine\\{$this->providerCode}\\RewardAvailability;
        
        class KeepHotConfig extends \\Tests\\Functional\\RewardAvailability\\KeepHotConfigParser {}
        ");

        /** @var DocumentManager $mongoManager */
        $mongoManager = $I->grabService(DocumentManager::class);
        /** @var HotSessionRepository $repo */
        $repo = $mongoManager->getRepository(HotSession::class);

        $managerMock = \Codeception\Util\Stub::make(HotPoolManager::class, [
            'rep' => $repo,
            'logger' => Stub::makeEmpty(Logger::class),
            'getHotConnection' => Expected::atLeastOnce(function ($session) {
                return Stub::makeEmpty(\SeleniumConnection::class, [
                    'getWebDriver' => Stub::makeEmpty(\RemoteWebDriver::class),
                    'getHost' => $session->getSessionInfo()->getHost(),
                    'getPort' => $session->getSessionInfo()->getPort(),
                    'getSessionId' => $session->getSessionInfo()->getSessionId(),
                ]);
            })
        ]);
        $I->mockService(HotPoolManager::class, $managerMock);

        $httpMock = \Codeception\Util\Stub::makeEmpty(\HttpBrowser::class);
        $I->mockService(\HttpBrowser::class, $httpMock);
    }

    public function testKeepActive(\FunctionalTester $I)
    {
        $prefix = 'someTestPrefix';
        $accountKey = 'someTestAccount';

        KeepHotConfigParser::reset();
        KeepHotConfigParser::$count = 5;
        KeepHotConfigParser::$time = strtotime("-3 min");

        $lastUseDate = strtotime('-15 min');// for del as not actual
        $id1 = $I->addHotSession($this->providerCode, $prefix, $accountKey, $lastUseDate);
        $id2 = $I->addHotSession($this->providerCode, $prefix, $accountKey, $lastUseDate);
        $id3 = $I->addHotSession($this->providerCode, $prefix, $accountKey, $lastUseDate);
        $lastUseDate = strtotime('-2 min');// for check keepActive
        $id4 = $I->addHotSession($this->providerCode, $prefix, $accountKey, $lastUseDate);
        $id5 = $I->addHotSession($this->providerCode, $prefix, $accountKey, $lastUseDate);

        $I->runKeepActiveHot($this->providerCode);
        $I->assertEquals(1, $I->haveHotSessions($this->providerCode, $prefix, $accountKey));
        $session = $I->getHotSession($this->providerCode, $prefix, $accountKey, $lastUseDate);
        $I->assertNotNull($session);
        $I->assertArrayHasKey($session->getId(), [$id4 => 1, $id5 => 1]);
        $I->assertLessThan($session->getLastUseDate()->getTimestamp(), $lastUseDate);

        $I->wantToTest('keep only 2 sessions if maxSession = 2, old sessions unlock');
        KeepHotConfigParser::reset();
        KeepHotConfigParser::$success = true;

        $lastUseDate = strtotime('-15 min');// all actual, not del. del only more maxCount
        $id1 = $I->addHotSession($this->providerCode, $prefix, $accountKey, $lastUseDate);
        $id2 = $I->addHotSession($this->providerCode, $prefix, $accountKey, $lastUseDate);

        $I->runKeepActiveHot($this->providerCode);
        $I->assertEquals(2, $I->haveHotSessions($this->providerCode, $prefix, $accountKey));

        $I->wantToTest('close all sessions (opened above). by lifetime 1 min');
        KeepHotConfigParser::reset();
        KeepHotConfigParser::$success = true;
        KeepHotConfigParser::$lifeTime = 1;
        $I->runKeepActiveHot($this->providerCode);
        $I->assertEquals(0, $I->haveHotSessions($this->providerCode, $prefix, $accountKey));

    }

    public function testKeepActiveParseMode(\FunctionalTester $I)
    {
        $prefix = 'someTestPrefix';
        $accountKey = 'someTestAccount';

        KeepHotConfigParser::reset();
        KeepHotConfigParser::$checkParseMode = true;
        KeepHotConfigParser::$count = 3;
        KeepHotConfigParser::$time = strtotime("-3 min");
        KeepHotConfigParser::$success = true;

        $lastUseDate = strtotime('-2 minutes');
        $I->addHotSession($this->providerCode, $prefix, $accountKey, $lastUseDate);
        $I->addHotSession($this->providerCode, $prefix, $accountKey, $lastUseDate);
        $I->addHotSession($this->providerCode, $prefix, $accountKey, $lastUseDate);
        $I->addHotSession($this->providerCode, $prefix, $accountKey, $lastUseDate);
        $I->addHotSession($this->providerCode, $prefix, $accountKey, $lastUseDate);

        $I->runKeepActiveHot($this->providerCode);
        $I->assertEquals(0, $I->haveHotSessions($this->providerCode, $prefix, $accountKey));
        $session = $I->getHotSession($this->providerCode, $prefix, $accountKey, $lastUseDate);
        $I->assertNull($session);
    }
}