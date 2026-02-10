<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBermuda extends TAccountChecker
{
    use ProxyList;

    public $Csrf;
    public $formParams;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function ClearFormParams()
    {
        unset($this->formParams['ajaxEvent']);
        unset($this->formParams['ajaxData']);
        unset($this->formParams['bank_account_num']);
        unset($this->formParams['phone']);
    }

    public function SetFormParams($action)
    {
        $this->formParams['postaction'] = 'NavForm';
        $this->formParams['next_page_name'] = $action;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        // on this page no errors for account with invalid credentials
        //		$this->http->getURL('https://www.loyaltygateway.com/bankofbermuda/rewards.com/rewards/ControllerServlet?bank_id=186067&i18n=en_US');
        $this->http->getURL('https://www.loyaltygateway.com/bankofbermuda/rewards.com/rewards/SignInServlet?b');
        $this->Csrf = $this->http->FindSingleNode('//input[@id="csrf_token_keeper"]/@value');

        if (empty($this->Csrf)) {
            return $this->checkErrors();
        }

        $this->LoadLoginCardNumberForm();
        $this->ClearFormParams();
        $this->formParams['postaction'] = 'NavForm';
        //$this->formParams['next_page_name'] = 'SPN_VERIFY';

        $this->http->FormURL = "https://www.loyaltygateway.com/bankofbermuda/rewards.com/rewards/ControllerServlet?bank_id=186067&i18n=en_US";
        $this->http->Form = $this->formParams;
        $this->Login();

        $this->LoadLoginPhoneForm();
        $this->ClearFormParams();
        //$this->formParams['next_page_name'] = 'SPN_HOME';

        $this->http->FormURL = "https://www.loyaltygateway.com/bankofbermuda/rewards.com/rewards/ControllerServlet?bank_id=186067&i18n=en_US";
        $this->http->Form = $this->formParams;

        return true;
    }

    public function LoadLoginCardNumberForm()
    {
        if (!$this->http->ParseForm('LoginForm')) {
            return false;
        }
        $this->http->FormURL = "https://www.loyaltygateway.com/bankofbermuda/rewards.com/rewards/AjaxDataServlet/";
        $this->http->SetFormText('ajaxEvent=loginPost&ajaxData=', '&', false);
        $this->http->Form['bank_account_num'] = $this->AccountFields['Login'];
        $this->http->Form['csrf_token'] = $this->Csrf;

        $this->formParams = $this->http->Form;

        $this->Login();
    }

    public function checkErrors()
    {
        if ($this->http->FindPreg("/Http\/1\.1 Service Unavailable<\/b/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function LoadLoginPhoneForm()
    {
        if (!$this->http->ParseForm('VerifyForm')) {
            return false;
        }
        $this->http->FormURL = "https://www.loyaltygateway.com/bankofbermuda/rewards.com/rewards/AjaxDataServlet/";
        $this->http->SetFormText('ajaxEvent=verifyPost&ajaxData=', '&', false);
        $this->http->Form['phone'] = $this->AccountFields['Pass'];
        $this->http->Form['csrf_token'] = $this->Csrf;

        $this->formParams = $this->http->Form;

        $this->Login();
    }

    public function Login()
    {
        $this->http->PostForm();

        if ($this->http->FindPreg('/NEXT_PAGE:SPN_ERROR/')) {
            $this->formParams['next_page_name'] = 'SPN_ERROR';
        } elseif ($this->http->FindPreg('/NEXT_PAGE:SPN_VERIFY/')) {
            $this->formParams['next_page_name'] = 'SPN_VERIFY';
        } elseif ($this->http->FindPreg('/NEXT_PAGE:SPN_HOME/')) {
            $this->formParams['next_page_name'] = 'SPN_HOME';
        } else {
            $this->formParams['next_page_name'] = 'SPN_VERIFY';
        }

        $error = $this->http->FindSingleNode('//div[@id="status_msg"]');

        if (isset($error) && trim($error) != '') {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        $error = $this->http->FindSingleNode('//div[@id="page_status"]');
        $error = (!$error) ? $this->http->FindSingleNode('//span[@id="status_msg"]') : $error;

        if (isset($error) && trim($error) != '') {
            if (strstr($error, 'Your account has been locked')) {
                throw new CheckException($error, ACCOUNT_LOCKOUT);
            } else {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
        }

        if ($this->http->FindSingleNode("//a[contains(@href, 'logoutPost')]")) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        //# Name
        $this->SetProperty("Name", beautifulName(trim($this->http->FindSingleNode('//tr[@class="summaryText"]/td[1]'), ':')));
        //# Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//td[contains(text(), 'Status')]", null, true, "/Status:\s*([^<]+)/ims"));
        //# Order Total
        $this->SetProperty("OrderTotal", $this->http->FindSingleNode("//span[@id = 'orderTotalStrHeader']"));
        //# Points Remaining
        $this->SetProperty("PointsRemaining", $this->http->FindSingleNode("//span[@id = 'pointsRemainingStrHeader']"));
        //# Balance
        $this->SetBalance($this->http->FindSingleNode('//tr[@class="summaryText"]/td[2]'));
    }
}
