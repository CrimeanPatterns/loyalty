<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerDrury extends TAccountChecker
{
    use ProxyList;

    public const REWARDS_PAGE_URL = 'https://www.druryhotels.com/account';
    private $stepItinerary;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);

        if (strstr($this->http->Error, 'Network error 35 - OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to www.druryhotels.com:443')) {
            $this->http->SetProxy($this->proxyReCaptcha());
            $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        }

        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function script()
    {
        $this->logger->notice(__METHOD__);

        if ($script = $this->http->FindPreg('#script type="text/javascript">\s*eval(.+?)</script>#s')) {
            sleep(rand(2, 4));
            $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
            $script = str_replace("'\\b'", "'\\\\b'", $script);
            $script = str_replace('k[c] || e(c);', "'' + k[c];", $script);
            $script = str_replace("(!''.replace", "(''.replace", $script);
            // not sure
            $script = "sendResponseToPhp($script);";
            $script = $jsExecutor->executeString($script);

            if (!$script) {
                $this->logger->error('Failed js');

                return;
            }

            //"?" + str1 + "=" + RYHKLYYYHSBPKQQY(tmevtre4);
            $input = $this->http->FindPreg('/"\?"\s*\+\s*str1\s*\+\s*"="\s*\+\s*[A-Z]+\((\w+)\);/', false, $script);
            // var tmevtre0 = "WkNFR0lLTlBSVFZYQUNFR0lLTlBSVFYxNDY4ODAzQUM=";
            $input = $this->http->FindPreg('/var\s*' . $input . '\s*=\s*"(.+?)";/', false, $script);
            //var keyStr = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
            $keyStr = $this->http->FindPreg('/var\s*keyStr\s*=\s*"(.+?)"/', false, $script);

            $script2 = "var keyStr = \"{$keyStr}\";
            function RYHKLYYYHSBPKQQY(input) {
            var output = \"\";
            var chr1, chr2, chr3 = \"\";
            var enc1, enc2, enc3, enc4 = \"\";
            var i = 0;
            input = input.replace(/[^A-Za-z0-9+/=]/g, \"\");
            do {
                enc1 = keyStr.indexOf(input.charAt(i++));
                enc2 = keyStr.indexOf(input.charAt(i++));
                enc3 = keyStr.indexOf(input.charAt(i++));
                enc4 = keyStr.indexOf(input.charAt(i++));
                chr1 = (enc1 << 2) | (enc2 >> 4);
                chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
                chr3 = ((enc3 & 3) << 6) | enc4;
                output = output + String.fromCharCode(chr1);
                if (enc3 != 64) {
                    output = output + String.fromCharCode(chr2);
                }
                if (enc4 != 64) {
                    output = output + String.fromCharCode(chr3);
                }
                chr1 = chr2 = chr3 = \"\";
                enc1 = enc2 = enc3 = enc4 = \"\";
            } while (i < input.length);
            return unescape(output);
            };sendResponseToPhp(RYHKLYYYHSBPKQQY('{$input}'))";
            $param = $jsExecutor->executeString($script2, 'basic.js');

            $this->http->RetryCount = 0;

            if ($data = $this->http->FindPreg('/var\s*send_data\s*=\s*"fwb_dat="\s*\+\s*"(.+?)";/', false, $script)) {
                $data = ['fwb_dat' => $data];
                $headers = ['Content-Type' => 'text/html'];
                $this->http->PostURL("https://www.druryhotels.com/account/login?cookiesession8341={$param}", $data, $headers);

                if (strstr($this->http->Error, 'Network error 35 - OpenSSL SSL_connect')) {
                    sleep(1);
                    $this->http->PostURL("https://www.druryhotels.com/account/login?cookiesession8341={$param}", $data, $headers);
                }

                if (strstr($this->http->Error, 'Network error 35 - OpenSSL SSL_connect')) {
                    sleep(3);
                    $this->http->PostURL("https://www.druryhotels.com/account/login?cookiesession8341={$param}", $data, $headers);
                }

                if (
                    $this->http->Response['code'] == 500
                    && ($message = $this->http->FindPreg("/The DruryHotels\.com site is experiencing some technical difficulties right now. We apologize for the inconvenience\./"))
                ) {
                    throw new CheckRetryNeededException(2, 7, $message);
                }
            }
            $this->http->RetryCount = 2;
        }
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.druryhotels.com/account/login?ReturnUrl=%2faccount");
        $this->http->RetryCount = 2;
        $this->script();

        if (!$this->http->ParseForm(null, "//form[@action = '/account/login']")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('UserName', $this->AccountFields['Login']);
        $this->http->SetInputValue('Password', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('//p[contains(text(), "The page cannot be displayed. Please contact the administrator for additional information.")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            // The DruryHotels.com site is experiencing some technical difficulties right now. We apologize for the inconvenience.
            if ($message = $this->http->FindPreg("/The DruryHotels.com site is experiencing some technical difficulties right now. We apologize for the inconvenience\./")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // The page cannot be displayed. Please contact the administrator for additional information.
            if ($message = $this->http->FindSingleNode("//p[contains(normalize-space(text()), 'The page cannot be displayed. Please contact the administrator for additional information.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }

        $this->script();

        // Access is allowed
        if ($this->http->currentUrl() == self::REWARDS_PAGE_URL && !$this->loginSuccessful()) {
            $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        }

        if ($this->loginSuccessful()) {
            return true;
        }
        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//div[@class = 'validation-summary-errors']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The field Password must be a string with a minimum length of 6 and a maximum length of 20.
        if ($message = $this->http->FindSingleNode("//span[contains(normalize-space(text()), 'The field Password must be a string with a minimum length of 6 and a maximum length of 20.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // account doesn't has any errors
        $this->logger->debug("'{$this->http->FindSingleNode("//form[@action = '/account/login']")}'");

        if (
            in_array($this->http->FindSingleNode("//form[@action = '/account/login']"), [
                'Username* Password* Caps Lock is on! Forgot Password? Sign In',
                'Username* Password* Caps Lock is on! Forgot Username?Forgot Password? Sign In',
            ])
            && $this->http->FindSingleNode("//input[@id = 'UserName']/@value") == $this->AccountFields['Login']
            && $this->http->FindSingleNode("//input[@id = 'Password']/@value") == ''
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - You have ... Points
        $this->SetBalance($this->http->FindSingleNode("//section[contains(@class, 'welcome-header')]//h2/span[contains(text(), 'Point')]", null, true, "/have\s*([^<]+)\s+Point/"));
        // Member #
        $this->SetProperty("Number", $this->http->FindSingleNode("//section[contains(@class, 'welcome-header')]//h2/span[contains(text(), 'Member #')]", null, true, "/#\s*([^<]+)/"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // The DruryHotels.com site is experiencing some technical difficulties right now. We apologize for the inconvenience.
            if ($message = $this->http->FindPreg('/The DruryHotels.com site is experiencing some technical difficulties right now\. We apologize for the inconvenience\./')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        // Name
        $this->http->GetURL("https://druryhotels.com/account/userprofilesettings");
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//input[@name = 'FirstName']/@value") . " " . $this->http->FindSingleNode("//input[@name = 'LastName']/@value")));

        //# Expiration Date refs #24086
        if ($this->Balance <= 0) {
            return;
        }
        $this->logger->info('Expiration Date', ['Header' => 3]);
        $this->http->GetURL("https://www.druryhotels.com/account/druryrewardspoints");

        $activities = $this->http->XPath->query('//table[@id="PointsActivity"]//text()[normalize-space(.)="Date"]/ancestor::tr[contains(.,"Activity")]/following-sibling::tr');
        $this->logger->debug("Found {$activities->length} transactions found");

        $now = 0;

        foreach ($activities as $activity) {
            // 5/15/2023
            $lastActivityStr = $this->http->FindSingleNode("./td[1]", $activity, false, '#\d+/\d+/\d+#');
            $balance = str_replace(',', '', $this->http->FindSingleNode("./td[3]", $activity));
            $this->logger->debug("Last Activity: $lastActivityStr");
            $lastActivity = strtotime($lastActivityStr);

            if ($now < $lastActivity && $balance > 0) {
                $now = $lastActivity;
                // LastActivity
                $this->SetProperty("LastActivity", $lastActivityStr);
                // Expiration Date
                if ($exp = strtotime("+36 month", $now)) {
                    $this->SetExpirationDate($exp);
                }
            }
        }
    }

    public function ParseItineraries()
    {
        $this->http->GetURL('https://www.druryhotels.com/account/futurereservations');
        $urls = $this->http->FindNodes('//a[contains(@href,"/bookandstay/printconfirmation?printId=")]/@href');
        $this->logger->debug("Found " . count($urls) . " itineraries found");

        if (count($urls) == 0 && $this->http->FindSingleNode('//p[contains(text(), "You have no future trips.")]')) {
            $this->itinerariesMaster->setNoItineraries(true);

            return;
        }

        foreach ($urls as $url) {
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);
            $this->parseItinerary();
        }
    }

    private function parseItinerary()
    {
        $this->logger->notice(__METHOD__);
        $conf = $this->http->FindSingleNode('//th[contains(text(),"Confirmation Number:")]/following-sibling::td');
        $this->logger->info(sprintf("[%s] Parse Itinerary #%s", $this->stepItinerary++, $conf), ['Header' => 3]);
        $h = $this->itinerariesMaster->createHotel();
        $h->general()->confirmation($conf);
        $h->general()->traveller(beautifulName($this->http->FindSingleNode('//th[contains(text(),"Name:")]/following-sibling::td')));

        $i = 0;
        $name = null;
        $address = $phone = [];
        $nodes = $this->http->FindNodes('//th[contains(text(),"Hotel Information:")]/following-sibling::td/text()');

        foreach ($nodes as $node) {
            if ($i == 0) {
                $name = $node;
            }
            // 412-490-4988
            elseif ($this->http->FindPreg('/^\s*[\d\-\s]{6,20}/', false, $node)) {
                $phone[] = $node;
            } else {
                $address[] = $node;
            }
            $i++;
        }
        $h->hotel()
            ->name($name)
            ->address(join(', ', $address));

        if (!empty($phone)) {
            $h->hotel()->phone(join(', ', $phone));
        }

        $h->booked()
            ->checkIn2($this->http->FindSingleNode('//th[contains(text(),"Arrival Date:")]/following-sibling::td'))
            ->checkOut2($this->http->FindSingleNode('//th[contains(text(),"Departure Date:")]/following-sibling::td'));

        $days = $this->http->XPath->query('//table//text()[normalize-space(.)="Room Description"]/ancestor::tr[contains(.,"Room Cost")]/following-sibling::tr');
        $this->logger->debug("Found {$days->length} days found");
        $r = $h->addRoom();

        $rates = [];

        foreach ($days as $day) {
            $r->setDescription($this->http->FindSingleNode('./td[2]', $day));
            $rates[] = $this->http->FindSingleNode('./td[3]', $day);
        }
        $r->setRates($rates);

        $h->price()->cost($this->http->FindSingleNode('//th[contains(text(),"Subtotal:")]/following-sibling::td', null, false, '/\$([\d.,]+)/'));
        // $558.57**
        $h->price()->total($this->http->FindSingleNode('//th[contains(text(),"Estimated Total:")]/following-sibling::td', null, false, '/\$([\d.,]+)\*\*/'));
        $h->price()->tax($this->http->FindSingleNode('//th[contains(text(),"Estimated Taxes and Fees:")]/following-sibling::td', null, false, '/\$([\d.,]+)/'));

        if ($currency = $this->http->FindSingleNode('//th[contains(text(),"Estimated Total:")]/following-sibling::td', null, false, '/(\$)[\d.,]+/')) {
            $h->price()->currency($currency);
        }
        $this->logger->debug('Parsed Hotel:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[@id = 'logout-link']")
            && !$this->http->FindPreg('/login/', false, $this->http->currentUrl())
        ) {
            return true;
        }

        return false;
    }
}
