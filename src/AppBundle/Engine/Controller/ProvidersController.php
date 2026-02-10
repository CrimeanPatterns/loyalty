<?php

namespace AppBundle\Controller;


use AppBundle\Extension\CheckerFactory;
use AppBundle\Extension\Loader;
use AppBundle\Extension\ProvidersHelper;
use AppBundle\Model\Resources\HistoryColumn;
use AppBundle\Model\Resources\Input;
use AppBundle\Model\Resources\PropertyInfo;
use AppBundle\Model\Resources\ProviderInfoResponse;
use AppBundle\Model\Resources\ProvidersListItem;
use AppBundle\Security\ApiUser;
use AppBundle\Service\RequestFactory;
use AppBundle\Service\ResponseFactory;
use Doctrine\DBAL\Connection;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerInterface;
use AppBundle\Service\InvalidParametersException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;


class ProvidersController
{

    /** @var Loader */
    private $loader;

    /** @var Serializer */
    private $serializer;

    /** @var LoggerInterface */
    private $logger;

    /** @var Connection */
    private $connection;

    /** @var CheckerFactory */
    private $factory;

    /** @var \Memcached */
    private $memcached;

    /** @var TokenStorage */
    private $token;

    /** @var ProvidersHelper */
    private $providersHelper;

    /** @var ResponseFactory */
    private $responseFactory;

    public function __construct(
        Connection $connection,
        Loader $loader,
        SerializerInterface $serializer,
        CheckerFactory $factory,
        \Memcached $memcached,
        TokenStorageInterface $token,
        LoggerInterface $logger,
        ProvidersHelper $providersHelper,
        RequestFactory $requestFactory,
        ResponseFactory $responseFactory
    )
    {
        $this->loader = $loader;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->connection = $connection;
        $this->factory = $factory;
        $this->memcached = $memcached;
        $this->token = $token;
        $this->providersHelper = $providersHelper;
        $this->responseFactory = $responseFactory;
    }

    /**
     * @Route("/providers/list", name="aw_controller_providers_list", methods={"GET"})
     * @Route("/v{apiVersion}/providers/list", host="%host%", name="aw_controller_v2_providers_list", requirements={"apiVersion"="1|2"}, methods={"GET"})
     * @return Response
     */
    public function providersList(Request $request, int $apiVersion = 1)
    {
        $sql = $this->providersHelper->AvailableProvidersQuery('list', $this->token->getToken()->getUser());
        $result = $this->connection->executeQuery($sql, [
            ':PROVIDER_ENABLED' => PROVIDER_ENABLED,
            ':PROVIDER_WSDL_ONLY' => PROVIDER_WSDL_ONLY,
        ])->fetchAll();

        $result = array_map(function (array $row) {
            return new ProvidersListItem($row['Code'], $row['DisplayName'], (int)$row['Kind']);
        }, $result);

        return $this->responseFactory->buildNoSwaggerResponse($result);
    }

    /**
     * @Route("/providers/{code}", name="aw_controller_providers_info", methods={"GET"})
     * @Route("/v{apiVersion}/providers/{code}", host="%host%", name="aw_controller_v2_providers_info", requirements={"apiVersion"="1|2"}, methods={"GET"})
     * @return Response
     */
    public function providerInfo(Request $request, $code, int $apiVersion = 1)
    {
        /** @var ApiUser $user */
        $user = $this->token->getToken()->getUser();
        $sql = $this->providersHelper->AvailableProvidersQuery('info', $user);
        $result = $this->connection->executeQuery($sql, [
            ':CODE' => $code,
            ':PROVIDER_ENABLED' => PROVIDER_ENABLED,
            ':PROVIDER_WSDL_ONLY' => PROVIDER_WSDL_ONLY,
        ])->fetch();
        if (!$result) {
            throw new InvalidParametersException('Invalid provider code specified in the URL, please use the "/providers/list" call to get the correct provider code.', []);
        }

        $credentials = ['Login' => null, 'Login2' => null, 'Login3' => null, 'Password' => null];
        foreach ($credentials as $field => $value) {
            $fcode = $field;
            $caption = $result[$field . 'Caption'];
            $required = $field === 'Login' ? true : $result[$field . 'Required'] == '1';
            $options = [];
            switch ($field) {
                case 'Password':
                {
                    $fcode = empty($caption) ? "Password" : $caption;
                    break;
                }
                case 'Login3':
                case 'Login2':
                {
                    if (empty($caption)) {
                        continue 2;
                    }
                    $options = $this->InputOptions($result['ProviderID'], $field);
                    break;
                }
            }
            $value = new Input();
            $value->setCode($fcode)
                ->setTitle($result[$field . 'Caption'])
                ->setOptions($options)
                ->setRequired($required)
                ->setDefaultvalue('');
            $credentials[$field] = $value;
        }

        $response = (new ProviderInfoResponse())
            ->setKind($result['Kind'])
            ->setCode($result['Code'])
            ->setDisplayname($result['DisplayName'])
            ->setProvidername($result['Name'])
            ->setProgramname($result['ProgramName'])
            ->setLogin($credentials['Login'])
            ->setLogin2($credentials['Login2'])
            ->setLogin3($credentials['Login3'])
            ->setPassword($credentials['Password'])
            ->setProperties($this->providerProperties($result))
            ->setAutologin(in_array($result['AutoLogin'], array(AUTOLOGIN_SERVER, AUTOLOGIN_MIXED)))
            ->setDeeplinking($result['DeepLinking'] == DEEP_LINKING_SUPPORTED)
            ->setCancheckconfirmation($result['CanCheckConfirmation'] == CAN_CHECK_CONFIRMATION_YES_SERVER || $result['CanCheckConfirmation'] == CAN_CHECK_CONFIRMATION_YES_EXTENSION_AND_SERVER)
            ->setCancheckitinerary($result['CanCheckItinerary'] == '1')
            ->setCanCheckPastItinerary($result['CanCheckPastItinerary'] == '1')
            ->setCancheckexpiration((int)$result['CanCheckExpiration'])
            ->setConfirmationnumberfields($this->getConfirmationNumberFields($result['Code']))
            ->setHistorycolumns($this->getHistoryColumns($result['Code'], $user->getUsername()))
            ->setCombineHistoryBonusToMiles($this->getCombineHistoryBonusToMiles($result['Code'], $user->getUsername()))
            ->setElitelevelscount($result["EliteLevelsCount"])
            ->setCanparsehistory($result["CanCheckHistory"] == '1')
            ->setCanparsefiles($result["CanCheckFiles"] == '1');

        return $this->responseFactory->buildResponse($response, $apiVersion);
    }

    /**
     * @param $fields
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    private function providerProperties($fields)
    {
        $properties = [];
        $codes = [];

        $sql = <<<SQL
            SELECT Code, Name, Kind
            FROM ProviderProperty
            WHERE ProviderID = :ProviderID
            ORDER BY SortIndex
SQL;
        $result = $this->connection->executeQuery($sql, [':ProviderID' => $fields['ProviderID']])->fetchAll();

        foreach ($result as $row) {
            $property = new PropertyInfo();
            $properties[] = $property->setCode($row['Code'])
                ->setName($row['Name'])
                ->setKind(!isset($row['Kind']) ? 0 : intval($row['Kind']));
            $codes[] = $row['Code'];
        }

        return $properties;
    }

    private function getHistoryColumns($providerCode, $partner)
    {
        $checker = $this->factory->getAccountChecker($providerCode);
        $columns = $checker->GetHistoryColumns();

        $result = [];
        if (empty($columns)) {
            return $result;
        }

        $hiddenCols = $checker->GetHiddenHistoryColumns();
        foreach ($columns as $name => $kind) {
            $column = HistoryColumn::createFromTAccountCheckerDefinition($name, $kind);
            $isHidden = in_array($name, $hiddenCols);

            if ('awardwallet' === $partner) {
                $column->setIsHidden($isHidden);
            }

            // filter hidden columns
            if ($isHidden && 'awardwallet' !== $partner) {
                continue;
            }

            $result[] = $column;
        }

        return $result;
    }

    private function getCombineHistoryBonusToMiles($providerCode, $partner)
    {
        if ('awardwallet' !== $partner) {
            return null;
        }

        $checker = $this->factory->getAccountChecker($providerCode);
        return $checker->combineHistoryBonusToMiles();
    }

    private function InputOptions($providerId, $fieldName)
    {
        $result = [];
        $sql = <<<SQL
            SELECT Code, Name
            FROM ProviderInputOption
            WHERE FieldName = :FIELD and ProviderID = :ProviderID
            ORDER BY SortIndex
SQL;
        $options = $this->connection->executeQuery($sql, [':FIELD' => $fieldName, ':ProviderID' => $providerId])->fetchAll();

        foreach ($options as $option) {
            $row = new PropertyInfo();
            $result[] = $row->setCode($option['Code'])->setName($option['Name'])->setKind(0);
        }
        return $result;
    }

    private function getConfirmationNumberFields($providerCode)
    {
        $cached = $this->memcached->get("loyalty_confirmation_fields_" . $providerCode);
        if (!empty($cached)) {
            $fields = unserialize($cached);
        } else {
            $checker = $this->factory->getAccountChecker($providerCode);
            $fields = $checker->GetConfirmationFields();
            $this->memcached->set("loyalty_confirmation_fields_" . $providerCode, serialize($fields), 1800);
        }

        $result = [];
        if (isset($fields)) {
            foreach ($fields as $code => $info) {
                if (isset($info['Options']))
                    $options = $this->arrayToOptions($info['Options']);
                else
                    $options = null;

                $value = new Input();
                $result[] = $value->setCode($code)
                    ->setTitle(ArrayVal($info, 'Caption', NameToText($code)))
                    ->setOptions($options)
                    ->setRequired(isset($info['Required']) && $info['Required'])
                    ->setDefaultvalue(ArrayVal($info, 'Value'));
            }
        }
        return $result;
    }

    private function arrayToOptions($array)
    {
        $result = [];
        foreach ($array as $key => $value) {
            $column = new PropertyInfo();
            $result[] = $column->setCode($key)->setName($value)->setKind(0);
        }
        return $result;
    }
}