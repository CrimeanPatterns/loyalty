<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerDeliveryclub extends TAccountChecker
{
    use ProxyList;

    private array $headers = [
        'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        'Accept' => 'application/json, text/javascript, */*; q=0.01',
        'X-Requested-With' => 'XMLHttpRequest',
    ];

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['PreloadAsImages'] = true;

        return $arg;
    }

    /*
    public $regionOptions = [
        ""      => "Select login type",
        'Email' => 'Email',
        'Phone' => 'Phone #',
    ];

    function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields, $values);
        $arFields['Login2']['Options'] = $this->regionOptions;
        $arFields["Login2"]["Value"] = 'Phone';
    }
    */

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->setProxyBrightData(null, "dc_ips_ru", "ru");
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn(): bool
    {
        return false;
    }

    public function LoadLoginForm(): bool
    {
        $m = [];

        if (!preg_match('/\+7(\d{3})(\d{3})(\d{2})(\d{2})/', $this->AccountFields['Login'], $m)) {
            throw new CheckException('Недопустимый формат номера', ACCOUNT_INVALID_PASSWORD);
        }
//        $this->AccountFields['Login'] = urlencode(sprintf('+7 (%s) %s-%s-%s', $m[1], $m[2], $m[3], $m[4]));

        $this->http->removeCookies();
        $this->http->GetURL('https://passport.delivery-club.ru/auth/reg?backpath=https%3A%2F%2Fwww.delivery-club.ru%2Fmoscow%3FshippingType%3Ddelivery&origin=eats_desktop&retpath=https%3A%2F%2Fwww.delivery-club.ru%2Fmoscow%3Fauth_from%3Dside_menu%26shippingType%3Ddelivery&theme=light');

        $csrf = $this->http->FindPreg('/"csrf":"([\w:]+)/');

        if (empty($csrf)) {
            return $this->checkErrors();
        }
        $data = [
            'csrf_token' => $csrf,
            'process'    => 'ENTRY_REGISTER_NEOPHONISH',
            'origin'     => 'delivery-club',
        ];
        $this->logger->debug('Load track_id');
        $this->http->PostURL('https://passport.delivery-club.ru/registration-validations/user-entry-flow-submit', $data);
        $trackId = $this->http->JsonLog()->id ?? null;

        if (empty($trackId)) {
            return $this->checkErrors();
        }

        $data = [
            'csrf_token' => $csrf,
            'phone'      => $this->AccountFields['Login'],
            'scenario'   => 'register',
            'track_id'   => $trackId,
        ];
        $this->logger->debug('Phone number validation step 1');
        $this->http->PostURL('https://passport.delivery-club.ru/registration-validations/phone-validate-by_squatter', $data, $this->headers);
        $responseStatus = $this->http->JsonLog()->status ?? null;

        if ($responseStatus != 'ok') {
            return $this->checkErrors();
        }

        $data = [
            'csrf_token'        => $csrf,
            'track_id'          => $trackId,
            'validate_for_call' => true,
            'phone_number'      => $this->AccountFields['Login'],
        ];
        $this->logger->debug('Phone number validation step 2');
        $this->http->PostURL('https://passport.delivery-club.ru/registration-validations/validate-phone', $data, $this->headers);
        $responseStatus = $this->http->JsonLog()->status ?? null;

        if ($responseStatus != 'ok') {
            return $this->checkErrors();
        }

        $data = [
            'csrf_token'       => $csrf,
            'track_id'         => $trackId,
            'display_language' => 'ru',
            'number'           => $this->AccountFields['Login'],
            'confirm_method'   => 'by_sms',
            'isCodeWithFormat' => true,
        ];
        $this->logger->debug('Posting phone number finally');
        $this->http->PostURL('https://passport.delivery-club.ru/registration-validations/phone-confirm-code-submit', $data, $this->headers);
        $this->State['csrf'] = $csrf;
        $this->State['track_id'] = $trackId;

        return true;
    }

    public function checkErrors(): bool
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login(): bool
    {
        $responseStatus = $this->http->JsonLog()->status ?? null;

        if ($responseStatus != 'ok') {
            return $this->checkErrors();
        }
        $this->parseQuestion();

        return false;
    }

    public function parseQuestion(): bool
    {
        $this->logger->notice(__METHOD__);
        $this->Question = 'Введите код из смс. Мы отправили его на номер ' . $this->AccountFields['Login'];
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $tokens = [
            'csrf_token' => $this->State['csrf'],
            'track_id'   => $this->State['track_id'],
        ];
        $data = [
            'code' => $this->Answers[$this->Question],
        ] + $tokens;
        unset($this->Answers[$this->Question]);
        $this->http->PostURL('https://passport.delivery-club.ru/registration-validations/phone-confirm-code', $data, $this->headers);
        $responseStatus = $this->http->JsonLog()->status ?? null;

        if ($responseStatus != 'ok') {
            return false;
        }

        $this->http->PostURL('https://passport.delivery-club.ru/registration-validations/find-accounts-by-phone', $tokens, $this->headers);
        $response = $this->http->JsonLog();

        if (empty($response->status)
            || $response->status != 'ok'
            || empty($response->accounts)
            || !is_array($response->accounts)
            || empty($response->accounts[0]->uid)
        ) {
            return false;
        }

        $data = [
            'uid'                  => $response->accounts[0]->uid,
            'useNewSuggestByPhone' => true,
        ] + $tokens;
        $this->http->PostURL('https://passport.delivery-club.ru/registration-validations/neo-phonish-auth', $data, $this->headers);
        $responseStatus = $this->http->JsonLog()->status ?? null;

        if ($responseStatus != 'ok') {
            return false;
        }

        // Invalid answer
//        if ($error = ) {
//            $this->AskQuestion($this->Question, $error, 'Question');
//            return false;
//        }

        return true;
    }

    public function toJson($mixed)
    {
        return str_replace(["\\", '"'], "", preg_replace_callback(
            "/\\\u([a-f0-9]{4})/",
            function ($matches) {
                return iconv('UCS-4LE', 'UTF-8', pack('V', hexdec('U' . $matches[0])));
            },
            json_encode($mixed)
        ));
    }

    public function Parse(): void
    {
        $response = $this->http->JsonLog(null, false);
        // Balance - ... баллов
        $this->SetBalance($response->scores ?? null);
        // Name
        $this->SetProperty("Name", beautifulName($response->name ?? null));
    }

    private function loginSuccessful(): bool
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();

        if (isset($response->scores)) {
            return true;
        }

        return false;
    }
}
