<?php

namespace AwardWallet\Engine\drury\Credentials;

class Login extends \TAccountCheckerExtended
{
    protected $http2 = null;

    public function getCredentialsImapFrom()
    {
        return [
            "newsletter@druryhotels.com",
            "drury@druryhotels.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "DruryHotels.com login request",
        ];
    }

    public function getParsedFields()
    {
        return ["Login"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Login'] = re('#The\s+username\(s\)\s+you\s+requested\s+for\s+DruryHotels\.com\s+is\s*:\s*(\d+)#msi', text($this->http->Response['body']));

        return $result;
    }

    public function GetRetrieveFields()
    {
        return [
            'Email',
        ];
    }

    public function RetrieveCredentials($data)
    {
        /* * * GET FORGOT LOGIN FORM * * */

        // Load page
        $this->http->GetURL('https://wwws.druryhotels.com/AwardClubHome.aspx');
        // Get form
        if (!$this->http->ParseForm("aspnetForm")) {
            return false;
        }

        // Set system fields
        $this->http->SetInputValue('ctl00$ctl00$ScriptManager', 'ctl00$ctl00$MyDrurySignInUserControl$MyDruryLoginUpdatePanel|ctl00$ctl00$MyDrurySignInUserControl$ForgotUserLogonLinkButton');
        // Is value giving into script from page
        $this->http->SetInputValue("ctl00_ctl00_ScriptManager_HiddenField", urldecode($this->http->FindSingleNode("//script[contains(@src,'_TSM_HiddenField_')]/@src", null, true, "#_TSM_CombinedScripts_=(.+)#")));
        $this->http->SetInputValue("__LASTFOCUS", "");
        $this->http->SetInputValue("__ASYNCPOST", "true");

        // Clone for ajax. http is default form state
        $this->http2 = clone $this->http;

        // Get link href. Is use js function.
        $href = $this->http2->FindSingleNode("//a[contains(text(),'Forgot Username?')]/@href");

        // Set arguments
        preg_match("#javascript\s*:\s*__doPostBack\s*\(\s*'([^']*)'\s*,\s*'([^']*)'\s*\)#", $href, $m);

        // Set into form
        $this->http2->SetInputValue("__EVENTTARGET", $m[1]);
        $this->http2->SetInputValue("__EVENTARGUMENT", $m[2]);

        // Send
        if (!$this->http2->PostForm()) {
            return false;
        }

        // Parse response
        $result = explode("|", $this->http2->Response['body']);

        // Get body
        if (!isset($result[7])) {
            return false;
        }

        if (preg_match("#<label>\s*Forgot\s+Username\?\s*</label>#ms", $result[7])) {
            $this->http2->setBody($result[7]);
        } else {
            return false;
        }

        /* * * SEND FORGOT LOGIN FORM * * */

        // Validations
        if (($key = $this->FindKey("__VIEWSTATE", $result)) !== false) {
            $this->http->SetInputValue("__VIEWSTATE", $result[$key + 1]);
        }

        if (($key = $this->FindKey("__EVENTVALIDATION", $result)) !== false) {
            $this->http->SetInputValue("__EVENTVALIDATION", $result[$key + 1]);
        }

        if (($key = $this->FindKey("__VIEWSTATEGENERATOR", $result)) !== false) {
            $this->http->SetInputValue("__VIEWSTATEGENERATOR", $result[$key + 1]);
        }

        // Remove form fields
        $this->RemoveFields($this->http->Form);

        // Set new form fields
        $this->http->SetInputValue($this->http2->FindSingleNode("//input[contains(@name,'Email')]/@name"), $data['Email']);
        $this->http->SetInputValue($this->http2->FindSingleNode("//input[@value='Submit']/@name"), "Submit");

        // System fields
        $this->http->SetInputValue('ctl00$ctl00$ScriptManager', 'ctl00$ctl00$MyDrurySignInUserControl$MyDruryLoginUpdatePanel|ctl00$ctl00$MyDrurySignInUserControl$ForgotUserLogonButton');

        // Send
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindPreg("#An\s+email\s+with\s+your\s+username\s+is\s+on\s+the\s+way#i")) {
            return true;
        } else {
            return false;
        }
    }

    public function FindKey($val, $arr)
    {
        foreach ($arr as $key=>$v) {
            if ($v == $val) {
                return $key;
            }
        }

        return false;
    }

    public function RemoveFields(&$array)
    {
        foreach ($array as $key=>$val) {
            if (strpos($key, 'ctl00$ctl00$MyDrurySignInUserControl') !== false) {
                unset($array[$key]);
            }
        }
    }
}
