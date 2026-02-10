<?php

namespace Tests\Unit;

use AppBundle\Extension\HttpCallbackSender;
use AwardWallet\Common\Partner\CallbackAuth;
use AwardWallet\Common\Partner\CallbackAuthSource;
use Psr\Log\LoggerInterface;

class HttpCallbackSenderTest extends \Codeception\TestCase\Test
{

    public function testSuccess()
    {
        $authSource = $this->createMock(CallbackAuthSource::class);
        $authSource
            ->expects($this->once())
            ->method('getByUrl')
            ->with('partner1', 'http://some/url')
            ->willReturn(new CallbackAuth('some', 'auth'))
        ;

        $httpDriver = $this->createMock(\HttpDriverInterface::class);
        $httpDriver
            ->expects($this->once())
            ->method('request')
            ->with(new \HttpDriverRequest('http://some/url', 'POST', 'some_body', [
                'Content-type' => 'application/json',
                'Expect' => '',
                'Authorization' => 'Basic ' . base64_encode('some:auth'),
            ], 30))
            ->willReturn(new \HttpDriverResponse('ok', 200))
        ;


        $throttler = $this->createMock(\Throttler::class);
        $throttler
            ->expects($this->once())
            ->method('getDelay')
            ->willReturn(0)
        ;
        $throttler
            ->expects($this->never())
            ->method('increment')
        ;

        $sender = new HttpCallbackSender(
            $this->createMock(LoggerInterface::class),
            $authSource,
            $httpDriver,
            $throttler
        );

        $this->assertTrue($sender->sendCallback('partner1', 'http://some/url', 'some_body'));
    }

    public function testHttpError()
    {
        $authSource = $this->createMock(CallbackAuthSource::class);
        $authSource
            ->expects($this->once())
            ->method('getByUrl')
            ->with('partner1', 'http://some/url')
            ->willReturn(new CallbackAuth('some', 'auth'))
        ;

        $httpDriver = $this->createMock(\HttpDriverInterface::class);
        $httpDriver
            ->expects($this->once())
            ->method('request')
            ->with(new \HttpDriverRequest('http://some/url', 'POST', 'some_body', [
                'Content-type' => 'application/json',
                'Expect' => '',
                'Authorization' => 'Basic ' . base64_encode('some:auth'),
            ], 30))
            ->willReturn(new \HttpDriverResponse('fail', 403))
        ;

        $throttler = $this->createMock(\Throttler::class);
        $throttler
            ->expects($this->once())
            ->method('getDelay')
            ->willReturn(0)
        ;
        $throttler
            ->expects($this->once())
            ->method('increment')
            ->with('cbt_http_some', 1)
        ;

        $sender = new HttpCallbackSender(
            $this->createMock(LoggerInterface::class),
            $authSource,
            $httpDriver,
            $throttler
        );

        $this->assertFalse($sender->sendCallback('partner1', 'http://some/url', 'some_body'));
    }

    public function testThrottled()
    {
        $authSource = $this->createMock(CallbackAuthSource::class);
        $authSource
            ->expects($this->never())
            ->method('getByUrl')
        ;

        $httpDriver = $this->createMock(\HttpDriverInterface::class);
        $httpDriver
            ->expects($this->never())
            ->method('request')
        ;

        $throttler = $this->createMock(\Throttler::class);
        $throttler
            ->expects($this->once())
            ->method('getDelay')
            ->willReturn(100)
        ;

        $sender = new HttpCallbackSender(
            $this->createMock(LoggerInterface::class),
            $authSource,
            $httpDriver,
            $throttler
        );

        $this->assertFalse($sender->sendCallback('partner1', 'http://some/url', 'some_body'));
    }

}