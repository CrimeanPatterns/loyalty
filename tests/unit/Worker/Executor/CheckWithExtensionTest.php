<?php

namespace Tests\Unit\Worker\Executor;

use AppBundle\Extension\S3Custom;
use AppBundle\Worker\CheckExecutor\CheckAccountExecutor;
use AwardWallet\Common\AWS\Util;
use AwardWallet\Common\Parsing\Exception\ErrorFormatter;
use AwardWallet\ExtensionWorker\Client;
use AwardWallet\ExtensionWorker\ClientFactory;
use AwardWallet\ExtensionWorker\Communicator;
use AwardWallet\ExtensionWorker\ParserLogger;
use Codeception\Module\Symfony;
use Doctrine\Common\Persistence\ObjectRepository;
use Helper\Aw;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Psr\Log\NullLogger;

class CheckWithExtensionTest extends \Codeception\TestCase\Test
{

    public function testNoItineraries()
    {
        $response = $this->runExtension(__DIR__ . '/Extensions/NoItinerariesExtension.php');

        self::assertEquals(ACCOUNT_CHECKED, $response['state']);
        self::assertEquals(true, $response['noItineraries']);
    }

    public function testItineraries()
    {
        $response = $this->runExtension(__DIR__ . '/Extensions/ItinerariesExtension.php');

        self::assertEquals(ACCOUNT_CHECKED, $response['state']);
        self::assertEquals(false, $response['noItineraries']);
        unset($response['itineraries'][0]['providerInfo']);
        self::assertEquals(array (
            0 =>
                array (
                    'confirmationNumbers' =>
                        array (
                            0 =>
                                array (
                                    'number' => '12345',
                                ),
                        ),
                    'hotelName' => 'Marriott',
                    'address' =>
                        array (
                            'text' => 'Lenina 17',
                        ),
                    'checkInDate' => '2049-01-01T00:00:00',
                    'checkOutDate' => '2049-02-01T00:00:00',
                    'type' => 'hotelReservation',
                ),
        ), $response['itineraries']);
    }

    public function testItinerariesValidation()
    {
        $response = $this->runExtension(__DIR__ . '/Extensions/ItinerariesValidationExtension.php');

        self::assertEquals(ACCOUNT_CHECKED, $response['state']);
        self::assertEquals(false, $response['noItineraries']);
        unset($response['itineraries'][0]['providerInfo']);
        self::assertEquals([], $response['itineraries']);
    }

    public function testBalanceAndProperties()
    {
        $response = $this->runExtension(__DIR__ . '/Extensions/BalanceAndPropertiesExtension.php');

        self::assertEquals(ACCOUNT_CHECKED, $response['state']);
        self::assertEquals(100, $response['balance']);
    }

    public function testProfileUpdateMessage()
    {
        $response = $this->runExtension(__DIR__ . '/Extensions/ProfileUpdateExtension.php');

        self::assertEquals(ACCOUNT_PROVIDER_ERROR, $response['state']);
        self::assertMatchesRegularExpression("#Test Extension Provider\w+ website is asking you to update your profile, until you do so we would not be able to retrieve your account information.#", $response['message']);
    }

    public function testAcceptTermsMessage()
    {
        $response = $this->runExtension(__DIR__ . '/Extensions/AcceptTermsExtension.php');

        self::assertEquals(ACCOUNT_PROVIDER_ERROR, $response['state']);
        self::assertMatchesRegularExpression("#Test Extension Provider\w+ website is asking you to accept their new Terms and Conditions, until you do so we would not be able to retrieve your account information.#", $response['message']);
    }

    public function testNotAMemberMessage()
    {
        $response = $this->runExtension(__DIR__ . '/Extensions/NotAMemberExtension.php');

        self::assertEquals(ACCOUNT_PROVIDER_ERROR, $response['state']);
        self::assertEquals("You are not a member of this loyalty program.", $response['message']);
    }

//    public function testHistory()
//    {
//        $response = $this->runExtension(__DIR__ . '/Extensions/HistoryExtension.php');
//
//        self::assertEquals(ACCOUNT_CHECKED, $response['state']);
//        self::assertEquals(100, $response['balance']);
//        // Loyalty::solve (MasterSolver::solve) not called, so history field not loaded
//        // so History parsing requires Itineraries parsing, that's wrong
//        self::assertEquals(100, $response['history']);
//    }
//
    public function testLogging()
    {
        /** @var Aw $aw */
        $aw = $this->getModule('\Helper\Aw');

        $s3client = $this->createMock(S3Custom::class);
        $s3client
            ->expects($this->once())
            ->method('uploadCheckerLogToBucket')
            ->willReturnCallback(function(string $requestId, string $logDir, array $accountFields, ObjectRepository $repo) {
                $log = file_get_contents($logDir . "/log.html");
                $this->assertStringContainsString("Server info > hostname: loyalty, local ip: 1.2.3.4", $log);
                $this->assertStringContainsString("running with browser extension, session id: 123", $log);
                $this->assertStringContainsString("Account Check Result", $log);
            })
        ;
        $aw->mockService(S3Custom::class, $s3client);

        $awsUtil = $this->createMock(Util::class);
        $awsUtil
            ->expects($this->once())
            ->method('getLocalIP')
            ->willReturn('1.2.3.4')
        ;
        $aw->mockService('aw.aws_util', $awsUtil);

        $aw->mockService('monolog.logger', new Logger('app', [new NullHandler()]));

        $response = $this->runExtension(__DIR__ . '/Extensions/BalanceAndPropertiesExtension.php');

        self::assertEquals(ACCOUNT_CHECKED, $response['state']);
        self::assertEquals(100, $response['balance']);


    }

    public function testUndefinedVariable()
    {
        $response = $this->runExtension(__DIR__ . '/Extensions/UndefinedVariableExtension.php');

        self::assertEquals(ACCOUNT_ENGINE_ERROR, $response['state']);
        global $arAccountErrorCode;
        self::assertEquals($arAccountErrorCode[ACCOUNT_ENGINE_ERROR], $response['message']);
        self::assertStringContainsString("Undefined variable", $response['debugInfo']);
    }

    private function runExtension(string $extensionFile) : array
    {
        /** @var Aw $aw */
        $aw = $this->getModule('\Helper\Aw');
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');

        $providerCode = "p" . bin2hex(random_bytes(5));
        $aw->createAwProvider(null, $providerCode, ['CanCheckItinerary' => 1, 'DisplayName' => 'Test Extension Provider' . bin2hex(random_bytes(4))], [
            'GetHistoryColumns' => function() {
                return [
                    "Crediting Date" => "PostingDate",
                    "Activity"       => "Description",
                    "Miles"     => "Miles",
                ];
            }
        ]);
        $aw->registerExtensionParser($providerCode, $extensionFile);

        $communicator = $this->createMock(Communicator::class);
        $communicator
            ->expects($this->exactly(6))
            ->method('sendMessageToExtension')
            ->willReturnOnConsecutiveCalls(
                ["tabId" => 123], // new tab
                [], // show message
                [], // hide message
                [], // show message
                [] // hide message
            )
        ;
        $parserLogger = new ParserLogger(new Logger('app', [new NullHandler()]));
        try {
            $clientFactory = $this->createMock(ClientFactory::class);
            $clientFactory
                ->expects($this->once())
                ->method('createClient')
                ->willReturn(new Client($communicator, new NullLogger(), $parserLogger->getFileLogger(), new ErrorFormatter('Test Extension Provider', 'Test Extension')));
            $aw->mockService(ClientFactory::class, $clientFactory);

            $row = $aw->createCheckAccountRow($providerCode, false);
            $row->setRequest(array_merge($row->getRequest(), ['browserExtensionAllowed' => true,
                'browserExtensionSessionId' => '123', 'history' => ['range' => 'complete']]));
            /** @var CheckAccountExecutor $executor */
            $executor = $symfony->grabService(CheckAccountExecutor::class);
            $executor->execute($row);
        } finally {
            $parserLogger->cleanup();
        }

        self::assertNotNull($row->getFirstCheckDate());

        return $row->getResponse();
    }

}