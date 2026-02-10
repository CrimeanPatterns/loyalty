<?php

namespace Tests\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;

/**
 * @backupGlobals disabled
 */
class DatabaseHelperTest extends \Codeception\TestCase\Test
{

    public function _before()
    {
        $container = $this->getModule('Symfony')->grabService('kernel')->getContainer();
        $loader = $container->get('aw.old_loader');
    }

    public function _after() {
        $symfony = $this->getModule('Symfony');
        $symfony->kernel->shutdown();
        $symfony->kernel = null;
        $symfony->_initialize();
    }

    protected function getDbHelper($data, $method = 'fetch'){
        $stmtMock = $this->getCustomMock(Statement::class);
        $stmtMock->expects($this->any())
                   ->method($method)
                   ->willReturn($data);

        $connectionMock = $this->getCustomMock(Connection::class);
        $connectionMock->expects($this->any())
                       ->method('executeQuery')
                       ->willReturn($stmtMock);

        return new \DatabaseHelper($connectionMock);
    }

    protected function getCustomMock($className) {
        return $this->getMockBuilder($className)
                    ->disableOriginalConstructor()
                    ->getMock();
    }

    public function testProviders()
    {
        $providersJson = '{"ProviderID":"21","Name":"Hertz","Code":"hertz","Kind":"3","Engine":"2"}';
        $db = $this->getDbHelper([json_decode($providersJson, true)], 'fetchAll');
        $result = $db->getProvidersBy(['Kind' => PROVIDER_KIND_CAR_RENTAL, 'Code' => 'hertz']);
        codecept_debug("RESULT: ".print_r($result, true));
        $this->assertEquals(1, count($result));
    }

    public function testAirport()
    {
        $airportJson = '{"AirCodeID":"224","CityCode":"ALL","AirCode":"ALL","CityName":"ALBENGA","CountryCode":"IT","CountryName":"Italy","State":"42","StateName":"Liguria","AirName":"ALBENGA","Type":"A","AirCountryCode":"IT","ServiceType":"M","Flag":"1","Lat":"44","Lng":"8","TimeZone":"1004500","TimeZoneID":"514","Preference":"9","DST":"","TimeZoneUpdateDate":"2012-05-30 04:44:56","LastUpdateDate":null,"AirType":"medium_airport"}';
        $db = $this->getDbHelper([json_decode($airportJson, true)], 'fetchAll');
        $result = $db->getAirportBy(['AirName' => 'Albenga']);
        codecept_debug("RESULT: ".print_r($result, true));
        $this->assertEquals('ALL', $result['AirCode']);
    }

}
