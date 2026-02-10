<?php


use AppBundle\Model\Resources\BaseCheckResponse;
use AppBundle\Model\Resources\CheckConfirmationRequest;
use AppBundle\Model\Resources\InputField;
use AppBundle\Model\Resources\PostCheckResponse;
use AppBundle\Security\ApiUser;
use Codeception\Example;
use Codeception\Util\Stub;
use JMS\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class CheckConfirmationControllerCest
{
    private const CHECK_POST = '/confirmation/check';
    private const CHECK_GET = '/confirmation/check/%s';
    private const CHECK_QUEUE = '/confirmation/queue';
    private const CHECK_QUEUE_V2 = '/v2/confirmation/queue';
    private const CHECK_POST_V2 = '/v2/confirmation/check';
    private const CHECK_GET_V2 = '/v2/confirmation/check/%s';

    public function _before(\FunctionalTester $I)
    {
        $partner = 'test_' . bin2hex(random_bytes(5));

        $tokenStorage = Stub::make(TokenStorage::class, [
            'getToken' => $I->createApiRoleUserToken($partner,
                [ApiUser::ROLE_USER, ApiUser::ROLE_RESERVATIONS_CONF_NO, ApiUser::ROLE_RESERVATIONS_INFO]
            )
        ]);
        $I->mockService('security.token_storage', $tokenStorage);
    }

    /**
     * @dataProvider getCheckConfirmationCestData
     */
    public function testCheckConfirmation(\FunctionalTester $I, Example $example)
    {
        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");

        $fields = [];
        $fields[] = (new InputField())->setCode('ConfNo')->setValue('TESTCODE0');
        $fields[] = (new InputField())->setCode('LastName')->setValue('TESTNAME');

        $request = new CheckConfirmationRequest();
        $request->setProvider('testprovider')
            ->setUserid(md5(time()))
            ->setPriority(9)
            ->setFields($fields);

        $I->sendPOST($example['Post'], $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseMatchesJsonType(['requestId' => 'string']);

        /** @var PostCheckResponse $postResponse */
        $postResponse = $serializer->deserialize($I->grabResponse(), PostCheckResponse::class, 'json');

        $I->sendGET(sprintf($example['Get'], $postResponse->getRequestid()));
        $I->seeResponseCodeIs(Response::HTTP_OK);

        /** @var BaseCheckResponse $checkResponse */
        $checkResponse = $serializer->deserialize($I->grabResponse(), $example['ResponseCls'], 'json');
        $I->assertEquals($postResponse->getRequestid(), $checkResponse->getRequestId());

        $I->sendGET(sprintf($example['WrongVersionGet'], $postResponse->getRequestid()));
        $I->seeResponseCodeIs(Response::HTTP_NOT_FOUND);
    }

    public function testCheckAccountQueue(\FunctionalTester $I)
    {
        foreach ([self::CHECK_QUEUE, self::CHECK_QUEUE_V2] as $url) {
            $I->sendGET($url);
            $I->seeResponseCodeIs(Response::HTTP_OK);
            $I->seeResponseMatchesJsonType(['queues' => 'array']);
        }
    }

    protected function getCheckConfirmationCestData()
    {
        return [
            [
                'Post' => self::CHECK_POST,
                'Get' => self::CHECK_GET,
                'WrongVersionGet' => self::CHECK_GET_V2,
                'ResponseCls' => \AppBundle\Model\Resources\CheckConfirmationResponse::class
            ],
            [
                'Post' => self::CHECK_POST_V2,
                'Get' => self::CHECK_GET_V2,
                'WrongVersionGet' => self::CHECK_GET,
                'ResponseCls' => \AppBundle\Model\Resources\V2\CheckConfirmationResponse::class
            ],
        ];
    }
}