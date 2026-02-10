<?php

namespace Tests\Unit;

use AppBundle\Document\CheckAccount;
use AppBundle\Model\Resources\Answer;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AppBundle\Document\RetriesState;
use AwardWallet\Engine\testprovider\Checker\Retry;
use Doctrine\Common\Persistence\ObjectRepository;
use JMS\Serializer\SerializerInterface;

/**
 * @backupGlobals disabled
 */
class CheckRetriesStateTest extends BaseWorkerTestClass
{

    /** @var string */
    private $requestId;
    /** @var CheckAccountRequest */
    private $request;
    /** @var CheckAccountResponse */
    private $response;
    /** @var ObjectRepository */
    private $repo;

    private const RETRIES_STATE = [
        'invalidAnswers' => [
            Retry::QUESTION => Retry::ANSWER,
        ],
        'checkerState' => [
            Retry::STATE_KEY => Retry::STATE_VALUE,
        ]
    ];

    public function _before()
    {
        parent::_before();
        $this->requestId = 'someId_' . time() . rand(10000, 99999);
        $this->request = (new CheckAccountRequest())->setTimeout(120)
            ->setUserId('MyUserID')
            ->setProvider('testprovider')
            ->setLogin('Checker.Retry')
            ->setLogin2('-ub')
            ->setPassword('-invalid-answer-with-state')
            ->setRetries(99)
            ->setAnswers([new Answer(Retry::QUESTION, Retry::ANSWER)])
            ->setUserData('{requestId: "' . $this->requestId . '"}');

        $this->row = (new CheckAccount())
            ->setPartner($this->partner)
            ->setApiVersion(2);
        ;

        $this->repo = $this->getCustomMock(ObjectRepository::class);
        $this->repo->expects($this->never())
            ->method('find')
            ->with($this->requestId)
            ->willReturn($this->row);
    }

    public function _after()
    {
        unset($this->request, $this->response, $this->repo, $this->requestId);
        parent::_after();
    }

    public function testSaveRetriesState()
    {
        $retriesState = new RetriesState(self::RETRIES_STATE['invalidAnswers'], self::RETRIES_STATE['checkerState']);

        $this->check();

        $this->assertEquals(ACCOUNT_UNCHECKED, $this->response->getState());
        $this->assertEquals($this->row->getRetriesState(), $retriesState);
    }

    public function testLoadRetriesState()
    {
        $retriesState = new RetriesState(self::RETRIES_STATE['invalidAnswers'], self::RETRIES_STATE['checkerState']);
        $this->request->setPassword('-load-retries-state');
        $this->row->setRetriesState($retriesState);

        $this->check();

        $this->assertEquals(ACCOUNT_CHECKED, $this->response->getState());
    }

    public function testUnsetState() {
        /** @var \Helper\Aw $aw */
        $aw = $this->getModule('\Helper\Aw');

        $provider = 'testprov' . bin2hex(random_bytes(4));

        $parseStep = null;

        $aw->createAwProvider(null, $provider, [], [
            'InitBrowser' => function() {
                parent::InitBrowser();
                $this->KeepState = true;
            },
            'LoadLoginForm' => function() {
                return true;
            },
            'Parse' => function() use (&$parseStep) {
                if ($parseStep === 'save-state') {
                    $this->State['SomeState'] = 'SomeValue';
                    $this->State['SomeState2'] = 'SomeValue2';
                    $this->SetBalance(1);
                }

                if ($parseStep === 'unset-state') {
                    if (!isset($this->State['SomeState'])) {
                        $this->logger->info("missing state, will return UE");
                        return;
                    }
                    unset($this->State['SomeState']);
                    throw new \CheckRetryNeededException(5, 1);
                }

                if ($parseStep === 'state-key-missing') {
                    if (isset($this->State['SomeState'])) {
                        $this->logger->info("state key exists, will return UE");
                        return;
                    }
                    if (!isset($this->State['SomeState2'])) {
                        $this->logger->info("other state key missing, will return UE");
                        return;
                    }
                    $this->SetBalance(2);
                }
            }
        ]);

        $this->request->setProvider($provider);

        // step one - save state
        $parseStep = "save-state";
        $this->check();
        $this->assertEquals(ACCOUNT_CHECKED, $this->response->getState());
        $this->assertEquals(1, $this->response->getBalance());
        $browserState = $this->response->getBrowserstate();
        $this->assertNotEmpty($browserState);
        $this->assertEmpty($this->row->getRetriesState());

        // step two - repeat request with state from previous step. unset key in state and call retry
        $parseStep = "unset-state";
        $this->request->setBrowserstate($browserState);
        $this->check();
        $this->assertEquals(ACCOUNT_UNCHECKED, $this->response->getState());
        $this->assertNotEmpty($this->row->getRetriesState());

        // step three - repeat request with state from previous step. unset key in state and call retry
        $parseStep = "state-key-missing";
        $this->check();
        $this->assertEquals(ACCOUNT_CHECKED, $this->response->getState());
        $this->assertEquals(2, $this->response->getBalance());
    }

    private function check()
    {
        $serializer = $this->container->get('jms_serializer');

        $this->response = (new CheckAccountResponse())
            ->setRequestdate(new \DateTime())
            ->setRequestid($this->requestId)
            ->setState(ACCOUNT_UNCHECKED)
        ;

        $serializedRequest = $serializer->serialize($this->request, 'json');
        $serializedResponse = $serializer->serialize($this->response, 'json');

        $this->row
            ->setResponse(json_decode($serializedResponse, true))
            ->setRequest(json_decode($serializedRequest, true))
        ;

        $this->getCheckAccountWorker(null, null, $this->repo)->execute($this->row);

        $this->response = $serializer->deserialize(json_encode($this->row->getResponse()), CheckAccountResponse::class, 'json');
    }

}