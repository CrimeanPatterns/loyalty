<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 18.03.16
 * Time: 11:55
 */
namespace Tests\Unit;

use AppBundle\Extension\Loader;
use Doctrine\DBAL\Connection;
use Helper\CustomDb;
use JMS\Serializer\Serializer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class BaseTestClass extends \Codeception\TestCase\Test
{
    /** @var ContainerInterface */
    protected $container;

    /** @var Loader */
    protected $loader;

    /** @var  Serializer */
    protected $serializer;

    /** @var  Connection */
    protected $connection;

    /** @var  Connection */
    protected $shared_connection;

    protected $partner;

    public function _before()
    {
        parent::_before();
        $this->container = $this->getModule('Symfony')->grabService('kernel')->getContainer();
        $this->loader = $this->container->get('aw.old_loader');
        $this->serializer = $this->container->get('jms_serializer');
        $this->connection = $this->container->get('database_connection');
        $this->shared_connection = $this->container->get('doctrine.dbal.shared_connection');
        $this->partner = 'test_' . bin2hex(random_bytes(5));
        /** @var CustomDb $db */
        $db = $this->getModule('\Helper\CustomDb');
        $db->haveInDatabase("Partner", ["Login" => $this->partner, "ReturnHiddenProperties"  => 0, "CanDebug" => 0, "Pass" => "xxx"]);
    }

    public function _after() {
        $this->container = null;
        $this->loader = null;
        $this->serializer = null;
        $this->connection = null;
        $this->shared_connection = null;

        $symfony = $this->getModule('Symfony');
        $symfony->kernel->shutdown();
        $symfony->kernel = null;
        $symfony->_initialize();
        parent::_after();
    }

    /*
     * @returns MockObject
     */
    protected function getCustomMock($className) {
        return $this->getMockBuilder($className)
                    ->disableOriginalConstructor()
                    ->getMock();
    }

}