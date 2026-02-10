var plugin = {
    hideOnStart: false,
    // keepTabOpen: true,//todo
    mobileUserAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.88 Safari/537.36',
    blockImages: /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor),

    hosts: {
        'www.britishairways.com'     : true,
        'accounts.britishairways.com': true,
    },

    getFocusTab: function (account, params) {
        return true;
    },

	getStartingUrl: function (params) {
        return plugin.setCountryUrl(params, 'https://www.britishairways.com/travel/home/execclub/_gf/**');
	},

    setCountryUrl: function(params, url) {
        let country = params.account.login2;

        if (typeof country  === "string" && country.length > 0) {
            country = country.toLowerCase();
        }

        return url.replace(/\*\*/, 'en_' + country);
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }
            if (isLoggedIn === null && counter > 25) {
                clearInterval(start);
                provider.logBody("lastPage");
                if ($('h2:contains("Error 404--Not Found"):visible, p:contains("Error 403 - You don\'t have enough permissions to proceed further")').length > 0) {
                    provider.setError(util.errorMessages.providerErrorMessage, true);
                    return false;
                }
                var maintenance = $('p:contains("Sorry, our website is unavailable while we make a quick update to our systems."):visible, p:contains("Sorry, there seems to be a technical problem. Please try again in a few minutes"):visible, p:contains("Both ba.com and our apps are temporarily unavailable while we make some planned improvements to our systems.")');
                if (maintenance.length > 0)
                    provider.setError(util.errorMessages.providerErrorMessage, true);
                else
                    provider.setError(util.errorMessages.unknownLoginState);
                return;
            }
            counter++;
        }, 1000);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        browserAPI.log('location: ' + document.location.href);
		if (
            (document.querySelector("ba-header") && document.querySelector("ba-header").shadowRoot.querySelector("#logoutLinkDesktop"))
            || $('ul.memberInfo, #logoutLinkDesktop').length > 0
            || $('.membership-details:contains("Membership number:")').length
            || $('#membershipNumberValue:visible').length
            || $('.logOut:visible').length
        ) {
			browserAPI.log("LoggedIn");
			return true;
		}
		if ($('.account.logged-out:visible, #execLoginrForm:visible, form:has(input#username):visible').length > 0) {
			browserAPI.log("not LoggedIn");
			return false;
		}
		// Web Page Blocked
		let error = $('p:contains("Unfortunately access to the web page you were trying to visit has been blocked as our systems have detected unusual traffic from your computer network."):visible');
		if (error.length === 0)
            error = $('p:contains("We are experiencing technical issues today with our website."):visible');
        // Sorry, there seems to be a technical problem. Please try again in a few minutes.
		if (error.length === 0)
            error = $('p:contains("Sorry, there seems to be a technical problem. Please try again in a few minutes."):visible');
        /*
         * Major IT system failure . latest information at 23.30 Saturday May 27
         *
         * Following the major IT system failure experienced throughout Saturday,
         * we are continuing to work hard to fully restore all of our global IT systems.
         *
         * Flights on Saturday May 27
         */
		if (error.length === 0)
            error = $('p:contains("Following the major IT system failure experienced throughout"):visible');
		if (error.length > 0) {
            provider.setError([error.text(), util.errorCodes.providerError], true);
			return false;
		}
        return null;
	},

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.trim($('p.membership-details > .personaldata').text());

        if (number.length === 0) {
            number = util.findRegExp($('p:contains("number:")').text(), /number:\s*(\d+)/i)
        }

        browserAPI.log("number: " + number);
        return typeof account.properties !== 'undefined'
            && (typeof account.properties.Number !== 'undefined')
            && account.properties.Number !== ''
            && number === account.properties.Number;
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            let logout = $('#header').find('a:contains("Log out"):eq(0)');
            browserAPI.log('logout button: ' + logout.length);
            // new design
            if (logout.length === 0) {
                logout = $('.logOut:visible');
                browserAPI.log('logout button (new design): ' + logout.length);
            }

            if (logout.length > 0) {
                logout.get(0).click();
                return;
            }

            // refs #15791
            document.location.href = 'https://www.britishairways.com/travel/loginr/execclub/_gf/en_us?eId=109004';
        });
	},

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");

        if (typeof(params.account.itineraryAutologin) === "boolean" && params.account.itineraryAutologin && params.account.accountId === 0) {
			provider.setNextStep('getConfNoItinerary', function(){
                document.location.href = plugin.setCountryUrl(params, "https://www.britishairways.com/travel/managebooking/public/**");
            });
			return;
		}

        const form = $('form[name = "form1"], form:has(input#username)');
        form.find('input[name = "password"], input#password').val(params.account.password);

        if (form.length === 0) {
            provider.logBody("lastPage");
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("logBody -> login");
        provider.logBody("login");
        browserAPI.log("submitting saved credentials");
        form.find('input[name = "membershipNumber"], input#loginid, input#username').val(params.account.login);
        form.find('input[name = "password"], input#password').val(params.account.password);
        util.sendEvent(form.find('input[name = "password"], input#password').get(0), 'click');
        util.sendEvent(form.find('input[name = "password"], input#password').get(0), 'input');
        util.sendEvent(form.find('input[name = "password"], input#password').get(0), 'change');
        util.sendEvent(form.find('input[name = "password"], input#password').get(0), 'blur');
        provider.setNextStep('checkLoginErrors', function () {

            if (form.find('#ulp-hcaptcha iframe:visible').length > 0) {
                browserAPI.log("logBody -> captcha");
                provider.logBody("captcha");
                provider.reCaptchaMessage();
                let counter = 0;
                let login = setInterval(function () {
                    browserAPI.log("waiting... " + counter);
                    if (counter > 120) {
                        clearInterval(login);
                        let errElement = form.find('#error-element-captcha:visible, #error-element-password:visible');
                        if (errElement.length > 0 && util.filter(errElement.text()) !== '') {
                            plugin.checkLoginErrors(params);
                            browserAPI.log("logBody -> errElement");
                            provider.logBody("errElement");
                        } else
                            provider.setError(util.errorMessages.captchaErrorMessage, true);
                    }
                    counter++;
                }, 1000);
            }
            else {
                browserAPI.log("captcha is not found");
                browserAPI.log('buttons: ' + form.find('button[type = "submit"]').length);
                browserAPI.log('hashAnchor: ' + $('#hashAnchor').attr('data-event'));
                provider.eval("try { formEvent('109001'); } catch (e) { document.querySelector('button[type = \"submit\"][name = \"action\"]').click(); }");
            }
        });
	},

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        browserAPI.log('location1: ' + document.location.href);

        // Verify you are human.
        let form = $('form[name = "form1"], form:has(input#username)');
        let errCaptcha = form.find('#error-element-captcha:visible');
        if (errCaptcha.length > 0 && errCaptcha.text().includes('Verify you are human.')) {
            provider.setError(util.errorMessages.captchaErrorMessage);
            return;
        }
        // We couldn't sign you in at the moment. Please review your login details. If issue persists, your account may be locked. To unlock it, check your email.
        let errPassword = form.find('#error-element-password:visible');
        if (errPassword.length > 0 && errPassword.text().includes('t sign you in at the moment. Please review your login details')) {
            provider.setError([util.filter(errPassword.text()), util.errorCodes.invalidPassword], true);
            return;
        }
        // british defender workaround
        if ($('h1:contains("This page is not available"):visible').length > 0) {
            var retry = $.cookie("british.com_aw_retry_"+params.account.login);
            browserAPI.log(">>> This page is not available");
            browserAPI.log(">>> retry: " + retry);
            if ((typeof(retry) === 'undefined' || retry === null) || retry < 2) {
                if (typeof(retry) === 'undefined' || retry === null)
                    retry = 0;
                browserAPI.log(">>> retry: " + retry);
                retry++;
                $.cookie("british.com_aw_retry_" + params.account.login, retry, {
                    expires: 0.01,
                    path: '/',
                    domain: '.britishairways.com',
                    secure: true
                });
                provider.setNextStep('start', function () {
                    document.location.href = plugin.getStartingUrl(params);
                });
                return;
            }
        }// if ($('h1:contains("This page is not available"):visible').length > 0)
        // Validation question
        /*var form = $('form#captcha_form');
        if (form.length > 0 && $('title:contains("Validation - British Airways")').length > 0) {
            browserAPI.log("Sorry to interrupt you, we need to check you are a real person before you can continue");
            var captchaRetry = $.cookie("british.com_aw_captchaRetry_" + params.account.login);
            if ((typeof(captchaRetry) === 'undefined' || captchaRetry === null) || captchaRetry < 2) {
                if ((typeof(captchaRetry) === 'undefined' || captchaRetry === null))
                    captchaRetry = 0;
                browserAPI.log(">>> captchaRetry: " + captchaRetry);
                captchaRetry++;
                $.cookie("british.com_aw_captchaRetry_" + params.account.login, captchaRetry, { expires: 0.01, path:'/', domain: '.britishairways.com', secure: true });
            }
            else {
                browserAPI.log("We could not recognize captcha. Please try again later.");
                provider.reCaptchaMessage();
                provider.setNextStep('checkLoginErrors', function(){
                    setTimeout(function() {
                        provider.setError(util.errorMessages.captchaErrorMessage, true);
                    }, 120000);
                });
                return;
            }
            setTimeout(function() {
                var captcha = $('form#captcha_form').find('div.g-recaptcha');
                //browserAPI.log("waiting captcha -> " + captcha);
                if (captcha.length > 0) {
                    if(!provider.isMobile) {
                        provider.reCaptchaMessage();
                        browserAPI.log("waiting...");
                        provider.setNextStep('loginComplete', function() {
                            setTimeout(function() {
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }, 120000);
                        });
                    } else {
                        provider.command('show', function(){
                            provider.reCaptchaMessage();
                            var form = $('form[name=captcha_form]');
                            var submit = form.find('input[type=submit]');
                            submit.removeAttr('onclick');
                            submit.unbind('click');
                            submit.bind('click', function(event){
                                provider.command('hide', function () {
                                    provider.setNextStep('checkLoginErrors', function(){
                                        browserAPI.log("captcha entered by user");
                                        form.submit();
                                    });
                                });
                                event.preventDefault();
                            });
                        });
                        //$('form[name=captcha_form] input[type=submit]')
                        //Do anything else with captcha
                        //provider.setError(['We could not recognize captcha. Please try again later.', util.errorCodes.providerError], true);
                    }
                }// if (captcha.length > 0)
                else
                    browserAPI.log("captcha is not found");
            }, 2000);
            return;
        }// Validation question

        if ($('h1:contains("Please change your PIN to a password"):visible').length > 0
            || $('div:contains("Please could you confirm the details displayed, amend or supply them as necessary"):visible').length > 0) {
            provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
            return;
        }*/// if ($('h1:contains("Please change your PIN to a password"):visible').length > 0)

        /*if (plugin.checkProviderErrors(params))
            return;*/

        if ($('input[name = "code"]:visible').length > 0) {
            browserAPI.log("logBody -> 2fa");
            provider.logBody("2fa");
            if (params.autologin)
                provider.setError(['It seems that British Airways (Executive Club) needs to identify this computer before you can log in. Please follow the instructions on the new tab to get this computer authorized and then please try to auto-login again.', util.errorCodes.providerError], true);
            else {
                provider.showFader('It seems that British Airways (Executive Club) needs to identify this computer before you can update this account. Please follow the instructions on the new tab to get this computer authorized and then please try to update this account again.');
                provider.setNextStep('loadSend2fa', function () {
                    let counter = 0;
                    let login = setInterval(function () {
                        browserAPI.log("waiting... " + counter);
                        let success = (document.querySelector("ba-header") && document.querySelector("ba-header").shadowRoot.querySelector("#logoutLinkDesktop"));
                        if (success) {
                            clearInterval(login);
                            plugin.checkLoginErrors(params);
                            browserAPI.log("logBody -> 2faSuccess");
                            provider.logBody("2faSuccess");
                        }

                        if (counter > 120) {
                            clearInterval(login);
                            let errElement = form.find('#error-element-captcha:visible, #error-element-password:visible');
                            if (errElement.length > 0 && util.filter(errElement.text()) !== '') {
                                plugin.checkLoginErrors(params);
                                browserAPI.log("logBody -> errElement");
                                provider.logBody("errElement");
                            } else {
                                let questionMessage = $('p:contains("We\'ve sent a text message to"), p:contains("We\'ve sent an email with your code to")');
                                let questionId =
                                    $('p:contains("We\'ve sent a text message to")').closest('header').next().find('span:contains("XXXXXX")');
                                if (questionId.length === 0)
                                    questionId =
                                        $('p:contains("We\'ve sent an email with your code to")').closest('header').next().find('span:contains("****")');
                                if (questionMessage.length && questionId.length) {
                                    provider.setError([questionMessage.text() + ' ' + questionId.text(), util.errorCodes.question], true);
                                    return true;
                                }
                            }
                        }
                        counter++;
                    }, 1000);
                });
            }

            return true;
        }

        browserAPI.log('Location2: ' + document.location.href);
        if (document.location.href.indexOf('/travel/loginr/public/en_') !== -1) {
            browserAPI.log('Wait "We have updated our Terms and Conditions"');
            util.waitFor({
                timeout: 15,
                selector: 'h3:contains("We have updated our Terms and Conditions."):visible',
                success: function(){
                    provider.setError(["British Airways (Executive Club) website is asking you to accept their new Terms and Conditions, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);
                },
                fail: function(){
                    plugin.loginComplete(params);
                }
            });
        }
        else
            plugin.loginComplete(params);

        /*
         * Sorry, there seems to be a technical problem. Please try again in a few minutes, and please contact us if it still doesn't work.
         * We apologise for the inconvenience.
         */
       /* var technicalProblem = $('p:contains("Sorry, there seems to be a technical problem."):visible');
        if (technicalProblem.length > 0)
            provider.setError([technicalProblem.text(), util.errorCodes.providerError], true);
        // for logs
        if (provider.isMobile)
            provider.setError(['Unknown error', util.errorCodes.engineError]);
        else
            provider.complete();*/
	},

    loadSend2fa: function (params) {
        browserAPI.log('loadSend2fa');
        provider.showFader('It seems that British Airways (Executive Club) needs to identify this computer before you can update this account. Please follow the instructions on the new tab to get this computer authorized and then please try to update this account again.');
        provider.setNextStep('checkLoginErrors', function () {
            let counter = 0;
            let login = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                let success = (document.querySelector("ba-header") && document.querySelector("ba-header").shadowRoot.querySelector("#logoutLinkDesktop"));
                if (success) {
                    clearInterval(login);
                    plugin.checkLoginErrors(params);
                    browserAPI.log("logBody -> 2faSuccess");
                    provider.logBody("2faSuccess");
                }
                if (counter > 120) {
                    clearInterval(login);
                    let questionMessage = $('p:contains("We\'ve sent a text message to"), p:contains("We\'ve sent an email with your code to")');
                    let questionId =
                        $('p:contains("We\'ve sent a text message to")').closest('header').next().find('span:contains("XXXXXX")');
                    if (questionId.length === 0)
                        questionId =
                            $('p:contains("We\'ve sent an email with your code to")').closest('header').next().find('span:contains("****")');
                    if (questionMessage.length && questionId.length) {
                        provider.setError([questionMessage.text() + ' ' + questionId.text(), util.errorCodes.question], true);
                        return true;
                    }
                }
                counter++;
            }, 1000);
        });
    },
    checkProviderErrors: function (params) {
        browserAPI.log(">>> check provider errors");
        var errors = $('div.errorMessage p');
        if (errors.length == 0)
            errors = $('div#blsErrosContent > div > ul');
        if (errors.length == 0)
            errors = $('p:contains("You have made too many invalid login attempts.")');
        if (errors.length === 0) {
            errors = $('span.ulp-input-error-message:visible, div#prompt-alert:visible > p');
        }
        if (errors.length > 0 && $.inArray(util.trim(errors.text()), ['', 'No data found for Gender']) === -1) {
            provider.setError(util.trim(errors.text()), true);
            return true;
        }
        if ($('h1:contains(You have not yet validated your email address)').length > 0) {
            provider.setError(["British Airways website needs you to update your profile, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);
            return true;
        }

        let technicalError = $('p:contains("Unfortunately our systems are not responding"):visible');
        if (technicalError.length > 0) {
            provider.setError([technicalError.text(), util.errorCodes.providerError], true);
            return true;
        }

        if (errors.length == 0 && $('h1:contains("You have not yet validated your email address"):visible').length > 0) {
            provider.setError(["British Airways website needs you to update your profile, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);
            return true;
        }
        if (errors.length == 0 && $('h1:contains("Internal Server Error - Read"):visible').length > 0) {
            provider.setError(util.errorMessages.providerErrorMessage, true);
            return true;
        }

        if ($('input[name = "code"]:visible').length > 0) {
            if (params.autologin)
                provider.setError(['It seems that British Airways (Executive Club) needs to identify this computer before you can log in. Please follow the instructions on the new tab to get this computer authorized and then please try to auto-login again.', util.errorCodes.providerError], true);
            else {
                provider.setError(['It seems that British Airways (Executive Club) needs to identify this computer before you can update this account. Please follow the instructions on the new tab to get this computer authorized and then please try to update this account again.', util.errorCodes.providerError], true);
            }

            return true;
        }

        /**
         * Sorry, there seems to be a technical problem. Please try again in a few minutes, and please contact us if it still doesn't work.
         * We apologise for the inconvenience.
         */
        if (errors.length == 0 && $('p:contains("Sorry, there seems to be a technical problem. Please try again in a few minutes"):visible').length > 0) {
            errors = $('p:contains("Sorry, there seems to be a technical problem. Please try again in a few minutes"):visible');
        }
        // We regret to advise that this section of the site is temporarily unavailable.
        if (errors.length == 0 && $('p:contains("We regret to advise that this section of the site is temporarily unavailable"):visible').length > 0) {
            errors = $('p:contains("We regret to advise that this section of the site is temporarily unavailable"):visible');
        }
        // Unfortunately access to the web page you were trying to visit has been blocked as our systems have detected unusual traffic from your computer network.
        if (errors.length == 0 && $('p:contains("Unfortunately access to the web page you were trying to visit has been blocked as our systems have detected unusual traffic from your computer network."):visible').length > 0) {
            errors = $('p:contains("Unfortunately access to the web page you were trying to visit has been blocked as our systems have detected unusual traffic from your computer network."):visible');
        }
        if (errors.length === 0 && $('p:contains("Error 403 - You don\'t have enough permissions to proceed further"):visible').length > 0) {
            errors = $('p:contains("Error 403 - You don\'t have enough permissions to proceed further"):visible');
        }
        // Sorry, there's a problem with our systems. Please try again, and if it still doesn't work, you might want to try again later.
        if (errors.length == 0 && $('li:contains("Sorry, there\'s a problem with our systems. Please try again, and if it still doesn\'t work, you might want to try again later."):visible').length > 0) {
            errors = $('li:contains("Sorry, there\'s a problem with our systems. Please try again, and if it still doesn\'t work, you might want to try again later."):visible');
        }
        // Sorry we can't show you this page at the moment.
        if (errors.length == 0 && $('p:contains("Sorry we can\'t show you this page at the moment."):visible').length > 0)
            errors = $('p:contains("Sorry we can\'t show you this page at the moment."):visible');
        // Two Factor Authentication    // refs #14276
        if (errors.length == 0 && $('h3:contains("We need to confirm your identity"):visible').length > 0) {
            if (params.autologin)
                provider.setError(['It seems that British Airways needs to confirm your identity before you can log in. Please follow the instructions on the new tab (the one that shows your British Airways authentication options) to get you authorized and then please try to auto-login again.', util.errorCodes.providerError], true);/*review*/
            else
                provider.setError(['It seems that British Airways needs to confirm your identity before you can update this account. Please follow the instructions on the new tab (the one that shows your British Airways authentication options) to get you authorized and then please try to update this account again.', util.errorCodes.providerError], true);/*review*/
            return true;
        }
        if (
            $('form[id = "TFA-code-verfication-form"] input[name = "confirmpassword"]:visible').length
            && $('form[id = "TFA-code-verfication-form"] input[name = "newPassword"]:visible').length
        ) {
            provider.setError(["British Airways website needs you to update your profile, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);
        }
        if (errors.length > 0) {
            provider.setError([errors.text(), util.errorCodes.providerError], true);
            return true;
        }
        if (errors.length == 0) {
            errors = $('p:contains("Your account is now locked for up to 24 hours unless you"):visible, h1:contains("Your account is now locked"):visible');
            if (errors.length > 0) {
                provider.setError([errors.text(), util.errorCodes.lockout], true);
                return true;
            }// if (errors.length > 0)
        }// if (error.length == 0)

        return false;
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        browserAPI.log("logBody -> loginComplete");
        provider.logBody("loginComplete");
        if (typeof(params.account.itineraryAutologin) === "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function(){
                document.location.href = plugin.setCountryUrl(params, 'https://www.britishairways.com/travel/viewaccount/execclub/_gf/**/device-all?eId=106010');
            });
            return;
        }

        if (params.autologin) {
            browserAPI.log("only autologin");
            provider.complete();
            return;
        }

        plugin.loadAccount(params);
	},

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
		var properties = params.account.properties.confFields;
		var form = $('form#simpleform');
		if (form.length > 0 && typeof(properties) == "object") {
            form.find('input[name="bookingRef"]').val(properties.ConfNo);
            form.find('input[name="lastname"]').val(properties.LastName);
            provider.setNextStep('itineraryLoginComplete', function () {
                form.find('#findbookingbuttonsimple').click();
            });
		}// if (form.length > 0 && typeof(properties) == "object")
		else
            provider.setError(util.errorMessages.itineraryFormNotFound);
	},

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
		var link = $('a[href*="bookingRef=' + params.account.properties.confirmationNumber + '"][href*="britishairways.com"]');
		if (link.length === 0) {
			if (typeof(params.account.properties.confFields) !== "object") {
                provider.setError(util.errorMessages.itineraryNotFound);
                throw 'Itinerary not found';
            }
			else
				plugin.getConfNoItinerary(params)
		}// if (link.length == 0)
		else {
			provider.setNextStep('itineraryLoginComplete', function(){
                document.location.href = link.attr('href');
            });
		}
	},

    itineraryLoginComplete: function (params) {
		provider.complete();
	},

    loadAccount: function (params) {
        browserAPI.log("loadAccount");

        // https://www.britishairways.com/travel/viewaccount/inet/en_br?eId=106011

        // Make sure that we are on the right page

        if (document.location.href.indexOf('/travel/echome/execclub/_gf/en_') === -1 &&
            // You are not a member of this loyalty program.
            document.location.href.indexOf('/travel/viewaccount/inet/en_') === -1) {

            provider.setNextStep('parse', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
            return;
        }

        plugin.parse(params);
    },

    getCookie: function (name) {
        var matches = document.cookie.match(new RegExp(
            "(?:^|; )" + name.replace(/([.$?*|{}()\[\]\\\/+^])/g, '\\$1') + "=([^;]*)"
        ));
        return matches ? decodeURIComponent(matches[1]) : undefined;
    },

    ajaxRequest: function (url = '', method = 'POST', data = null, withCredentials = true, callback, callbackError) {
        browserAPI.log('plugin.ajax');
        browserAPI.log(url + ' => ' + method + ' ' + (data ? JSON.stringify(data) : null));
        return $.ajax({
            url: url,
            type: method,
            beforeSend: function (request) {
                request.setRequestHeader('Accept', 'application/json, application/javascript');
                request.setRequestHeader('Cache-Control', 'application/json');
                request.setRequestHeader('Ba_api_context', 'https://www.britishairways.com/api/sc4');
                request.setRequestHeader('Ba_client_applicationname', 'ba.com');
                request.setRequestHeader('Ba_client_devicetype', 'DESKTOP');
                request.setRequestHeader('Ba_client_organisation', 'BA');
                request.setRequestHeader('Authorization', 'Bearer ' + plugin.getCookie('token'));
            },
            data: (data ? JSON.stringify(data) : null),
            crossDomain: true,
            dataType: 'json',
            cache: false,
            async: false,
            xhrFields: {
                withCredentials: withCredentials
            },
            success: function (response) {
                if (response) {
                    callback(response);
                } else {
                    browserAPI.log('ajax error, response null');
                    callbackError(response);
                }
            },
            error: function (xhr, status) {
                browserAPI.log('ajax error' + xhr.responseText);
                callbackError(xhr.responseText);
            }
        });
    },


    // 31 May 2025
    formatDate: function (str) {
        browserAPI.log('formatDate');
        browserAPI.log(str);
        const currentDate = new Date(str);
        const months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        return currentDate.getDate() + ' ' + months[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
    },

    parse: function (params) {
        browserAPI.log("parse");
        browserAPI.log("logBody -> parse");
        provider.logBody("parse");
        let data = {};

        plugin.ajaxRequest('https://www.britishairways.com/api/sc4/badotcomadapter-bdca/rs/v1/customers;dataGroups=loyalties;businessContext=HomePage?locale=en_GB&locale=en_US',
            'GET', null, false, function (customer) {
                let detail = customer.customer.registeredCustomer.loyaltyAccountDetails;
                if (typeof customer.customer == 'undefined') {
                    return;
                }


                browserAPI.log("Number: " + detail.loyaltyAccountNumber);
                data.Number = detail.loyaltyAccountNumber;

                browserAPI.log("Balance: " + detail.individualAccountBalance.balance);
                data.Balance = detail.individualAccountBalance.balance;

                data.YearEnds = plugin.formatDate(detail.tierLevelEndDate);
                browserAPI.log("YearEnds: " + data.YearEnds);

                data.CardExpiryDate = plugin.formatDate(detail.execCardExpiryDate);
                browserAPI.log("CardExpiryDate: " + data.CardExpiryDate);

                if (detail.renewalTierPointsThreshold.balance) {
                    browserAPI.log("TierPoints: " + detail.renewalTierPointsThreshold.balance);
                    data.TierPoints = detail.renewalTierPointsThreshold.balance;
                }
                if (detail.lifeTimeTierPoints.balance) {
                    browserAPI.log("LifetimeTierPoints: " + detail.lifeTimeTierPoints.balance);
                    data.LifetimeTierPoints = detail.lifeTimeTierPoints.balance;
                }

                // Date of joining the club
                switch (detail.tierLevel) {
                    case 'EXECUTIVE_BLUE':
                        data.Level = 'Blue Member';
                        break;
                }

            }, function (response) {
                browserAPI.log('Parse Props error: ' + response);
            });



       /* let name = $('li[class = "member-name"] > a > .personaldata');
        if (name.length === 0) {
            browserAPI.log("Name v.2");
            name = $('div.exec-member-inforamtion > p.personaldata');
        }
        // new design
        if (name.length === 0) {
            browserAPI.log("Name v.3");
            name = $('span.login');
        }
        if (name.length > 0) {
            name = util.beautifulName( util.filter(name.text().replace(/\&nbsp;/ig, ' ')) );
            data.Name = name;
            browserAPI.log("Name: " + data.Name);
        } else
            browserAPI.log("Name not found");

        // Member Number membership-details
        let number = $('p.membership-details > .personaldata');
        if (number.length > 0) {
            data.Number = util.trim(number.text());
            browserAPI.log("Number: " + data.Number);
        } else {
            browserAPI.log("Number not found");
            // new design
            number = $('p:contains("number:")');
            if (number.length > 0) {
                browserAPI.log("Number v.3");
                data.Number = util.findRegExp(number.text(), /number:\s*(\d+)/i)
                browserAPI.log("Number: " + data.Number);
            }
        }

        // Tier Point collection year ends
        let yearEnds = $('p.account-info:contains("Tier Point collection year ends")').siblings('p.account-info-right');
        if (yearEnds.length > 0) {
            data.YearEnds = util.trim(yearEnds.text());
            browserAPI.log("Tier Point collection year ends: " + data.YearEnds );
        } else {
            browserAPI.log("Tier Point collection year ends not found");
            // new design
            yearEnds = $('p:contains("Tier Point collection year ends")');
            if (yearEnds.length > 0) {
                browserAPI.log("Tier Point collection year ends v.3");
                data.YearEnds = util.findRegExp(yearEnds.text(), /year ends:\s*(.+)/i)
                browserAPI.log("Tier Point collection year ends: " + data.YearEnds );
            }
        }

        // Card expiry date
        let cardExpiryDate = $('p.account-info:contains("Card expiry date")').siblings('p.account-info-right');
        if (cardExpiryDate.length > 0) {
            data.CardExpiryDate = util.trim(cardExpiryDate.text());
            browserAPI.log("Card expiry date: " + data.CardExpiryDate);
        } else {
            browserAPI.log("Card expiry date not found");
            // new design
            cardExpiryDate = $('p:contains("Membership card expiry")');
            if (cardExpiryDate.length > 0) {
                browserAPI.log("Card expiry date v.3");
                data.CardExpiryDate = util.findRegExp(cardExpiryDate.text(), /Membership card expiry:\s*(.+)/i)
                browserAPI.log("Card expiry date: " + data.CardExpiryDate);
            }
        }

        // My Tier
        let tier = $('p:contains(", you can "):contains("get rewarded every time you fly")');
        if (tier.length > 0 && util.trim(tier.text()) !== '') {
            browserAPI.log("Level: " + tier);
            data.Level = util.findRegExp(tier.text(), /^\s*As a ([\w\s]+), you can /);
            browserAPI.log("Level: " + data.Level);
        } else
            browserAPI.log("Tier not found");

        // Tier Points
        let tierPoints = $('p.points-heading:contains("My Tier Points")').siblings('div.tier-points-value').find('> p.tier-points-value');
        if (tierPoints.length > 0) {
            data.TierPoints = util.trim(tierPoints.text());
            browserAPI.log("Tier Points: " + data.TierPoints);
        } else {
            browserAPI.log("Tier Points not found");
            // new design
            tierPoints = $('p[data-cy = "tier-points-progress"]');
            if (tierPoints.length > 0) {
                browserAPI.log("Tier Points v.3");
                data.TierPoints = util.findRegExp(tierPoints.text(), /^\s*([^\/]+)/i)
                browserAPI.log("Tier Points: " + data.TierPoints);
            }
        }

        // My Lifetime Tier Points
        let lifetimeTierPoints = $('p.points-heading:contains("My Lifetime Tier Points")').siblings('p.tier-points-value');
        if (lifetimeTierPoints.length > 0) {
            data.LifetimeTierPoints = util.trim(lifetimeTierPoints.text());
            browserAPI.log("My Lifetime Tier Points: " + data.LifetimeTierPoints);
        } else {
            browserAPI.log("My Lifetime Tier Points not found");
            // new design
            lifetimeTierPoints = $('p[data-cy="lifetime-tier-points"]');
            if (lifetimeTierPoints.length > 0) {
                browserAPI.log("My Lifetime Tier Points v.3");
                data.LifetimeTierPoints = util.findRegExp(lifetimeTierPoints.text(), /^\s*([^\/]+)\s+point/i)
                browserAPI.log("My Lifetime Tier Points: " + data.LifetimeTierPoints);
            }
        }

        // Date of joining the club
        var dateOfJoining = $('p.account-info:contains("Date of joining the club")').siblings('p.account-info-right');
        if (dateOfJoining.length > 0) {
            data.DateOfJoining =  util.trim(dateOfJoining.text());
            browserAPI.log("Date of joining the club: " + data.DateOfJoining);
        } else
            browserAPI.log("Date of joining the club not found");

        // My Household Avios
        let householdMiles = $('p.points-heading:contains("My Household Avios")').siblings('p.tier-points-value');
        if (householdMiles.length === 0) {
            // new design
            browserAPI.log("My Household Avios v.3");
            householdMiles = $('#household-account span[data-cy="primary-value"]');
        }
        if (householdMiles.length > 0) {
            data.HouseholdMiles = util.trim(householdMiles.text());
            browserAPI.log("My Household Avios: " + data.HouseholdMiles);
        } else
            browserAPI.log("My Household Avios not found");

        // Flights to Next Tier
        if (!util.findRegExp($('div#hiddenBenefitsDetails .tier-points-label').text(), /(to\s+retain)/i)) {
            // eligible flights - Eligible Flights To Next Tier (2 blocks)
            var eligibleFlightsToNextTierLinked = util.findRegExp($('div.TierAndFlightsRight > p:contains("eligible flights")').text(), /(\d+)\s+eligible/i);
            if (eligibleFlightsToNextTierLinked && eligibleFlightsToNextTierLinked.length > 0) {
                browserAPI.log("Eligible Flights To Next Tier (2 blocks): " + eligibleFlightsToNextTierLinked);
                data.EligibleFlightsToNextTierLinked = eligibleFlightsToNextTierLinked;
            } else
                browserAPI.log("Eligible Flights To Next Tier (2 blocks) not found");

            // eligible flights - Eligible Flights To Next Tier (1 block)
            var eligibleFlightsToNextTier = util.findRegExp(
                $('div.TierAndFlightsRight').closest('.tier-points').next('p:contains("or:")').next().find('p.tier-points-label:contains("eligible flights")').text(), /(\d+)\s+eligible/i);
            if (eligibleFlightsToNextTier && eligibleFlightsToNextTier.length > 0) {
                browserAPI.log("Eligible Flights To Next Tier (1 block): " + eligibleFlightsToNextTier );
                data.EligibleFlightsToNextTier = eligibleFlightsToNextTier;
            } else {
                browserAPI.log("Eligible Flights To Next Tier (1 block) not found");
                // new design
                try {
                    browserAPI.log("Eligible Flights To Next Tier (1 block) v.3");
                    $('label:has(input[value="allFlights"])').click();
                } catch (e) {
                    browserAPI.log("[Eligible Flights To Next Tier]: trace...");
                }
                // new design
                eligibleFlightsToNextTier = $('p[data-cy = "all-flights-progress"]');
                if (eligibleFlightsToNextTier.length > 0) {
                    data.EligibleFlightsToNextTier = util.findRegExp(eligibleFlightsToNextTier.text(), /^\s*([^\/]+)/i)
                    browserAPI.log("Eligible Flights To Next Tier (1 block): " + data.EligibleFlightsToNextTier );
                }
            }
        }

        // Balance - My Avios
        let balance = $('#aviosInfo').siblings('.tier-points-value');
        if (balance.length > 0) {
            data.Balance = util.trim(balance.text());
            browserAPI.log("Balance: " + data.Balance);
        } else {
            browserAPI.log("Balance not found");
            // new design
            browserAPI.log("My Avios v.3");
            balance = $('#avios-points span[data-cy="primary-value"]');
            if (balance.length > 0) {
                data.Balance = util.trim(balance.text());
                browserAPI.log("Balance: " + data.Balance);
            }
            else if (document.location.href.indexOf('/travel/viewaccount/inet/en_') !== -1 &&
                $('a:contains("Convert to Executive Club")').length > 0) {
                var notMember = 'You are not a member of this loyalty program.';
                browserAPI.log(notMember);
                provider.setWarning(notMember);
            }
        }*/

        // My eVouchers  // refs #7224
        var eVouchersLink = $('a[href *= "https://www.britishairways.com/travel/membership/execclub/"], a[href *= "https://www.britishairways.com/travel/membership/premier/"]');
        if (eVouchersLink.length > 0) {
            browserAPI.log("My eVouchers link: " + eVouchersLink.attr('href'));
            params.data.eVouchersLink = eVouchersLink.attr('href');
        } else {
            browserAPI.log("My eVouchers link not found");
            // new design
            browserAPI.log("My eVouchers link v.3");
            params.data.eVouchersLink = 'https://www.britishairways.com/travel/membership/execclub/_gf?eId=188010';
        }

        // Get Last 10 Transactions (View all transactions)
        var expLink = $('a[href *= "viewstatement"]');
        if (expLink.length > 0) {
            browserAPI.log("My recent transactions link: " + expLink.attr('href'));
            params.data.expLink = expLink.attr('href');
        } else {
            browserAPI.log("My recent transactions link not found");
            // new design
            browserAPI.log("My recent transactions link v.3");
            params.data.expLink = plugin.setCountryUrl(params, 'https://www.britishairways.com/travel/viewtransaction/execclub/_gf/**?eId=172705');
        }

        params.data.properties = data;
        // save data
        //console.log(params.data.properties);
        params.data.properties.HistoryRows = [];
        params.data.endHistory = false;
        provider.saveTemp(params.data);

        /*
        TODO: debug
        if (typeof(params.account.parseItineraries) === 'boolean' && params.account.parseItineraries) {
            provider.setNextStep('parseItineraries', function(){
                document.location.href = plugin.itinerariesLink(params);
            });
        }
        else
            return;*/

        // Parsing eVouchers
        if (typeof(params.data.eVouchersLink) !== 'undefined' && params.data.eVouchersLink) {
            provider.setNextStep('parseSubAccounts', function() {
                document.location.href = params.data.eVouchersLink;
            });
        }
        // Parsing exp date
        else if (typeof(params.data.expLink) !== 'undefined' && params.data.expLink) {
            provider.setNextStep('parseLastActivity', function(){
                document.location.href = params.data.expLink;
            });
        }
        else {
            // Parsing History
            provider.setNextStep('parseHistory', function () {
                document.location.href = plugin.historyLink(params);
            });
            params.account.properties = params.data.properties;
            provider.saveProperties(params.account.properties);
            provider.complete();
        }
    },

    parseSubAccounts: function(params){
        browserAPI.log("parseSubAccounts");
        provider.updateAccountMessage();
        var i = 0;
        var subAccounts = [];
        $("div#unusedVouchers").find('div.table-body').each(function () {
            // Type
            var displayName = util.trim($('p.voucher-list-type', this).text());
            browserAPI.log("DisplayName: " + displayName );
            // Voucher number
            var code = util.trim($('p.voucher-list-number > span:not(:contains("number"))', this).text());
            browserAPI.log("Code: " + code);
            // Expiry
            var exp =  util.trim($('p.voucher-list-details > span:contains("Expiry")', this).next('span.text').text());
            // IE, FF fix
            exp = exp.replace(/-/g, ' ');
            exp = new Date(exp + ' UTC');
            var unixtime =  exp / 1000;
            if (!isNaN(exp)) {
                browserAPI.log("Expiration Date: " + exp + " Unixtime: " + unixtime );
                subAccounts.push({
                    "Code" : 'britishVouchers' + code,
                    "DisplayName" : "Voucher #" + code + " - " + displayName,
                    "Balance" : null,
                    "ExpirationDate" : unixtime
                });
            } else
                subAccounts.push({
                    "Code" : 'britishVouchers' + code,
                    "DisplayName" : "Voucher #" + code + " - " + displayName,
                    "Balance" : null
                });
            i++;
            //console.log(subAccounts);
        });

        params.data.properties.SubAccounts = subAccounts;
        params.data.properties.CombineSubAccounts = 'false';
        params.account.properties = params.data.properties;
        provider.saveTemp(params.data);
        //console.log(params.account.properties);
        provider.saveProperties(params.account.properties);

        // Parsing exp date
        if (typeof(params.data.expLink) !== 'undefined' && params.data.expLink) {
            provider.setNextStep('parseLastActivity', function(){
                document.location.href = params.data.expLink;
            });
        }
        else {
            // Parsing History
            provider.setNextStep('parseHistory', function () {
                document.location.href = plugin.historyLink(params);
            });
        }
    },

    replaceURL: function () {
        var replaceURL = ($('body').text().indexOf("var replaceURL = '") !== -1 || $('div#mainContent > div').attr('data-redirecturl'));
        browserAPI.log("replaceURL: " + replaceURL);
        return replaceURL;
    },

    parseLastActivity: function (params) {
        browserAPI.log("parseLastActivity");
        // fixed bug in sorting of transactions
        provider.setNextStep('parseLastActivity2', function () {
            // provider bug fix
            if ($('h1:contains("You are already logged in with a different account"):visible').length) {
                browserAPI.log(">>> [History form not found]: You are already logged in with a different account");

                // Parsing itineraries
                if (typeof(params.account.parseItineraries) === 'boolean' && params.account.parseItineraries) {
                    provider.setNextStep('parseItineraries', function () {
                        document.location.href = plugin.setCountryUrl(params, "https://www.britishairways.com/travel/VIEWACCOUNT/execclub/**?eId=106010&dr=&dt=British%20Airways%20%7C%20Book%20Flights,%20Holidays,%20City%20Breaks%20%26%20Check%20In%20Online&scheme=&logintype=execclub&tier=Blue&audience=travel&CUSTSEG=&GGLMember=&clickpage=HOME&source=accountBar");
                    });
                }
                else {
                    params.account.properties = params.data.properties;
                    //console.log(params.account.properties);
                    provider.saveProperties(params.account.properties);
                    provider.complete();
                }

                return;
            }

            $('form#transForm input[value = "Search"]').click();
        });
    },

    parseLastActivity2: function (params) {
        browserAPI.log("parseLastActivity2");
        // waiting js redirect
        if (plugin.replaceURL())
            provider.setNextStep('parseLastActivity3');
        else
            plugin.parseLastActivity3(params);
    },

    parseLastActivity3: function(params) {
        browserAPI.log("parseLastActivity3");
        provider.updateAccountMessage();
        var activityIgnore = [
            "Expired Avios",
            "Points Reset for New Membership Year",
            "Combine My Avios",
            "Manual Avios Adjustment",
            "Redemption Redeposit",
            "Avios Adjustment",
            "Tier Points Adjustment",
        ];
        var ignoreBookings = [];
        // this not working in extension
        //$("table#recentTransTbl").find('tr:has(td:nth-child(4))').each(function () {
        browserAPI.log('Total ' + $(".info-detail-main-transaction-row").length + ' items were found');
        $(".info-detail-main-transaction-row").each(function () {
            // Type
            var activity = util.trim($('.info-detail-item.desc', this).text());
            browserAPI.log("activity " + activity);
            // Avios
            var avios = util.trim($('.info-detail-item.avio > p:eq(1)', this).text());
            browserAPI.log("avios " + avios);
            // refs #7665 - ignore certain activities
            var ignore = false;
            for (var i = 0; i < activityIgnore.length; i++) {
                //browserAPI.log("i ->" + i);
                if (activity.indexOf(activityIgnore[i]) !== -1) {
                    ignore = true;
                    break;
                }// if (activity.indexOf(activityIgnore[i]) !== -1)
            }// for (var i = 0; i < activityIgnore.length; i++)
            // refs #7665 - ignore certain activities, part 2
            var reference = util.findRegExp(activity, /Reference:\s*([^\s]+)/i);
            if (activity.indexOf('Avios refund') !== -1 || typeof (ignoreBookings[reference]) !== 'undefined') {
                browserAPI.log("Booking Reference: " + reference);
                if (typeof (ignoreBookings[reference]) !== 'undefined') {
                    if ("-"+ignoreBookings[reference] == avios) {
                        browserAPI.log("Skip Avios refund: " + reference);
                        ignore = true;
                    }// if ("-"+ignoreBookings[reference] == avios)
                    else
                        browserAPI.log("First transaction not found: " + reference);
                }// if (typeof (ignoreBookings[reference]) != 'undefined')
                else {
                    browserAPI.log("Add Avios refund to ignore transactions: " + reference);
                    ignoreBookings[reference] = avios;
                    ignore = true;
                }
            }// if (activity.indexOf('Avios refund') !== -1 || typeof (ignoreBookings[reference]) != 'undefined')

            if (!ignore) {
                browserAPI.log("activity: " + activity );
                var lastActivity = util.findRegExp($('.info-detail-item.tran > p:eq(0)', $(this)).text(), /Transaction:\s*(.+)/i);
                browserAPI.log("Date: " + lastActivity + " / " + avios);
                // refs #9168 - ignore row with empty avios
                if (avios != '' && avios != '-') {
                    // Last Activity
                    browserAPI.log("Last Activity: " + lastActivity);
                    params.data.properties.LastActivity = lastActivity;
                    // IE, FF fix
                    lastActivity = plugin.fullYearInDate(lastActivity, '-');
                    lastActivity = lastActivity.replace(/-/g, ' ');
                    var date = new Date(lastActivity + ' UTC');
                    date.setFullYear(date.getFullYear() + 3);
                    var unixtime = date / 1000;
                    if ( date != 'NaN' && !isNaN(unixtime) ) {
                        browserAPI.log("ExpirationDate = lastActivity + 3 years");
                        browserAPI.log("Expiration Date: " + date + " Unixtime: " + util.trim(unixtime) );
                        params.data.properties.AccountExpirationDate = unixtime;
                    }
                    return false;
                }// if ($avios != '' && $avios != '-')
                else
                    browserAPI.log("ignore row with empty avios");
            }// if ($.inArray(activity, activityIgnore) === -1)
            else
                browserAPI.log("Skip Activity: " + activity);
        });

        params.account.properties = params.data.properties;
        provider.saveTemp(params.data);
        //console.log(params.account.properties);
        provider.saveProperties(params.account.properties);

        // Parsing History
       plugin.parseHistory(params);
    },

    historyLink: function(params) {
        return plugin.setCountryUrl(params, 'https://www.britishairways.com/travel/viewtransaction/execclub/_gf/**?eId=172705');
    },

    getMonth: function () {
        var date = new Date(),
            month = date.getMonth();

        month = month + 1;
        if (month > 11)
            month = 1;

        return month < 10 ? "0" + (month + 1) : month + 1;
    },

    parseHistory: function (params) {
        browserAPI.log("parseHistory");
        // select 'Date Range'
        var dateRange = $('#dateRangeRadio');
        if (dateRange.length > 0) {
            browserAPI.log("set Date Range");
            $('#dateRangeRadio').click();

            var month = plugin.getMonth();
            browserAPI.log("Month: " + month);
            // set Month
            $('#from_month').val(month);
            // set Year
            var shift = 3;
            if (month == 1)
                shift = 2;
            browserAPI.log("Year shift: " + shift);
            $('#from_year').val(new Date().getFullYear() - shift);

            provider.setNextStep('parseHistory2', function () {
                $('form#transForm input[value = "Search"]').click();
            });
        }// if (dateRange.length > 0)
        else {
            browserAPI.log("History form not found");
            //params.account.parseItineraries = true;
            // Parsing itineraries
            if (typeof(params.account.parseItineraries) === 'boolean' && params.account.parseItineraries) {
                provider.setNextStep('parseItineraries', function(){
                    document.location.href = plugin.itinerariesLink(params);
                });
            }
            else {
                params.account.properties = params.data.properties;
                //console.log(params.account.properties);
                provider.saveProperties(params.account.properties);
                provider.complete();
            }
        }
    },

    parseHistory2: function (params) {
        browserAPI.log("parseHistory2");
        // waiting js redirect
        if (plugin.replaceURL())
            provider.setNextStep('parseHistory3');
        else
            plugin.parseHistory3(params);
    },

    parseHistory3: function (params) {
        browserAPI.log("parseHistory3");
        provider.updateAccountMessage();
        var history = [];
        var startDate = params.account.historyStartDate;

        browserAPI.log("historyStartDate: " + startDate);

        // this not working in extension
        var nodes = $(".info-detail-main-transaction-row");
        browserAPI.log('Total ' + nodes.length + ' items were found');
        for (var i = 0; i < nodes.length; i++) {
            var row = {};

            // Transaction date
            var transactionDate = util.findRegExp($('.info-detail-item.tran > p:eq(0)', nodes.eq(i)).text(), /Transaction:\s*(.+)/i);
            // Posted date
            var postedDate = util.findRegExp($('.info-detail-item.tran > p:eq(1)', nodes.eq(i)).text(), /Posted:\s*(.+)/i);
            // Description
            var description = util.filter($('.info-detail-item.desc', nodes.eq(i)).text());
            // Tier Points
            var tierPoints = util.findRegExp($('.info-detail-item.tier', nodes.eq(i)).text(), /Tier points\s*(.+)/i);
            // Avios
            var avios = util.filter($('.info-detail-item.avio > p:eq(1)', nodes.eq(i)).text());
            browserAPI.log("Date: " + transactionDate + " / " + description + " / " + tierPoints + " / " + avios);
            var dateStr = postedDate;
            var postDate = null;
            browserAPI.log("date: " + dateStr );
            if ((typeof(dateStr) !== 'undefined') && (dateStr != '')) {
                // IE, FF fix
                dateStr = plugin.fullYearInDate(dateStr, '-');
                dateStr = dateStr.replace(/-/g, ' ');
                var date = new Date(dateStr + ' UTC');
                var unixtime =  date / 1000;
                if (unixtime != 'NaN') {
                    browserAPI.log("Date: " + date + " Unixtime: " + unixtime );
                    postDate = unixtime;
                }// if (date != 'NaN')
            }// if ((typeof(dateStr) != 'undefined') && (dateStr != ''))
            else
                postDate = null;

            if ((typeof(transactionDate) !== 'undefined') && (transactionDate != '')) {
                // IE, FF fix
                transactionDate = plugin.fullYearInDate(transactionDate, '-');
                transactionDate = transactionDate.replace(/-/g, ' ');
                var date = new Date(transactionDate + ' UTC');
                var unixtime =  date / 1000;
                if (unixtime != 'NaN') {
                    browserAPI.log("Transaction date: " + date + " Unixtime: " + unixtime );
                    transactionDate = unixtime;
                }// if (date != 'NaN')
            }// if ((typeof(dateStr) != 'undefined') && (dateStr != ''))
            else
                postDate = null;

            if (startDate > 0 && postDate < startDate) {
                // var message;//todo
                // if (message = util.findRegExp( $('div.g-grid--indent').text(),/(NO ACTIVITY DONE DURING SELECTED PERIOD)/i))
                //     browserAPI.log(message);

                browserAPI.log("break at date " + dateStr + " " + postDate);
                params.data.endHistory = true;
                break;
            }// if (startDate > 0 && postDate < startDate)

            row = {
                'Transaction date': transactionDate,
                'Posted date': postDate,
                'Description': description,
                'Tier Points': tierPoints,
                'Avios': avios
            };

            params.data.properties.HistoryRows.push(row);
            // console.log(row);//todo
        }// for (var i = 0; i < nodes.length; i++)

        params.account.properties = params.data.properties;
        provider.saveTemp(params.data);
        //console.log(params.account.properties);//todo
        provider.saveProperties(params.account.properties);

        //params.account.parseItineraries = true;
        // Parsing itineraries
        if (typeof(params.account.parseItineraries) === 'boolean' && params.account.parseItineraries) {
            provider.setNextStep('parseItineraries', function(){
                document.location.href = plugin.itinerariesLink(params);
            });
        }
        else
            provider.complete();
    },

    fullYearInDate: function (date, separator) {
        if (!separator)
            separator = '/';
        var LogSplitter = "-----------------------------";
        console.log(LogSplitter);
        console.log("Transfer Date In Full Format");
        console.log("Date: " + date);
        console.log("Separator: " + separator);

        if (date != null) {
            var new_date = date.split(separator);
            if (typeof(new_date[1]) != 'undefined' && new_date[2].length == 2)
                date = new_date[0] + separator + new_date[1] + separator + '20' + new_date[2];
            else {
                console.log("Please set the correct separator!");
                console.log(LogSplitter);
                return null;
            }
            console.log("Date In New Format: " + date);
            console.log(LogSplitter);
            return date;
        }
        else {
            console.log("Date format is not valid!");
            console.log(LogSplitter);
            return null;
        }
    },

    itinerariesLink: function(params) {
        // return plugin.setCountryUrl(params, 'https://www.britishairways.com/travel/viewaccount/execclub/_gf/**/device-all?eId=106010');
        // return plugin.setCountryUrl(params, 'https://www.britishairways.com/travel/echome/execclub/_gf/**');
        return plugin.setCountryUrl(params, 'https://www.britishairways.com/travel/viewaccount/execclub/_gf/**?eId=106010');
    },

    parseItineraries: function(params) {
        browserAPI.log("parseItineraries");
        provider.updateAccountMessage();

        provider.saveProperties(params.account.properties);
        // no Itineraries
        if (
            $('h3:contains("We can\'t find any bookings for this account."):visible').length > 0
            || $('h4:contains("We can\'t find any bookings for this account."):visible').length > 0
            || $('h3:contains("We\'re currently unable to show your bookings"):visible').length > 0
        ) {
            params.account.properties = params.data.properties;
            params.account.properties.Itineraries = [{ NoItineraries: true }];
            browserAPI.log('NoItineraries: true');
            // console.log(params.account.properties);todo
            provider.saveProperties(params.account.properties);
            provider.complete();
            return;
        }

        var link = $('a:has(span:contains("View all bookings")), a:has(span:contains("View all current flight bookings"))');
        if (link.length > 0) {
            provider.setNextStep('parseAllItineraries', function(){
                link.get(0).click();
            });
        }
        else
            plugin.parseAllItineraries(params);
    },

    parseAllItineraries: function(params) {
        browserAPI.log("parseAllItineraries");
        provider.updateAccountMessage();

        params.data.Itineraries = [];
        params.data.Reservations = [];
        params.data.Rentals = [];
        params.data.links = [];
        params.data.preParseCancelled = {};
        params.data.pnrs = [];
        // // preParseCancelled
        var its = $('a.small-btn:has(span:contains("Manage My Booking")), a.mmb-button:contains("Manage My Booking")');
        browserAPI.log('Total ' + its.length + ' reservation(s) were found');
        for (var i = 0; i < its.length; i++) {
            var link = its.eq(i).attr('href');
            browserAPI.log('Link ' + link);
            params.data.links.push(link);
            var parentObj = its.eq(i).parentsUntil('div').parent().parent();
            var pnr = parentObj.find('p:contains("Booking Reference")').next().text();
            params.data.pnrs.push(pnr);
            // preParseCancelled
            var cancelled = parentObj.find('div[class="flight-details"]>p:contains("CANCELLED")');
            if (cancelled.length === 1 ) {
                browserAPI.log("preParseCancelled [" + pnr + "]:");
                params.data.preParseCancelled[pnr] = true;
            }
        }// for (var i = 0; i < its.length; i++)
        if (params.data.links.length > 0) {
            var nextLink = params.data.links.shift();
            provider.setNextStep('nextItinerary', function(){
                browserAPI.log('>>> go to: ' + nextLink);
                document.location.href = nextLink;
            });
            // save data
            provider.saveTemp(params.data);
        }
        else
            provider.complete();
    },

    nextItinerary: function(params) {
        browserAPI.log("nextItinerary");
        // waiting js redirect
        if (plugin.replaceURL()) {
            provider.setNextStep('nextItinerary2');
        } else {
            plugin.nextItinerary2(params);
        }
    },

    nextItinerary2: function(params) {
        browserAPI.log("nextItinerary2");
        plugin.nextItinerary3(params);
        return;
        var link = $('a:contains("Go back to current design"):visible, a:contains("Go back to previous design"):visible');
        if (link.length > 0) {
            provider.setNextStep('nextItinerary3', function () {
                link.get(0).click();
            });
        }
        else {
            var linkNew = $('a:contains("Try new design"):visible');
            var linkCurrent = $('a:contains("Continue in current design"):visible');
            if (linkNew.length > 0 && linkCurrent.length > 0) {
                provider.setNextStep('nextItinerary3', function () {
                    linkCurrent.get(0).click();
                });
            }
            else {
                browserAPI.log('Step nextItinerary3');
                plugin.nextItinerary3(params);
            }
        }
    },

    nextItinerary3: function(params) {
        browserAPI.log("nextItinerary3");
        provider.updateAccountMessage();
        // TODO: Debug
        setTimeout(function() {
        browserAPI.log('>>> Current Url: ' + document.location.href);
        if (util.findRegExp(document.location.href, /(disruption-recovery)/)) {
            browserAPI.log(">>> ParseItineraryDisruption");
            plugin.parseItineraryDisruption(params);
        } else if ($('h1:contains("Booking")>strong').length > 0) {
            plugin.parseItinerary2021(params);
        } else {
            plugin.parseItinerary(params);
        }

        if (params.data.links.length === 0) {
            params.account.properties = params.data.properties;
            params.account.properties.Itineraries = params.data.Itineraries;
            params.account.properties.Reservations = params.data.Reservations;
            params.account.properties.Rentals = params.data.Rentals;
            //console.log(params.account.properties);
            provider.saveProperties(params.account.properties);
            provider.complete();
        }
        else {
            var nextLink = params.data.links.shift();
            provider.setNextStep('nextItinerary', function(){
                browserAPI.log('>>> go to: ' + nextLink);
                document.location.href = nextLink;
            });
            // save data
            provider.saveTemp(params.data);
        }
        }, 3000);
    },

    parseItineraryDisruption: function (params) {
        browserAPI.log('parseItineraryDisruption');
        if ($('span:contains("Something went wrong, please try again"):visible').length > 0) {
            browserAPI.log('Skipping: Something went wrong, please try again');
            return;
        }

        var res = {};
        var DT = null;
        var unixtime = null;
        var pnr = params.data.pnrs.shift();
        // RecordLocator
        res.RecordLocator = util.findRegExp(document.location.href, /id1=(\w+)/);
        browserAPI.log('RecordLocator: ' + res.RecordLocator);
        // TripSegments
        res.TripSegments = [];
        var flightContainer = $('div.flight-container');
        if (flightContainer.length === 0) {
            provider.logBody("notFlightContainer 2");
            browserAPI.log('Not flight container 2');
        }
        flightContainer.each(function () {
            var node = $(this);
            var seg = {};
            // AirlineName
            seg.AirlineName = util.findRegExp(util.trim(node.find('p.flight-number').text()), /^(\w{2})/);
            browserAPI.log('AirlineName: ' + seg.AirlineName);
            // FlightNumber
            seg.FlightNumber = util.findRegExp(util.trim(node.find('p.flight-number').text()), /(\d+)$/);
            browserAPI.log('FlightNumber: ' + seg.FlightNumber);
            // DepCode
            seg.DepCode = util.trim(node.find('span.departure-arrival-info:nth(0)').text());
            browserAPI.log('DepCode: ' + seg.DepCode);
            // ArrCode
            seg.ArrCode = util.trim(node.find('span.departure-arrival-info:nth(1)').text());
            browserAPI.log('ArrCode: ' + seg.ArrCode);
            // DepDate
            var depDate = util.trim(node.find('p.flight-departure-date').text());
            var depTime = util.trim(node.find('p.departure-arrival-time:nth(0)').text());
            DT = depDate + ' ' + depTime;
            unixtime = new Date(DT + ' UTC').getTime() / 1000;
            if (!isNaN(unixtime)) {
                browserAPI.log("DepDate: " + DT + " Unixtime: " + unixtime);
                seg.DepDate = unixtime;
            } else {
                browserAPI.log(">>> Invalid DepDate");
            }
            // ArrDate
            var arrDate = util.trim(node.find('div.flight-arrival-date').text());
            var arrTimeText = util.trim(node.find('p.departure-arrival-time:nth(1)').text());
            var arrTime = util.findRegExp(arrTimeText, /(\d+:\d+)/);
            DT = arrDate + ' ' + arrTime;
            unixtime = new Date(DT + ' UTC').getTime() / 1000;
            if (!isNaN(unixtime)) {
                browserAPI.log("ArrDate: " + DT + " Unixtime: " + unixtime);
                seg.ArrDate = unixtime;
            } else {
                browserAPI.log(">>> Invalid DepDate");
            }
            // Cabin
            seg.Cabin = util.trim(node.find('p.flight-cabin span:first').text());
            browserAPI.log('Cabin: ' + seg.Cabin);
            // Status
            if (node.find('div.cancelled').length > 0) {
                seg.Status = 'Cancelled';
                seg.Cancelled = true;
                browserAPI.log('Cancelled: ' + seg.Cancelled);
            }
            res.TripSegments.push(seg);
        });
        if (plugin.allSegmentsCancelled(res)) {
            browserAPI.log('set cancelled true');
            res.Cancelled = true;
        }
        params.data.Itineraries.push(res);
    },

    parseItinerary: function (params) {
        browserAPI.log('parseItinerary');
        if ($('ul li:contains("Not able to connect to AGL Group Loyalty Platform and IO Error Recieved"):visible').length > 0) {
            browserAPI.log('Skipping: Not able to connect to AGL Group Loyalty Platform and IO Error Recieved');
            return;
        }
        var result = {};
        var depTime = null;
        var depDate = null;
        var DT = null;
        var arrTime = null;
        var arrDate = null;
        var unixtime = null;
        var pnr = params.data.pnrs.shift();

        // RecordLocator
        result.RecordLocator = util.trim($('span:contains("Booking reference:") + span').text());
        if (result.RecordLocator.length === 0 && pnr.length > 0)
            result.RecordLocator = pnr;
        browserAPI.log("ConfirmationNumber: " + result.RecordLocator);
        // Passengers
        var passengerInfo = $('div#passengerDetails').find('li:not(.hideHeader) > div:has(div:nth-of-type(3))');
        result.Passengers = util.beautifulName(plugin.unionArray(passengerInfo.find('div:eq(0)'), ', ', true));
        browserAPI.log("Passengers: " + result.Passengers);
        // AccountNumbers
        var accountNumbers = passengerInfo.find('div:eq(1) p:eq(1)');
        browserAPI.log("accountNumbers: " + accountNumbers.length);
        var accounts = [];
        for (var an = 0; an < accountNumbers.length; an++) {
            var number = util.findRegExp(accountNumbers.eq(an).text(), /\w+\s+([^<]+)/);
            console.log('number ' + number);
            if (number && number.length > 3 && number.indexOf('number') == -1 && number.indexOf('cannot add') == -1)
                accounts.push(number);
        }// for (var an = 0; an < its.length; an++)
        result.AccountNumbers = plugin.arrayUnique(accounts).join(', ');
        browserAPI.log("AccountNumbers: " + result.AccountNumbers);

        // Segments
        var i = 0;
        result.TripSegments = [];
        $('div[id *= "flightDetail"]').each(function () {
            var node = $(this);
            var segment = {};
            browserAPI.log(">>> Segment " + i);

            // details link
            var detailsLink = node.find('a:contains("flight information")').attr('href');
            // Status
            if (node.find('span.highlight:contains("CANCELLED")').length > 0) {
                segment.Status = 'Cancelled';
                segment.Cancelled = true;
                browserAPI.log('Cancelled: ' + segment.Cancelled);
            }
            $.ajax({
                url: detailsLink,
                async: false,
                success: function (data) {
                    browserAPI.log("parseDetails");

                    data = $(data);
                    // Aircraft
                    segment.Aircraft = util.trim($('th:contains("Aircraft type:") + td', data).text());
                    browserAPI.log("Aircraft: " + segment.Aircraft);
                    // Meal
                    segment.Meal = util.trim($('th:contains("Economy catering:") + td', data).text());
                    browserAPI.log("Meal: " + segment.Meal);
                    // Stops
                    segment.Stops = util.trim($('th:contains("Number of stops:") + td', data).text());
                    browserAPI.log("Stops: " + segment.Stops);
                    // Booking class
                    segment.BookingClass = util.trim($('th:contains("Selling class:") + td', data).text());
                    browserAPI.log("BookingClass: " + segment.BookingClass);
                    // Smoking
                    segment.Smoking = util.findRegExp($('th:contains("Flight:") + td', data).text(), /\S*\s(.+)/i);
                    browserAPI.log("Smoking: " + segment.Smoking);
                    // Duration
                    segment.Duration = util.trim($('th:contains("Flying duration:") + td', data).text());
                    browserAPI.log("Duration: " + segment.Duration);
                    // FlightNumber
                    segment.FlightNumber = util.findRegExp(detailsLink, /\&FlightNumber=(\d+)\&/i);
                    browserAPI.log("FlightNumber: " + segment.FlightNumber);
                    // AirlineName and Operator
                    var carrier = util.findRegExp(detailsLink, /Carrier=([^\&]+)\&/i);
                    segment.AirlineName = carrier;
                    var operatedBy = util.trim($('th:contains("Operated by:") + td', data).text());
                    if (segment.AirlineName) {
                        if (operatedBy) {
                            segment.Operator = util.findRegExp(operatedBy, /\s+As\s+(.+?)\s+For\s+/i) || operatedBy;
                            browserAPI.log("Operator: " + segment.Operator);
                        }
                    } else if (operatedBy) {
                        segment.AirlineName = operatedBy;
                    }
                    browserAPI.log("AirlineName: " + segment.AirlineName);
                    // Seats
                    segment.Seats = plugin.arrayUnique(node.find('span:contains("Your allocated seats are")').find('.allocation').text().split(/,\s*/));
                    browserAPI.log("Seats: " + segment.Seats.join(', '));
                    // DepCode
                    segment.DepCode = util.findRegExp(detailsLink, /&from=([A-Z]{3})&to=[A-Z]{3}&/i);
                    browserAPI.log("DepCode: " + segment.DepCode);
                    // ArrCode
                    segment.ArrCode = util.findRegExp(detailsLink, /&from=[A-Z]{3}&to=([A-Z]{3})&/i);
                    browserAPI.log("ArrCode: " + segment.ArrCode);
                    // DepName
                    segment.DepName = node.find('p:contains("Depart") + p:eq(0) > span:eq(0)').text();
                    browserAPI.log("DepName: " + segment.DepName);
                    // DepartureTerminal
                    segment.DepartureTerminal = util.findRegExp(node.find('p:contains("Depart") + p:eq(0) > span:eq(1)').text(), /Terminal(.+)/);
                    browserAPI.log("DepartureTerminal: " + segment.DepartureTerminal);
                    // ArrName
                    segment.ArrName = node.find('p:contains("Arrive") + p:eq(0) > span:eq(0)').text();
                    browserAPI.log("ArrName: " + segment.ArrName);
                    // ArrivalTerminal
                    segment.ArrivalTerminal = util.findRegExp(node.find('p:contains("Arrive") + p:eq(0) > span:eq(1)').text(), /Terminal(.+)/);
                    browserAPI.log("ArrivalTerminal: " + segment.ArrivalTerminal);
                    // DepDate
                    var depTime = node.find('p:contains("Depart") + p + p').text();
                    browserAPI.log("depart time: " + depTime);
                    var depDate = util.findRegExp(node.find('p:contains("Depart") + p + p + p').text(), /\S*\s(.+)/i);
                    browserAPI.log("depart: " + depDate);
                    DT = depDate + ' ' + depTime;
                    DT = new Date(DT + ' UTC');
                    unixtime = DT / 1000;
                    if (!isNaN(unixtime)) {
                        browserAPI.log("DepDate: " + depDate + ' ' + depTime + " Unixtime: " + unixtime);
                        segment.DepDate = unixtime;
                    } else
                        browserAPI.log(">>> Invalid DepDate");
                    // ArrDate
                    arrTime = node.find('p:contains("Arrive") + p + p').text();
                    browserAPI.log("arrive time: " + arrTime);
                    var arrDate = util.findRegExp(node.find('p:contains("Arrive") + p + p + p').text(), /\S*\s(.+)/i);
                    browserAPI.log("arrDate: " + arrDate);
                    DT = arrDate + ' ' + arrTime;
                    DT = new Date(DT + ' UTC');
                    unixtime = DT / 1000;
                    if (!isNaN(unixtime)) {
                        browserAPI.log("ArrDate: " + arrDate + ' ' + arrTime + " Unixtime: " + unixtime);
                        segment.ArrDate = unixtime;
                    } else
                        browserAPI.log(">>> Invalid ArrDate");
                    // Cabin
                    var cabin = node.find('div:has(p:contains("Arrive")) + div').text().split(',');
                    if (typeof (cabin[2]) != 'undefined' && !/^\s*Travelled/.test(cabin[2])) {
                        segment.Cabin = util.trim(cabin[2]);
                    }
                    browserAPI.log("Cabin: " + segment.Cabin);

                    browserAPI.log("<<< Segment " + i);

                    result.TripSegments.push(segment);
                    i++;
                }
            });
        });
        if (plugin.allSegmentsCancelled(result)) {
            result.Cancelled = true;
        }

        var cancelledMessage = (
            util.trim($('h3:contains("We are currently processing a cancellation and refund for this booking"):visible').text()) ||
            util.trim($('p:contains("We\'re replacing your booking with the voucher, so you\'ll no longer be able to use your"):visible').text()) ||
            util.trim($('h1:contains("We\'re sorry your flight has been cancelled"):visible').text()) ||
            util.trim($('span.wrapText:contains("There are no confirmed flights in this booking"):visible').parent().next().find('p:contains("There are no confirmed flights in this booking.")').text())
        );
        if (cancelledMessage) {
            browserAPI.log(cancelledMessage);
            if (typeof params.data.preParseCancelled[pnr] !== 'undefined') {
                browserAPI.log("in preParseCancelled");
            }
            browserAPI.log("result.Cancelled" + result.Cancelled);
            result.Status = 'Cancelled';
            result.Cancelled = true;
        }

        // console.log(result);
        var itinError = $('' +
            'li:contains("we are unable to display your booking"):visible,' +
            'li:contains("Sorry, We are unable to find your booking."):visible,' +
            'h3:contains("Sorry, we can\'t display this booking"):visible,' +
            'span:not(.wrapText):contains("There are no confirmed flights in this booking"):visible,' +
            'li:contains("Sorry, we can\'t display this booking"):visible,' +
            'li:contains("There was a problem with your request, please try again later."):visible' +
            '');//wrapText  - contains style: text-decoration: line-through;
        if (
            result.TripSegments.length === 0
            && itinError.length > 0
        ) {
            if (typeof params.data.preParseCancelled[pnr] !== 'undefined') {
                browserAPI.log("in preParseCancelled");
                browserAPI.log("result.Cancelled" + result.Cancelled);
                result.Cancelled = true;
                params.data.Itineraries.push(result);
            } else {
                browserAPI.log("[Skip itinerary]: " + itinError.text());
                browserAPI.log(JSON.stringify(result));
            }
        } else {
            params.data.Itineraries.push(result);
        }
        var nonFlightLink = $('span:contains("Print non-flight voucher"):first').closest('a').attr('href');
        if (nonFlightLink) {
            plugin.parseVouchers(params, nonFlightLink);
        }
    },

    parseItinerary2021: function (params) {
        browserAPI.log('parseItinerary2021');
        var result = {};
        var depTime = null;
        var depDate = null;
        var DT = null;
        var arrTime = null;
        var arrDate = null;
        var unixtime = null;
        var pnr = params.data.pnrs.shift();

        var segments = $('div[data-modal-name*="flight-"]');
        if (segments.length === 0 && $('h2:contains("Where will your eVoucher take you?")').length > 0) {
            browserAPI.log('Skip: Where will your eVoucher take you?');
            return null;
        }

        // RecordLocator
        result.RecordLocator = util.trim($('h1:contains("Booking")>strong').text());
        browserAPI.log("ConfirmationNumber: " + result.RecordLocator);
        var msg = util.trim($('p:contains("We\'re replacing your booking with the voucher, so you\'ll no longer be able to use your"):visible').text());
        if (msg) {
            browserAPI.log(msg);
            if (typeof params.data.preParseCancelled[pnr] !== 'undefined') {
                browserAPI.log("in preParseCancelled");
            }
            browserAPI.log("result.Cancelled" + result.Cancelled);
            result.Status = 'Cancelled';
            result.Cancelled = true;
        }

        // Passengers
        var passengerInfo = $('h5[class*="passenger"]').map(function () {
            return $(this).text();
        }).get();
        result.Passengers = util.beautifulName(plugin.arrayUnique(passengerInfo).join(', '));
        browserAPI.log("Passengers: " + result.Passengers);
        // AccountNumbers
        var accountNumbers = $('p:contains("Membership number:")');
        browserAPI.log("accountNumbers: " + accountNumbers.length);
        var accounts = [];
        for (var an = 0; an < accountNumbers.length; an++) {
            var number = util.findRegExp(accountNumbers.eq(an).text(), /Membership number:\s*\w+\s+([^<]+)/);
            console.log('number ' + number);
            if (number && number.length > 3)
                accounts.push(number);
        }// for (var an = 0; an < its.length; an++)
        result.AccountNumbers = plugin.arrayUnique(accounts).join(', ');
        browserAPI.log("AccountNumbers: " + result.AccountNumbers);

        // Segments
        var SegmentsScript = $('script:contains("trackflightArray")').text();
        // var resRegExp = util.findRegExp(SegmentsScript, /var trackflightArray = \{\};\s+([\s\S]+?)\s+trackFlightsArrayList.push/g);
        // console.log(resRegExp);
        var i = 0;
        result.TripSegments = [];
        $('div[data-modal-name*="flight-"]').each(function () {
            var node = $(this);
            var segment = {};
            browserAPI.log(">>> Segment " + i);

            // Aircraft
            segment.Aircraft = util.trim(node.find('p:contains("Depart at")').next('div').next('div').find('span:eq(4)').text());
            browserAPI.log("Aircraft: " + segment.Aircraft);
            // Status
            let status = util.trim(node.find('p:contains("Depart at")').next('div').next('div').find('span:eq(2)').text());
            if (status !== 'Information only') {
                segment.Status = util.trim(node.find('p:contains("Depart at")').next('div').next('div').find('span:eq(2)').text());
                browserAPI.log("Status: " + segment.Status);
                if (util.findRegExp(segment.Status, /cancell?ed/i)) {
                    segment.Cancelled = true;
                    browserAPI.log('Cancelled: ' + segment.Cancelled);
                }
            }
            // Duration
            segment.Duration = node.find('p:contains("Depart at")').next('div').next('div').next('div').find('span.-duration').text();
            browserAPI.log("Duration: " + segment.Duration);
            // FlightNumber
            var flightText = util.trim(node.find('p:contains("Depart at")').next('div').next('div').find('span:eq(0)').text());
            segment.FlightNumber = util.findRegExp(flightText, /^\w{2}(\d+)$/);
            browserAPI.log("FlightNumber: " + segment.FlightNumber);
            // AirlineName and Operator
            segment.AirlineName = util.findRegExp(flightText, /^(\w{2})\d+$/);
            browserAPI.log("AirlineName: " + segment.AirlineName);
            var operatedBy = node.find('p:contains("Depart at")').next('div').next('div').next('div').find('span:contains("Operated by")').text();
            if (operatedBy) {
                var operator = util.findRegExp(operatedBy, /Operated by (.+?)\s*(?:Flight |Operated by|$)/);
                if (operator && operator.length > 50) {
                    if (operator.indexOf('AMERICAN AIRLINES (AA) ') !== -1) {
                        operator = 'American Airlines';
                    }
                }
                segment.Operator = operator;
                browserAPI.log("Operator: " + segment.Operator);
            }
            // Seats
            segment.Seats = node.find('span:contains("Your seat number is")').next('span').map(function () {
                return $(this).text();
            }).get().join(',');
            browserAPI.log("Seats: " + segment.Seats);
            // Meal
            segment.Meal = plugin.arrayUnique(node.find('h6:contains("Meal")').next('p').map(function () {
                return $(this).text();
            }).get()).join(',');
            browserAPI.log("Meal: " + segment.Meal);
            var reg = new RegExp('\'' + flightText + '\'([\\s\\S]+?)\\s+trackFlightsArrayList.push');
            resRegExp = util.findRegExp(SegmentsScript, reg);
            if (resRegExp) {
                // DepCode
                segment.DepCode = util.findRegExp(resRegExp, /\.airportfrom = '([A-Z]{3})';/);
                browserAPI.log("DepCode: " + segment.DepCode);
                // ArrCode
                segment.ArrCode = util.findRegExp(resRegExp, /\.airportto = '([A-Z]{3})';/);
                browserAPI.log("ArrCode: " + segment.ArrCode);
                // Booking class
                segment.BookingClass = util.findRegExp(resRegExp, /\.sellingclass = '([A-Z]{1,2})';/);
                browserAPI.log("BookingClass: " + segment.BookingClass);
            } else {
                browserAPI.log("Not Found DepCode and ArrCode -> " + flightText);
                segment.DepCode = 'UnknownCode';
                segment.ArrCode = 'UnknownCode';
            }
            // DepName
            segment.DepName = node.find('p:contains("Depart at")').next('div').clone().children().remove().end().text();
            browserAPI.log("DepName: " + segment.DepName);
            // DepartureTerminal
            segment.DepartureTerminal = util.findRegExp(node.find('p:contains("Depart at")').next('div').find('span').text(), /Terminal(.+)/);
            browserAPI.log("DepartureTerminal: " + segment.DepartureTerminal);
            // ArrName
            segment.ArrName = node.find('p:contains("Arrive at")').next('div').clone().children().remove().end().text();
            browserAPI.log("ArrName: " + segment.ArrName);
            // ArrivalTerminal
            segment.ArrivalTerminal = util.findRegExp(node.find('p:contains("Arrive at")').next('div').find('span').text(), /Terminal(.+)/);
            browserAPI.log("ArrivalTerminal: " + segment.ArrivalTerminal);
            // DepDate
            var depTime = util.findRegExp(util.trim(node.find('p:contains("Depart at")>span:first').text()), /Depart at (.+)/);
            browserAPI.log("depart time: " + depTime);
            var depDate = node.find('p:contains("Depart at")>span:last').text();
            browserAPI.log("depart: " + depDate);
            DT = depDate + ' ' + depTime;
            DT = new Date(DT + ' UTC');
            unixtime = DT / 1000;
            if (!isNaN(unixtime)) {
                browserAPI.log("DepDate: " + depDate + ' ' + depTime + " Unixtime: " + unixtime);
                segment.DepDate = unixtime;
            } else
                browserAPI.log(">>> Invalid DepDate");
            // ArrDate
            arrTime = util.findRegExp(util.trim(node.find('p:contains("Arrive at")>span:first').text()), /Arrive at (.+)/);
            browserAPI.log("arrive time: " + arrTime);
            var arrDate = node.find('p:contains("Arrive at")>span:last').text();
            browserAPI.log("arrDate: " + arrDate);
            DT = arrDate + ' ' + arrTime;
            DT = new Date(DT + ' UTC');
            unixtime = DT / 1000;
            if (!isNaN(unixtime)) {
                browserAPI.log("ArrDate: " + arrDate + ' ' + arrTime + " Unixtime: " + unixtime);
                segment.ArrDate = unixtime;
            } else
                browserAPI.log(">>> Invalid ArrDate");
            // Cabin
            segment.Cabin = node.find('p:contains("Depart at")').next('div').next('div').find('span:eq(1)').text().replace(/\([\w\s]+\)/, '');
            browserAPI.log("Cabin: " + segment);

            browserAPI.log("<<< Segment " + i);

            result.TripSegments.push(segment);
            i++;
        });
        if (plugin.allSegmentsCancelled(result)) {
            result.Cancelled = true;
        }
        params.data.Itineraries.push(result);
    },

    allSegmentsCancelled: function (flight) {
        browserAPI.log('allSegmentsCancelled');
        var segments = flight.TripSegments || [];
        if (segments.length === 0) {
            return false;
        }
        for (var i = 0; i < segments.length; i++) {
            var seg = segments[i];
            if (seg.Cancelled !== true) {
                return false;
            }
        }
        return true;
    },

    parseVouchers: function (params, nonFlightLink) {
        browserAPI.log('parseVouchers');
        var res = [];
        var index = 2;
        $.ajax({
            url: nonFlightLink,
            async: false,
            success: function (data) {
                data = $(data);
                data.find('span:contains("Print voucher")').each(function () {
                    var node = $(this);
                    var link = node.closest('a').attr('href');
                    $.ajax({
                        url: link,
                        async: false,
                        success: function (data) {
                            data = $(data);
                            var voucherType = data.find('h1.voucher-heading').text();
                            if (util.findRegExp(voucherType, /(Hotel Voucher)/)) {
                                var hotel = plugin.parseHotel(data, index);
                                params.data.Reservations.push(hotel);
                                index++;
                            } else if (util.findRegExp(voucherType, /(Car Rental Voucher)/)) {
                                var car = plugin.parseCar(data);
                                params.data.Rentals.push(car);
                            } else {
                                browserAPI.log('Skipping unknown voucher');
                            }
                        }
                    });
                });
            }
        });
        return res;
    },

    parseCar: function (data) {
        browserAPI.log('parseCar');
        var res = {};
        // Number
        res.Number = data.find('p:contains("Car confirmation number")').next('p').text();
        browserAPI.log('Number: ' + res.Number);
        // RentalCompany
        res.RentalCompany = data.find('p:contains("Rental company")').next('p').find('span:first').text();
        browserAPI.log('RentalCompany: ' + res.RentalCompany);
        // PickupLocation
        var locationNodes = data.find('p:contains("Rental company")').next('p').find('span:first').nextAll('span');
        var location = util.unionArray(locationNodes, ', ');
        res.PickupLocation = location;
        browserAPI.log('PickupLocation: ' + res.PickupLocation);
        // DropoffLocation
        res.DropoffLocation = location;
        browserAPI.log('DropoffLocation: ' + res.DropoffLocation);
        // PickupDatetime
        var date1 = data.find('p:contains("Pick-up date")').next('p').text();
        var time1 = data.find('p:contains("Pick-up date")').closest('div').next('div').find('p:nth(1)').text();
        var dt1 = new Date(date1 + ' ' + time1 + ' UTC');
        res.PickupDatetime = isNaN(dt1) ? null : dt1.getTime() / 1000;
        browserAPI.log('PickupDatetime: ' + res.PickupDatetime + ' (from ' + date1 + ' ' + time1 + ')');
        // DropoffDatetime
        var date2 = data.find('p:contains("Drop-off date")').next('p').text();
        var time2 = data.find('p:contains("Drop-off date")').closest('div').next('div').find('p:nth(1)').text();
        var dt2 = new Date(date2 + ' ' + time2 + ' UTC');
        res.DropoffDatetime = isNaN(dt2) ? null : dt2.getTime() / 1000;
        browserAPI.log('DropoffDatetime: ' + res.DropoffDatetime + ' (from ' + date2 + ' ' + time2 + ')');
        // CarModel
        res.CarModel = data.find('p:contains("Car Group")').next('p').text();
        browserAPI.log('CarModel: ' + res.CarModel);
        // RenterName
        res.RenterName = data.find('p:contains("Traveller(s) name")').next('p').text();
        browserAPI.log('RenterName: ' + res.RenterName);
        return res;
    },

    parseHotel: function (data, index) {
        browserAPI.log('parseHotel');
        var res = {};
        // ConfirmationNumber
        var conf = util.findRegExp(data.find('div.bookingref').text(), /\b([A-Z0-9]+)\s*$/);
        conf += '-' + index;
        res.ConfirmationNumber = conf;
        browserAPI.log('ConfirmationNumber: ' + res.ConfirmationNumber);
        // HotelName
        var hotelName = data.find('p:contains("Property Name")').next('p').find('span:first').text();
        res.HotelName = hotelName;
        browserAPI.log('HotelName: ' + res.HotelName);
        // Address
        var addressNodes = data.find('p:contains("Property Name")').next('p').find('span:first').nextAll('span');
        var address = util.unionArray(addressNodes, ', ');
        res.Address = address;
        browserAPI.log('Address: ' + res.Address);
        // Phone
        var phone = data.find('p:contains("Telephone")').next('p').text();
        res.Phone = /^([*]+|na)/i.test(phone) ? null : phone;
        browserAPI.log('Phone: ' + res.Phone);
        // Fax
        var fax = data.find('p:contains("Fax")').next('p').text();
        res.Fax = /^([*]+|na)/i.test(fax) ? null : fax;
        browserAPI.log('Fax: ' + res.Fax);
        // CheckInDate
        var date1 = data.find('p:contains("Check-in date")').next('p').text();
        var checkInDate = new Date(date1 + ' UTC');
        res.CheckInDate = isNaN(checkInDate) ? null : checkInDate.getTime() / 1000;
        browserAPI.log('CheckInDate: ' + res.CheckInDate + ' (from ' + date1 + ')');
        // CheckOutDate
        var date2 = data.find('p:contains("Check-out date")').next('p').text();
        var checkOutDate = new Date(date2 + ' UTC');
        res.CheckOutDate = isNaN(checkOutDate) ? null : checkOutDate.getTime() / 1000;
        browserAPI.log('CheckOutDate: ' + res.CheckOutDate + ' (from ' + date2 + ')');
        // GuestNames
        var names = data.find('p:contains("Traveller(s)")').next('p').find('span.personaldata');
        res.GuestNames = util.unionArray(names, ', ');
        browserAPI.log('GuestNames: ' + res.GuestNames);
        // RoomType and RoomDescription
        var roomText = data.find('p:contains("Room Description")').next('p').text();
        if (/, /.test(roomText)) {
            var type = util.findRegExp(roomText, /^(.+?)\s*, /);
            if (type.length > 1 && type.length <= 200) {
                res.RoomType = util.findRegExp(roomText, /^(.+?)\s*, /);
                res.RoomDescription = util.findRegExp(roomText, /, (.+)$/);
            } else {
                res.RoomDescription = roomText;
            }
            browserAPI.log('RoomType: ' + res.RoomType);
            browserAPI.log('RoomDescription: ' + res.RoomDescription);
        } else {
            res.RoomType = roomText;
            browserAPI.log('RoomType: ' + res.RoomType);
        }
        return res;
    },

    arrayUnique: function (array) {
        var unique = [];
        for (var i = 0; i < array.length; ++i) {
            if (unique.indexOf(array[i]) == -1)
                unique.push(array[i]);
        }
        return unique;
    },

    unionArray: function (elem, separator, unique) {
        // $.map not working in IE 8, so iterating through items
        var result = [];
        for (var i = 0; i < elem.length; i++) {
            var text = util.trim(elem.eq(i).text());
            if (text != "" && (!unique || result.indexOf(text) == -1))
                result.push(text);
        }
        return result.join(separator);
    }

};
