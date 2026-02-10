<?php

class TAccountCheckerBeeline extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://lk.beeline.ru/next=/bonus/internet/');
        // parse form
        if (!$this->http->ParseForm()) {
            return false;
        }
        // fill fields
        $this->http->FormURL = 'https://identity.beeline.ru/identity/fpcc';
        $this->http->SetInputValue('login', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->ParseForm(null, 1, true, "//form[@action = 'https://www.beeline.ru/logincallback']")) {
            $this->http->PostForm();

            if ($this->http->ParseForm("form1")) {
                $this->http->PostForm();
            }

            return true;
        }// if ($this->http->ParseForm(null, 1, true, "//form[@action = 'https://www.beeline.ru/logincallback']"))
        // failed to login
        if ($errorMsg = $this->http->FindSingleNode('//ul[@class="errorlist"]/li[1]')) {
            // wrong card num
            if (strpos($errorMsg, 'Логин или пароль неправильные') !== false) {
                throw new CheckException($errorMsg, ACCOUNT_INVALID_PASSWORD);
            } else {
                throw new CheckException($errorMsg, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($errorMsg  = $this->http->FindSingleNode('//ul[@class="errorlist"]/li[1]'))
//        /*
//         * У Вас подключено предложение «Все в одном»
//         * Оплачивать услуги и управлять сервисами Мобильной связи и
//         * Домашнего интернета необходимо с единого счета мобильной связи
//         * на нашем сайте beeline.ru
//         */
//        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'У Вас подключено предложение «Все в одном»')]"))
//            throw new CheckException("У Вас подключено предложение «Все в одном». Оплачивать услуги и управлять сервисами Мобильной связи и Домашнего интернета необходимо с единого счета мобильной связи на нашем сайте <a target='_blank' href='https://www.beeline.ru/login/'>beeline.ru</a>", ACCOUNT_PROVIDER_ERROR);
        // Условия оферты
        if ($message = $this->http->FindSingleNode("//label[contains(text(), 'Принять оферту')]")) {
            throw new CheckException("Beeline Internet website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }
        // У Вас подключено предложение «Всё в одном». Для авторизации необходимо использовать логин и пароль от кабинета мобильной связи.
        if ($modelJson = $this->http->FindHTMLByXpath("//script[@id = 'modelJson']", "/<script[^>]+>\s*<\!\[CDATA\[(.+)\]\]>\s*<\/script>/ims")) {
            $modelJson = str_replace('&quot;', '"', trim($modelJson));
            $modelJson = $this->http->JsonLog($modelJson);

            if (isset($modelJson->errorMessage)) {
                if ($message = $this->http->FindPreg('/authError:(.+)/', false, $modelJson->errorMessage)) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Непредвиденная ошибка
                if (CleanXMLValue($modelJson->errorMessage) == 'Непредвиденная ошибка') {
                    throw new CheckException(CleanXMLValue($modelJson->errorMessage), ACCOUNT_PROVIDER_ERROR);
                }
            }// if (isset($modelJson->errorMessage))
        }// if ($modelJson = $this->http->FindSingleNode("//script[@id = 'modelJson']"))

        return false;
    }

    public function Parse()
    {
        $oamAuthToken = $this->http->getCookieByName("BISAuthTokenCookie", null, "/", true);
        $this->http->setDefaultHeader("OamAuthToken", $oamAuthToken);
        $this->http->setDefaultHeader("Accept", "*/*");
        $this->http->GetURL('https://widgets.beeline.ru/api/Profile/Index');
        $response = $this->http->JsonLog(null, true, true);

        // refs #14991
        if (!isset($response['ContractStatusWidget'])) {
            $this->http->setDefaultHeader('X-Requested-With', 'XMLHttpRequest');
            $this->http->PostURL('https://buryatiya.beeline.ru/Gtm/GetDataLayerAuth', []);
            $response = $this->http->JsonLog(null, true);

            if (isset($response->View->List[0]->Balance) || $this->http->FindPreg("/User is not a mobile user\./")) {
                $this->SetBalanceNA();
            }

            return;
        }// if (!isset($response['ContractStatusWidget']))

        // Name
        $this->SetProperty('Name', ArrayVal($response['ContractStatusWidget'], 'Alias'));
        // Account number - Лицевой счет
        $this->SetProperty('AccountNumber', ArrayVal($response['ContractStatusWidget'], 'Ctn'));
        // Contract number - Номер договора
        $this->SetProperty('ContractNumber', ArrayVal($response['ContactDataWidget'], 'Ctn'));
        // Current balance - Текущий баланс
        $currentBalance = ArrayVal($response['BalanceWidget'], 'Balance');
        $this->SetProperty('CurrentBalance', number_format($currentBalance, 2, ',', ' '));
        // Sum to be payed - Сумма к оплате
        $sumToBePayed = ArrayVal($response['BalanceWidget'], 'NextSubscriptionFee') - $currentBalance;
        $this->SetProperty('SumToBePayed', number_format($sumToBePayed, 2, ',', ' '));
        // To payed by - Дата окончания расчетного периода
        $this->SetProperty('ToPayedBy', str_replace("T", ' ', ArrayVal($response['BalanceWidget'], 'DueDate')));
        // Balance - (ru:Бонусы)
//        $this->SetBalance(ArrayVal($response['ContractStatusWidget'], 'BonusBalance'));

        if (isset($this->Properties['CurrentBalance'], $this->Properties['AccountNumber'])) {
            $this->SetBalanceNA();
        }
    }
}
