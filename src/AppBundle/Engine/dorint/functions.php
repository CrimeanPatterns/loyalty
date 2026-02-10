<?php

class TAccountCheckerDorint extends TAccountChecker
{
    private $history = [];

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://dorintcard.dorint.com/loyalty");

        if (!$this->http->ParseForm("login-form")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // switch to english
        if ($this->http->FindSingleNode("//a[contains(text(), 'Abmelden')]")) {
            $this->http->GetURL("https://dorintservice.dorint.com/centralmember/profile_overview.aspx?language=E");
        }
        // Login successful
        if ($this->http->FindSingleNode("//a[contains(text(), 'Logout')]")) {
            return true;
        }
        // Login successful
        if ($this->http->FindSingleNode("//a[contains(text(), 'Log Out')]")) {
            return true;
        }
        // The password you entered is incorrect. Please try again
        if ($error = $this->http->FindSingleNode('//li[contains(text(), "The password you entered is incorrect. Please try again")]')) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid credentials
        if ($error = $this->http->FindSingleNode("//label[contains(text(), 'Your Member Card / Login ID could not be found')]")) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }
        // Your password does not match
        if ($error = $this->http->FindSingleNode("//label[contains(text(), 'Your password does not match')]")) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }
        // The email address for your account could not be activated. Please contact the customer service.
        if ($error = $this->http->FindSingleNode("//label[contains(text(), 'The email address for your account could not be activated.')]")) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }
        // Unable to log in - please try again later.
        if ($error = $this->http->FindSingleNode("//label[contains(text(), 'Unable to log in - please try again later.')]")) {
            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
        }
        // Terms and Conditions
        if ($message = $this->http->FindPreg("/(?:We request that you please register your acceptance of the Conditions of Participation and the Data Protection Regulations\.|You need to accept the terms and conditions to proceed\. \(Custom\)|Sie müssen die AGBs akzeptieren um fortzufahren\. \(Custom\))/ims")) {
            $this->throwAcceptTermsMessageException();
        }
        // We are glad to confirm your profile. Please choose a new password in order to complete the registration
        if ($message = $this->http->FindPreg("/(We are glad to confirm your profile\.\s*Please choose a new password in order to complete the registration)/ims")) {
            throw new CheckException("Dorint (Card) website is asking you to choose a new password, until you do so we would not be able to retrieve your account information", ACCOUNT_PROVIDER_ERROR);
        }/*review*/
        // An error occured.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'An error occured.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//header[@id = "header"]//div[contains(@class, "mem-name")]/text()[1]')));
        // Customer number
        $this->SetProperty("CustomerNumber", $this->http->FindSingleNode('//header[@id = "header"]//span[contains(@class, "number")]'));
        // Balance - Points balance
        $this->SetBalance($this->http->FindSingleNode('//header[@id = "header"]//span[contains(@class, "points")]', null, true, '/([\d\.\,]+)/ims'));
        // Member since
        $memberSince = $this->http->FindSingleNode('//div[contains(@class, "user-greeting")]', null, true, '/a loyal member since (.+?),/');
        $this->SetProperty("MemberSince", $memberSince);

        $this->setExpDate();
    }

    public function ParseHistory($startDate = null)
    {
        if ($this->history) {
            return $this->history;
        }

        $this->http->GetURL('https://dorintcard.dorint.com/loyalty/overview/account-statement');
        $transactions = $this->http->XPath->query('//div[@id = "redeemed-points"]//tbody//tr') ?? [];

        $res = [];

        foreach ($transactions as $node) {
            $row = [];
            $bookingDate = $this->http->FindSingleNode('./td[1]', $node);
            $bookingDate = str_replace('/', '.', $bookingDate);
            $date = strtotime($bookingDate);

            $row['Date'] = $date;
            $row['Reason'] = $this->http->FindSingleNode('./td[2]', $node);
            $row['Description'] = $this->http->FindSingleNode('./td[3]', $node);
            $points = $this->http->FindSingleNode('./td[4]', $node);
            $points = str_replace('.', '', $points);
            $row['Points'] = is_numeric($points) ? floatval($points) : null;
            $res[] = $row;
        }

        return $res;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"        => "PostingDate",
            "Reason"      => "Info",
            "Description" => "Description",
            "Points"      => "Miles",
        ];
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Our member area is temporarily not available for maintenance reasons. Please try again later.
        if ($message = $this->http->FindPreg("/Our member area is temporarily not available for maintenance reasons\.\s*Please try again later\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'System maintenance underway')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/centralmember' Application.
        if ($message = $this->http->FindPreg("/Server Error in '\/centralmember' Application\./")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function setExpDate(): bool
    {
        $this->logger->notice(__METHOD__);
        // find when balance was zero
        if (!$this->Balance) {
            return false;
        }
        $balance = str_replace('.', '', $this->Balance);
        $balance = floatval($balance);
        $history = $this->ParseHistory();
        $this->history = $history;
        $expiringBalance = $balance;

        for ($i = 0; $i < count($history); $i++) {
            if ($balance <= 0) {
                break;
            }
            $points = $history[$i]['Points'];

            if (!$points || $points <= 0) {
                continue;
            }
            $expiringBalance = $balance;
            $balance -= $points;
        }
        $zeroBalanceIndex = $i - 1;
        $this->logger->info("zero balance transaction: {$history[$zeroBalanceIndex]['Date']}");

        for ($i = $zeroBalanceIndex; $i >= 0; $i--) {
            $points = $history[$i]['Points'];

            if (!$points || $points <= 0) {
                continue;
            }

            $date = $history[$i]['Date'];

            if (!$date) {
                $this->sendNotification('check exp date // MI');

                break;
            }
            // +36 months
            $date = strtotime('+3 years', $date);
            // next quarter
            $month = intval(date('m', $date));
            $year = intval(date('Y', $date));

            if ($month <= 3) {
                $date = strtotime("30.06.{$year}");
            } elseif ($month <= 6) {
                $date = strtotime("30.09.{$year}");
            } elseif ($month <= 9) {
                $date = strtotime("31.12.{$year}");
            } else {
                $year++;
                $date = strtotime("31.03.{$year}");
            }
            // covid, https://redmine.awardwallet.com/issues/3176#note-24
            if (in_array(intval(date('Y', $date)), [2020, 2021])) {
                $date = strtotime('31.12.2021');
            }

            if ($date > strtotime('now')) {
                $this->SetExpirationDate($date);
                $this->SetProperty('ExpiringBalance', intval($expiringBalance));
                $earningDate = date('d.m.Y', $history[$i]['Date']);
                $this->SetProperty('EarningDate', $earningDate);

                return true;
            }
        }

        return false;
    }
}
