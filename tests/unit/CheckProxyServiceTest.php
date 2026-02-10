<?php
namespace Tests\Unit;


use AwardWallet\Common\Parsing\ProxyChecker;
use AwardWallet\Engine\ProxyList;
use Psr\Container\ContainerInterface;

class CheckProxyServiceTest extends BaseTestClass
{
    use ProxyList;

    public function testProxyCheckerExisting()
    {
        /** @var ContainerInterface $serviceLocator */
        $serviceLocator = $this->container->get("aw.parsing.web.service_locator");
        /** @var ProxyChecker $checkProxy */
        $checkProxy = $serviceLocator->get(ProxyChecker::class);
        $this->assertTrue($checkProxy instanceof ProxyChecker);
    }
}