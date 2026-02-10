<?php

namespace Tests\Unit;

use AppBundle\Extension\S3Custom;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AwardWallet\Common\Parsing\JsExecutor;
use Doctrine\Common\Persistence\ObjectRepository;
use Helper\Aw;
use Helper\CustomDb;
use WebDriverBy;

/**
 * @backupGlobals disabled
 */
class JsExecutorIntegrationTest extends BaseWorkerTestClass
{

    /**
     * @var Aw
     */
    private $aw;

    public function _before()
    {
        parent::_before();

        /** @var Aw $aw */
        $this->aw = $this->getModule('\Helper\Aw');
    }

    public function testModule()
    {
        $providerCode = "test" . bin2hex(random_bytes(4));
        $providerId = $this->aw->createAwProvider(null, $providerCode, [], [
            'Parse' => function() {
                $jsExecutor = $this->services->get(JsExecutor::class);
                $this->SetProperty("Encoded", $jsExecutor->executeString(" 
                var enFirstParam = CryptoJS.enc.Latin1.parse('AEE0715D0778A4E4');
                var enSecondParam = CryptoJS.enc.Latin1.parse('secredemptionKey');
                function changedLoginData(encryptValue, enFirstParam, enSecondParam) {
                    const givenString =
                        typeof encryptValue === 'string'
                            ? encryptValue.slice()
                            : JSON.stringify(encryptValue);
                    const encrypted = CryptoJS.AES.encrypt(givenString, enSecondParam, {
                        iv: enFirstParam,
                        mode: CryptoJS.mode.CBC,
                        padding: CryptoJS.pad.Pkcs7,
                    });
                    return encrypted.toString();
                }
                sendResponseToPhp(changedLoginData('somepass', enFirstParam, enSecondParam));
                ", 5, ['https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.9-1/crypto-js.js']));
                $this->SetBalance(1);
            }
        ]);
        /** @var CustomDb $db */
        $db = $this->getModule('\Helper\CustomDb');
        $db->haveInDatabase("ProviderProperty", ["ProviderID" => $providerId, "Code" => "Encoded", "Name" => "Encoded", "SortIndex" => 1]);

        // first request, no cookie
        $request = new CheckAccountRequest();
        $request->setProvider($providerCode)
                ->setUserid('blah')
                ->setLogin('blah')
                ->setPassword('blah');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertEquals(1, $response->getBalance());
        $this->assertEquals("zls2S3sCBj40MfES/P7dEg==", $response->getProperties()[0]->getValue());
    }

    public function testNoAmd()
    {
        $providerCode = "test" . bin2hex(random_bytes(4));
        $providerId = $this->aw->createAwProvider(null, $providerCode, [], [
            'Parse' => function() {
                $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
                $this->SetProperty("Encoded", $jsExecutor->executeString("
                    var encrypted = CryptoJS.AES.encrypt('testpass', 'ffpenckey');
                    sendResponseToPhp(encrypted.toString());
                ", 5, ['https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.2/rollups/aes.js']));
                $this->SetBalance(1);
            }
        ]);
        /** @var CustomDb $db */
        $db = $this->getModule('\Helper\CustomDb');
        $db->haveInDatabase("ProviderProperty", ["ProviderID" => $providerId, "Code" => "Encoded", "Name" => "Encoded", "SortIndex" => 1]);

        // first request, no cookie
        $request = new CheckAccountRequest();
        $request->setProvider($providerCode)
                ->setUserid('blah')
                ->setLogin('blah')
                ->setPassword('blah');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertEquals(1, $response->getBalance());
        $this->assertEquals(strlen("U2FsdGVkX19KSCpJeRGrRC/WjOuTP3l49WSCMYhOFtw="), strlen($response->getProperties()[0]->getValue()));
    }

}