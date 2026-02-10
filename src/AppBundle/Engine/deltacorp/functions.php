<?php

require_once __DIR__ . '/../delta/functions.php';

class TAccountCheckerDeltacorp extends TAccountChecker
{
    public function getFormMessages()
    {
        //		if ($this->securityContext->isGranted('SESSION_CAN_CHECK_FIRST_TIME', getRepository('Provider')->find($this->AccountFields['ProviderID'])))
        //			return [];

        return array_merge(
            [TAccountCheckerDelta::getWarning()],
            \AwardWallet\MainBundle\Form\Account\EmailHelper::getMessages(
                $this->AccountFields,
                $this->userFields,
                "https://skybonus.delta.com/bizCompanyContactInfoLoad.sb?",
                "https://skybonus.delta.com/bizViewCommunicationSetting.sb",
                null
            )
        );
    }

    public function LoadLoginForm()
    {
        //		$this->EmailLogs = true;
        $this->http->removeCookies();
        $this->http->GetURL("http://skybonus.delta.com/");

        if (!$this->http->ParseForm("BizCoLoginActionForm")) {
            $this->CheckErrors();

            return false;
        }
        $this->http->SetInputValue("userName", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function CheckErrors()
    {
        //# Maintenance
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "website will be unavailable")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://skybonus.delta.com/index.jsp";

        return $arg;
    }

    public function Login()
    {
        return false;
    }

    public function Parse()
    {
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        //		if (!$this->securityContext->isGranted('SESSION_CAN_CHECK_FIRST_TIME', getRepository('Provider')->find($this->AccountFields['ProviderID'])))
        ArrayInsert($arFields, "SavePassword", true, [
            "Balance" => [
                "Type"     => "float",
                "Caption"  => "Balance",
                "Required" => false,
                "Value"    => ArrayVal($values, "Balance"),
            ],
        ]);

        if (intval(ArrayVal($_GET, 'skipping', 0)) == 1) {
            unset($arFields['Alert']);
        }
    }

    public static function GetStatusParams($arFields, &$title, &$img, &$msg)
    {
        $msg = "Unfortunately Delta Airlines forced us to stop supporting their loyalty programs.<br>
				To find out more, please check out our
				<a href='http://awardwallet.com/forum/viewtopic.php?f=16&t=2697' target='_blank'>discussion forum on this subject matter</a>.<br>
				Also, there is a petition going on at change.org:<br>
                <a href='http://www.change.org/petitions/delta-airlines-reverse-your-recent-lockout-of-awardwallet-service' target='_blank'>http://www.change.org/petitions/delta-airlines-reverse-your-recent-lockout-of-awardwallet-service</a><br>
                If you care about this problem, please sign this petition.<br>
				Finally, please voice your opinion by tweeting it to <a href='https://twitter.com/Delta'
				target='_blank'>https://twitter.com/Delta</a>";
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'DeltaCertificates')) {
            if (isset($properties['Currency']) && $properties['Currency'] != '(USD)') {
                return $fields['Balance'] . ' ' . $properties['Currency'];
            } else {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
            }
        } else {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "");
        }
    }
}
