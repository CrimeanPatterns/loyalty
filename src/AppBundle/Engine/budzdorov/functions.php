<?php

class TAccountCheckerBudzdorov extends TAccountChecker
{
    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = [
            ""      => "Select your login type",
            "card"  => "Card #",
            "email" => "E-mail",
            "phone" => "Mobile #",
        ];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        //		$this->http->LogHeaders = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.budzdorov.ru/customer/account/");
        //		$this->http->GetURL("http://www.budzdorov.ru/login/");

        if (!$this->http->ParseForm("login-form")) {
            return false;
        }

        $login = str_replace(' ', '', $this->AccountFields['Login']);

        if (strpos($login, '@') === false) {
            $login = str_replace('-', '', $login);
        }

        /*if ($this->AccountFields['Login2'] == 'card') {
            $login = preg_replace("#^(\d)(\d{6})(\d{6})$#", '$1 $2 $3', $login);
            $this->http->SetInputValue("card", $login);
            $this->http->SetInputValue("login-way", "card");
            $this->logger->debug("[card] " . $login);
        } else*/
        if ($this->AccountFields['Login2'] == 'email') {
            $this->http->SetInputValue("login[username]", $login);
            $this->http->SetInputValue("type_login", "#tab-email");
            $this->logger->debug("[email] " . $login);
        } elseif ($this->AccountFields['Login2'] == 'phone') {
            $login = substr($login, -10);
            $login = preg_replace("#^(\d{3})(\d{3})(\d{2})(\d{2})$#", '+7 ($1) $2-$3-$4', $login);
            $this->http->SetInputValue("login[telephone]", $login);
            $this->http->SetInputValue("type_login", "#tab-phone");
            $this->logger->debug("[phone] " . $login);
        } else {
            return false;
        } //actually never should be

        $this->http->SetInputValue("login[password]", $this->AccountFields['Pass']);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://bp.budzdorov.ru/";
        $arg["SuccessURL"] = "http://bp.budzdorov.ru/account/";

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        $messages = $this->http->getCookieByName('mage-messages');
        $response = $this->http->JsonLog(urldecode($messages));

        if (isset($response[0]->text, $response[0]->type) && $response[0]->text == 'Неправильный номер телефона или пароль.') {
            throw new CheckException($response[0]->text, ACCOUNT_INVALID_PASSWORD);
        }

        return true;
    }

    public function Parse()
    {
        $this->http->GetURL("http://bp.budzdorov.ru/account");
        // Balance
        $this->SetBalance($this->http->FindSingleNode("//*[text()='Ваш баланс:']/following-sibling::*[1]"));
        // CardNumber
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//*[text()='Номер карты:']/following-sibling::*[1]"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//*[starts-with(text(),'Имя')]/following::input[1]/@value") . ' ' . $this->http->FindSingleNode("//*[starts-with(text(),'Отчество')]/following::input[1]/@value")));

        if (empty($this->http->FindSingleNode("//text()[normalize-space()='Операций не было']"))) {
            $lastActivity = $this->http->FindSingleNode("//div[@class='panel table']/descendant::text()[normalize-space(.)='Дата и время']/following::tr[1]/td[1]");
            $this->logger->notice('last activity: ' . $lastActivity);
            $lastActivity = strtotime($lastActivity);
            $exp = strtotime("+1 year", $lastActivity);

            if ($exp) {
                $this->SetExpirationDate($exp);
            }
        }
    }
}
