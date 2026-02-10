<?php

namespace Tests\Functional;

use AppBundle\Model\Resources\CheckExtensionSupportPackageRequest;
use AppBundle\Model\Resources\CheckExtensionSupportRequest;
use AwardWallet\ExtensionWorker\AccountOptions;
use Codeception\Example;
use Helper\DynamicCaller\StaticCalleeFactory;
use JMS\Serializer\Serializer;

class CheckExtensionSupportPackageCest
{
    private const CHECK_ACCOUNT_EXTENSION_SUPPORT_PACKAGE_POST_V2 = '/v2/account/check-extension-support/package';

    /**
     * @dataProvider extensionV3SupportDataProvider
     */
    public function testExtensionV3Support(\FunctionalTester $I, Example $example)
    {
        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");
        $dbConnection = $I->grabService("database_connection");
        $key = $dbConnection->executeQuery("select ApiKey from PartnerApiKey where PartnerID = 3 and Enabled = 1")->fetchColumn(0);
        $providerCode = $this->makeProvider( $I, $example);
        $I->haveHttpHeader("Content-Type", "application/json");
        $I->haveHttpHeader("X-Authentication", $key);
        $package = $example['packageGen'] instanceof \Closure ?
            $example['packageGen']($providerCode) :
            $example['packageGen'];
        $request =
            (new CheckExtensionSupportPackageRequest())
            ->setPackage($package);
        $I->sendPOST(
            self::CHECK_ACCOUNT_EXTENSION_SUPPORT_PACKAGE_POST_V2,
            $serializer->serialize($request, 'json'),
        );
        $I->assertSame($example['response'], $I->grabDataFromResponseByJsonPath('$')[0]);
    }

    protected function makeProvider(\FunctionalTester $I, Example $example): string
    {
        $providerCode = 'aa' . \bin2hex(\random_bytes(9));
        $I->haveInDatabase('Provider', [
            'Name' => $providerCode,
            'DisplayName' => $providerCode,
            'ShortName' => $providerCode,
            'Code' => $providerCode,
            'IsExtensionV3ParserEnabled' => $example['IsExtensionV3ParserEnabled'],
            'Kind' => 1,
            'State' => 1,
            'LoginCaption' => 'Login',
            'LoginRequired' => 1,
            'PasswordCaption' => 'Password',
            'PasswordRequired' => 1,
            'LoginURL' => "http://some.$providerCode.provider/login",
        ]);

        if ($example['isParseAllowed']) {
            $isParseAllowedCallableClass = StaticCalleeFactory::makeCallee($example['isParseAllowed']);
            $isParseAllowedCallableClass = \str_replace('$', "\\$", $isParseAllowedCallableClass);
            $className = \lcfirst($providerCode) . 'ExtensionOptions';
            eval("
                namespace AwardWallet\\Engine\\{$providerCode};
                use AwardWallet\\ExtensionWorker\\ParseAllowedInterface;
                use AwardWallet\\ExtensionWorker\\AccountOptions;
                
                class {$className} implements ParseAllowedInterface {
                    public function isParseAllowed(AccountOptions \$options) : bool
                    {
                        return \\call_user_func(\"{$isParseAllowedCallableClass}::invoke\", \$options);
                    }
                }
            ");
        }

        return $providerCode;

    }

    protected function extensionV3SupportDataProvider()
    {
        $twoAccountsPackage = fn (string $providerCode) => [
            (new CheckExtensionSupportRequest())
            ->setLogin('abc1')
            ->setLogin2('abc1')
            ->setLogin3('abc1')
            ->setId(100500)
            ->setProvider($providerCode)
            ->setIsMobile(false),
            (new CheckExtensionSupportRequest())
            ->setLogin('abc2')
            ->setLogin2('abc2')
            ->setLogin3('abc2')
            ->setId(100501)
            ->setProvider($providerCode)
            ->setIsMobile(false),
        ];

        return [
            [
                'IsExtensionV3ParserEnabled' => true,
                'packageGen' => [],
                'isParseAllowed' => fn (AccountOptions $options) => true,
                'response' => [
                    'package' => []
                ],
            ],
            [
                'IsExtensionV3ParserEnabled' => false,
                'packageGen' => $twoAccountsPackage,
                'isParseAllowed' => fn (AccountOptions $options) => $options->login === 'abc1',
                'response' => [
                    'package' => [
                        100500 => false,
                        100501 => false,
                    ]
                ]
            ],
            [
                'IsExtensionV3ParserEnabled' => true,
                'packageGen' => $twoAccountsPackage,
                // *ExtensionOptions class will not be generated
                'isParseAllowed' => null,
                'response' => [
                    'package' => [
                        100500 => true,
                        100501 => true,
                    ]
                ]
            ],
            [
                'IsExtensionV3ParserEnabled' => true,
                'packageGen' => $twoAccountsPackage,
                'isParseAllowed' => fn (AccountOptions $options) => $options->login === 'abc1',
                'response' => [
                    'package' => [
                        100500 => true,
                        100501 => false,
                    ]
                ]
            ],
        ];
    }
}