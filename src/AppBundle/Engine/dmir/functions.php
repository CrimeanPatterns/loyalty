<?php

use AwardWallet\Engine\ProxyList;

/**
 * Class TAccountCheckerDmir
 * Display name: Detskiy Mir (Bonus Card)
 * Database ID: 1036
 * Author: APuzakov
 * Created: 23.03.2015 15:11.
 */
class TAccountCheckerDmir extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->TimeLimit = 500;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://dmbonus.korona.net/dm/");

        if (!$this->http->ParseForm("card_form_id")) {
            return false;
        }

        $this->http->FormURL = "https://dmbonus.korona.net/dm/detmir/info";

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }

        $this->http->SetInputValue("card", $this->AccountFields['Login']);
        $this->http->SetInputValue("captcha", $captcha);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'Пожалуйста, введите верный номер карты')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

//        if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'Пожалуйста, введите верный набор символов с картинки')]")) {
        if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'Пожалуйста, пройдите проверку, что вы не робот, установив галочку.')]")) {
            $this->recognizer->reportIncorrectlySolvedCAPTCHA();

            throw new CheckRetryNeededException(3, 7);
        }

        if ($this->http->FindSingleNode("//a[contains(@class, 'close')]/@class")) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        // Номер карты
        $this->SetProperty("CardNumber", $this->http->FindPreg('/Номер карты:\s*(\d+)\s*</ims'));
        // Card status
        $this->SetProperty("CardStatus", $this->http->FindPreg('/Статус карты:\s*([^<]+)/ims'));
        // Дата последнего использования
        $this->SetProperty("DateOfLastTransaction", $this->http->FindSingleNode("//tr[2]/td[1]", null, true, '/([\d\.]+)\s+/ims'));
        // Количество активных бонусов
        $this->SetBalance($this->http->FindPreg('/Количество активных бонусов:\s*([\-\d\.]+)\s*</ims'));
        // Количество неактивных бонусов: 16,9 - Inactive points
        $this->SetProperty("InactivePoints", $this->http->FindPreg('/Количество неактивных бонусов:\s*([\-\d\.]+)\s*</ims'));
        // Общее количество бонусов: 16,9 - Total points
        $this->SetProperty("TotalPoints", $this->http->FindPreg('/Общее количество бонусов:\s*([\-\d\.]+)\s*</ims'));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
//        $file = $this->http->DownloadFile("https://dmbonus.korona.net/dm/captcha", "png");
//        $this->recognizer = $this->getCaptchaRecognizer();
//        $this->recognizer->RecognizeTimeout = 120;
//        $captcha = $this->recognizeCaptcha($this->recognizer, $file);
//        unlink($file);

        $key = $this->http->FindPreg("/\"sitekey\": \"([^\"]+)/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        return $captcha;
    }
}
