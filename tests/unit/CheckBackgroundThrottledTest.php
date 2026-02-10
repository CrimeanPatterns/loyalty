<?php
namespace Tests\Unit;

use AppBundle\Document\CheckAccount;
use AppBundle\Extension\TimeCommunicator;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AppBundle\Worker\CheckExecutor\BaseExecutor;
use Doctrine\Common\Persistence\ObjectRepository;
use Helper\CustomDb;
use JMS\Serializer\SerializerInterface;

/**
 * @backupGlobals disabled
 */
class CheckBackgroundThrottledTest extends BaseWorkerTestClass
{

    /** @var string */
    private $requestId;
    /** @var CheckAccountRequest */
    private $request;
    /** @var CheckAccountResponse */
    private $response;
    /** @var string */
    private $serializedRequest;
    /** @var string */
    private $serializedResponse;
    /** @var ObjectRepository */
    private $repo;

    /**
     * @dataProvider scenarios
     */
    public function testSuccess($priorityRequest, $throttleBelow, $state, $inDay)
    {
        $this->requestId = 'someId_'.time().random_int(10000,99999);
        $partner = $this->partner . '_' . bin2hex(random_bytes(3));
        /** @var CustomDb $db */
        $db = $this->getModule('\Helper\CustomDb');
        $db->haveInDatabase("Partner", ["Login" => $partner, "ThrottleBelowPriority"  => $throttleBelow, "Pass" => "xxx"]);

        $this->request = (new CheckAccountRequest())
            ->setUserId('MyUserID')
            ->setPriority($priorityRequest)
            ->setLogin2('-u')
            ->setProvider('testprovider')
            ->setLogin('Checker.Retry')
            ->setUserData('{requestId: "'.$this->requestId.'"}');

        $this->response = (new CheckAccountResponse())
            ->setRequestid($this->requestId)
            ->setRequestdate(new \DateTime())
            ->setState(ACCOUNT_UNCHECKED);

        $tc = $this->getCustomMock(TimeCommunicator::class);
        if (!$inDay){
            $timeToStop = strtotime("+1 day 1 minute");
            $tc
                ->method('getCurrentTime')
                ->willReturn($timeToStop);
        }

        $serializer = $this->container->get('jms_serializer');
        $this->serializedRequest = $serializer->serialize($this->request, 'json');
        $this->serializedResponse = $serializer->serialize($this->response, 'json');

        $this->serializer = $this->getCustomMock(SerializerInterface::class);
        $this->serializer
            ->method('deserialize')
            ->will($this->onConsecutiveCalls($this->response, $this->request));
        $this->serializer->method('serialize')
            ->willReturnCallback(function($data, $format) use($serializer){
                return $serializer->serialize($data, $format);
            });

        $this->row = (new CheckAccount())->setPartner($partner)
            ->setThrottledTime(400)
            ->setRetries(-1)
            ->setResponse(json_decode($this->serializedResponse, true))
            ->setRequest(json_decode($this->serializedRequest, true))
            ->setApiVersion(2);

        $this->repo = $this->getCustomMock(ObjectRepository::class);

        unset($serializer);

        $this->getCheckAccountWorker(null, null, $this->repo, null, null, null, null, null, null, null, null, null, $tc)->execute($this->row);
        $this->assertEquals($state, $this->response->getState());
        if ($state > 0)
            $this->assertEquals($this->request->getUserData(), $this->response->getUserdata());
        else
            $this->assertNull($this->response->getUserdata());
    }

    public function scenarios() {
        // $priorityRequest, $throttleBelow, $state, $inDay
        return [
            [7, 7, 11, true], // юзерская, внутри дня => timeout
            [2, 7, 0, true], // фоновая, внутри дня => продолжаем провеку
            [2, 7, 11, false], // фоновая, уже более суток => timeout
        ];
    }

    /**
     * @dataProvider scenariosWithKilled
     */
    public function testKilled($priorityRequest, $throttleBelow, $state, $killedCounter)
    {
        $this->requestId = 'someId_'.time().random_int(10000,99999);
        $partner = $this->partner . '_' . bin2hex(random_bytes(3));
        /** @var CustomDb $db */
        $db = $this->getModule('\Helper\CustomDb');
        $db->haveInDatabase("Partner", ["Login" => $partner, "ThrottleBelowPriority"  => $throttleBelow, "Pass" => "xxx"]);

        $this->request = (new CheckAccountRequest())
            ->setUserId('MyUserID')
            ->setPriority($priorityRequest)
            ->setLogin2('-ub')
            ->setProvider('testprovider')
            ->setLogin('Checker.Retry')
            ->setUserData('{requestId: "'.$this->requestId.'"}');

        $this->response = (new CheckAccountResponse())
            ->setRequestid($this->requestId)
            ->setRequestdate(new \DateTime())
            ->setState(ACCOUNT_UNCHECKED);

        $tc = $this->getCustomMock(TimeCommunicator::class);

        $serializer = $this->container->get('jms_serializer');
        $this->serializedRequest = $serializer->serialize($this->request, 'json');
        $this->serializedResponse = $serializer->serialize($this->response, 'json');

        $this->serializer = $this->getCustomMock(SerializerInterface::class);
        $this->serializer
            ->method('deserialize')
            ->will($this->onConsecutiveCalls($this->response, $this->request));
        $this->serializer->method('serialize')
            ->willReturnCallback(function($data, $format) use($serializer){
                return $serializer->serialize($data, $format);
            });

        $this->row = (new CheckAccount())->setPartner($partner)
            ->setThrottledTime(400)
            ->setRetries(0)
            ->setKilled()
            ->incKilledCounter()
            ->setResponse(json_decode($this->serializedResponse, true))
            ->setRequest(json_decode($this->serializedRequest, true))
            ->setApiVersion(2);
        if ($killedCounter > 1) {
            $this->row->incKilledCounter();
        }

        $this->repo = $this->getCustomMock(ObjectRepository::class);

        unset($serializer);

        $this->getCheckAccountWorker(null, null, $this->repo, null, null, null, null, null, null, null, null, null, $tc)->execute($this->row);
        $this->assertEquals($state, $this->response->getState());
        if ($state > 0)
            $this->assertEquals($this->request->getUserData(), $this->response->getUserdata());
        else
            $this->assertNull($this->response->getUserdata());
    }

    public function scenariosWithKilled() {
        // $priorityRequest, $throttleBelow, $state, $killedCounter
        return [
            [7, 7, 11, 1], // юзерская, после Killed 1й раз => timeout
            [2, 7,  0, 1], // фоновая, после Killed 1й раз => продолжаем провеку
            [2, 7, 11, 2], // фоновая, после Killed 2й раз => timeout
        ];
    }
}