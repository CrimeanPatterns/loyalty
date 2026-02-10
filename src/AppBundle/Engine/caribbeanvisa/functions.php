<?php

require_once __DIR__ . '/../bankofamerica/functions.php';

class TAccountCheckerCaribbeanvisa extends TAccountCheckerBankofamerica
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->loginUrl = "https://www.managerewardsonline.bankofamerica.com/RMSapp/Ctl/entry?pid=grprwd&mc=RCCL";
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->FilterHTML = false;

        $this->AccountFields['Login3'] = 'WorldPoints';
        $loginUrl = "https://www.managerewardsonline.bankofamerica.com/RMSapp/Ctl/entry?pid=grprwd&mc=RCCL";
        $this->http->GetURL($loginUrl);

        if (!$this->http->ParseForm("loginForm")) {
            return $this->checkErrorsOfWorldPoints();
        }
        $nodes = $this->http->XPath->query("//select[@name = 'loginTarget']/option");

        for ($n = 0; $n < $nodes->length; $n++) {
            $cruiseLine = CleanXMLValue($nodes->item($n)->nodeValue);

            if ($cruiseLine == $this->AccountFields["Login2"]) {
                $loginUrl = CleanXMLValue($nodes->item($n)->getAttribute("value"));

                break;
            } else {
                $this->logger->debug("skip link -> {$cruiseLine}");
            }
        }// for ($n = 0; $n < $nodes->length; $n++)
        $this->loginUrl = $loginUrl;

        return true;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        $result = Cache::getInstance()->get('caribbeanvisa_cruise_lines');

        if (($result !== false) && (count($result) > 1)) {
            $arFields["Login2"]["Options"] = $result;
        } else {
            $arFields["Login2"]["Options"] = [
                "" => "Select your cruise line",
            ];
            $browser = new HttpBrowser("none", new CurlDriver());
            $browser->GetURL("https://www.managerewardsonline.bankofamerica.com/RMSapp/Ctl/entry?pid=grprwd&mc=RCCL");
            $nodes = $browser->XPath->query("//select[@name = 'loginTarget']/option");

            for ($n = 0; $n < $nodes->length; $n++) {
                $cruiseLine = CleanXMLValue($nodes->item($n)->nodeValue);
                $cruiseLineLink = CleanXMLValue($nodes->item($n)->getAttribute("value"));

                if ($cruiseLine != "" && $cruiseLineLink != "") {
                    $arFields['Login2']['Options'][$cruiseLine] = $cruiseLine;
                }
            }

            if (count($arFields['Login2']['Options']) > 1) {
                Cache::getInstance()->set('caribbeanvisa_cruise_lines', $arFields['Login2']['Options'], 3600);
            }
        }
    }
}
