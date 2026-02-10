<?php

class TAccountCheckerBevmo extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setDefaultHeader("Origin", "https://www.bevmo.com");
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['token'])) {
            return false;
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://api.freshop.com/2/sessions/{$this->State['token']}?app_key=bevmo&token={$this->State['token']}", [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $is_user_logged_in = $response->is_user_logged_in ?? null;

        if ($is_user_logged_in == true) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.bevmo.com/my-account/#!/login?next=%2Fmy-account%2F');
        $data = [
            "app_key" => "bevmo",
            "utc"     => date("UB"),
        ];
        $this->http->PostURL('https://api.freshop.com/2/sessions/create', $data);
        $response = $this->http->JsonLog();

        if (!isset($response->token)) {
            return $this->checkErrors();
        }
        $this->http->Form = [];
        $this->http->FormURL = 'https://api.freshop.com/2/users/me/sessions';
        $this->http->SetInputValue('app_key', 'bevmo');
        $this->http->SetInputValue('email', $this->AccountFields["Login"]);
        $this->http->SetInputValue('password', $this->AccountFields["Pass"]);
        $this->http->SetInputValue('utc', date("UB"));
        $this->http->SetInputValue('token', $response->token);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;
        $this->http->PostForm();
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->token)) {
            $this->State['token'] = $response->token;

            return true;
        }

        $message = $response->error_message ?? null;

        switch ($message) {
            case "Invalid login":
            case "Invalid login.":
            case "Invalid login. Email Address or Password is incorrect.":
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);

                break;

            case "An error occurred.":
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);

                break;

            default:
                $this->logger->error($message);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://api.freshop.com/2/users/me?app_key=bevmo&token={$this->State['token']}&_=" . date("UB"));
        $response = $this->http->JsonLog();
        // Account
        $cardNumber = $response->store_card_number ?? null;
        $this->SetProperty("AccountNumber", $cardNumber);
        // Name
        $this->SetProperty("Name", beautifulName($response->display_name ?? ($response->first_name . " " . $response->last_name)));

        $id = $response->id ?? null;

        if (!$id) {
            $this->logger->notice("id not found");

            return;
        }

        $this->http->GetURL("https://api.freshop.com/2/users/{$id}/rewards?app_key=bevmo&include_levels=true&include_types=true&token={$this->State['token']}");
        $response = $this->http->JsonLog();

        if ($this->http->Response['body'] != '{}') {
            $this->sendNotification("Rewards were found // RR");
        }
        /*
        $total = $response->total ?? null;
        $error = $response->error ?? null;

        if ($total == 0 && $error == 'There are currently no rewards for this user. Please continue shopping to earn rewards.') {
            $this->SetBalanceNA();
        }
        */

        // AccountID: 3130774
        if (!$cardNumber && !empty($this->Properties['Name'])) {
            $this->http->GetURL("https://api.freshop.com/1/users/me?app_key=bevmo&token={$this->State['token']}");
            $response = $this->http->JsonLog();
            // Name
            $cardNumber = $response->store_card_number ?? null;
            $this->SetProperty("AccountNumber", $cardNumber);
        }

        if (!$cardNumber) {
            $this->logger->notice("Card number not found");

            return;
        }

        $this->http->GetURL("https://api.breinify.com/res/t0hop9gpwJl9qGJfxTI5apiWDlD-zuweH1DCUmByCOKMLdaI4CyxfehWKoMLUOzCX3KOZE8~CvByIzJ2f7yiVm7YeBsUnQ8DLDE22LY6gMbAnXfk3mY2nbUruIS6-PEzJPuAfyQ9BrlZoKwZuTOavLiF9XFyiSQdvbwsesBEfHJd1N7Cq-K6tTYZEQSKselZPErIiU6JDHY5gpW5-2lMWQ__?userId={$cardNumber}");
        $response = $this->http->JsonLog(null, 3, false, "userPoints");
        // Balance - ClubBev! Points / Current Point Balance
        if (
            !$this->SetBalance($response->payload->result[0]->userPoints ?? null)
//            && $this->http->Response['body'] == '{"payload":{"result":[],"headers":["id","rewardPointId","userId","userPoints"],"customerDataClass":"com.brein.common.dto.CustomerAssetsDto","customerDataTtlSeconds":{},"searchables":["id","userId"],"dynamicPermissions":[]},"responseCode":200}'
            && (
                $this->http->Response['body'] == '{"payload":{"result":[],"headers":["id","rewardPointId","userId","userPoints"],"customerDataClass":"com.brein.common.dto.CustomerAssetsDto","searchables":["id","userId"],"dynamicPermissions":[]},"responseCode":200}'
                || $this->http->Response['body'] == '{"payload":{"result":[],"headers":["id","rewardPointId","userId","userPoints"],"customerDataClass":"com.brein.common.dto.CustomerAssetsDto","searchables":["id","userId"],"semanticTypes":[],"dynamicPermissions":[]},"responseCode":200}'
                || $this->http->Response['body'] == '{"payload":{"result":[],"headers":["id","rewardPointId","userId","userPoints"],"customerDataClass":"com.brein.common.dto.CustomerAssetsDto","searchables":["id","userId"],"semanticTypes":[]},"responseCode":200}'
                || $this->http->Response['body'] == '{"payload":{"result":[],"headers":["assetId","assetImageUrl","assetTitle","assetType","assetUrl","id","productCategories","rewardPointId","userId","userPoints"],"customerDataClass":"com.brein.common.dto.CustomerAssetsDto","searchables":["assetId","id","userId"],"semanticTypes":[]},"responseCode":200}'
            )
        ) {
            // AccountID: 4538252
            $this->SetBalance(0);
        } elseif ($this->http->FindPreg("/\"payload\":\"Error \[16000\]: \{/")) {
            throw new CheckException("We are currently not able to determine your current balance, please check again later.", ACCOUNT_PROVIDER_ERROR);
        }
    }
}
