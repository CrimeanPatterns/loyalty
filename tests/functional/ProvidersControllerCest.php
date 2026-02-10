<?php
namespace Tests\Functional;

use Codeception\Example;

/**
 * @backupGlobals disabled
 */
class ProvidersControllerCest
{

    protected $prefix = '';

    public function _before(\FunctionalTester $I)
    {
        $I->setServerParameters(['HTTP_HOST' => $I->grabParameter('host')]);
    }

    public function testProviders(\FunctionalTester $I)
    {
        $dbConnection = $I->grabService("database_connection");
        $key = $dbConnection->executeQuery("select ApiKey from PartnerApiKey where PartnerID = 3 and Enabled = 1")->fetchColumn(0);

        $I->haveHttpHeader("Content-Type", "application/json");
        $I->haveHttpHeader("X-Authentication", $key);

        $I->sendGET("{$this->prefix}/providers/list");
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $providers = json_decode($I->grabResponse(), true);
        $item = $providers[0];
        $I->assertTrue(in_array((int)$item['kind'], [1,2,3,4,5,6,7,8,9]));

        $I->sendGET("{$this->prefix}/providers/".$item['code']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();

        $I->sendGET("{$this->prefix}/providers/testprovider");
        $I->seeResponseCodeIs(200);
    }

    /**
     * @dataProvider getDataAA
     * @param \FunctionalTester $I
     * @param Example $example
     */
    private function testAccessAA(\FunctionalTester $I, Example $example)
    {
        $id = $I->haveInDatabase('Partner', $example[0]);
        $I->haveInDatabase('PartnerApiKey', ['PartnerID' => $id, 'ApiKey' => ($key = sprintf('%s:%s', $example[0]['Login'], $example[0]['Pass'])), 'Enabled' => '1']);

        $I->haveHttpHeader("Content-Type", "application/json");
        $I->haveHttpHeader("X-Authentication", $key);
        $I->sendGET("{$this->prefix}/providers/aa");
        $I->seeResponseCodeIs($example[1]);
    }

    protected function getDataAA()
    {
        return [
            [['Login' => uniqid(), 'Pass' => 'xxxx', 'CanParseAAWeb' => '1', 'LoyaltyAccess' => '1'], 200],
            [['Login' => uniqid(), 'Pass' => 'xxxx', 'CanParseAAWeb' => '0', 'LoyaltyAccess' => '1'], 400],
        ];
    }

}