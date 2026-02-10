<?php

/**
 * Class TAccountCheckerAdvantagecar
 * Display name: Advantage Rent a Car (Instant Rewards)
 * Database ID: 1197
 * Author: YBorisenko
 * Created: 18.05.2015 8:32.
 */
class TAccountCheckerAnymiles extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.anymiles.ru/");

        if (!$this->http->ParseForm(null, 1, true, "//*[contains(@class,'login_block')]/.//form")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('login', $this->AccountFields['Login']);
        $this->http->SetInputValue('pass', $this->AccountFields['Pass']);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://www.anymiles.ru/";

        return $arg;
    }

    public function checkErrors()
    {
        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode("(//a[contains(@href, 'auth/logout')]/@href)[1]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//*[contains(text(),'Ошибка - не верный логин или пароль')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Доступные баллы
        $this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Доступные баллы')]/following-sibling::a/span[contains(@class,'points_current')]"));
        // Бонусные баллы
        $this->SetProperty("BonusPoints", $this->http->FindSingleNode("//span[contains(text(), 'Бонусные баллы')]/following-sibling::a/span[contains(@class,'points_current')]"));
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//*[contains(@class,'menu-user-name')]"));
        // Frozen points
        $this->SetProperty("FrozenPoints", $this->http->FindSingleNode("//*[contains(@class,'points_blocked')]"));
        // Account number
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//*[contains(@class,'account_number')]/*[contains(@class,'num')]"));
    }
}
