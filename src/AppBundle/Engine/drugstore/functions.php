<?php

class TAccountCheckerDrugstore extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.drugstore.com/user/login.asp");

        if (!$this->http->ParseForm("frmLogin")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("txtEmail", $this->AccountFields['Login']);
        $this->http->SetInputValue("txtPassword", $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        // Message: "Our site is currently experiencing technical issues."
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'is currently experiencing technical issues')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//img[contains(@alt, 'Sorry for the inconvenience. Check back, we plan to be up and running by Sunday')]/@alt")) {
            throw new CheckException("We're down for planned maintenance.", ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode("//a[contains(@href, 'logoff')]/@href")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'Please take another look. We need you to fix the areas highlighted in')]")) {
            throw new CheckException("Your e-mail address or password is incorrect", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'signin status')]/text()")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/((Your|The) e-mail address and\/or password (is|are) incorrect)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Oops! There ís been an error
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Oops! Thereís been an error')]")) {
            throw new CheckException("Oops! There ís been an error", ACCOUNT_PROVIDER_ERROR);
        }

        // TODO: error text isn't found
        if ($this->http->currentUrl() == 'http://www.drugstore.com/morestores.asp'
            && $this->http->ParseForm("survey")) {
            throw new CheckException("Your e-mail address or password is incorrect", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->SetProperty("FirstName", $this->http->FindPreg("/>welcome(?:\&nbsp;)([^<:]+)/ims"));
        //$this->SetProperty("Items", $this->http->FindSingleNode("//a[@title = 'shopping bag']/text()"));
        //$this->http->FindSingleNode("//span[contains(@class, 'gndsShoppingBagDisplay')]/text()");

        // Balance
        $find = $this->http->FindPreg("/You've earned\s*<[^>]+>([^<]+)/ims");

        if (isset($find)) {
            // Remove the $ symbol
            $find = preg_replace("/^.(.*)/", "\\1", $find);
            // Transformation of the balance in the number
            $find = $find + 0;
            $this->SetBalance($find);

            // Date expiration
            if ($this->http->FindPreg("/You can spend your dollars[^\-]*\s*[-]+\s*([^<]+)/ims")) {
                $expire = $this->http->FindPreg("/You can spend your dollars[^\-]*\s*[-]+\s*([^<]+)/ims");
            } elseif ($this->http->FindPreg("/The redemption period ends ([^\.]+)/ims")) {
                $expire = $this->http->FindPreg("/The redemption period ends ([^\.]+)/ims");
            }

            if (isset($expire)) {
                $expire = strtotime($expire);

                if ($expire !== false) {
                    $this->SetExpirationDate($expire);
                }
            }
        } else {
            $this->SetBalanceNA();
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.drugstore.com/user/login.asp';
        $arg['SuccessURL'] = 'https://www.drugstore.com/user/modify_account.asp';

        return $arg;
    }

    public function GetExtensionFinalURL(array $fields)
    {
        return 'https://www.drugstore.com/user/modify_account.asp';
    }
}
