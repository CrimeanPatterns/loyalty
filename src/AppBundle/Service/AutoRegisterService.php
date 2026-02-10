<?php

namespace AppBundle\Service;

use AppBundle\Controller\Common\RabbitMessageCreator;
use AppBundle\Document\RaAccount;
use AppBundle\Document\RegisterAccount;
use AppBundle\Extension\Loader;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountRequest;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountResponse;
use AwardWallet\Common\Parsing\MailslurpApiControllersCustom;
use AwardWallet\Engine\RaRegistrationData;
use Aws\CloudWatch\CloudWatchClient;
use Doctrine\ODM\MongoDB\DocumentManager;
use Faker\Factory;
use Faker\Generator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AutoRegisterService
{

    private Generator $faker;
    private DocumentManager $dm;
    private LoggerInterface $logger;
    private CloudWatchClient $cloudWatchClient;
    private RabbitMessageCreator $rabbitMessageCreator;
    private MailslurpApiControllersCustom $mailslurpApiControllers;

    public function __construct(
        Loader $loader,
        DocumentManager $dm,
        LoggerInterface $logger,
        CloudWatchClient $cloudWatchClient,
        RabbitMessageCreator $rabbitMessageCreator,
        MailslurpApiControllersCustom $mailslurpApiControllers
    ) {
        $this->faker = Factory::create();
        $this->dm = $dm;
        $this->logger = $logger;
        $this->cloudWatchClient = $cloudWatchClient;
        $this->rabbitMessageCreator = $rabbitMessageCreator;
        $this->mailslurpApiControllers = $mailslurpApiControllers;
    }

    public function generateRegisterRequest(string $provider, string $email, int $delay, int $index, bool $isAuto = false): ?RegisterAccountRequest
    {
        $identity = $this->generateIdentity($provider, $email);

        if (is_null($identity)) {
            return null;
        }

        $request = (new RegisterAccountRequest())
            ->setProvider($provider)
            ->setFields($identity)
            ->setIsAuto($isAuto);

        if ($delay > 0) {
            $request->setRegisterNotEarlierDate(
                (new \DateTime())->setTimestamp(time() + $index * $delay)
            );
        }

        return $request;
    }

    public function retryFailRegistration($id)
    {

        $regAcc = $this->dm->getRepository(RegisterAccount::class)
            ->find($id);

        if ($regAcc) {
            $request = $regAcc->getRequest();
            $oldResp = $regAcc->getResponse();

            if ($oldResp->getState() == 0) {
                return [
                    'status' => 'ok',
                    'message' => 'Request already in queue',
                ];
            }

            $this->rabbitMessageCreator->createRabbitMessage(
                $regAcc->getId(),
                $regAcc->getRequest()->getPriority(),
                RegisterAccount::METHOD_KEY
            );

            $resp = new RegisterAccountResponse($regAcc->getId(), ACCOUNT_UNCHECKED, $request->getUserData(), 'placed into queue, use requestId to check result', new \DateTime());
            $regAcc->setResponse($resp);
            $this->dm->persist($regAcc);
            $this->dm->flush($regAcc);

            return [
                'status' => 'ok',
                'requestId' => $resp->getRequestId(),
                'message' => $resp->getMessage(),
            ];
        }

        return [
            'status' => 'error',
            'message' => 'Register account request not found, refresh page and try again.',
        ];
    }

    public function enabledReservedAccountsBySchedule(array $config): void
    {
        $enabledAccounts = $this->getRaAccounts($config);
        $diffCount = ($enabledAccounts['cnt'] < $config['minCountEnabled']) ?  $config['minCountEnabled'] - $enabledAccounts['cnt'] : 0;

        if ($diffCount) {
            $reservedAccounts = $this->getRaAccounts($config, 2);

            if ($diffCount > $reservedAccounts['cnt']) {
                $notEnoughAccountsCnt = $diffCount - $reservedAccounts['cnt'];
                $diffCount = $reservedAccounts['cnt'];

                try {
                    $this->cloudWatchClient->putMetricData([
                        'Namespace' => 'RA/AutoRegistration',
                        'MetricData' => [
                            [
                                'MetricName' => "auto-registration-ra_blocks",
                                'Timestamp' => time(),
                                'Value' => $notEnoughAccountsCnt,
                            ],
                        ],
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());
                }
                $this->logger->warning("Not enough reserved accounts, high blocking level by provider: {$config['provider']}");
            }

            for ($i = 0; $i < $diffCount; $i++){
                $this->dm->createQueryBuilder(RaAccount::class)
                    ->updateOne()
                    ->field('_id')->equals($reservedAccounts['accounts'][$i])
                    ->field('state')->set(1)
                    ->getQuery()
                    ->execute();
            }
        }
    }

    public function getDiffCount(array $config): int
    {
        $reservedAccounts = $this->getRaAccounts($config, 2);
        $registerAccount = $this->getRegisterAccount($config);

        return ($reservedAccounts['cnt'] < $config['minCountReserved'])
            ?  $config['minCountReserved'] - $reservedAccounts['cnt'] - $registerAccount['cnt']
            : 0;
    }

    private function getRaAccounts(array $config, int $state = 1): array
    {
        $accounts = array_keys(
            $this->dm->createQueryBuilder(RaAccount::class)
                ->hydrate(false)
                ->field('provider')->equals($config['provider'])
                ->field('state')->equals($state)
                ->field('errorCode')->notEqual(ACCOUNT_LOCKOUT)
                ->field('errorCode')->notEqual(ACCOUNT_PREVENT_LOCKOUT)
                ->getQuery()
                ->execute()
                ->toArray()
        );

        $cnt = count($accounts);

        return [
            'accounts' => $accounts,
            'cnt' => $cnt,
        ];
    }

    private function getRegisterAccount(array $config): array
    {
        $accounts = array_keys(
            $this->dm->createQueryBuilder(RegisterAccount::class)
                ->hydrate(false)
                ->field('request.provider')->equals($config['provider'])
                ->field('response.state')->equals(0)
                ->getQuery()
                ->execute()
                ->toArray()
        );

        $cnt = count($accounts);

        return [
            'accounts' => $accounts,
            'cnt' => $cnt,
        ];
    }

    private function getEmail(string $provider, string $email, string $username): ?string
    {
        $emailsByProvider = $this->dm->createQueryBuilder(RaAccount::class)
            ->distinct('email')
            ->field('provider')->equals($provider)
            ->getQuery()
            ->execute()
            ->toArray();

        $emailsFromRequests = $this->dm->createQueryBuilder(RegisterAccount::class)
            ->distinct('request.fields.Email')
            ->field('method')->equals('reward-availability-register')
            ->field('request.provider')->equals($provider)
            ->field('isChecked')->equals(false)
            ->getQuery()
            ->execute()
            ->toArray();

        $regType = 'manual';

        if ((strpos($email, '@gmail.com') !== false && strpos($email, '@gmail.com') > 1)) {
            $regType = 'gmail';
        } elseif ((strpos($email, '@') === 0)) {
            $regType = 'domain';
        } elseif ($email === 'test@mailslurpconfig.com') {
            $regType = 'mailslurp';
        } elseif ($email === 'custom@mailslurpconfig.com') {
            $regType = 'mailslurpCustom';
        }

        switch ($regType) {
            case 'gmail':
                $emails = array_diff(
                    array_unique($this->generateEmails($email)),
                    $emailsByProvider,
                    $emailsFromRequests
                );
                break;

            case 'domain':
                $emails = array_diff(
                    array_unique($this->generateEmails($username . $email)),
                    $emailsByProvider,
                    $emailsFromRequests
                );
                break;

            case 'mailslurp':
                $emails = array_diff(
                    $this->getMailSlurpInboxes(),
                    $emailsByProvider,
                    $emailsFromRequests
                );

                if (is_empty($emails)) {
                    $emails[] = $this->mailslurpApiControllers->getInboxControllerApi()->createInbox(
                        null, null, null, null, true
                    )->getEmailAddress();
                }
                break;

            case 'mailslurpCustom':
                $emails = array_diff(
                    $this->getMailSlurpInboxes(),
                    $emailsByProvider,
                    $emailsFromRequests
                );

                $emails = array_filter($emails, function ($item) {
                    return !strstr($item, '@mailslurp.');
                });

                if (is_empty($emails)) {
                    $emails[] = $this->mailslurpApiControllers->getInboxControllerApi()->createInbox(
                        $username . '@vmversion.com'
                    )->getEmailAddress();
                }
                break;

            default:
                $emails = array_diff(
                    [$email],
                    $emailsByProvider,
                    $emailsFromRequests
                );
        }

        rsort($emails);

        return strtolower(array_shift($emails));
    }

    private function getMailSlurpInboxes(): array
    {
        $emails = [];
        $inboxes = $this->mailslurpApiControllers->getInboxControllerApi()->getAllInboxes(0, 100000)->getContent();
        foreach ($inboxes as $inbox) {
            $emails[] = strtolower($inbox->getEmailAddress());
        }

        return $emails;
    }

    private function generateEmails(string $email): array
    {
        if (!strlen($email) > 30) {
            throw new \EngineError('Too long email. Length must not exceed 30 characters');
        }

        $emailName = strtolower(str_replace('.', '', substr($email, 0, strpos($email, '@'))));
        $suffix =  substr($email, strpos($email, '@'));

        $result = [];
        $this->recursiveGenerate($emailName, $result);
        array_unshift($result, $emailName);

        foreach ($result as $key => $value) {
            $result[$key] = $value . $suffix;
        }

        return $result;
    }

    private function recursiveGenerate(string $emailName, array &$array, int $end = 0): void
    {
        $length = strlen($emailName) - 1;

        for ($i = $length; $i >= 0; $i--) {
            $firstLetters = substr($emailName, 0, $i);
            $lastLetters = substr($emailName, $i, $length);

            if ($i == $end) {
                return;
            }
            $newEmail = $firstLetters . '.' . $lastLetters;
            array_push($array, $newEmail);

            $positionWordAfterDot = strrpos($newEmail, '.');
            if($positionWordAfterDot != $length) {
                $this->recursiveGenerate($newEmail, $array, $positionWordAfterDot + 1);
            }
        }
    }

    private function generateIdentity(string $provider, string $email): ?array
    {
        $states = RaRegistrationData::$states;
        $genderTitle = RaRegistrationData::$genderTitle;$gender = array_rand($genderTitle);
        $firstName = str_replace(['\\', '\'', '\"'], '', $this->faker->firstName($gender));
        $lastName = str_replace(['\\', '\'', '\"'], '', $this->faker->lastName);
        $userName = substr($firstName . $lastName, 0, 6) . random_int(1, 9999);
        $freeEmail =  $this->getEmail($provider, $email, $userName);
        $password = $this->generatePassword($provider);
        $answer =  str_replace(['\\', '\'', '\"', ' '], '', strtolower($this->faker->city));
        $state = $this->faker->stateAbbr;
        $areaCode = $states[$state]['areaCode'];

        if (!$freeEmail) {
            if (strpos($email, '@gmail.com') === false) {
                $this->logger->info("Email {$email} already used. Check it");
                return null;
            }

            $errorText = "Unused email addresses for {$provider} provider was ended. Create a new email!";

            try {
                $this->cloudWatchClient->putMetricData([
                    'Namespace' => 'RA/AutoRegistration',
                    'MetricData' => [
                        [
                            'MetricName' => "auto-registration-ra_end-emails",
                            'Timestamp' => time(),
                            'Value' => 1,
                        ],
                    ],
                ]);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
            $this->logger->error($errorText);
            throw new \EngineError($errorText);
        }

        return [
            'Username' => $userName,
            'Title' => $genderTitle[$gender],
            'Gender' => $gender,
            'FirstName' => $firstName,
            'LastName' => $lastName,
            'BirthdayDate' => $this->faker->date('m/d/Y', '-21 years'),
            'Email' => $freeEmail,
            'Password' => $password,
            'MobileAreaCode' => $areaCode,
            'PhoneNumber' => $areaCode . $this->faker->regexify("[1-9]{7}"),
            'Country' => 'US',
            'State' => $state,
            'ZipCode' => $states[$state]['zipCode'],
            'City' => $states[$state]['city'],
            'Address' => $this->faker->numberBetween(1, 163) . ' ' . $this->faker->streetName,
            'Answer' => $answer,
        ];
    }

    private function generatePassword(string $provider)
    {
        if (in_array($provider,['qantas','turkish'])) {
            if ($provider==='qantas') {
                $numbers = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'];
                $cnt = 4;
            } else {
                $numbers = ['1', '2', '3', '4', '5', '6', '7', '8', '9'];
                $cnt = 6;
            }
            $password = '';
            $prevNum = null;
            while ($cnt) {
                shuffle($numbers);
                $num = array_shift($numbers);
                if (isset($prevNum) && abs($prevNum - $num) === 1) {
                    $numbers[] = $num; // back in tail
                    $num = array_shift($numbers);
                    if (abs($prevNum - $num) === 1) {
                        $numbers[] = $num; // back in tail
                        $num = array_shift($numbers);
                    }
                }
                $prevNum = $num;
                $password .= $prevNum;
                $cnt--;
            }
            return $password;
        }

        return $this->faker->regexify('[0-9]{1,2}[A-Z]{1}[a-z]{1}[A-Za-z]{6}[#$]{1}[0-9]{0,2}');
    }
}