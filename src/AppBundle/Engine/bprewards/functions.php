<?php

class TAccountCheckerBprewards extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->TimeLimit = 500;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('The email address is invalid', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.mybpstation.com/bp-driver-rewards');
        $theme_token = $this->http->FindPreg("/\"theme_token\":\"([^\"]+)/");
        $form_build_id = $this->http->FindSingleNode("//form[@id = 'user-login']//input[@name = 'form_build_id']/@value");

        if (!$this->http->ParseForm("user-login") || !$theme_token || !$form_build_id) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'https://www.mybpstation.com/system/ajax';
        //$this->http->SetInputValue('name', $this->AccountFields['Login']);
        //$this->http->SetInputValue('pass', $this->AccountFields['Pass']);
        $this->http->SetFormText("form_build_id={$form_build_id}&form_id=user_login&name=" . urlencode($this->AccountFields['Login']) . "&pass=" . urlencode($this->AccountFields['Pass']) . "&_triggering_element_name=op&_triggering_element_value=Confirm&ajax_html_ids%5B%5D=skip-link&ajax_html_ids%5B%5D=link-station-finder&ajax_html_ids%5B%5D=link-station-finder&ajax_html_ids%5B%5D=home-link&ajax_html_ids%5B%5D=link-station-finder&ajax_html_ids%5B%5D=link-station-finder&ajax_html_ids%5B%5D=link-our-fuels&ajax_html_ids%5B%5D=link-our-credit-cards&ajax_html_ids%5B%5D=DR_login&ajax_html_ids%5B%5D=user-menu-link-menu-1133&ajax_html_ids%5B%5D=user-menu-link-menu-1133&ajax_html_ids%5B%5D=search-block-form&ajax_html_ids%5B%5D=edit-search-block-form--2&ajax_html_ids%5B%5D=edit-actions--3&ajax_html_ids%5B%5D=edit-submit--3&ajax_html_ids%5B%5D=main-content&ajax_html_ids%5B%5D=link-contact-us&ajax_html_ids%5B%5D=link-privacy-policy&ajax_html_ids%5B%5D=loginModal&ajax_html_ids%5B%5D=ajax-user-login-wrapper&ajax_html_ids%5B%5D=user-login&ajax_html_ids%5B%5D=loginName&ajax_html_ids%5B%5D=pass&ajax_html_ids%5B%5D=edit-join&ajax_html_ids%5B%5D=edit-actions&ajax_html_ids%5B%5D=edit-submit&ajax_html_ids%5B%5D=forgotPasswordModal&ajax_html_ids%5B%5D=ajax-user-pass-wrapper&ajax_html_ids%5B%5D=user-pass&ajax_html_ids%5B%5D=name&ajax_html_ids%5B%5D=edit-actions--2&ajax_html_ids%5B%5D=edit-submit--2&ajax_html_ids%5B%5D=video-player-modal&ajax_html_ids%5B%5D=video-player-modal_html5_api&ajax_html_ids%5B%5D=video-player-modal_component_289_description&ajax_html_ids%5B%5D=ui-id-1&ajax_html_ids%5B%5D=ui-id-2&ajax_html_ids%5B%5D=ui-id-3&ajax_html_ids%5B%5D=&ajax_page_state%5Btheme%5D=bp_theme&ajax_page_state%5Btheme_token%5D=" . $theme_token . "&ajax_page_state%5Bcss%5D%5Bmodules%2Fsystem%2Fsystem.base.css%5D=1&ajax_page_state%5Bcss%5D%5Bmisc%2Fui%2Fjquery.ui.core.css%5D=1&ajax_page_state%5Bcss%5D%5Bmisc%2Fui%2Fjquery.ui.theme.css%5D=1&ajax_page_state%5Bcss%5D%5Bmisc%2Fui%2Fjquery.ui.menu.css%5D=1&ajax_page_state%5Bcss%5D%5Bmisc%2Fui%2Fjquery.ui.autocomplete.css%5D=1&ajax_page_state%5Bcss%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fdate%2Fdate_api%2Fdate.css%5D=1&ajax_page_state%5Bcss%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fdate%2Fdate_popup%2Fthemes%2Fdatepicker.1.7.css%5D=1&ajax_page_state%5Bcss%5D%5Bmodules%2Ffield%2Ftheme%2Ffield.css%5D=1&ajax_page_state%5Bcss%5D%5Bmodules%2Fnode%2Fnode.css%5D=1&ajax_page_state%5Bcss%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Foffice_hours%2Foffice_hours.css%5D=1&ajax_page_state%5Bcss%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fextlink%2Fextlink.css%5D=1&ajax_page_state%5Bcss%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fviews%2Fcss%2Fviews.css%5D=1&ajax_page_state%5Bcss%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fctools%2Fcss%2Fctools.css%5D=1&ajax_page_state%5Bcss%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fpanels%2Fcss%2Fpanels.css%5D=1&ajax_page_state%5Bcss%5D%5Bsites%2Fall%2Fthemes%2Fcustom%2Fbp_theme%2Fcss%2Fstyle.css%5D=1&ajax_page_state%5Bjs%5D%5B0%5D=1&ajax_page_state%5Bjs%5D%5B1%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fctools%2Fjs%2Fajax-responder.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcontrib%2Fbootstrap%2Fjs%2Fbootstrap.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fjquery_update%2Freplace%2Fjquery%2F1.10%2Fjquery.min.js%5D=1&ajax_page_state%5Bjs%5D%5Bmisc%2Fjquery.once.js%5D=1&ajax_page_state%5Bjs%5D%5Bmisc%2Fdrupal.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fjquery_update%2Freplace%2Fui%2Fui%2Fminified%2Fjquery.ui.core.min.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fjquery_update%2Freplace%2Fui%2Fui%2Fminified%2Fjquery.ui.widget.min.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fjquery_update%2Freplace%2Fui%2Fui%2Fminified%2Fjquery.ui.position.min.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fjquery_update%2Freplace%2Fui%2Fui%2Fminified%2Fjquery.ui.menu.min.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fjquery_update%2Freplace%2Fui%2Fui%2Fminified%2Fjquery.ui.autocomplete.min.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fjquery_update%2Freplace%2Fui%2Fexternal%2Fjquery.cookie.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fjquery_update%2Freplace%2Fmisc%2Fjquery.form.min.js%5D=1&ajax_page_state%5Bjs%5D%5Bmisc%2Fajax.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fjquery_update%2Fjs%2Fjquery_update.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fajax_error_behavior%2Fajax_error_behavior.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fmodules%2Fcustom%2Fbp_epsilon%2Fjs%2Fbp_epsilon.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fgss%2Fscripts%2Fautocomplete.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fmodules%2Fcontrib%2Fextlink%2Fextlink.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcontrib%2Fbootstrap%2Fjs%2Fmisc%2F_progress.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcustom%2Fbp_theme%2Fbootstrap%2Fassets%2Fjavascripts%2Fbootstrap%2Faffix.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcustom%2Fbp_theme%2Fbootstrap%2Fassets%2Fjavascripts%2Fbootstrap%2Falert.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcustom%2Fbp_theme%2Fbootstrap%2Fassets%2Fjavascripts%2Fbootstrap%2Fbutton.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcustom%2Fbp_theme%2Fbootstrap%2Fassets%2Fjavascripts%2Fbootstrap%2Fcarousel.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcustom%2Fbp_theme%2Fbootstrap%2Fassets%2Fjavascripts%2Fbootstrap%2Fcollapse.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcustom%2Fbp_theme%2Fbootstrap%2Fassets%2Fjavascripts%2Fbootstrap%2Fdropdown.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcustom%2Fbp_theme%2Fbootstrap%2Fassets%2Fjavascripts%2Fbootstrap%2Fmodal.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcustom%2Fbp_theme%2Fbootstrap%2Fassets%2Fjavascripts%2Fbootstrap%2Ftooltip.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcustom%2Fbp_theme%2Fbootstrap%2Fassets%2Fjavascripts%2Fbootstrap%2Fpopover.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcustom%2Fbp_theme%2Fbootstrap%2Fassets%2Fjavascripts%2Fbootstrap%2Fscrollspy.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcustom%2Fbp_theme%2Fbootstrap%2Fassets%2Fjavascripts%2Fbootstrap%2Ftab.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcustom%2Fbp_theme%2Fbootstrap%2Fassets%2Fjavascripts%2Fbootstrap%2Ftransition.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcustom%2Fbp_theme%2Fjs%2Fvendor.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcustom%2Fbp_theme%2Fjs%2Futil.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcustom%2Fbp_theme%2Fjs%2Fservices.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcustom%2Fbp_theme%2Fjs%2Fcomponents.js%5D=1&ajax_page_state%5Bjs%5D%5Bsites%2Fall%2Fthemes%2Fcontrib%2Fbootstrap%2Fjs%2Fmisc%2Fajax.js%5D=1&ajax_page_state%5Bjquery_version%5D=1.10", "&");

        return true;
    }

//    function GetRedirectParams($targetURL = null) {
//        $arg = parent::GetRedirectParams($targetURL);
//        $arg["CookieURL"] = "https://mybpstation.com/";
//        $arg["SuccessURL"] = "https://mybpstation.com/";
//
//        return $arg;
//    }

    public function checkErrors()
    {
        // maintenance
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'My BP Station is currently under maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // An Internal Error Has Occurred.
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'An Internal Error Has Occurred.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // My BP Station is currently under maintenance. We should be back shortly. Thank you for your patience.
        if ($message = $this->http->FindPreg("/(My BP Station is currently under maintenance\. We should be back shortly\. Thank you for your patience\.)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // 503 Backend fetch failed
        if ($message = $this->http->FindSingleNode("//title[contains(text(), '503 Backend fetch failed')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $headers = [
            "X-Requested-With" => "XMLHttpRequest",
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
        ];
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm($headers)) {
            if (empty($this->http->Response['body'])) {
                $this->DebugInfo = 'Retry for empty body';

                throw new CheckRetryNeededException(3);
            }

            return $this->checkErrors();
        }// if (!$this->http->PostForm($headers))
        $this->http->RetryCount = 2;

        if ($this->http->FindPreg('/(?:"command":"reload"|"command":"redirect")/')) {
            return true;
        }

        // Login failed. Please try again.
        if ($message = $this->http->FindPreg("/Login failed\. Please try again\./")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your account has been locked out. Please reset your password.
        if ($message = $this->http->FindPreg("/(Your account has been locked out\.)/")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // A system error has occured. Please try again later.
        if ($message = $this->http->FindPreg("/A system error has occured\. Please try again later\./")) {
            throw new CheckRetryNeededException(2, 10, $message);
        }

//        // The email address is invalid
//        if (count($this->http->FindNodes("//div[contains(text(), 'An email is required.')]")) == 2)
        //			throw new CheckException('The email address is invalid', ACCOUNT_INVALID_PASSWORD);
        //		// Please answer the security question below to get a temporary password
        //		if ($message = $this->http->FindSingleNode('//h2[contains(text(), "Please answer the security question below to get a temporary password")]'))
//            $this->throwProfileUpdateMessageException();
//        // There was a problem with the request. Please try again.
//        if ($message = $this->http->FindSingleNode('//div[contains(text(), "There was a problem with the request. Please try again.")]'))
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
//        // hard code
//        if ($this->http->currentUrl() == 'https://mybpstation.com/' && $this->AccountFields['Login'] == 'mmainka@ameritech.net')
//            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.mybpstation.com/account");
        // Balance - REWARDS BALANCE
        $noBalance = false;

        if (!$this->SetBalance($this->http->FindSingleNode('//div[contains(text(), "Rewards Balance")]/following-sibling::p[1]', null, true, '#\s*(.*)\s+per\s+gallon#i')) && $this->http->FindSingleNode('//div[contains(text(), "Rewards Type")]/following-sibling::p[contains(text(), "Go to your United MileagePlus® account to view your balance.")]')) {
            $this->logger->notice("Account without Balance");
            $noBalance = true;
        }
        // Name
        $this->http->GetURL("https://www.mybpstation.com/account/account-info");
        $xpath = "//form[@id = '-bp-account-info-update-my-account-form']";
        $this->SetProperty('Name', beautifulName(
            $this->http->FindSingleNode("{$xpath}//input[@name = 'firstName']/@value") . ' ' . $this->http->FindSingleNode("{$xpath}//input[@name = 'lastName']/@value")));

        // AccountID: 2048627, 4099920, 4099428
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $noBalance && !empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }
    }
}
