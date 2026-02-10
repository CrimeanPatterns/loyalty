<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 18.03.16
 * Time: 11:30
 */
namespace Tests\Unit;

use AppBundle\Controller\AdminController;
use AppBundle\Extension\Loader;
use AppBundle\Extension\S3Custom;
use AppBundle\Model\Resources\AdminLogsRequest;
use AppBundle\Model\Resources\AdminLogsResponse;
use AppBundle\Security\ApiUser;
use Doctrine\ODM\MongoDB\DocumentManager;
use Monolog\Logger;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 * @backupGlobals disabled
 */
class AdminControllerTest extends BaseControllerTestClass
{

    protected $partner = 'awardwallet';
    protected $role = ApiUser::ROLE_ADMIN;

    /* пока непонятно как это нормально можно протестировать */
    public function __testGetLogs() {

        $tokenMock = $this->getCustomMock(TokenStorage::class);
        $tokenMock->expects($this->once())
                  ->method('getToken')
                  ->willReturn($this->createApiRoleUserToken());

        $controller = new AdminController(
            $this->connection,
            $this->container->get(DocumentManager::class),
            $this->serializer,
            $tokenMock,
            $this->getCustomMock(Logger::class),
            $this->getCustomMock(S3Custom::class),
            $this->getCustomMock(Loader::class)
        );

        $request = (new AdminLogsRequest())
                    ->setMethod('CheckAccount')
                    ->setPartner('awardwallet');

        $response = $controller->getLogs($request);
        $this->assertInstanceOf(AdminLogsResponse::class, $response);
    }

}