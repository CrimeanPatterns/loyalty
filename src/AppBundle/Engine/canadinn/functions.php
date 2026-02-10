<?php

class TAccountCheckerCanadinn extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->http->GetURL("http://www.merchantsales.ca/accounts/default.asp");

        if (!$this->http->ParseForm("frmMain")) {
            return false;
        }

        $this->http->Form["currentError"] = '';
        $this->http->Form["fldUsername01"] = '';
        $this->http->Form["fldPassword01"] = '';
        $this->http->Form["btnLogin"] = 'I Agree';

        $this->http->setDefaultHeader('Host', 'www.merchantsales.ca');
        $this->http->setDefaultHeader('User-Agent', 'Mozilla/5.0 (Windows NT 6.1; rv:6.0.2) Gecko/20100101 Firefox/6.0.2');
        $this->http->setDefaultHeader('Accept', '*/*');
        $this->http->setDefaultHeader('Accept-Language', 'ru-ru,ru;q=0.8,en-us;q=0.5,en;q=0.3');
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate');
        $this->http->setDefaultHeader('Accept-Charset', 'windows-1251,utf-8;q=0.7,*;q=0.7');
        $this->http->setDefaultHeader('Connection', 'keep-alive');
        $this->http->setDefaultHeader('Referer', 'http://www.merchantsales.ca/accounts/searchpage1.asp');

        $this->http->PostForm();

        // $this->http->GetURL("http://www.merchantsales.ca/accounts/searchpage1.asp");
        if (!$this->http->ParseForm("frmMain")) {
            return false;
        }
        $this->http->Form["InWindow"] = '';
        $this->http->Form["table500"] = 'Accounts';
        $this->http->Form["frmname500"] = '1';
        $this->http->Form["LineIDIndex507"] = '[1AccountNumber]';
        $this->http->Form["currentError"] = '';
        $this->http->Form["DisplayName"] = 'Account Numbers';
        $this->http->Form["Lookup"] = '';
        $this->http->Form["refFieldName"] = '';
        $this->http->Form["refReturnFieldCode"] = '';
        $this->http->Form["refReturnFieldDesc"] = '';
        $this->http->Form["refUpdateField"] = '';
        $this->http->Form["0001grd101AccountNumberL"] = 'Account Number';
        $this->http->Form["0002grd101CashPurse1R"] = 'Gift Card $';
        $this->http->Form["0003grd101CashPurse2R"] = 'Promo Card $';
        $this->http->Form["0004grd101PointsPurse2R"] = 'Loyalty Points';
        $this->http->Form["0005grd101PointsPurse3R"] = 'Token Points';
        $this->http->Form["0006grd101PointsPurse1R"] = 'Token Points';
        $this->http->Form["ind501AccountNumber1"] = 'AccountNumber1';
        $this->http->Form["Zfld2010001"] = '1';
        $this->http->Form["typefld2010001"] = '1';
        $this->http->Form["dbfld2010001"] = 'AccountNumber';
        $this->http->Form["fld2010001"] = $this->AccountFields['Login'];
        $this->http->Form["Yfld2010001"] = '';
        $this->http->Form["btnSearch"] = 'Search';

        $this->http->PostForm();

        $this->http->GetURL("http://www.merchantsales.ca/accounts/searchdata.asp");

        if (!$this->http->ParseForm("frmCards")) {
            return false;
        }
        $this->http->Form["SQL"] = 'SELECT * FROM [Accounts] WHERE [AccountNumber] = [039SGLQUOT]' . $this->AccountFields['Login'] . '[039SGLQUOT] ORDER BY [AccountNumber]';
        $this->http->Form["sFROM"] = 'FROM [Accounts]';
        $this->http->Form["sWHERE"] = 'WHERE [AccountNumber] = [039SGLQUOT]' . $this->AccountFields['Login'] . '[039SGLQUOT]';
        $this->http->Form["sORDER"] = 'ORDER BY [AccountNumber]';
        $this->http->Form["frmname500"] = '1';
        $this->http->Form["LineIDIndex507"] = '[1AccountNumber]';
        $this->http->PostForm();

        if (!$this->http->ParseForm("frmCSV")) {
            return false;
        }
        $this->http->Form["SQL"] = 'SELECT * FROM [Accounts] WHERE [AccountNumber] = [039SGLQUOT]' . $this->AccountFields['Login'] . '[039SGLQUOT] ORDER BY [AccountNumber]';

        $this->http->PostForm();

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();

        $this->http->GetURL("http://www.merchantsales.ca/accounts/datapage1.asp");

        $access = $this->http->FindSingleNode("//input[contains(@name, 'Close Window')]");

        if (isset($access)) {
            return true;
        }
        $error = $this->http->FindSingleNode("//div[contains(@class, 'signin status')]/text()");

        if (isset($error)) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = $error;

            return false;
        }

        return false;
    }

    public function Parse()
    {
        $this->SetProperty("CardNumber", $this->http->FindPreg("/Card Number\s*<[^>]+>\s*<[^>]+>\s*<[^>]+>\s*<[^>]+>([^<]+)/ims"));

        $find = $this->http->FindSingleNode("//div[contains(@class, 'points')]/text()");

        if ($find != null) {
            $this->SetBalance($find);
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'http://www.merchantsales.ca/accounts/default.asp';
        $arg['SuccessURL'] = 'http://www.merchantsales.ca/accounts/datapage1.asp';

        return $arg;
    }
}
