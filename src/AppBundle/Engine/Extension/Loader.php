<?php
namespace AppBundle\Extension;

use AwardWallet\Common\Parsing\ParsingConstants;
use AwardWallet\Engine\Settings;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Loader {

    use ContainerAwareTrait;

    /** @var Logger */
    protected $logger;

    public function __construct(ContainerInterface $container, LoggerInterface $logger, ParsingConstants $parsingConstants) {
        $this->setContainer($container);
        $this->logger = $logger;

        global $DieAsException, $DieTraceOnWarning;
        $DieTraceOnWarning = [$this, 'DieTraceOnWarning'];
        $DieAsException = 'fatal';

        // TODO: migrate all this constants to parsing_constants
        if(!defined('MEMCACHED_HOST'))
            define('MEMCACHED_HOST', $this->container->getParameter('memcached_host'));
        if(!defined('SHARED_MEMCACHED_HOST'))
            define('SHARED_MEMCACHED_HOST', $this->container->getParameter('shared_memcached_host'));
        if(!defined('ANTIGATE_KEY'))
            define('ANTIGATE_KEY', $this->container->getParameter('antigate_key'));
        if(!defined('ANTIGATE_PROXY_PASSWORD'))
            define('ANTIGATE_PROXY_PASSWORD', $this->container->getParameter('antigate_proxy_password'));
        if(!defined('RUCAPTCHA_KEY'))
            define('RUCAPTCHA_KEY', $this->container->getParameter('rucaptcha_key'));
        if(!defined('FRAUDGUARD_AUTH'))
            define('FRAUDGUARD_AUTH', $this->container->getParameter('fraudguard_auth'));
        if(!defined('IPINTEL_AUTH'))
            define('IPINTEL_AUTH', $this->container->getParameter('ipintel_auth'));

        if(!defined('DEBUG_SERVICE_LOCATION'))
            define('DEBUG_SERVICE_LOCATION', $this->container->getParameter('debug_service_location'));

        /* American Airlines API credintials */
        if(!defined('AA_WSDL_ENDPOINT'))
            define('AA_WSDL_ENDPOINT', $this->container->getParameter('aa_api_endpoint'));
        if(!defined('AA_WSDL_LOGIN'))
            define('AA_WSDL_LOGIN', $this->container->getParameter('aa_api_login'));
        if(!defined('AA_WSDL_PASSWORD'))
            define('AA_WSDL_PASSWORD', $this->container->getParameter('aa_api_password'));
        if(!defined('AA_WSDL_PASSWORD_2017_08_01'))
            define('AA_WSDL_PASSWORD_2017_08_01', $this->container->getParameter('aa_api_password_2017_08_01'));
        if(!defined('AA_WSDL_AUTHORIZATION_PASSWORD'))
            define('AA_WSDL_AUTHORIZATION_PASSWORD', $this->container->getParameter('aa_api_auth_password'));

        /* Bank of America API */
        if(!defined('BANKOFAMERICA_CLIENT_ID'))
            define('BANKOFAMERICA_CLIENT_ID', $this->container->getParameter('bankofamerica_client_id'));
        if(!defined('BANKOFAMERICA_CLIENT_SECRET'))
            define('BANKOFAMERICA_CLIENT_SECRET', $this->container->getParameter('bankofamerica_client_secret'));
        if(!defined('BANKOFAMERICA_SSL_CERT_FILE_2023'))
            define('BANKOFAMERICA_SSL_CERT_FILE_2023', $this->container->getParameter('bankofamerica_ssl_cert_file_2023'));
        if(!defined('BANKOFAMERICA_SSL_CERT_FILE_2024'))
            define('BANKOFAMERICA_SSL_CERT_FILE_2024', $this->container->getParameter('bankofamerica_ssl_cert_file_2024'));

        /* NET NUT */
        if(!defined('NETNUT_USERNAME'))
            define('NETNUT_USERNAME', $this->container->getParameter('netnut_username'));
        if(!defined('NETNUT_PASSWORD'))
            define('NETNUT_PASSWORD', $this->container->getParameter('netnut_password'));

        /* OXYLABS */
        if(!defined('OXYLABS_USERNAME'))
            define('OXYLABS_USERNAME', $this->container->getParameter('oxylabs_username'));
        if(!defined('OXYLABS_PASSWORD'))
            define('OXYLABS_PASSWORD', $this->container->getParameter('oxylabs_password'));

        /* GOPROXIES */
        if(!defined('GOPROXIES_USERNAME'))
            define('GOPROXIES_USERNAME', $this->container->getParameter('goproxies_username'));
        if(!defined('GOPROXIES_PASSWORD'))
            define('GOPROXIES_PASSWORD', $this->container->getParameter('goproxies_password'));
        $providers = [
            'delta',
            'mileageplus',
            'aeroplan',
            'aviancataca',
            'virgin',
            'iberia',
            'british',
            'alaskaair',
            'qantas',
            'jetblue',
            'rapidrewards',
            'turkish',
            'etihad',
            'asia',
            'hawaiian',
            'tapportugal',
            'asiana',
            'korean',
            'velocity',
            'aeromexico',
            'eurobonus',
            'israel'
        ];
        foreach ($providers as $provider) {
            $providerUP = strtoupper($provider);
            if (!defined('GOPROXIES_USERNAME_' . $providerUP)) {
                define('GOPROXIES_USERNAME_' . $providerUP,
                    $this->container->getParameter('goproxies_username_' . $provider));
            }
            if (!defined('GOPROXIES_PASSWORD_' . $providerUP)) {
                define('GOPROXIES_PASSWORD_' . $providerUP,
                    $this->container->getParameter('goproxies_password_' . $provider));
            }
        }

        /* MOUNT PROXIES */
        if(!defined('MOUNT_APIKEY'))
            define('MOUNT_APIKEY', $this->container->getParameter('mount_apikey'));
        if(!defined('MOUNT_USERNAME'))
            define('MOUNT_USERNAME', $this->container->getParameter('mount_username'));
        if(!defined('MOUNT_PASSWORD'))
            define('MOUNT_PASSWORD', $this->container->getParameter('mount_password'));


        /* GEOSERF */
        if(!defined('GEOSURF_ID'))
            define('GEOSURF_ID', $this->container->getParameter('geosurf_id'));
        if(!defined('GEOSURF_PASSWORD'))
            define('GEOSURF_PASSWORD', $this->container->getParameter('geosurf_password'));

        /* ILLUMINATI */
        if(!defined('ILLUMINATI_CUSTOMER'))
            define('ILLUMINATI_CUSTOMER', $this->container->getParameter('illuminati_customer'));
        if(!defined('ILLUMINATI_ZONE'))
            define('ILLUMINATI_ZONE', $this->container->getParameter('illuminati_zone'));
        if(!defined('ILLUMINATI_PASS'))
            define('ILLUMINATI_PASS', $this->container->getParameter('illuminati_pass'));
        if(!defined('ILLUMINATI_API_TOKEN'))
            define('ILLUMINATI_API_TOKEN', $this->container->getParameter('illuminati_api_token'));

        /* LPM */
        if(!defined('LPM_HOST'))
            define('LPM_HOST', $this->container->getParameter('lpm_host'));

        if (!defined('CACHE_HOST')) {
            define('CACHE_HOST', $this->container->getParameter('cache_host'));
        }

        /* ASTRA */
        if(!defined('ASTRA_API_KEY'))
            define('ASTRA_API_KEY', $this->container->getParameter('astra_api_key'));

        /* MAILSLURP */
        if(!defined('MAILSLURP_API_KEY'))
            define('MAILSLURP_API_KEY', $this->container->getParameter('mailslurp_api_key'));

        $serviceDir = $this->container->getParameter("kernel.project_dir") . "/vendor/awardwallet/service";
        require_once __DIR__.'/../Lib/constants.php';
        require_once __DIR__.'/../Lib/functions.php';
        require_once $serviceDir . '/old/functions.php';

        /** @var \AppKernel $kernel */
        $kernel = $this->container->get('kernel');
        if(!file_exists($kernel->getLogDir()))
            mkdir($kernel->getLogDir());
        \TAccountChecker::$logDir = $kernel->getLogDir() . "/check";
        if(!file_exists(\TAccountChecker::$logDir)) {
            $this->logger->info("creating log directory: " . \TAccountChecker::$logDir);
            mkdir(\TAccountChecker::$logDir);
        }
        Settings::setAwUrl($this->container->getParameter("debug_service_location"));
        \StatLogger::setLogger($this->logger);

        // TODO: убрать этот грязный хак
        if(!defined('DATE_TIME_FORMAT'))
            define( "DATE_TIME_FORMAT", "F d, Y H:i:s" );
        if(!defined('DATE_FORMAT'))
            define( "DATE_FORMAT", "m/d/Y" );

        $Config[CONFIG_SITE_STATE] = $kernel->isDebug() ? SITE_STATE_DEBUG : SITE_STATE_PRODUCTION;
        $Config[CONFIG_TRAVEL_PLANS] = false;

        if (!function_exists('\AppBundle\Extension\loadProviderChecker') && !function_exists('loadProviderChecker')) {
            function loadProviderChecker($className) {
                if(strpos($className, 'TAccountChecker') === 0){
                    $code = strtolower(substr($className, strlen('TAccountChecker')));
                    if(!empty($code)) {
                        $file = __DIR__ . '/../Engine/' . $code . '/functions.php';
                        if(file_exists($file))
                            require_once $file;
                    }
                }
            }
            spl_autoload_register('\AppBundle\Extension\loadProviderChecker');
        }
    }

    public function DieTraceOnWarning($message, $moreInfo)
    {
        $this->logger->critical($message, ["info" => $moreInfo]);
    }

}

