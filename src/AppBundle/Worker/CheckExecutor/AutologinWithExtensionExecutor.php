<?php

namespace AppBundle\Worker\CheckExecutor;

use AppBundle\Document\AutoLoginWithExtension;
use AppBundle\Document\BaseDocument;
use AppBundle\Extension\S3Custom;
use AppBundle\Extension\Watchdog;
use AppBundle\Model\Resources\AutologinWithExtensionRequest;
use AppBundle\Model\Resources\ExtensionAnswersConverter;
use AwardWallet\Common\Parsing\Exception\ErrorFormatter;
use AwardWallet\ExtensionWorker\ClientFactory;
use AwardWallet\ExtensionWorker\CommunicationException;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParserFactory;
use AwardWallet\ExtensionWorker\ParserLogger;
use AwardWallet\ExtensionWorker\ParserRunner;
use AwardWallet\ExtensionWorker\ProviderInfo;
use AwardWallet\ExtensionWorker\ProviderInfoFactory;
use AwardWallet\ExtensionWorker\SelectParserRequest;
use AwardWallet\ExtensionWorker\WarningLogger;
use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use Monolog\Logger;

class AutologinWithExtensionExecutor implements ExecutorInterface
{

    private Logger $logger;
    private Watchdog $watchdog;
    private DocumentManager $manager;
    private Connection $connection;
    private ParserFactory $parserFactory;
    private ClientFactory $clientFactory;
    private ParserRunner $parserRunner;
    private S3Custom $s3Client;
    private ProviderInfoFactory $providerInfoFactory;

    public function __construct(
        Logger $logger,
        Watchdog $watchdog,
        DocumentManager $manager,
        Connection $connection,
        ParserFactory $parserFactory,
        ClientFactory $clientFactory,
        ParserRunner $parserRunner,
        S3Custom $s3Client,
        ProviderInfoFactory $providerInfoFactory
    )
    {
        $this->logger = $logger;
        $this->watchdog = $watchdog;
        $this->manager = $manager;
        $this->connection = $connection;
        $this->parserFactory = $parserFactory;
        $this->clientFactory = $clientFactory;
        $this->parserRunner = $parserRunner;
        $this->s3Client = $s3Client;
        $this->providerInfoFactory = $providerInfoFactory;
    }

    public function getMethodKey(): string
    {
        return AutoLoginWithExtension::METHOD_KEY;
    }

    /**
     * @param AutoLoginWithExtension $row
     */
    public function execute(BaseDocument $row): void
    {
        /** @var AutoLoginWithExtensionRequest $request */
        $request = $row->getRequest();
        $this->logger->pushProcessor(static function (array $record) use ($request, $row) {
            $record['extra']['partner'] = $row->getPartner();
            $record['extra']['provider'] = $request->getProvider();
            $record['extra']['login'] = $request->getLogin();
            $record['extra']['userData'] = $request->getUserData();
            $record['extra']['worker'] = AutoLoginWithExtension::METHOD_KEY;

            return $record;
        });

        try {
            $parserLogger = new ParserLogger($this->logger);

            try {
                $this->logger->info("starting autologin with extension");

                if (is_null($row->getFirstCheckDate())) {
                    $row->setFirstCheckDate(new \DateTime());
                    $this->manager->persist($row);
                    $this->manager->flush();
                } else {
                    $this->logger->info("double autologin attempt, ignored");

                    return;
                }

                $this->watchdog->addContext(
                    getmypid(),
                    [
                        'logContext' => [
                            'document' => get_class($row),
                            'requestId' => $row->getId(),
                            'userData' => $request->getUserData(),
                            'provider' => $request->getProvider(),
                            'partner' => $row->getPartner(),
                        ]
                    ]
                );

                $this->runBrowserExtension($request, $parserLogger);
            } finally {
                $this->s3Client->uploadLogToBucket(
                    $row->getId(),
                    $request->getProvider(),
                    AutoLoginWithExtension::METHOD_KEY,
                    $row->getPartner(),
                    $parserLogger->getLogDir(),
                    $this->createAccountFields($request)
                );
                $parserLogger->cleanup();
            }
        } finally {
            $this->logger->popProcessor();
        }
    }

    private function runBrowserExtension(AutologinWithExtensionRequest $request, ParserLogger $parserLogger)
    {
        $selectParserRequest = new SelectParserRequest(
            $request->getLogin2(),
            $request->getLogin3()
        );
        $providerInfo = $this->providerInfoFactory->createProviderInfo($request->getProvider());
        $errorFormatter = new ErrorFormatter($providerInfo->getDisplayName(), $providerInfo->getShortName());
        $answers = ExtensionAnswersConverter::convert($request->getAnswers() ?? []);
        try {
            $client = $this->clientFactory->createClient($request->getBrowserExtensionSessionId(), $parserLogger->getFileLogger(), $errorFormatter);
            /** @var LoginWithIdInterface $parser */
            $parser = $this->parserFactory->getParser($request->getProvider(), $parserLogger, $selectParserRequest, $providerInfo);
            if ($parser === null) {
                $url = $this->connection->fetchOne("select LoginURL from Provider where Code = ?", [$request->getProvider()]);
                $client->newTab($url, true);
                $client->complete("no parser for this provider");

                return;
            }

            $credentials = new Credentials($request->getLogin(), $request->getLogin2(), $request->getLogin3(), DecryptPassword($request->getPassword()), $answers);
            $this->parserRunner->loginWithLoginId($parser, $client, $credentials, true, $request->getLoginId() ?? '', true, $parserLogger->getFileLogger());
            $client->complete();
        }
        catch (CommunicationException $exception) {
            $this->logger->info("CommunicationException: " . $exception->getMessage(), ["Trace" => $exception->getTraceAsString()]);
        }
    }

    private function createAccountFields(AutologinWithExtensionRequest $request)
    {
        return [
            'Login' => $request->getLogin(),
            'Login2' => $request->getLogin(),
            'Login3' => $request->getLogin(),
            'Pass' => $request->getPassword(),
        ];
    }

}