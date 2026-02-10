<?php

namespace AwardWallet\Engine\delta\Transfer;

// For use with LoadRegistrationOptionsCommand
use AwardWallet\Engine\ProxyList;

class RegistrationOptionsLoader extends \TAccountChecker
{
    use ProxyList;

    public $timeout = 10;
    protected $registrationUrl = 'https://www.delta.com/profile/enrolllanding.action';

    public function InitBrowser()
    {
        $this->UseCurlBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyDOP());
        } else {
            // This provider should be tested via proxy even locally
            $this->http->SetProxy('localhost:8000');
        }
    }

    public function load()
    {
        $logDir = $this->http->LogDir;
        echo "Logs dir: $logDir/\n";
        $logUrl = 'http://awardwallet.local/admin/common/logFile.php?Dir=' . urlencode($this->http->LogDir) . '&File=' . urlencode("log.html");
        echo "Logs link: $logUrl\n";
        $this->http->GetURL($this->registrationUrl);
        $countries = $this->http->FindNodes('//select[@id="countryCode-1"]/option/@value');
        $statesByCountry = [];
        $i = 1;
        $countriesCount = count($countries);

        foreach ($countries as $c) {
            echo "[$i/$countriesCount] Loading states for $c: ";
            $this->http->GetURL('https://www.delta.com/profile/json/profile_populateStateProvinceList.action?countryCode=' . $c);
            $statesJSON = $this->http->Response['body'];

            if ($states = json_decode($statesJSON, true)) {
                echo count($states) . "\n";
                $statesByCountry[$c] = $states;
            } else {
                echo "EMPTY\n";
            }
            $i++;
        }
        var_export($statesByCountry);
    }
}
