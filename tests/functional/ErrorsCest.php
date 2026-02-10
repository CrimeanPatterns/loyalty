<?php 
namespace Tests\Functional;

use AppBundle\Controller\Common\RequestValidatorService;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\History;
use AppBundle\Model\Resources\RequestItemHistory;
use JMS\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Response;

/**
 * @backupGlobals disabled
 */
class ErrorsCest
{
    private $key;

    protected $urlProviders = "/providers/list";
    protected $urlCheckAccount = "/account/check";

    public function _before(\FunctionalTester $I){
        /** @var \Doctrine\DBAL\Connection $conn */
        $conn = $I->grabService("database_connection");
        $this->key = $conn->executeQuery("select ApiKey from PartnerApiKey where PartnerID = 3 and Enabled = 1")->fetchColumn(0);
    }

    public function testUnAuthorized(\FunctionalTester $I)
    {
        $I->setServerParameters(['HTTP_HOST' => $I->grabParameter('host')]);
        $I->sendGET($this->urlProviders, []);
        $I->seeResponseCodeIs(Response::HTTP_UNAUTHORIZED);

        $I->haveHttpHeader("X-Authentication", "baduser:badpassword");
        $I->sendGET($this->urlProviders);
        $I->seeResponseCodeIs(Response::HTTP_UNAUTHORIZED);
        $I->seeResponseMatchesJsonType(['message' => 'string', 'logref' => 'string']);
        $I->seeResponseContainsJson(['message' => 'Unauthorized']);
    }

    public function testAuthorized(\FunctionalTester $I)
    {
        $I->setServerParameters(['HTTP_HOST' => $I->grabParameter('host')]);
        $I->haveHttpHeader("X-Authentication", $this->key);
        $I->sendGET($this->urlProviders);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseIsJson();
    }

    public function testNotAllowed(\FunctionalTester $I)
    {
        $I->sendGET($this->urlCheckAccount);
        $I->seeResponseCodeIs(Response::HTTP_METHOD_NOT_ALLOWED);
        $I->seeResponseMatchesJsonType(['message' => 'string', 'logref' => 'string']);
        $I->seeResponseContainsJson(['message' => 'Method Not Allowed']);
    }

    public function testNotFound(\FunctionalTester $I)
    {
        $I->sendGET($this->urlCheckAccount.'kkk');
        $I->seeResponseCodeIs(Response::HTTP_NOT_FOUND);
        $I->seeResponseMatchesJsonType(['message' => 'string', 'logref' => 'string']);
        $I->seeResponseContainsJson(['message' => 'Not Found']);
    }

    public function testInvalidParameters(\FunctionalTester $I)
    {
        $I->haveHttpHeader("X-Authentication", $this->key);
        $I->sendPOST($this->urlCheckAccount, '{}');
        $I->seeResponseCodeIs(Response::HTTP_BAD_REQUEST);
        $I->seeResponseMatchesJsonType(['message' => 'string', 'logref' => 'string', 'errors' => 'array']);
    }

    public function testInvalidJson(\FunctionalTester $I)
    {
        $I->haveHttpHeader("X-Authentication", $this->key);
        $I->sendPOST($this->urlCheckAccount, '{ param1: "qww" param2: "zxc"}');
        $I->seeResponseCodeIs(Response::HTTP_BAD_REQUEST);
        $I->seeResponseMatchesJsonType(['message' => 'string', 'logref' => 'string']);
        $I->seeResponseContainsJson(['message' => 'Invalid JSON format']);
    }

    public function testUnknownProvider(\FunctionalTester $I)
    {
        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");
        $request = (new CheckAccountRequest())->setProvider('unknownprovider')
                                              ->setPriority(7)
                                              ->setLogin('SomeLogin')
                                              ->setUserId('SomeUserID');

        $I->haveHttpHeader("X-Authentication", $this->key);
        $I->sendPOST($this->urlCheckAccount, $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_BAD_REQUEST);
        $I->seeResponseMatchesJsonType(['message' => 'string', 'logref' => 'string']);
        $I->seeResponseContainsJson(['message' => 'Provider code (unknownprovider) does not exist, please use the "/providers/list" call to get the correct provider code.']);
    }

    public function testUnavailableCallback(\FunctionalTester $I)
    {
        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");
        $request = (new CheckAccountRequest())->setProvider('testprovider')
                                              ->setPriority(7)
                                              ->setLogin('SomeLogin')
                                              ->setUserId('SomeUserID')
                                              ->setCallbackUrl('http://test.callback.url/account');

        $I->haveHttpHeader("X-Authentication", $this->key);
        $I->sendPOST($this->urlCheckAccount, $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_BAD_REQUEST);
        $I->seeResponseMatchesJsonType(['message' => 'string', 'logref' => 'string']);
        $I->seeResponseContainsJson(['message' => RequestValidatorService::UNAVAILABLE_CALLBACK]);
    }

    public function testValidCallback(\FunctionalTester $I)
    {
        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");
        $host = bin2hex(random_bytes(5)) . ".com";
        $I->haveInDatabase("PartnerCallback", ["PartnerID" => 3, "URL" => $host, "Username" => "awuser", "Pass" => "awpass"]);

        $request = (new CheckAccountRequest())->setProvider('testprovider')
                                              ->setPriority(7)
                                              ->setLogin('SomeLogin')
                                              ->setUserId('SomeUserID')
                                              ->setCallbackUrl("http://{$host}/account");

        $I->haveHttpHeader("X-Authentication", $this->key);
        $I->sendPOST($this->urlCheckAccount, $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_OK);
    }

    public function testInvalidHistoryState(\FunctionalTester $I)
    {
        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");
        $request = (new CheckAccountRequest())->setProvider('testprovider')
            ->setPriority(7)
            ->setLogin('SomeLogin')
            ->setUserId('SomeUserID')
            ->setHistory((new RequestItemHistory())->setRange(History::HISTORY_INCREMENTAL));

        $I->haveHttpHeader("X-Authentication", $this->key);
        $I->sendPOST($this->urlCheckAccount, $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_BAD_REQUEST);
        $I->seeResponseMatchesJsonType(['message' => 'string', 'logref' => 'string']);
        $I->seeResponseContainsJson(['message' => 'The History `state` field can not be empty if the `range` field is set to `incremental`']);
    }
}