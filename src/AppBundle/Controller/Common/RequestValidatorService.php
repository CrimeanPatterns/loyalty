<?php


namespace AppBundle\Controller\Common;


use AppBundle\Extension\ProvidersHelper;
use AppBundle\Model\Resources\BaseCheckRequest;
use AppBundle\Model\Resources\CheckConfirmationRequest;
use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelRequest;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityRequest;
use AppBundle\Security\ApiUser;
use AppBundle\Service\AccessChecker;
use AppBundle\Service\InvalidParametersException;
use AwardWallet\Common\Partner\CallbackAuthSource;
use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RequestValidatorService
{

    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var Connection */
    private $connection;

    /** @var CallbackAuthSource */
    private $callbackAuthSource;

    /** @var ProvidersHelper */
    private $providersHelper;

    public const UNAVAILABLE_CALLBACK = 'Specified callback URL is not registered with AwardWallet. Please contact AwardWallet support to register this callback URL per our documentation.';
    private const INVALID_PROVIDER_MESSAGE = "Provider code (%s) does not exist, please use the \"/providers/list\" call to get the correct provider code.";

    public function __construct(TokenStorageInterface $tokenStorage, Connection $connection, ProvidersHelper $providersHelper, CallbackAuthSource $callbackAuthSource)
    {
        $this->tokenStorage = $tokenStorage;
        $this->connection = $connection;
        $this->callbackAuthSource = $callbackAuthSource;
        $this->providersHelper = $providersHelper;
    }

    public function validateRequest(LoyaltyRequestInterface $request): void
    {
        $user = $this->tokenStorage->getToken()->getUser();

        if ($request->getProvider() === 'testprovider') {
            $sql = "SELECT * FROM Provider WHERE Code = :CODE ORDER BY Code";
            $provider = $this->connection->executeQuery($sql, [
                ':CODE' => $request->getProvider(),
            ])->fetch();
        } else {
            switch (true) {
                case $request instanceof CheckConfirmationRequest:
                    $queryType = 'confirmation';
                    break;
                case $request instanceof RewardAvailabilityRequest:
                    $queryType = 'reward-availability';
                    break;
                case $request instanceof RaHotelRequest:
                    $queryType = 'reward-availability-hotel';
                    break;
                default:
                    $queryType = 'check';
                    break;
            }
            $sql = $this->providersHelper->AvailableProvidersQuery($queryType, $user);
            $provider = $this->connection->executeQuery($sql, [
                ':CODE' => $request->getProvider(),
                ':PROVIDER_ENABLED' => PROVIDER_ENABLED,
                ':PROVIDER_WSDL_ONLY' => PROVIDER_WSDL_ONLY,
            ])->fetch();
        }

        // validate callback
        if (!empty($request->getCallbackurl())) {
            if ($this->callbackAuthSource->getByUrl($user->getUsername(), $request->getCallbackurl()) === null) {
                throw new InvalidParametersException(self::UNAVAILABLE_CALLBACK, []);
            }
        }

        if ($request instanceof RewardAvailabilityRequest || $request instanceof RaHotelRequest) {
            return;
        }

        if (
            !$provider
            || (
                in_array($request->getProvider(), [
                    'rapidrewards',
                    'delta',
                    'mileageplus'
                ])
                && in_array($user->getUsername(), ['awardwallet', 'test'])
            )
        ) {
            throw new InvalidParametersException(
                sprintf(self::INVALID_PROVIDER_MESSAGE, $request->getProvider()),
                []
            );
        }

        if ($request->getProvider() === 'aa') {
            return;
        }

        if ($request instanceof CheckConfirmationRequest) {
            return;
        }

        if ((int)$provider['PasswordRequired'] !== 1) {
            return;
        }

        if (empty($request->getPassword()) || trim($request->getPassword()) == "") {
            throw new InvalidParametersException(
                'The specified parameter was rejected: the "password" property is required for "' . $request->getProvider() . '" provider',
                []
            );
        }
    }

    public function checkAccess(array $roles): void
    {
        /** @var ApiUser $user */
        $user = $this->tokenStorage->getToken()->getUser();

        foreach ($roles as $role) {
            if (!AccessChecker::isGranted($user, $role)) {
                throw new InvalidParametersException(AccessChecker::getAccessDeniedMessage($role), [], 403);
            }
        }
    }

    /**
     * @param RewardAvailabilityRequest|RaHotelRequest $request
     */
    public function validateRewardAvailabilityRequest(LoyaltyRequestInterface $request)
    {
        $this->validateRequest($request);

        $fieldName = 'CanCheckRewardAvailability';
        if ($request instanceof RAHotelRequest) {
            $fieldName = 'CanCheckRaHotel';
        }
        $provider = $this->connection->executeQuery(
            "SELECT {$fieldName} FROM Provider WHERE Code = :CODE",
            [':CODE' => $request->getProvider()]
        )->fetch();
        if (!$provider) {
            throw new InvalidParametersException(sprintf(self::INVALID_PROVIDER_MESSAGE, $request->getProvider()), []);
        }

        /** @var ApiUser $user */
        $user = $this->tokenStorage->getToken()->getUser();
        $canDebug = AccessChecker::isGranted($user, ApiUser::ROLE_DEBUG);
        if (!($request instanceof RewardAvailabilityRequest) && $canDebug) {
            return;
        }

        // CanCheckRewardAvailability: 0 - disabled, 1 - enabled for everyone, 2 - enabled only for aw
        if (
            ($fieldName === 'CanCheckRewardAvailability' && $user->getUsername() !== 'awardwallet' && (int)$provider[$fieldName] === 2)
            || (int)$provider[$fieldName] === 0
        ) {
            throw new InvalidParametersException(sprintf(self::INVALID_PROVIDER_MESSAGE, $request->getProvider()), []);
        }
    }

}