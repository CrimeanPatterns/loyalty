<?php

class TAccountCheckerCokeunleashed extends TAccountChecker
{
    private $transactionId = null;

    public function LoadLoginForm()
    {
        // Login page
        $this->http->FilterHTML = false;
        $this->http->GetURL('https://www.cokerewards.com.au/');
        // Parse form
        if (!$this->http->ParseForm("capture_signIn_userInformationForm")) {
            return false;
        }

        if (!isset($this->http->Form['capture_transactionId'])) {
            $message = "capture_transactionId not found";
            $this->DebugInfo = $message;
            $this->http->Log($message);

            return false;
        }
        $this->http->SetInputValue("traditionalSignIn_emailAddress", $this->AccountFields['Login']);
        $this->http->SetInputValue("traditionalSignIn_password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("capture_screen", "signIn");

        $this->transactionId = $this->http->Form['capture_transactionId'];

//        $this->http->FormURL = 'https://ccsp.janraincapture.com/widget/traditional_signin.jsonp';
//        $this->http->SetFormText('utf8=%E2%9C%93&capture_screen=signIn&js_version=d321da0&capture_transactionId=mjd0ies6vw2axo6coz9wfibdxzgsz0wmn08j3trf&form=userInformationForm&flow=signIn&client_id=86s5fyvhd895fw66sqfdzb78mjavqmee&redirect_uri=https%3A%2F%2Fwww.cokerewards.com.au%2Faccount%2Fget-token&response_type=code&flow_version=k8S8ISGp_1MvBL4CfoTdUA&locale=en-US&traditionalSignIn_emailAddress='.urlencode($this->AccountFields['Login']).'&traditionalSignIn_password='.urlencode(urlencode($this->AccountFields['Pass'])).'&traditionalSignIn_signInButton=Sign+In', '&', true, true);

        // fill fields
//        $this->http->Form['vs_userId'] = $this->AccountFields['Login'];
//        $this->http->Form['vs_password'] = $this->AccountFields['Pass'];
//        $this->http->Form['loginButton'] = 'Login';
//        $this->http->Form['vs_channelType'] = 'EMAIL';

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'http://www.cokeunleashed.com.au/index.jsp';
        $arg['SuccessURL'] = 'http://www.cokeunleashed.com.au/index.jsp';

        return $arg;
    }

    public function Login()
    {
        // Post form
        if (!$this->http->PostForm()) {
            return false;
        }

        $this->http->GetURL("https://ccsp.janraincapture.com/widget/get_result.jsonp?transactionId={$this->transactionId}&cache=" . time() . date('B'));

        $authorizationCode = $this->http->FindPreg("/authorizationCode\":\"([^\"]+)/ims");

        if (!isset($authorizationCode)) {
            return false;
        }
        $this->http->GetURL("https://www.cokerewards.com.au/account/get-token?code=" . $authorizationCode);

        // Success login?
        if ($this->http->FindPreg('/LoginWidget_empty/')) {
            return true;
        }
        // Failed to login
        else {
            $errorCode = ACCOUNT_PROVIDER_ERROR;
            $errorMsg = $this->http->FindSingleNode('//div[@id="messagesDiv"]/p[@class="errorMessage"]');
            // unknown error
            if (!$errorMsg) {
                return false;
            }
            // wrong login/pass
            if (strpos($errorMsg, 'Incorrect Email or Password') !== false) {
                $errorCode = ACCOUNT_INVALID_PASSWORD;
            }
            // exception
            throw new CheckException($errorMsg, $errorCode);
        }
    }

    public function Parse()
    {
        // Token balance
        $this->SetBalance($this->http->FindSingleNode('//span[@id="points"]'));
        // Profile
        $this->http->GetURL('https://www.cokerewards.com.au/account/#account');
        // Name
        $this->SetProperty('Name', $this->http->FindSingleNode('//p[@id="FIRST_NAME_label"]') . ' ' . $this->http->FindSingleNode('//p[@id="LAST_NAME_label"]'));
    }
}
