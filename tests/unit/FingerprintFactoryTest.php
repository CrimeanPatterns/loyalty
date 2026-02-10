<?php

namespace Tests\Unit;

use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use Helper\Aw;
use Helper\CustomDb;

/**
 * @backupGlobals disabled
 */
class FingerprintFactoryTest extends BaseWorkerTestClass
{

    /**
     * @var Aw
     */
    private $aw;

    public function _before()
    {
        parent::_before();

        /** @var CustomDb $db */
        $db = $this->getModule('\Helper\CustomDb');
        $db->executeQuery("delete from Fingerprint where BrowserVersion = 100 and UserID = 1");

        foreach (['firefox', 'chrome'] as $browser) {
            $db->haveInDatabase('Fingerprint', [
                'UserID' => 1,
                'Hash' => 'test' . bin2hex(random_bytes(10)),
                'BrowserFamily' => $browser,
                'BrowserVersion' => 100,
                'Platform' => 'windows',
                'IsMobile' => 0,
                'Fingerprint' => '{"fp2": {"userAgent":"test", "screen":{"width":100, "height":50}}}'
            ]);
        }

        foreach (['safari'] as $browser) {
            for($n = 0; $n < 10; $n++) {
                $db->haveInDatabase('Fingerprint', [
                    'UserID' => 1,
                    'Hash' => 'test' . bin2hex(random_bytes(10)),
                    'BrowserFamily' => $browser,
                    'BrowserVersion' => 100,
                    'Platform' => 'MacIntel',
                    'IsMobile' => 0,
                    'Fingerprint' => '{"fp2": {"userAgent":"test", "screen":{"width":100, "height":50}}}'
                ]);
            }
        }

        $this->aw = $this->getModule('\Helper\Aw');
    }

    public function testFactory()
    {
        $test = $this;
        $providerCode = "test" . bin2hex(random_bytes(4));
        $this->aw->createAwProvider(null, $providerCode, [], [
            'InitBrowser' => function() use ($test) {
                /** @var \TAccountChecker $this */
                /** @var BaseWorkerTestClass $test */
                parent::InitBrowser();
                $factory = $this->services->get(FingerprintFactory::class);

                // any fingerprint
                $fp = $factory->getOne([]);
                $test->assertNotEmpty($fp);
                $test->assertIsArray($fp->getFingerprint());
                $test->assertArrayHasKey('fp2', $fp->getFingerprint());

                $fp = $factory->getOne([FingerprintRequest::chrome()]);
                $test->assertEquals('chrome', $fp->getBrowserFamily());

                $fp = $factory->getOne([FingerprintRequest::firefox()]);
                $test->assertEquals('firefox', $fp->getBrowserFamily());

                $fp = $factory->getOne([FingerprintRequest::safari()]);
                $test->assertEquals('safari', $fp->getBrowserFamily());

                $fp = $factory->getOne([FingerprintRequest::mac()]);
                $test->assertEquals('MacIntel', $fp->getPlatform());
            },
            'LoadLoginForm' => function() {
                return true;
            },
            'Parse' => function() {
                $this->SetBalance(1);
            }
        ], [], ['SeleniumCheckerHelper']);

        $request = new CheckAccountRequest();
        $request->setProvider($providerCode)
                ->setUserid('blah')
                ->setLogin('blah')
                ->setPassword('blah');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker($this->container->get('console_exception_logger'), null, null, null)->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertEquals(1, $response->getBalance());
    }

}