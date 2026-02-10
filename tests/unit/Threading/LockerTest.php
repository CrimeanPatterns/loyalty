<?php

namespace Tests\Unit\Threading;

use AppBundle\Extension\Locker;
use Codeception\Stub;

class LockerTest extends \Codeception\TestCase\Test
{

    public function testAcquired()
    {
        $memcached = Stub::makeEmpty(\Memcached::class, [
            'add' => true,
        ]);

        $locker = new Locker($memcached, 'test', 1);

        $this->assertTrue($locker->acquire('ivan', 30));
    }

    public function testNotAcquired()
    {
        $memcached = Stub::makeEmpty(\Memcached::class, [
            'add' => false,
        ]);

        $locker = new Locker($memcached, 'test', 1);

        $this->assertFalse($locker->acquire('ivan', 30));
    }

    public function testLocked()
    {
        $memcached = Stub::makeEmpty(\Memcached::class, [
            'get' => 'test_1',
        ]);

        $locker = new Locker($memcached, 'test', 1);

        $this->assertTrue($locker->isLocked('ivan'));
    }

    public function testNotLocked()
    {
        $memcached = Stub::makeEmpty(\Memcached::class, [
            'get' => false,
        ]);

        $locker = new Locker($memcached, 'test', 1);

        $this->assertFalse($locker->isLocked('ivan'));
    }

    public function testLockedByOther()
    {
        $memcached = Stub::makeEmpty(\Memcached::class, [
            'get' => 'test_2',
        ]);

        $locker = new Locker($memcached, 'test', 1);

        $this->assertTrue($locker->isLocked('ivan'));
    }

    public function testKeep()
    {
        $memcached = Stub::makeEmpty(\Memcached::class, [
            'get' => ['value' => 'test_1', 'cas' => 1],
            'cas' => true,
        ]);

        $locker = new Locker($memcached, 'test', 1);

        $this->assertTrue($locker->keep('ivan', 30));
    }

    public function testKeepCasFailed()
    {
        $memcached = Stub::makeEmpty(\Memcached::class, [
            'get' => ['value' => 'test_1', 'cas' => 1],
            'cas' => false,
        ]);

        $locker = new Locker($memcached, 'test', 1);

        $this->assertFalse($locker->keep('ivan', 30));
    }

    public function testKeepNotLocked()
    {
        $memcached = Stub::makeEmpty(\Memcached::class, [
            'get' => false,
        ]);

        $locker = new Locker($memcached, 'test', 1);

        $this->assertFalse($locker->keep('ivan', 30));
    }

    public function testKeepLockedByOther()
    {
        $memcached = Stub::makeEmpty(\Memcached::class, [
            'get' => ['value' => 'test_2', 'cas' => 1],
        ]);

        $locker = new Locker($memcached, 'test', 1);

        $this->assertFalse($locker->keep('ivan', 30));
    }

}