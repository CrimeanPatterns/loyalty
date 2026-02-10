<?php

class TAccountCheckerAgrotea extends TAccountChecker
{
    public function LoadLoginForm()
    {
        // Url to post form: https://argotea.myguestaccount.com/login/accountbalance.srv?id=...
        // IDK maybe ID is magic number or not but I prefer to dynamically fetch login URL
        $this->http->GetURL('http://www.argotea.com/loyaltea_balance.shtml');
        $loginUrl = $this->http->FindSingleNode('//form[@target="_lybalance"]/@action');

        if (!$loginUrl) {
            return false;
        }
        // load form
        $this->http->GetURL($loginUrl);
        // parse form
        if (!$this->http->ParseForm()) {
            return false;
        }
        // setup fields
        $this->http->SetInputValue('cardnum', $this->AccountFields['Login']);
        $this->http->SetInputValue('reg_code', $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        // post form
        if (!$this->http->PostForm()) {
            return false;
        }
        // success login?
        if ($this->http->FindSingleNode('//td[@class="table_header"]/b[contains(text(),"Account Balance")]')) {
            return true;
        }
        // failed to login
        else {
            $errorCode = ACCOUNT_PROVIDER_ERROR;
            $errorMsg = $this->http->FindSingleNode('//td/div[contains(@style, "color: red")]/b');
            // unknown error
            if (!$errorMsg) {
                return false;
            }
            // wrong login/pass
            if (strpos($errorMsg, 'Invalid Card') !== false) {
                $errorCode = ACCOUNT_INVALID_PASSWORD;
            }
            // exception
            throw new CheckException($errorMsg, $errorCode);
        }
    }

    public function Parse()
    {
        // table xpath
        $tbx = '//div[@class="balance_info"]/form//table';
        // Card
        $this->SetProperty('Card', $this->http->FindSingleNode($tbx . '/tr[2]/td[2]/div[@class="value"]'));
        // Card Type
        $this->SetProperty('CardType', $this->http->FindSingleNode($tbx . '/tr[3]/td[2]/div[@class="value"]'));
        // Stored Value
        $this->SetProperty('StoredValue', $this->http->FindSingleNode($tbx . '/tr[4]/td[2]/div[@class="value"]'));
        // Max Rewards Per Check
        $this->SetProperty('MaxRewardsPerCheck', $this->http->FindSingleNode($tbx . '/tr[5]/td[2]/div[@class="value"]'));
        // Drink Credits - Balance
        $this->SetBalance($this->http->FindSingleNode($tbx . '/tr[6]/td[2]/div[@class="value"]'));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindPreg("/merchant not active<\/div>/")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }
}
