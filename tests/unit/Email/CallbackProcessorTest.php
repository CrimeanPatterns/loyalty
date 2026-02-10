<?php


namespace Tests\Unit\Email;


use AppBundle\Document\RaAccount;
use AppBundle\Email\CallbackProcessor;
use AppBundle\Repository\RaAccountRepository;
use AppBundle\Service\Otc\Cache;
use AwardWallet\Common\API\Email\V2\Meta\EmailAddress;
use AwardWallet\Common\API\Email\V2\Meta\EmailInfo;
use AwardWallet\Common\API\Email\V2\ParseEmailResponse;
use Codeception\Util\Stub;
use DateTime;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\NullLogger;
use Tests\Unit\BaseWorkerTestClass;

class CallbackProcessorTest extends BaseWorkerTestClass
{


    public function testSuccess()
    {
        $accountId = bin2hex(random_bytes(4));
        $otc = bin2hex(random_bytes(4));
        $email = 'test@email.com';
        $provider = 'delta';
        $processor = new CallbackProcessor(new NullLogger(), $this->getCacheMock($accountId, $otc), $this->getMongoMock($provider, $email, $accountId));
        $this->assertTrue($processor->processResponse($this->getResponseObject($provider, [$otc], ['first@random.com', $email, 'second@random.com'], (new DateTime('- 1 minute'))->format('Y-m-d H:i:s'))));
    }

    public function testOld()
    {
        $provider = 'delta';
        $processor = new CallbackProcessor(new NullLogger(), $this->getCacheMock(null, null), $this->getMongoMock(null, null, null));
        $this->assertFalse($processor->processResponse($this->getResponseObject($provider, ['abcd'], ['email'], (new DateTime('-20 min'))->format('Y-m-d H:i:s'))));
    }

    public function testEmpty()
    {
        $provider = 'delta';
        $processor = new CallbackProcessor(new NullLogger(), $this->getCacheMock(null, null), $this->getMongoMock(null, null, null));
        $this->assertFalse($processor->processResponse($this->getResponseObject($provider, null, ['email'], (new DateTime('-5 min'))->format('Y-m-d H:i:s'))));
        $this->assertFalse($processor->processResponse($this->getResponseObject($provider, ['abcd'], null, (new DateTime('-5 min'))->format('Y-m-d H:i:s'))));
    }

    public function testNoMatch()
    {
        $provider = 'delta';
        $processor = new CallbackProcessor(new NullLogger(), $this->getCacheMock(null, null), $this->getMongoMock($provider, 'email', null));
        $this->assertFalse($processor->processResponse($this->getResponseObject($provider, ['abcd'], ['another'], (new DateTime('-5 min'))->format('Y-m-d H:i:s'))));
    }

    private function getResponseObject($provider, $codes, $tos, $date): ParseEmailResponse
    {
        $response = new ParseEmailResponse();
        $response->providerCode = $provider;
        if (isset($tos) || isset($date)) {
            $response->metadata = new EmailInfo();
            $response->metadata->to = $this->buildTo($tos);
            $response->metadata->receivedDateTime = $date;
        }
        if (isset($codes)) {
            $response->oneTimeCodes = $codes;
        }
        return $response;
    }

    private function buildTo($tos) {
        if (empty($tos))
            return [];
        $result = [];
        foreach($tos as $to) {
            $result[] = new EmailAddress(null, $to);
        }
        return $result;
    }

    private function getCacheMock($accountId, $otc)
    {
        $cache = $this->getMockBuilder(Cache::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['saveOtc'])
            ->getMock();
        if (isset($accountId)) {
            $cache->expects($this->once())
                ->method('saveOtc')
                ->with($accountId, $otc);
        }
        else {
            $cache->expects($this->never())
                ->method('saveOtc');
        }
        return $cache;
    }

    private function getMongoMock($provider, $email, $accountId)
    {
        $repoMock = $this->getMockBuilder(RaAccountRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        if (isset($provider)) {
            $invocation = $repoMock->expects($this->atLeastOnce())
                ->method('findOneBy');
            if (isset($accountId)) {
                $account = new RaAccount($provider, 'login', 'pass', $email);
                $account->setId($accountId);
                $invocation->willReturnCallback(function($row) use($account){
                    if ($row['email'] === $account->getEmail()) {
                        return $account;
                    }
                    else {
                        return null;
                    }
                });
            }
            else {
                $invocation->willReturn(null);
            }
        }
        else {
            $repoMock->expects($this->never())
                ->method('findOneBy');
        }
        return Stub::makeEmpty(DocumentManager::class, ['getRepository' => $repoMock]);
    }

}