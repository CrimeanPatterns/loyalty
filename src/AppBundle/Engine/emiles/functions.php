<?php

// refs #15034

use AwardWallet\Engine\ProxyList;

class TAccountCheckerEmiles extends TAccountChecker
{
    use ProxyList;

    private $authorizationResponse = '';
    private $authorization = '';
    private $baseUrl = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
//        $this->http->LogHeaders = true;
//        $this->setProxyBrightData();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://emiles.com");
        $this->http->RetryCount = 2;
//        $this->checkProxy();
        $this->baseUrl = stripslashes($this->http->FindPreg("/baseURL=\"([^\"]+)/"));

        if ($this->http->Response['code'] != 200 || !$this->baseUrl) {
            return $this->checkErrors();
        }

        if ($this->baseUrl == 'https://loyl-srv.emiles.com/reception/api/v1') {
            $this->baseUrl = 'https://loyl-srv.emiles.com/api/v1';
        }
        $data = ['provider' => 'email', 'email' => $this->AccountFields['Login'], 'password' => $this->AccountFields['Pass']];
        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'DNT'          => '1',
            'Origin'       => 'https://emiles.com',
        ];
        sleep(3);
        $this->http->RetryCount = 0;
        $this->http->PostURL("{$this->baseUrl}/authenticate", json_encode($data), $headers);
        $this->http->RetryCount = 2;
//        $this->checkProxy();

        return true;
    }

//    function checkProxy() {
//        $this->logger->notice(__METHOD__);
//        $this->logger->debug("errorMessage: {$this->http->Response["errorMessage"]}");
//        // retries
//        if (stristr($this->http->Response["errorMessage"], 'Received HTTP code 502 from proxy after CONNECT')
//            || stristr($this->http->Response["errorMessage"], 'Operation timed out after'))
//            throw new CheckRetryNeededException(2, 10);
//    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("//title[contains(text(), '503 Service Temporarily Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!empty($this->http->Response['headers']['authorization'])) {
            $this->authorization = $this->http->Response['headers']['authorization'];
        }

        $response = $this->http->JsonLog();

        if ($this->http->Response['code'] == 200 && !empty($this->authorization) && isset($response->id)) {
            $this->authorizationResponse = $response;

            return true;
        }
        // Login failed
        if ($this->http->Response['code'] == 401 && isset($response->message) && $response->message == 'Invalid Access') {
            throw new CheckException("Login failed!", ACCOUNT_INVALID_PASSWORD);
        }
        // Email confirmation required!
        if ($this->http->Response['code'] == 403 && isset($response->message) && $response->message == 'ERR_ONBOARD') {
            throw new CheckException("Email confirmation required!", ACCOUNT_INVALID_PASSWORD);
        }

        // it's no lockout, false positive
        if ($this->http->Response['code'] == 403 && isset($response->message) && $response->message == 'ERR_BLOCKED') {
            throw new CheckRetryNeededException(3);
        }
        // Network error 28 - SSL connection timeout
        if (strstr($this->http->Error, 'Network error 28 - SSL connection timeout')) {
            throw new CheckRetryNeededException(3);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $headers = [
            'accept'        => 'application/json',
            'authorization' => $this->authorization,
            'content-type'  => 'application/json',
            'referer'       => 'https://emiles.com/earn',
        ];
        $this->http->GetURL("{$this->baseUrl}/balance?", $headers);
        $response = $this->http->JsonLog();
        // Balance - Points
        if (!empty($response) && count($response) == 1) {
            if (isset($response[0]->balance)) {
                $this->SetBalance($response[0]->balance);
            } else {
                $this->sendNotification('emiles: response items > 1');
            }
        }
        // Name
        if (!empty($this->authorizationResponse->first_name) && !empty($this->authorizationResponse->last_name)) {
            $this->SetProperty('Name', beautifulName($this->authorizationResponse->first_name . ' ' . $this->authorizationResponse->last_name));
        }

        // Expiration Date  // refs #15034, https://redmine.awardwallet.com/issues/15034#note-19
        $this->http->GetURL("{$this->baseUrl}/activity?", $headers);
        $nodes = $this->http->JsonLog(null, true, true);

        if (is_array($nodes)) {
            $this->logger->debug("Total nodes found: " . count($nodes));

            foreach ($nodes as $node) {
                $exp = $this->http->FindPreg("/(.+)T\d/", false, ArrayVal($node, 'created_at'));
                $type = ArrayVal($node, 'type');
                $points = $this->http->FindPreg("/<strong>([\+\d\,\.]+) Point/ims", false, ArrayVal($node, 'message'));
                $this->logger->debug("Date: {$exp} / type: {$type} / points: {$points}");

                if (strtotime($exp) && $type == 'points_earned' && $points > 0) {
                    $exp = strtotime($exp);
                    // Last Activity
                    $this->SetProperty("LastActivity", date("d/m/Y", $exp));
                    // Exp date
                    $this->SetExpirationDate(strtotime("+1 year", $exp));

                    break;
                }// if (strtotime($exp) && $type == 'points_earned' && $points > 0)
            }// foreach ($nodes as $node)
        }// if (is_array($nodes))
    }
}
