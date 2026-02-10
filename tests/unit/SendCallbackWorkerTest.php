<?php

namespace Tests\Unit;
use AppBundle\Document\CheckAccount;
use AppBundle\Extension\HttpCallbackSender;
use AppBundle\Extension\Loader;
use AppBundle\Extension\MongoCommunicator;
use AppBundle\Extension\MQMessages\CallbackRequest;
use AppBundle\Extension\MQSender;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Worker\SendCallbackWorker;
use JMS\Serializer\SerializerInterface;
use Monolog\Logger;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;

class SendCallbackWorkerTest extends \Codeception\TestCase\Test
{

    public function testSuccess(){
        $request = new CheckAccountRequest();
        $request->setCallbackUrl('http://some/url');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer
            ->expects($this->exactly(2))
            ->method('deserialize')
            ->willReturn($request)
        ;
        $serializer
            ->expects($this->once())
            ->method('serialize')
            ->willReturn('some_serialized_data')
        ;

        $row = new CheckAccount();
        $row->setPartner('partner1');
        $row->setMethod('Account');
        $row->setRequest([]);

        $mongoCommunicator = $this->createMock(MongoCommunicator::class);
        $mongoCommunicator
            ->expects($this->once())
            ->method('getRowsByIds')
            ->willReturn(['id1' => $row])
        ;

        $httpCallbackSender = $this->createMock(HttpCallbackSender::class);
        $httpCallbackSender
            ->expects($this->once())
            ->method('sendCallback')
            ->with('partner1', 'http://some/url', '{"method": "Account", "response": some_serialized_data}')
            ->willReturn(true)
        ;

        $producer = $this->createMock(ProducerInterface::class);
        $producer
            ->expects($this->never())
            ->method('publish')
        ;

        $worker = new SendCallbackWorker(
            $this->createMock(Logger::class),
            $producer,
            $serializer,
            $mongoCommunicator,
            $this->createMock(MQSender::class),
            $httpCallbackSender,
            $this->createMock(Loader::class),
            1000000000
        );

        $callbackRequest = new CallbackRequest();
        $callbackRequest->setMethod('Account');
        $callbackRequest->setIds(['id1']);

        $result = $worker->processRequest($callbackRequest);
        $this->assertTrue(true, $result);
    }

    public function testFailure(){
        $request = new CheckAccountRequest();
        $request->setCallbackUrl('http://some/url');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer
            ->expects($this->once())
            ->method('deserialize')
            ->willReturn($request)
        ;
        $serializer
            ->expects($this->once())
            ->method('serialize')
            ->willReturn('some_serialized_data')
        ;

        $row = new CheckAccount();
        $row->setPartner('partner1');
        $row->setMethod('Account');

        $mongoCommunicator = $this->createMock(MongoCommunicator::class);
        $mongoCommunicator
            ->expects($this->once())
            ->method('getRowsByIds')
            ->willReturn(['id1' => $row])
        ;

        $httpCallbackSender = $this->createMock(HttpCallbackSender::class);
        $httpCallbackSender
            ->expects($this->once())
            ->method('sendCallback')
            ->with('partner1', 'http://some/url', '{"method": "Account", "response": some_serialized_data}')
            ->willReturn(false)
        ;

        $producer = $this->createMock(ProducerInterface::class);
        $producer
            ->expects($this->once())
            ->method('publish')
        ;

        $worker = new SendCallbackWorker(
            $this->createMock(Logger::class),
            $producer,
            $serializer,
            $mongoCommunicator,
            $this->createMock(MQSender::class),
            $httpCallbackSender,
            $this->createMock(Loader::class),
            1000000000
        );

        $callbackRequest = new CallbackRequest();
        $callbackRequest->setMethod('Account');
        $callbackRequest->setIds(['id1']);

        $result = $worker->processRequest($callbackRequest);
        $this->assertTrue(true, $result);
    }

}
