<?php

class TAccountCheckerFreerice extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://play.freerice.com/profile-login");

        if ($this->http->Response['code'] != 200) {
            return false;
        }
        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Accept"          => "application/json",
            "Content-Type"    => "application/json",
            "Accept-Encoding" => "gzip, deflate, br",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://accounts.freerice.com/accounts/auth/login?_format=json", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->token, $response->uuid)) {
            $this->http->setDefaultHeader("Authorization", "Bearer {$response->token}");

            return true;
        }

        $message = $response->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: " . $message);

            if (
                $message == 'Sorry, unrecognized username or password.'
                || $message == "The email was not verified. Reset the password to verify the email address."
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'Your account email address is not verified. Please verify the email address.'
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", $response->userData->displayname);

        $this->http->GetURL("https://engine.freerice.com/users/{$response->uuid}?_format=json");
        $response = $this->http->JsonLog();
        // Balance - You have donated ... grains
        $this->SetBalance($response->data->attributes->rice ?? null);
    }
}
