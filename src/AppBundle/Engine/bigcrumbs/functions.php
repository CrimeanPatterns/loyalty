<?php

class TAccountCheckerBigcrumbs extends TAccountChecker
{
    /*
     * Cash back autologin - OLD
     */

    public $ContinueToStep;

    //	public $ContinueToStep = 'startRegistration';

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://mainstreetshares.com/myAccount.do");

        if (!$this->http->ParseForm("LoginForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("destinationUrl", "https://mainstreetshares.com/myAccount.do");
        $this->http->SetInputValue("memberName", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindNodes('//a[contains(@href, "logoff.do")]/@href')) {
            return true;
        }
        // No account was found using the information you provided
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'No account was found using the information you provided')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Attention: Please Reset Your Password
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Attention: Please Reset Your Password')]")) {
            throw new CheckException("MainStreetSHARES (msSHARES) website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        // Note: for your security, additional information is now required to sign in.
        if ($this->http->FindPreg("/for your security, additional information is now required to sign in\./")) {
            /*
             * BigCrumbs Members: Your account was transferred here, so there's no need to sign up again!
             * Just reset your password to get started!
             */
            if ($message = $this->http->FindSingleNode("//td[contains(., 'Your account was transferred here')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }
//            && $this->parseQuestion())
//            return false;

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->http->Log("parseQuestion");
        $question = $this->http->FindSingleNode("//label[contains(text(), 'Zip or Postal Code on Your Account')]");

        if (!isset($question)) {
            return false;
        }

        if (!$this->http->ParseForm("login_form")) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->http->Log("ProcessStep");
        $this->sendNotification("bigcrumbs. Security question was entered");
        $this->http->SetInputValue("zip", $this->Answers[$this->Question]);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("c", $this->parseCaptcha());

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($error = $this->http->FindSingleNode("//p[contains(text(), 'No account was found using the information you provided')]")) {
            $this->AskQuestion($this->Question, $error);

            return false;
        }

        return true;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h4[contains(text(), 'Hello,')]", null, true, '/Hello,\s*([^\!]+)/ims')));
        // Balance - You have ... msSHARES®
        $this->SetBalance($this->http->FindSingleNode("//h3[contains(text(), 'You have')]/span"));
        // My Revenue Share
        $this->SetProperty("RevenueShare", $this->http->FindSingleNode("//h4[contains(text(), 'My Revenue')]/following-sibling::h3"));
        // Referral Commissions
        $this->SetProperty("ReferralCommissions", $this->http->FindSingleNode("//h4[contains(text(), 'Referral')]/following-sibling::h3"));
        // Total Pending
        $this->SetProperty("TotalPending", $this->http->FindSingleNode("//h4[contains(text(), 'Total')]/following-sibling::h3"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = 'https://mainstreetshares.com/logoff.do';
        $arg['SuccessURL'] = 'https://mainstreetshares.com/myAccount.do';

        return $arg;
    }

    public function GetPartnerFields($values, $width)
    {
        global $sPath, $Interface;

        // find last address
        require_once "$sPath/schema/User.php";
        $address = TUserSchema::getLastAddress($this->userFields['UserID']);

        $countryOptions = [
            "AU" => "Australia",
            "CA" => "Canada",
            "IE" => "Ireland",
            "UK" => "United Kingdom",
            "US" => "United States",
        ];

        // parse states from bigcrumbs site
        $states = Cache::getInstance()->get("bigcrumbs_states");

        if (empty($states)) {
            $states = null;
            // curl stopped working
            //$html = curlRequest('https://www.bigcrumbs.com/crumbs/register.do', 10);
            $http = new HttpBrowser("none", new CurlDriver());
            $http->GetURL('https://www.bigcrumbs.com/crumbs/register.do');

            if (preg_match("/var countryChoiceSet = ([^;]+);/ims", $http->Response["body"], $matches)) {
                $states = json_decode($matches[1], true);
            }
            // TODO: remove it after maintenance
            //			if(!isset($states) || count($states) == 0)
            //				DieTrace("States not found");
            Cache::getInstance()->set("bigcrumbs_states", $states, 600);
        }

        $style = "style='width: {$width}px;'";
        $fields = [
            "Login"     => [
                "Type"            => "string",
                "Caption"         => "Desired User ID",
                "Size"            => 15,
                "Note"            => "numbers and letters only",
                "Required"        => true,
                "Value"           => $this->userFields['Login'],
                "InputAttributes" => $style,
            ],
            "Email"     => [
                "Type"            => "string",
                "Caption"         => "Email Address",
                "Size"            => 80,
                "Required"        => true,
                "Value"           => $this->userFields['Email'],
                "InputAttributes" => $style,
            ],
            "Pass"      => [
                "Type"            => "string",
                "Caption"         => "Password",
                "Size"            => 15,
                "Required"        => true,
                "Note"            => "6-15 characters, no spaces",
                "Value"           => RandomStr(ord('a'), ord('z'), 10),
                "InputAttributes" => $style,
            ],
            "FirstName" => [
                "Type"            => "string",
                "Caption"         => "First Name",
                "Size"            => 60,
                "Required"        => true,
                "Value"           => $this->userFields['FirstName'],
                "InputAttributes" => $style,
            ],
            "LastName"  => [
                "Type"            => "string",
                "Caption"         => "Last Name",
                "Size"            => 60,
                "Required"        => true,
                "Value"           => $this->userFields['LastName'],
                "InputAttributes" => $style,
            ],
            "Country"   => [
                "Type"            => "string",
                "Caption"         => "Country",
                "Size"            => 10,
                "Required"        => true,
                "Options"         => $countryOptions,
                "Value"           => "US",
                "InputAttributes" => $style . " onchange='this.form.DisableFormScriptChecks.value = \"1\"; this.form.submit();'",
            ],
            "Street"    => [
                "Type"            => "string",
                "Caption"         => "Street Address",
                "Size"            => 80,
                "Required"        => true,
                "Value"           => $address['Address1'],
                "InputAttributes" => $style,
            ],
            "City"      => [
                "Type"            => "string",
                "Caption"         => "City",
                "Size"            => 80,
                "Required"        => true,
                "Value"           => $address['City'],
                "InputAttributes" => $style,
            ],
            "State"     => [
                "Type"            => "string",
                "Caption"         => "State",
                "Size"            => 80,
                "Required"        => true,
                "InputAttributes" => $style,
            ],
            "Zip"       => [
                "Type"            => "string",
                "Caption"         => "Zip/Postal Code",
                "Size"            => 20,
                "Required"        => true,
                "Value"           => $address['Zip'],
                "InputAttributes" => $style,
            ],
            "Agree"     => [
                "Type"               => "boolean",
                "Caption"            => "By joining BigCrumbs, you agree to their <a href='http://www.bigcrumbs.com/crumbs/terms.do' target='_blank'>Terms of Service</a> and their <a href='http://www.bigcrumbs.com/crumbs/privacy.do' target='_blank'>Privacy Policy</a>",
                "Size"               => 20,
                "Required"           => true,
                "RegExp"             => '/^1$/ims',
                "RegExpErrorMessage" => "You must agree to BigCrumbs Terms of Service and Privacy Policy",
            ],
            //			"CaptchaImage" => array(
            //				"Type" => "string",
            //				"InputType" => "html",
            //				"Caption" => "",
            //				"HTML" => "<img id='captcha' style='width: 16px; height: 16px; margin: 32px 0;' src='/lib/images/progressCircle.gif'>",
            //			),
            //			"Captcha" => array(
            //				"Type" => "string",
            //				"Caption" => "Are you human?",
            //				"Size" => 20,
            //				"Required" => true,
            //				"InputAttributes" => $style,
            //				"DoNotCache" => true,
            //			),
        ];

        // define country
        $address['CountryCode'] = strtoupper($address['CountryCode']);

        if (in_array($address['CountryCode'], array_keys($countryOptions))) {
            $fields['Country']['Value'] = $address['CountryCode'];
        }

        if (isset($values['Country']) && isset($fields['Country']['Options'][$values['Country']])) {
            $fields['Country']['Value'] = $values['Country'];
        }

        // define state
        //		$fields['State']['Options'] = array_merge(
        //			array("" => "Please select"),
        //			$this->getStateOptions($states, $fields['Country']['Value'])
        //		);
        // TODO: remove it after maintenance
        $fields['State']['Options'] = ["" => "Please select"];
        $stateCodes = array_flip($fields['State']['Options']);

        if (isset($stateCodes[$address['StateName']])) {
            $fields['State']['Value'] = $stateCodes[$address['StateName']];
        }

        //		// preload captcha
        //		if($_SERVER['REQUEST_METHOD'] == 'GET'){
        //			if(ArrayVal($_GET, 'error') != '')
        //				$fields['CaptchaImage']['HTML'] = "<img style='width: 150px; height: 80px;' id='captcha' src='https://www.bigcrumbs.com/crumbs/verify/".time().".jpg?light=1'>";
        //			else
        //				$Interface->FooterScripts[] = "parent.browserExt.setAccountInfo({
        //					accountId: 0,
        //					providerCode: 'bigcrumbs',
        //					providerName: 'Big Crumbs',
        //					login: '',
        //					login2: '',
        //					login3: '',
        //					password: '',
        //					properties: {},
        //					focusTab: false,
        //					step: 'loadRegForm'
        //				});
        //				parent.browserExt.autologin(0, null, function(error){ parent.showMessagePopup('error', 'Registration failed', error)});
        //				var regFormLoaded = false;
        //				document.getElementById('submitButtonTrigger').onclick = function(){
        //					if(!regFormLoaded && document.getElementById('captcha')){
        //						document.getElementById('fldCaptcha').focus();
        //						return false;
        //					}
        //					var form = document.forms['editor_form'];
        //					if( CheckForm( form ) ) {
        //						form.submitButton.value='yes';
        //						return true;
        //					}
        //					else
        //						return false;
        //				}
        //				parent.browserExt.onInfo = function(info){
        //					if(info == 'regFormLoaded'){
        //						var captcha = $('#captcha');
        //						captcha.bind('load', function(){
        //							captcha.attr('style', 'margin: 0; width: 150px; height: 80px;');
        //						});
        //						$('#captcha').attr('src', 'https://www.bigcrumbs.com/crumbs/verify/".time().".jpg?light=1');
        //						regFormLoaded = true;
        //					}
        //				}";
        //		}
        //		else
        //			$fields['CaptchaImage']['HTML'] = "<img style='width: 150px; height: 80px;' id='captcha' src='https://www.bigcrumbs.com/crumbs/verify/".time().".jpg?light=1'>";
//
        //		if(ArrayVal($_GET, 'error') != '' && ArrayVal($_GET, 'captcha') == 'false'){
        //			unset($fields['CaptchaImage']);
        //			unset($fields['Captcha']);
        //		}
        //		if(ArrayVal($_GET, 'error') != ''){
        //			unset($fields['Agree']);
        //		}
        return $fields;
    }

    public function getStateOptions($states, $selectedCountry)
    {
        foreach ($states['countries'] as $country) {
            if ($country['code'] == $selectedCountry) {
                $result = [];

                foreach ($country['states'] as $state) {
                    $result[$state['code']] = $state['name'];
                }

                return $result;
            }
        }
        DieTrace("no states for country: $selectedCountry");
    }

    protected function parseCaptcha()
    {
        $this->http->Log("parseCaptcha");

        if ($link = $this->http->FindSingleNode("//img[@id = 'verify']/@src")) {
            $this->http->NormalizeURL($link);
            $this->http->Log("Download Image by URL");
            $http2 = clone $this->http;
            $file = $http2->DownloadFile($link, "jpg");
        }

        if (!isset($file)) {
            return false;
        }
        $this->http->Log("file: " . $file);
        $recognizer = $this->getCaptchaRecognizer();

        try {
            $captcha = str_replace(' ', '', $recognizer->recognizeFile($file));
        } catch (CaptchaException $e) {
            $this->http->Log("exception: " . $e->getMessage());
            // Notifications
            if (strstr($e->getMessage(), "ERROR_ZERO_BALANCE")) {
                $this->sendNotification("WARNING! " . $recognizer->domain . " - balance is null");

                throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }
        unlink($file);

        return $captcha;
    }

    //	function GetPartnerFormBuilder($builder, array $options, $user) {
////		require_once __DIR__.'/partnerFormBuilder.php';
////		bigcrumbsPartnerFormBuilder($builder, $options, $user);
//	}
//
//	function GetPartnerFormTemplate() {
//		return 'common';
//	}
}
