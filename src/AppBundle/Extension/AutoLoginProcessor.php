<?php

namespace AppBundle\Extension;

use AppBundle\Extension\MQMessages\AutoLoginMessage;
use AppBundle\Model\Resources\AutoLoginRequest;
use AppBundle\Model\Resources\AutoLoginResponse;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class AutoLoginProcessor
{
    /** @var LoggerInterface */
    private $logger;
    /** @var CheckerFactory */
    private $factory;
    /** @var Connection */
    private $connection;
    /** @var S3Custom */
    private $s3Client;

    public function __construct(LoggerInterface $logger, Connection $connection, CheckerFactory $factory, S3Custom $s3Client)
    {
        $this->logger = $logger;
        $this->factory = $factory;
        $this->connection = $connection;
        $this->s3Client = $s3Client;
    }

    public function processAutoLoginRequest(AutoLoginRequest $request, string $requestId, string $partner): ?AutoLoginResponse
    {
        $result = (new AutoLoginResponse())->setUserData($request->getUserData());

        $arg = null;
        try {
            $arg = $this->redirectAccount($request, $requestId, $partner);
        } catch (\ThrottledException $e) {
            if ($e->retryInterval === 99) {
                $this->logger->warning('Can not autologin by selenium');
            } else {
                $this->logger->warning('ThrottledException:' . $e->getMessage(),
                    ['provider' => $request->getProvider()]);
            }
        }

        if (!$arg) {
            return null;
        }

        if (!in_array($partner, ['awardwallet', 'test'])) {
            unset($arg['ClickURL']);
            unset($arg['ImageURL']);
        }

        if (!empty($request->getStartUrl())) {
            $arg['ClickURL'] = $request->getStartUrl();
        }

        $manager = new \AutologinManager(null, $arg);
        $manager->supportedProtocols = $request->getSupportedProtocols();
        if ($request->getTargetUrl() != '') {
            $manager->successPages[] = $request->getTargetUrl();
        }

        ob_start();
        $manager->drawPage();
        $result->setResponse(ob_get_clean());

        return $result;
    }

    private function redirectAccount(AutoLoginRequest $request, string $requestId, string $partner)
    {
        $sql = "SELECT Code as ProviderCode, Engine AS ProviderEngine, State as ProviderState, Login2Caption, AutoLogin, LoginURL, ClickURL, ImageURL, RequestsPerMinute FROM Provider WHERE Code = :CODE";
        $fields = $this->connection->executeQuery($sql, [
            ':CODE' => $request->getProvider(),
        ])->fetch();

        $arg = [
            'ClickURL' => $fields['ClickURL'],
            'ImageURL' => $fields['ImageURL']
        ];

        $checker = $this->factory->getAccountChecker($fields['ProviderCode'], [
            'ProviderEngine' => $fields['ProviderEngine'],
            'Pass' => !empty($request->getPassword()) ? DecryptPassword($request->getPassword()) : null,
            'Login' => $request->getLogin(),
            'Login2' => $request->getLogin2(),
            'Login3' => $request->getLogin3(),
            'ProviderCode' => $request->getProvider(),
        ]);

        if (((int)$fields["ProviderState"] < PROVIDER_ENABLED) || in_array((int)$fields['AutoLogin'],
                [AUTOLOGIN_DISABLED, AUTOLOGIN_EXTENSION])) {
            $arg = array_merge($arg, [
                'RedirectURL' => $fields["LoginURL"],
                'NoCookieURL' => true,
                'RequestMethod' => 'GET',
            ]);

            $checker->UpdateGetRedirectParams($arg);
            $arg['AutoLogin'] = $fields['AutoLogin'];
        } else {
            $redirectArg = $checker->Redirect($request->getTargetUrl());
            $this->saveLogs($requestId, $checker, $partner);
            if (is_array($redirectArg)) {
                $arg['AutoLogin'] = $fields['AutoLogin'];
                return array_merge($arg, $redirectArg);
            }

            if ((int)$redirectArg === ACCOUNT_PROVIDER_ERROR) {
                $this->logger->critical("Failed to autologin, Provider error, " . $request->getProvider() . ", Login: " . $request->getLogin());
            }
            $arg = array_merge($arg, [
                "AutoLogin" => $fields['AutoLogin'],
                "RequestMethod" => "GET",
                "NoCookieURL" => true,
                "RedirectURL" => $fields["LoginURL"],
            ]);
            $checker->UpdateGetRedirectParams($arg);
        }

        return $arg;
    }

    protected function saveLogs($requestId, \TAccountChecker $checker, string $partner)
    {
        if ($checker->http !== null) {
            $this->s3Client->uploadAutoLoginLog($requestId, $checker->http->LogDir, $partner, $checker->AccountFields);
            $this->logger->info("Log dir: " . $checker->http->LogDir);
        }
    }

}