<?php
namespace Tests\Functional;


use Doctrine\DBAL\Connection;

/**
 * @backupGlobals disabled
 */
class MysqlGoneAwayCest
{

    public function testGoneAway(\FunctionalTester $I)
    {
        /** @var Connection $conn */
        $conn = $I->grabService("database_connection");
        $conn->executeUpdate("set wait_timeout = 1");
        $conn->executeUpdate("set net_read_timeout = 2");
        $conn->executeUpdate("set net_write_timeout = 2");
        $conn->executeQuery("select now()");
        $sql = <<<SQL
            SELECT ProviderID, DisplayName, ShortName, ProgramName, Engine as ProviderEngine, Code, RequestsPerMinute, CanCheckItinerary, CanChangePasswordServer
            FROM Provider
            WHERE Code = :CODE
SQL;
        sleep(3);
        $provider = $conn->executeQuery($sql, [':CODE' => addslashes("jetblue")])->fetch();
        $I->assertEquals("jetblue", $provider["Code"]);

    }

}