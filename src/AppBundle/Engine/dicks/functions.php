<?php

// todo: looks like as golf

class TAccountCheckerDicks extends TAccountChecker
{
    private $headers = [
        "Accept"          => "application/json, text/plain, */*",
        "Accept-Encoding" => "gzip, deflate, br",
        "Content-Type"    => "application/json",
        "x-apikey"        => "SgqA9WFTm7EamEfL1EwP3ObfFmau6ctZ",
        "Origin"          => "https://www.dickssportinggoods.com",
    ];

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerSeleniumDicks.php";

        return new TAccountCheckerSeleniumDicks();
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'dicksAvailableRewards')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "");
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://www.dickssportinggoods.com/LogonForm");
//        if (!$this->http->ParseForm("login-form")) {
        $sensorPostUrl = $this->http->FindPreg('/src="(\/assets\/\w+)"/');

        if (!$sensorPostUrl) {
            return $this->checkErrors();
        }

        if ($sensorPostUrl) {
            sleep(1);
            $this->http->RetryCount = 0;
            $sensorDataHeaders = [
                "Accept"       => "*/*",
                "Content-type" => "application/json",
                "Origin"       => "https://www.dickssportinggoods.com",
            ];
            $sensorData = [
                'sensor_data' => "7a74G7m23Vrp0o5c9021631.43-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:71.0) Gecko/20100101 Firefox/71.0,uaend,11059,20100101,en-US,Gecko,1,0,0,0,387930,9665645,1440,829,1440,900,1440,363,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6000,0.312605507156,788324832822,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.dickssportinggoods.com/LogonForm-1,2,-94,-115,1,1,0,0,0,0,0,3,0,1576649665644,-999999,16866,0,0,2811,0,0,6,0,0,B97D2FE5A65CF438F9B315BCDA62AD6C~-1~YAAQuQcPFwhxDQBvAQAASqikFwNp4nf9Hp0bJHNRdGprTer2i8CNrUl8uAoDyspx7BggiEXL+j2N8xhK2JxJL3Ku4k8iIJ2jk5vy9NYkLThtnMeplxafoXghKFHuMB2/J8Zsp3uyX5Zs7VFSt9mhUUdA2xoopFaeWokmeJOFClK/hbpGDF57X2rPxu+eamSoQsVmYqmBlTHDBGg3tBV5cDHBQqr7AheUejQEJw6VLiJn3Irc4iynoISE3ED36je3HOqWujcRsKrzlw5aZqmObwyTMmkSRGoTPBuLDC0qIqDhwNuj2M/EEfNuzLEWOcWlc4W1nxUo~-1~-1~-1,31700,-1,-1,26067385-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,48328247-1,2,-94,-118,74454-1,2,-94,-121,;3;-1;0",
                //                'sensor_data' => null,
            ];
            $this->http->NormalizeURL($sensorPostUrl);
            $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
            $this->http->JsonLog();
            $this->http->RetryCount = 2;
            sleep(1);

            $sensorData = [
                'sensor_data' => "7a74G7m23Vrp0o5c9021631.43-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:71.0) Gecko/20100101 Firefox/71.0,uaend,11059,20100101,en-US,Gecko,1,0,0,0,387930,9665645,1440,829,1440,900,1440,363,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6000,0.04275673221,788324832822,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,0,-1,0,0,1429,630,0;0,-1,0,0,1125,-1,0;1,-1,0,0,1488,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,3,24;-1,2,-94,-112,https://www.dickssportinggoods.com/LogonForm-1,2,-94,-115,1,1,0,0,0,0,0,299,0,1576649665644,5,16866,0,0,2811,0,0,300,0,0,B97D2FE5A65CF438F9B315BCDA62AD6C~-1~YAAQuQcPFwlxDQBvAQAA3aykFwPQ9IbYhyyhRDxA7udNOrHofwzM+Zxwc80zh6S5OYBmB6A0ylyvtl0anWJaWMQZPw0JgopNQx/SoXLRX7kVz/5AMxlTx8oHmAWygIYCcL8cBKmGd4REqBUZx+eVRpOO18+zYoxAFuuetpEeh2SZtIUJB7Da5Q5JUauGMlHnmHnGjXYr4CK8ovy5F/yBQBgGXPzCZAcNcJLe00NhRE76fTOaiYipmQE2JcnhMNaY2y63quHr9oa9urWeGEsrKyRMbG9+opw4qsT4XzSoKg6KYBUo6/l3yvcP4x8QiRNfUjOX7sRzZtfrBnm8H2UKkc2co3M=~-1~-1~-1,33097,377,-649592004,26067385-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-70,1241107008;dis;,3;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,4835-1,2,-94,-116,48328247-1,2,-94,-118,79230-1,2,-94,-121,;1;8;0",
                //                'sensor_data' => null,
            ];
            $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
            $this->http->JsonLog();
            $this->http->RetryCount = 2;
            sleep(1);
        } else {
            $this->logger->error("sensor_data URL not found");
        }

        $data = [
            'Username' => $this->AccountFields['Login'],
            'Password' => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.dickssportinggoods.com/myaccount/services/redirectingservice/logonservice/v1/auth/dsg/login", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        // Access is allowed
        if (isset($response->content->userId) || $this->http->FindSingleNode('//a[contains(text(), "Sign Out")]')) {
            return $this->loginSuccessful();
        }
        $message = $response->errors[0]->errorMessage ?? null;

        if ($message) {
            $this->logger->error($message);

            if (
                $message == 'The specified logon ID or password are not correct. Verify the information provided and log in again.'
            ) {
                throw new CheckException("To sign in, please enter a valid email address and password.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'The user account is disabled. Contact your site administrator regarding your access.'
            ) {
                throw new CheckException("You have exceeded the number of password attempts. Please reset your password.", ACCOUNT_LOCKOUT);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, false);
        // ScoreCard Member
        $this->SetProperty("ScoreCardNumber", $response->loyaltyNumber ?? null);
        // Name
        $this->SetProperty("Name", beautifulName($response->name ?? null));

        $this->http->GetURL("https://www.dickssportinggoods.com/myaccount/services/redirectingservice/pointservice/v1/pointSummary/dsg");
        $response = $this->http->JsonLog();
        // Available Rewards
        $this->SetProperty("AvailableRewards", "$" . floatval($response->rewardAmount));
        //# My ScoreCard Balance
        if (isset($response->currentPointBalance)) {
            $this->SetBalance(floor($response->currentPointBalance));
        }

        // Available Rewards
        if ($response->rewardAmount > 0) {
            $this->logger->info('Available Rewards', ['Header' => 3]);
            $this->SetProperty("CombineSubAccounts", false);
            $this->http->GetURL("https://www.dickssportinggoods.com/myaccount/services/redirectingservice/athleteservice/v1/reward/dsg?activeOnly=true");
            $rewards = $this->http->JsonLog(null, true, true);

            foreach ($rewards as $reward) {
                // Expiration Date
                $expirationDate = ArrayVal($reward, 'expirationDate', null);
                // Online Code
                $code = ArrayVal($reward, 'onlineCode', null);
                $redeemed = ArrayVal($reward, 'redeemed', null);
                $active = ArrayVal($reward, 'active', null);
                $balance = ArrayVal($reward, 'amount', null);

                if (isset($code, $balance) && ($exp = strtotime($expirationDate)) && $active == true && $redeemed == false) {
                    $this->AddSubAccount([
                        'Code'           => 'dicksAvailableRewards' . $code,
                        'DisplayName'    => "Reward {$code}",
                        'Balance'        => $balance,
                        'ExpirationDate' => $exp,
                        'BarCode'        => ArrayVal($reward, 'storeCode', null),
                        "BarCodeType"    => BAR_CODE_CODE_128,
                    ]);
                }// if (isset($code) && ($exp = strtotime($expirationDate)) && $active == true && $redeemed == false)
            }// foreach ($discounts as $discount)
        }// if ($response->rewardAmount > 0)

        //# Expiration Date  // refs #6115
        if ($this->Balance <= 0) {
            return;
        }
        $points = $this->Balance;
        $this->logger->info('Expiration Date', ['Header' => 3]);
        $this->http->GetURL("https://www.dickssportinggoods.com/MyPointsHistoryView?catalogId=12301&langId=-1&storeId=15108");
        $transactions = $this->http->XPath->query('//table[contains(@class, "points-details-table")]//tr[td]');
        $this->logger->debug("Total {$transactions->length} transactions were found");

        for ($i = 0; $i < $transactions->length; $i++) {
            $historyPoints = $this->http->FindSingleNode('td[contains(@class, "points-pointsAdded")]//div[contains(@class, "points-pointsAdded-text")]', $transactions->item($i));
            $historyPoints = str_replace(',', '', $historyPoints);
            $this->logger->debug("Node # $i - Balance: $points, HistoryPoints: $historyPoints");

            if ($historyPoints > 0) {
                $points -= $historyPoints;
            }
            $this->logger->debug("Node # $i - Balance: $points / round: " . round($points, 2));

            if ($points <= 0) {
                $date = $this->http->FindSingleNode('td[contains(@class, "points-date")]//div[contains(@class, "points-date-text")]', $transactions->item($i));

                if (isset($date)) {
                    $this->SetProperty("EarningDate", $date);
                    $this->SetExpirationDate(strtotime("+1 year", strtotime($date)));
                }
                // Expiring balance
                $this->SetProperty("ExpiringBalance", ($points + $historyPoints));

                break;
            }// if ($points <= 0)
        }// for ($i = 0; $i < $historyPoints->length; $i++)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.dickssportinggoods.com/myaccount/services/redirectingservice/athleteservice/v1/loyaltymetadata", [], 20);
        $this->http->GetURL("https://www.dickssportinggoods.com/myaccount/services/redirectingservice/athleteservice/v1/athlete/personalinformation/dsg", [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!isset($response->loyaltyNumber)) {
            $this->logger->error("card not found");

            return false;
        }

        return true;
    }
}
