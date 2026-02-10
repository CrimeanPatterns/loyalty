<?php

class TAccountCheckerEtihadMobile extends TAccountCheckerEtihad
{
    protected $collectedHistory = false;

    private $retry = 0;

    public function LoadLoginForm()
    {
        $this->http->LogHeaders = true;

        if (strlen($this->AccountFields['Login']) == 1 && strlen($this->AccountFields['Pass']) == 1) {
            throw new CheckException('Username/Etihad Guest number and password do not match', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
//        https://mbooking.etihad.com/SSW2010/EYM0/#checkin/e1s1?execution=e1s1
        $this->http->GetURL("https://m.etihad.com/en/login/?id=6819&epslanguage=en");

        if (!$this->http->ParseForm("aspnetForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('GuestLoginForm_Username', $this->AccountFields['Login']);
        $this->http->SetInputValue('GuestLoginForm_Password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('GuestLoginForm_submit', "");

        return true;
    }

    public function checkErrors()
    {
        // The Etihad Airways website is currently unavailable due to planned maintenance works
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'The Etihad Airways website is currently unavailable due to planned maintenance works.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }
        // Username/Etihad Guest number and password do not match
        if ($message = $this->http->FindSingleNode("(//label[contains(text(), 'Username/Etihad Guest number and password do not match')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Email not yet verified
        if ($message = $this->http->FindSingleNode("(//label[contains(text(), 'Email not yet verified')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // retries
        if ($this->http->FindSingleNode("//h3[contains(text(), 'An error has occured')]")
            && $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->Response['code'] == 200 && $this->retry < 3) {
            $sleep = 7;
            sleep($sleep);
            $this->retry++;
            $this->http->Log("[Retry {$this->retry}]: waiting {$sleep} seconds");

            if ($this->LoadLoginForm()) {
                if ($this->Login()) {
                    $this->Parse();
                }
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Current Tier level
        $this->SetProperty("TierLevel", $this->http->FindSingleNode("//h2[contains(text(), 'Current tier level:')]/span"));
        $this->SetBalance($this->http->FindSingleNode("//span[@class = 'miles']"));

        $this->http->GetURL("https://m.etihad.com/en/etihad-guest/account-summary/");
        // Balance - Total Etihad Guest Miles
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'Total Etihad Guest Miles')]/strong"));
        // Etihad Guest number
        $this->SetProperty("Number", $this->http->FindSingleNode("//h4[contains(text(), 'Etihad Guest number:')]/following-sibling::p"));
        // Current Tier level
        $this->SetProperty("TierLevel", $this->http->FindSingleNode("//h4[contains(text(), 'Current tier level:')]/following-sibling::p"));
        // Guest Tier Miles
        $this->SetProperty("TierMiles", $this->http->FindSingleNode("//strong[contains(text(), 'Guest Tier Mile')]", null, true, '/(.+)\s+Guest/ims'));
        // Miles needed to next tier
        $this->SetProperty("TierMilesNeed", $this->http->FindSingleNode("(//small[contains(text(), 'tier you need:')]/following-sibling::p)[1]", null, true, "/([\d\.\,]+)/ims"));
        // Guest Tier Segments
        $this->SetProperty("TierSegments", $this->http->FindSingleNode("//strong[contains(text(), 'Guest Tier Segment')]", null, true, "/([\d\.\,]+)/ims"));
        // Segments needed to next tier
        $this->SetProperty("TierSegmentsNeed", $this->http->FindSingleNode("(//small[contains(text(), 'tier you need:')]/following-sibling::p)[1]", null, true, "/([\d\.\,]+)/ims"));
        // Mileage expiry
        $expirationNodes = $this->http->XPath->query("//th[contains(text(), 'Expire')]/ancestor::table/tbody/tr");
        $this->http->Log("Found {$expirationNodes->length} expiration nodes");

        for ($i = 0; $i < $expirationNodes->length; $i++) {
            $expireMiles = $this->http->FindSingleNode("td[1]", $expirationNodes->item($i));
            $expireDate = $this->http->FindSingleNode("td[2]", $expirationNodes->item($i));
            $this->http->Log("$expireDate -> $expireMiles");

            if (isset($expireDate) && isset($expireMiles) && ($expireMiles != "0")) {
                $d = strtotime($expireDate);

                if ($d !== false) {
                    $this->SetExpirationDate($d);
                    $this->SetProperty("MilesToExpire", $expireMiles);

                    break;
                }// if ($d !== false)
            }// if (isset($expireDate) && isset($expireMiles) && ($expireMiles != "0"))
        }// for ($i = 0; $i < $expirationNodes->length; $i++)

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@class = 'name']")));
    }

    public function ParseHistory($startDate = null)
    {
        $this->http->Log('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $result = [];
        $startTimer = microtime(true);

        if ($this->http->currentUrl() != 'https://m.etihad.com/en/etihad-guest/account-summary/') {
            $this->http->GetURL("https://m.etihad.com/en/etihad-guest/account-summary/");
        }

        $page = 0;

        if ($this->http->ParseForm("aspnetForm")) {
            $this->http->SetInputValue("TransactionForm_EndDate", date("d/m/Y"));
            $this->http->SetInputValue("TransactionForm_StartDate", date("d/m/Y", strtotime("-3 year")));
            $this->http->SetInputValue("webform", "formtransaction");
            $this->http->PostForm();
        }
        $page++;
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
        $this->http->Log("[Page: {$page}]");

        $this->http->Log("[Time parsing: " . (microtime(true) - $startTimer) . "]");

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->XPath->query("//th[contains(text(), 'Activity')]/ancestor::table/tbody/tr");
        $this->http->Log("Found {$nodes->length} history items");

        for ($i = 0; $i < $nodes->length; $i++) {
            $dateStr = $this->http->FindSingleNode("td[1]", $nodes->item($i));
            $dateStr = $this->ModifyDateFormat($dateStr);
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->http->Log("break at date {$dateStr} ($postDate)");

                break;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Activity'] = $this->http->FindSingleNode("td[2]", $nodes->item($i));

            if (preg_match('/Bonus/ims', $result[$startIndex]['Activity'])) {
                $result[$startIndex]['Etihad Bonus Miles'] = $this->http->FindSingleNode("td[3]", $nodes->item($i));
            } else {
                $result[$startIndex]['Etihad Guest Miles'] = $this->http->FindSingleNode("td[3]", $nodes->item($i));
            }
            $result[$startIndex]['Etihad Guest Tier Miles'] = $this->http->FindSingleNode("td[4]", $nodes->item($i));
            $startIndex++;
        }// for ($i = 0; $i < $nodes->length; $i++)

        return $result;
    }
}
