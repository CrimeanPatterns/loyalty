<?php

class TAccountCheckerAustral extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://mycapricorne.com/cwa/web/cff-portal/login");

        if (!$this->http->ParseForm('authForm')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("job", "AUTH_LOGIN");

        return true;
    }

    public function checkErrors()
    {
        // Service Temporarily Unavailable
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // The server is temporarily unable to service your request due to maintenance downtime or capacity problems.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The server is temporarily unable to service your request due to maintenance downtime or capacity problems.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function sendXML($url, $xml)
    {
        // Set content type
        $this->http->setDefaultHeader('Content-Type', 'text/xml; charset=UTF-8');

        // Add some cookies
        $this->http->setCookie("UserId", $this->AccountFields['Login']);
        $this->http->setCookie("Language", 'en', 'aust.loyaltyplus.aero');

        // post our form with xml
        if ($r = $this->http->PostURL($url, $xml)) {
            // clear namespaces to make xpath work correctly
            $this->http->SetBody($this->http->Response['body'] = preg_replace("#\b(ns|xmlns)\b=\"[^\"]+\"#i", '', $this->http->Response['body']));
        }

        return $r;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        //		// to find out if are logged-in we should check admin area
        //		$this->http->GetURL('https://aust.loyaltyplus.aero/Capricorne/liveapp');
//
        //		// prepare xml (javascript job on front-end)
        //		$this->sendXML("https://aust.loyaltyplus.aero/Capricorne/liveapp?serviceId=Echo.Synchronize",
        //			// xml
        //			'<client-message xmlns="http://www.nextapp.com/products/echo2/climsg" type="initialize">
        //				<message-part xmlns="" processor="EchoClientAnalyzer">
        //					<property type="text" name="navigatorAppName" value="Netscape"></property>
        //					<property type="text" name="navigatorAppVersion" value="5.0 (Windows)"></property>
        //					<property type="text" name="navigatorAppCodeName" value="Mozilla"></property>
        //					<property type="boolean" name="navigatorCookieEnabled" value="true"></property>
        //					<property type="boolean" name="navigatorJavaEnabled" value="false"></property>
        //					<property type="text" name="navigatorLanguage" value="ru-RU"></property>
        //					<property type="text" name="navigatorPlatform" value="Win32"></property>
        //					<property type="text" name="navigatorUserAgent" value="Mozilla/5.0 (Windows NT 6.1; WOW64; rv:24.0) Gecko/20100101 Firefox/24.0"></property>
        //					<property type="integer" name="screenWidth" value="1366"></property>
        //					<property type="integer" name="screenHeight" value="768"></property>
        //					<property type="integer" name="screenColorDepth" value="24"></property>
        //					<property type="integer" name="utcOffset" value="240"></property>
        //				</message-part>
        //			</client-message>
        //		');

        // the way to check authorization
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }
        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//span[@id = 'authForm.errors']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($english = $this->http->FindSingleNode("//a[contains(@href, 'languageId=en_US')]/@href")) {
            $this->http->Log("Switch to english");
            $this->http->NormalizeURL($english);
            $this->http->GetURL($english);
        }
        // Balance - Total Available Points
        $this->SetBalance($this->http->FindSingleNode("//h3[normalize-space(text()) = 'Solde' or normalize-space(text()) = 'Balance']/following-sibling::span[1]"));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//p[contains(text(), 'Bonjour') or contains(text(), 'Hello')]/b")));
        // Card number
        $this->SetProperty('MembershipID', $this->http->FindSingleNode("//div[contains(text(), 'Card number') or contains(text(), 'Numéro de carte')]/following-sibling::div[1]"));
        // Status
        if ($status = $this->http->FindSingleNode("//img[@class = 'statusLabel']/@src")) {
            $status = basename($status);
            $this->logger->debug(">>> Status " . $status);

            switch ($status) {
                case 'CAPR.png':
                    $this->SetProperty('CurrentTier', "Essentiel");

                    break;

                case 'CAPI.png':
                    $this->SetProperty('CurrentTier', "Abonné");

                    break;

                case 'PREI.png':
                    $this->SetProperty('CurrentTier', "Exclusive");

                    break;

                case 'PREM.png':
                    $this->SetProperty('CurrentTier', "Premium");

                    break;

                default:
                    $this->ArchiveLogs = true;
                    $this->sendNotification("austral: newStatus: $status");
            }// switch ($status)
        }// if ($status = $this->http->FindSingleNode("//img[@class = 'statusLabel']/@src"))
        // Status expiration
        $this->SetProperty('StatusExpiration', $this->http->FindSingleNode("//p[contains(text(), 'Your are currently')]", null, true, "/until\s+([^<]+)/"));
        // Points
        $this->SetProperty('Points', $this->http->FindSingleNode("//td[contains(text(), 'Points')]/following-sibling::td[1]"));
        // Club Austral class
        $this->SetProperty('ClubAustralClass', $this->http->FindSingleNode("//td[contains(text(), 'Club Austral class') or contains(text(), 'Classe Club Austral')]/following-sibling::td[1]"));
        // Confort class
        $this->SetProperty('ConfortClass', $this->http->FindSingleNode("//td[contains(text(), 'Confort class') or contains(text(), 'Classe Confort')]/following-sibling::td[1]"));
        // Loisirs class
        $this->SetProperty('LoisirsClass', $this->http->FindSingleNode("//td[contains(text(), 'Loisirs class') or contains(text(), 'Classe Loisirs')]/following-sibling::td[1]"));
        // Available vouchers
        $this->SetProperty('AvailableVouchers', $this->http->FindSingleNode('//div[contains(text(), "Vouchers available") or contains(text(), "Bons d\'échange disponibles")]/following-sibling::div[1]'));
        // Member Since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode('//p[contains(text(), "Member since") or contains(text(), "Membre depuis le")]', null, true, "/e\s+([^<]+)/ims"));
        // Expiration date
        if ($exp = $this->http->FindSingleNode('//div[contains(text(), "Prochaine date d\'expiration") or contains(text(), "Next expiration date")]/following-sibling::div[1]')) {
            $exp = $this->ModifyDateFormat($exp);

            if ($exp = strtotime($exp)) {
                $this->SetExpirationDate($exp);
            }
        }
        // My Vouchers
        $this->http->GetURL("https://mycapricorne.com/cwa/group/cff-portal/my-vouchers");
        $vouchers = $this->http->XPath->query("//div[@class = 'my-vouchers-portlet']//table//tr[td]");
        $this->logger->debug("Total {$vouchers->length} vouchers were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($vouchers as $voucher) {
            $status = $this->http->FindSingleNode('td[@data-title = "Status"]', $voucher);
            $exp = $this->http->FindSingleNode('td[@data-title = "Valid To"]', $voucher);
            $description = $this->http->FindSingleNode('td[@data-title = "Description"]', $voucher);

            if (strtolower($status) == 'active') {
                $subAccount = [
                    "Code"        => 'australVoucher' . $description,
                    "DisplayName" => "Voucher {$description}",
                    "Balance"     => null,
                ];

                if ($exp = strtotime($exp)) {
                    $subAccount['ExpirationDate'] = $exp;
                }
                $this->AddSubAccount($subAccount, true);
            }// if ($offerStatus == 'R')
        }// foreach ($vouchers as $voucher)
    }
}
