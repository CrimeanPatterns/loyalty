<?php

namespace Tests\Unit;

use AppBundle\Document\CheckAccount;
use AppBundle\Model\Resources\Answer;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AppBundle\Worker\CheckExecutor\CheckAccountExecutor;
use AwardWallet\Engine\Settings;
use AwardWallet\WebdriverClient\NodeFinder;
use Codeception\Util\Stub;
use Doctrine\Common\Persistence\ObjectRepository;
use Helper\Aw;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @backupGlobals disabled
 */
class SeleniumTest extends \Codeception\TestCase\Test
{

    /**
     * @var ContainerInterface
     */
    private $container;

    public function _before()
    {
        parent::_before();
        $this->container = $this->getModule('Symfony')->grabService('kernel')->getContainer();
        Settings::setAwUrl('http://awardwallet.docker');
    }

    public function _after()
    {
        $this->container = null;
        parent::_after();
    }

    /**
     * @dataProvider browsers
     */
    public function testBrowsers(array $initMethods, \SeleniumFinderRequest $expectedFinderRequest, \SeleniumOptions $expectedOptions)
    {
        /** @var Aw $aw */
        $aw = $this->getModule('\Helper\Aw');
        $providerCode = "rp" . bin2hex(random_bytes(9));
        $test = $this;
        $aw->createAwProvider(null, $providerCode, [], [
            'InitBrowser' => function () use($initMethods, $expectedOptions) {
                /** @var \TAccountChecker $this */
                parent::InitBrowser();
                foreach ($initMethods as $methodAndArguments) {
                    $method = array_shift($methodAndArguments);
                    call_user_func_array([$this, $method], $methodAndArguments);
                }
                if($expectedOptions->proxyHost !== null)
                    $this->http->SetProxy($expectedOptions->proxyHost . ':' . $expectedOptions->proxyPort);
                if($expectedOptions->proxyUser !== null)
                    $this->http->setProxyAuth($expectedOptions->proxyUser, $expectedOptions->proxyPassword);
                if($expectedOptions->userAgent !== null)
                    $this->http->userAgent = $expectedOptions->userAgent;
            },

            'Parse' => function () use($test) {
                /** @var $this \TAccountChecker */
                $test->assertInstanceOf(\RemoteWebDriver::class, $this->driver);
                $this->SetBalance(100);
            }
        ], [], [\SeleniumCheckerHelper::class]);

        $request = new CheckAccountRequest();
        $request->setProvider($providerCode)
            ->setUserid('SomeID')
            ->setLogin('Some')
            ->setPassword('Some');

        $server = new \SeleniumServer("fake", 4444);
        $this->container->set("aw.selenium_finder", new \SeleniumArrayFinder([$server]));

        $nodeFinder = $this->createMock(NodeFinder::class);
        $nodeFinder
            ->expects($this->atMost(1))
            ->method('getNode')
            ->willReturn("fake")
        ;
        $aw->mockService(NodeFinder::class, $nodeFinder);

        $manage = $this->createMock(\WebDriverOptions::class);
        $manage
            ->expects($this->once())
            ->method('getCookies')
            ->willReturn([]);

        $webDriver = $this
            ->getMockBuilder(\RemoteWebDriver::class)
            ->disableOriginalConstructor()
            ->getMock();
        $webDriver
            ->expects($this->once())
            ->method('quit');
        $webDriver
            ->expects($this->once())
            ->method('manage')
            ->willReturn($manage);
        $webDriver
            ->method('getCurrentURL')
            ->willReturn('http://some.url/path');
        $webDriver
            ->method('executeAsyncScript')
            ->willReturn([]);

        $seleniumStarter = $this
            ->getMockBuilder(\SeleniumStarter::class)
            ->disableOriginalConstructor()
            ->getMock();
        $seleniumStarter
            ->expects($this->once())
            ->method('createSession')->willReturnCallback(function(\SeleniumServer $server, \SeleniumFinderRequest $finderRequest, \SeleniumOptions $seleniumOptions) use($webDriver, $expectedFinderRequest, $expectedOptions, $providerCode){
                $this->assertStringContainsString($providerCode, $seleniumOptions->startupText);
                $seleniumOptions->startupText = 'new selenium session';
                $this->assertEquals($expectedFinderRequest, $finderRequest);
                $aOptions = clone $seleniumOptions;
                $aOptions->loggingContext = [];
                $this->assertEquals($expectedOptions, $aOptions);
                return new \SeleniumConnection($webDriver, "fake", "fake", 4444, '/wd/hub', "/tmp/fake", $finderRequest->getBrowser(), $finderRequest->getVersion(), [\SeleniumStarter::CONTEXT_BROWSER_FAMILY => $finderRequest->getBrowser(), \SeleniumStarter::CONTEXT_BROWSER_VERSION => $finderRequest->getVersion()]);
            });

        $this->container->set("aw.selenium_starter", $seleniumStarter);
        $this->container->get("aw.selenium_connector")->setPauseBetweenNewSessions(0);

        $response = $aw->checkAccount($request);

        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertEquals(100, $response->getBalance());
    }

    public function browsers()
    {
        // this method will fire before _before, so a little copy-paste here
        Settings::setAwUrl('http://awardwallet.docker');
        return [
            // browsers
            [
                [["UseSelenium"], ["InitSeleniumBrowser"]],
                new \SeleniumFinderRequest(\SeleniumFinderRequest::BROWSER_FIREFOX, \SeleniumFinderRequest::FIREFOX_DEFAULT),
                (new \SeleniumOptions())
                    ->setPacFile(Settings::getPacFile() . '?filterAds=1&directImages=1')
            ],
            [
                [["UseSelenium"], ["useChromium"]],
                new \SeleniumFinderRequest(\SeleniumFinderRequest::BROWSER_CHROMIUM, \SeleniumFinderRequest::CHROMIUM_DEFAULT),
                (new \SeleniumOptions())
                    ->setPacFile(Settings::getPacFile() . '?filterAds=1&directImages=1')
            ],
            [
                [["UseSelenium"], ["useFirefox", \SeleniumFinderRequest::FIREFOX_53]],
                new \SeleniumFinderRequest(\SeleniumFinderRequest::BROWSER_FIREFOX, \SeleniumFinderRequest::FIREFOX_53),
                (new \SeleniumOptions())->setPacFile(Settings::getPacFile() . '?filterAds=1&directImages=1')
            ],
            [
                [["UseSelenium"], ["useFirefox", \SeleniumFinderRequest::FIREFOX_59]],
                new \SeleniumFinderRequest(\SeleniumFinderRequest::BROWSER_FIREFOX, \SeleniumFinderRequest::FIREFOX_59),
                (new \SeleniumOptions())
                    ->setPacFile(Settings::getPacFile() . '?filterAds=1&directImages=1')
            ],
            // proxies
            [
                [["UseSelenium"]],
                new \SeleniumFinderRequest(\SeleniumFinderRequest::BROWSER_FIREFOX, \SeleniumFinderRequest::FIREFOX_DEFAULT),
                (new \SeleniumOptions())
                    ->setProxyHost("127.0.0.1")->setProxyPort(3128)
                    ->setUserAgent(\HttpBrowser::FIREFOX_USER_AGENT)
                    ->setPacFile(Settings::getPacFile() . '?proxy=' . urlencode('127.0.0.1:3128') . '&filterAds=1&directImages=1')
            ],
            [
                [["UseSelenium"], ["useGoogleChrome"]],
                new \SeleniumFinderRequest(\SeleniumFinderRequest::BROWSER_CHROME, \SeleniumFinderRequest::CHROME_DEFAULT),
                (new \SeleniumOptions())->setProxyHost("127.0.0.1")->setProxyPort(3128)
                    ->setProxyUser("hello")->setProxyPassword("world")
                    ->setPacFile(Settings::getPacFile() . '?proxy=' . urlencode('127.0.0.1:3128') . '&filterAds=1&directImages=1')
            ],
            [
                [["UseSelenium"], ["usePacFile"]],
                new \SeleniumFinderRequest(\SeleniumFinderRequest::BROWSER_FIREFOX, \SeleniumFinderRequest::FIREFOX_DEFAULT),
                (new \SeleniumOptions())
                    ->setPacFile(Settings::getPacFile() . '?filterAds=1&directImages=1')
            ],
            [
                [["UseSelenium"], ["useCache"]],
                new \SeleniumFinderRequest(\SeleniumFinderRequest::BROWSER_FIREFOX, \SeleniumFinderRequest::FIREFOX_DEFAULT),
                (new \SeleniumOptions())
                    ->setPacFile(Settings::getPacFile() . '?cache=' . urlencode("cache.awardwallet.com:3128") . '&filterAds=1&directImages=1')
            ],
            // user agent
            [
                [["UseSelenium"]],
                new \SeleniumFinderRequest(\SeleniumFinderRequest::BROWSER_FIREFOX, \SeleniumFinderRequest::FIREFOX_DEFAULT),
                (new \SeleniumOptions())
                    ->setUserAgent("My User Agent")
                    ->setPacFile(Settings::getPacFile() . '?filterAds=1&directImages=1')
            ],
            // screen resolution
            [
                [["UseSelenium"], ["setScreenResolution", [400, 300]], ["usePacFile", false]],
                new \SeleniumFinderRequest(\SeleniumFinderRequest::BROWSER_FIREFOX, \SeleniumFinderRequest::FIREFOX_DEFAULT),
                (new \SeleniumOptions())
                    ->setResolution([400, 300])
            ],
            // images
            [
                [["UseSelenium"], ["disableImages"]],
                new \SeleniumFinderRequest(\SeleniumFinderRequest::BROWSER_FIREFOX, \SeleniumFinderRequest::FIREFOX_DEFAULT),
                (new \SeleniumOptions())
                    ->setShowImages(false)
                    ->setPacFile(Settings::getPacFile() . '?filterAds=1&directImages=1')
            ],
        ];
    }

}