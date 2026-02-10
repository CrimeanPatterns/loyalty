<?php

namespace Tests\Unit\Threading;

use AppBundle\Extension\PartnerSource;
use Codeception\Stub;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;

class PartnerSourceTest extends \Codeception\TestCase\Test
{

    public function testNoCache()
    {
        $connection = Stub::makeEmpty(
            Connection::class,
            [
                'executeQuery' => Stub\Expected::once(Stub::makeEmpty(Statement::class, [
                    'fetchAll' => Stub\Expected::once(['ivan' => 3, 'petr' => 5])
                ]))
            ],
            $this
        );

        $getCallNumber = 0;

        $memcached = Stub::makeEmpty(
            \Memcached::class,
            [
                'get' => Stub\Expected::exactly(2, function($key) use (&$getCallNumber) {
                    $this->assertNotEmpty($key);
                    if ($getCallNumber++ === 0) {
                        return false;
                    }
                    return ['ivan' => 3, 'petr' => 5];
                }),
            ],
            $this
        );

        $source = new PartnerSource(
            $connection,
            $memcached
        );

        $this->assertEquals(['ivan' => 3, 'petr' => 5], $source->getPartners());
        $this->assertEquals(['ivan' => 3, 'petr' => 5], $source->getPartners());
    }

    public function testCache()
    {
        $memcached = Stub::makeEmpty(
            \Memcached::class,
            [
                'get' => Stub\Expected::once(['ivan' => 3, 'petr' => 5])
            ],
            $this
        );

        $source = new PartnerSource(
            Stub::makeEmpty(Connection::class),
            $memcached
        );

        $this->assertEquals(['ivan' => 3, 'petr' => 5], $source->getPartners());
    }



}