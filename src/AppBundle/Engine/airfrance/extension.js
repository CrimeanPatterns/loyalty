var plugin = {
    //hideOnStart: true, // todo
    // keepTabOpen: true, // todo
    clearCache: true,
    // it needed for correct opening itinerary in mobile
    mobileUserAgent: "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.2 Safari/605.1.15",
    blockImages: /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor),
    sha256HashReservations: '4e3f2e0b0621bc3b51fde95314745feb4fd1f9c10cf174542ab79d36c9dd0fb2',
    sha256HashReservation: '8ceaed40ef2387f278f78846e6b23f5861483a3277b65adacf0408f9f4a9c9a0',
    captchaSelector: 'span[class = "asfc-svg-captcha"]:visible',
    otpSelector: '.login-form-converse-stmt-greeting:contains("Get your one-time PIN code"):visible, .login-form-converse-stmt-greeting:contains("We’ve sent the PIN code"):visible',
    captchaErrorSetter: null,

    hosts: {
        'login.airfrance.com': true,
        'login.airfrance.us': true,
        'wwws.airfrance.us': true,
        'www.airfrance.us': true,
        'www.klm.com': true,
        'login.klm.com': true,
        'www.airfrance.co.uk': true,
        'www.airfrance.fr': true,
        'mobile.airfrance.us': true,
        'www.airfrance.com': true,
        '.airfrance.com': true,
        '.airfrance.us': true
    },

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function(params) {
        return 'https://wwws.airfrance.us/profile/flying-blue/dashboard';
    },

    getFocusTab: function (account, params) {
        return true;
    },

    // for Cashback auto-login
    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function(params) {
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
        browserAPI.log("[Current URL] -> " + document.location.href);
        if (
            document.location.host == 'www.airfrance.co.uk'
            || document.location.host == 'www.airfrance.fr'
            || /France/.test($('a#idCountryLanguageLink').text())
            || document.location.href === 'https://wwws.airfrance.us/trip/overview'
        ) {
            provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
            return;
        }
        if (document.location.host == 'mobile.airfrance.us') {
            provider.setNextStep('switchToDesktop', function () {
                document.location.href = plugin.getStartingUrl(params) + '&fromMobile=true';
            });
            return;
        }// if (document.location.host == 'mobile.airfrance.us')
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
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
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 30) {
                clearInterval(start);
                provider.logBody("lastPage");
                let error = $('p:contains("Access to this web site was blocked by an IT URL Access Control policy."):visible');
                if (error.length === 0)
                    error = $('h1:contains("Access Denied"):visible');
                if (error.length === 0)
                    error = $('i >b:contains("Server busy. Retry later."):visible');
                if (error.length > 0) {
                    provider.setError([error.text(), util.errorCodes.providerError], true);
                    return;
                }

                error = $('h1:contains("503 Service Unavailable"):visible');

                if (error.length > 0) {
                    provider.setError(['Due to technical issues, you may experience difficulties on our website. We\'re doing our best to resole these as soon as possible. Please note: the app is still working.', util.errorCodes.providerError]);
                    return;
                }

                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 20)
            counter++;
        }, 500);
    },

    switchToDesktop: function (params) {
        browserAPI.log("switchToDesktop");
        browserAPI.log("[Current URL] -> " + document.location.href);
        plugin.start(params);
        //provider.setNextStep('start', function () {
        //    document.location.href = plugin.getStartingUrl(params);
        //});
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if (
            $('h1.bwc-o-body-variant').text() !== ''
            || $('span.header__user__identity').text() !== ''
            || $('span.bwc-logo-header__user-name').text() !== ''
        ) {
            browserAPI.log("LoggedIn");
            return true;
        }
        let passwordForm = $('a[aria-label="Log in with your password instead?"]:visible');
        if (passwordForm.length > 0) {
            passwordForm.get(0).click();
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function(account) {
        browserAPI.log("isSameAccount");
        var number = $('.bw-fb-membership-card__number-text > strong').text();
        if (number.length > 0) {
            number = util.filter(number);
        }
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) !== 'undefined')
            && (typeof(account.properties.Number) !== 'undefined')
            && (account.properties.Number !== '')
            && number
            && (number === account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://wwws.airfrance.us/endpoint/v1/oauth/logout/cid';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        browserAPI.log("[Current URL] -> " + document.location.href);
        if (
            typeof params.account.itineraryAutologin === "boolean"
            && params.account.itineraryAutologin
            && params.account.accountId === 0
        ) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "https://wwws.airfrance.us/trip";
            });
            return;
        }

        const passwordForm = $('a[aria-label="Log in with your password instead?"]:visible');

        if (passwordForm.length > 0) {
            browserAPI.log("click on 'with password' btn");
            passwordForm.get(0).click();
        }

        const agree = $('button#accept_cookies_btn:visible');
        if (agree.length > 0) {
            browserAPI.log("click on cookies btn");
            agree.get(0).click();
        }

        util.waitFor({
            selector: 'div.login-form-container:visible',
            success : function (form) {
                browserAPI.log("submitting saved credentials");
                /*
                 let login = form.find('input[formcontrolname = "loginId"]').click().val(params.account.login).get(0);
                 login.dispatchEvent(new Event('input'));
                 // login.dispatchEvent(new Event('change'));
                 let pwd = form.find('input[formcontrolname = "password"]').click().val(params.account.password).get(0);
                 pwd.dispatchEvent(new Event('input'));
                 // pwd.dispatchEvent(new Event('change'));
                 */

                provider.eval(`
                    function triggerInput(selector, value) {
                        const input = document.querySelector(selector);
                        input.dispatchEvent(new Event('focus'));
                        input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));
                        const nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
                        nativeInputValueSetter.call(input, value);
                        const inputEvent = new Event('input', { bubbles: true });
                        input.dispatchEvent(inputEvent);
                    }
                    setTimeout(function() {
                        triggerInput('input[formcontrolname = "loginId"]', '${params.account.login}');
                        triggerInput('input[formcontrolname = "password"]', '${params.account.password}');
                    }, 777);
                `);

                setTimeout(function() {
                    var captcha = util.findRegExp( form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]').attr('src'), /k=([^&]+)/i);
                    if (!captcha)
                        captcha = util.findRegExp( $('div.grecaptcha-badge:visible').find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]').attr('src'), /k=([^&]+)/i);
                    if (captcha && captcha.length > 0) {
                        browserAPI.log("waiting...");
                        if (form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"][src *= "invisible"]').length > 0 || $('div.grecaptcha-badge:visible').length) {
                            browserAPI.log("invisible captcha workaround");
                            // submitButton.get(0).click();
                            provider.eval("document.querySelector('button.login-form-continue-btn').click()");
                        }
                        provider.setNextStep('checkLoginErrors', function () {
                            var counter = 0;
                            let messageShown = false;
                            var login = setInterval(function () {
                                browserAPI.log("waiting... " + counter);
                                if (
                                    (
                                        $('input[placeholder *= "Please enter the characters displayed above."], input[data-placeholder *= "Please enter the characters displayed above."]').length > 0
                                        || counter > 8 && $('button.login-form-continue-btn[disabled]').length > 0
                                    )
                                    && !messageShown
                                ) {
                                    browserAPI.log('messageShown = true');
                                    messageShown = true;
                                    if (provider.isMobile) {
                                        provider.command('show', function () {
                                            provider.reCaptchaMessage();
                                        });
                                    } else {
                                        provider.reCaptchaMessage();
                                    }
                                }

                                // Get your one-time PIN code
                                if ($('.otp-field-assist').length) {
                                    if (!isNaN(plugin.captchaErrorSetter)) {
                                        clearTimeout(plugin.captchaErrorSetter);
                                    }
                                    if (!provider.isMobile) {
                                        if (params.autologin)
                                            provider.setError(['It seems that Air France (Flying Blue) needs to identify this computer before you can log in. Please follow the instructions on the new tab (the one that shows your Airfrance authentication options) to get this computer authorized and then please try to auto-login again.', util.errorCodes.providerError], true);
                                        else {
                                            provider.setError(['It seems that Air France (Flying Blue) needs to identify this computer before you can update this account. Please follow the instructions on the new tab (the one that shows your Airfrance authentication options) to get this computer authorized and then please try to update this account again.', util.errorCodes.providerError], true);
                                        }

                                        return;
                                    }

                                    provider.command('show', function () {
                                        provider.showFader('Message from AwardWallet: In order to log in into this account please identify this device and click the “Continue” button. Once logged in, sit back and relax, we will do the rest.', true);
                                        provider.setNextStep('profileTimeout', function () {
                                            browserAPI.log("waiting answers...");
                                            let counter = 0;
                                            let waitingAnswers = () => {
                                                browserAPI.log("waiting... " + counter);
                                                if ($('.otp-field-assist, input[id^=otp_], input.login-captcha').length === 0) {
                                                    provider.setNextStep('profileTimeout', () => { document.location.href = 'https://wwws.airfrance.us/profile/flying-blue/dashboard' });
                                                    return;
                                                }
                                                if (counter > 180) {
                                                    provider.setError(['Message from AwardWallet: In order to log in into this account please identify this device and click the “Continue” button. Once logged in, sit back and relax, we will do the rest.', util.errorCodes.providerError], true);
                                                    return;
                                                }
                                                counter++;
                                                setTimeout(waitingAnswers, 500)
                                            }
                                            waitingAnswers();
                                        });
                                    });

                                    return;
                                }

                                var errors = $('div[class *= "error-message"] li:visible');
                                if (
                                    $('a.js-mya-logout').length > 0
                                    || errors.length > 0
                                    || $('h1:contains("New password"):visible').length > 0
                                    || $('h1:contains("Your Flying Blue account has been blocked"):visible').length > 0
                                    || $('asfc-form-error > .login-field-assist.bwc-form-errors:visible').length > 0
                                    || $('#password-label-error:visible').length > 0
                                    || $('span[awl-i18n^="form.login.errors."]:visible').length > 0
                                ) {
                                    clearInterval(login);
                                    if (provider.isMobile) {
                                        browserAPI.log(">>> hide site");
                                        provider.command('hide', function () { });
                                    }
                                    plugin.checkLoginErrors(params);
                                    return;
                                }// if (errors.length > 0)
                                if (counter > 120) {
                                    provider.logBody("login");
                                    clearInterval(login);
                                    if (provider.isMobile) {
                                        browserAPI.log(">>> hide site by timeout");
                                        provider.command('hide', function () { });
                                    }
                                    // IE not working properly
                                    if (!!navigator.userAgent.match(/Trident\/\d\./)) {
                                        document.location.reload();
                                        return;
                                    }
                                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                                }
                                counter++;
                            }, 500);
                        });
                    }// if (captcha && captcha.length > 0)
                    else {
                        browserAPI.log("captcha is not found");
                        // IE not working properly
                        if (!!navigator.userAgent.match(/Trident\/\d\./) && $('div#google-recaptcha-login iframe').length == 0) {
                            document.location.reload();
                            return;
                        }
                        provider.setNextStep('checkLoginErrors', function () {
                            util.waitFor({
                                timeout: 2,
                                selector: plugin.captchaSelector,
                                success: function() {
                                    if (provider.isMobile) {
                                        provider.command('show', () => {});
                                    }
                                    provider.reCaptchaMessage();
                                    waitResult(120);
                                },
                                fail: function() {
                                }
                            });
                            // submitButton.get(0).click();
                            provider.eval("document.querySelector('button.login-form-continue-btn').click()");

                            util.waitFor({
                                timeout: 5,
                                selector: plugin.otpSelector,
                                success: function() {
                                    plugin.loginComplete(params);
                                },
                                fail: function() {
                                    util.waitFor({
                                        timeout: 2,
                                        selector: plugin.captchaSelector,
                                        success: function() {
                                            provider.reCaptchaMessage();
                                            waitResult(120);
                                        },
                                        fail: function() {
                                            waitResult();
                                        }
                                    });
                                }
                            });

                            function waitResult(timeoutValue = 3) {
                                util.waitFor({
                                    timeout: timeoutValue,
                                    selector: 'a.js-mya-logout, div[class *= "error-message"] li:visible, h1:contains("Create a new password"):visible, h1:contains("Your Flying Blue account has been blocked"):visible, h2:contains("Sorry, your account has been blocked."):visible, div.bwc-form-errors:visible > span, .otp-field-assist',
                                    success: function() {
                                        plugin.checkLoginErrors(params);
                                    },
                                    fail: function() {
                                        plugin.checkLoginErrors(params);
                                    }
                                });
                            }
                        });
                    }
                }, 1000);
            },
            fail    : function (form) {
                plugin.checkLoginErrors(params);

                if (form.length === 0) {
                    provider.setError(util.errorMessages.loginFormNotFound);
                }
            }
        });
    },

    closeCookiebarModal: function(params) {
        browserAPI.log("closeCookiebarModal");
        let popup = $('#cookiebarModal');
        if (popup.length) {
            popup.hide();
        }
    },

    checkLoginErrors: function(params) {
        browserAPI.log("checkLoginErrors");
        provider.logBody("checkLoginErrorsPage");
        provider.hideFader();

        plugin.closeCookiebarModal(params);

        provider.setNextStep('loginComplete');//prevent loops
        setTimeout(function () {
            if ($(plugin.captchaSelector).length) {
                plugin.captchaErrorSetter = setTimeout(() => provider.setError(util.errorMessages.captchaErrorMessage, true), 5000);
                return false;
            }

            let errors = $('span[awl-i18n ^= "form.login.errors."]:visible');

            if (errors.length === 0)
                errors = $('h1:contains("Your Flying Blue account has been blocked"):visible, h2:contains("Sorry, your account has been blocked."):visible');
            if (errors.length === 0)
                errors = $('#password-label-error:contains("Please enter your password or PIN code."):visible');
            if (errors.length === 0)
                errors = $('.login-field-assist.bwc-form-errors span:contains("Incorrect username and/or password. Please check and try again."):visible');
            if (errors.length === 0)
                errors = $('.login-field-assist.bwc-form-errors span:contains("Sorry, your account is blocked. Please contact our customer support for assistance."):visible');
            if (errors.length === 0)
                errors = $('.bwc-form-error--pattern:visible');

            if (errors.length === 0) {
                errors = $('asfc-form-error > .login-field-assist.bwc-form-errors:visible');
            }

            // Create a new password
            if (errors.length === 0 && $('h1:contains("New password"):visible, div:contains("Update your password"):visible').length > 0) {
                provider.setError(["Air France (Flying Blue) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);
                return;
            }

            if (errors.length > 0) {
                browserAPI.log("[Error]: " + errors.text());

                // Your Flying Blue account has been blocked
                if (-1 !== errors.text().indexOf(" account has been blocked") ||
                    -1 !== errors.text().indexOf(" account is blocked.")) {
                    provider.setError([errors.text(), util.errorCodes.lockout], true);
                    return;
                }
                // We're sorry, an unexpected technical error occurred. Please try again later or contact the KLM Service Centre.
                // Sorry, an unexpected technical error occurred. Please try again or contact the Air France customer service team.
                if (
                    errors.text().indexOf("orry, an unexpected technical error occurred") !== -1
                    || errors.text().indexOf("Sorry, our system fell asleep. Please restart your login.") !== -1
                    || errors.text().indexOf("Communication email is invalid") !== -1
                    || errors.text().indexOf("Sorry, we cannot verify your password due to a technical issue") !== -1
                ) {
                    provider.setError([errors.text(), util.errorCodes.providerError], true);
                    return;
                }

                provider.setError(errors.text(), true);
                return;
            }// if (errors.length > 0)

            plugin.loginComplete(params);
        }, 3000);
    },

    loginComplete: function(params) {
        browserAPI.log('loginComplete');
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.airfrance.us/US/en/local/core/engine/myaccount/DashBoardAction.do?tabDisplayed=resaTab';
            });
            return;
        }
        // autologin complete
        if (params.autologin) {
            plugin.itLoginComplete(params);
            return;
        }

        if (provider.isMobile) {
            browserAPI.log(">>> hide site");
            //provider.command('hide', function () { });//todo
        }

        let otpUrl = 'https://login.airfrance.com/login/otp';
        if (document.location.href === otpUrl) {
            provider.setNextStep('profileTimeout');
            if ($(plugin.otpSelector).length > 0) {
                if (params.autologin)
                    provider.setError(['It seems that Air France (Flying Blue) needs to identify this computer before you can log in. Please follow the instructions on the new tab (the one that shows your Airfrance authentication options) to get this computer authorized and then please try to auto-login again.', util.errorCodes.providerError], true);
                else {
                    //provider.setError(['It seems that Air France (Flying Blue) needs to identify this computer before you can update this account. Please follow the instructions on the new tab (the one that shows your Airfrance authentication options) to get this computer authorized and then please try to update this account again.', util.errorCodes.providerError], true);
                    provider.showFader('It seems that Air France (Flying Blue) needs to identify this computer before you can update this account. Please follow the instructions on the new tab (the one that shows your Airfrance authentication options) to get this computer authorized and then please try to update this account again.');
                    plugin.profileTimeout(params);
                }
            }

            return;
        }
        // parse account
        let myAccountUrl = 'https://wwws.airfrance.us/profile/flying-blue/dashboard';
        if (document.location.href !== myAccountUrl && document.location.href !== otpUrl) {
            return provider.setNextStep('profileTimeout', function () {
                document.location.href = 'https://wwws.airfrance.us/profile/flying-blue/dashboard';
            });
        }// if (document.location.href != myAccountUrl)

        plugin.profileTimeout(params);
    },

    toItineraries: function(params) {
        browserAPI.log('toItineraries');
        var counter = 0;
        var confNo = params.account.properties.confirmationNumber;
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + toItineraries);
            var link = $('button[id = "link--booking_details_' + confNo + '"]');
            if (link.length > 0) {
                clearInterval(toItineraries);
                provider.setNextStep('itLoginComplete', function () {
                    link.click();
                });
            }// if (link.length > 0)
            if (counter > 30) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
            counter++;
        }, 500);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        util.waitFor({
            selector: 'form.ng-pristine',
            success: function(form) {
                form.find('input[name = "bookingCode"]').val(properties.ConfNo);
                util.sendEvent(form.find('input[name = "bookingCode"]').get(0), 'input');
                form.find('input[name = "lastName"]').val(properties.LastName);
                util.sendEvent(form.find('input[name = "lastName"]').get(0), 'input');
                provider.setNextStep('itLoginComplete', function() {
                    setTimeout(function() {
                        form.find('button[bwmitagclick="pnrlastname.click"]:contains("Search")').get(0).click();
                    }, 2000);
                });
            },
            fail: function() {
                provider.setError(util.errorMessages.itineraryFormNotFound);
            }
        })
    },

    itLoginComplete: function(params) {
        browserAPI.log('itLoginComplete');
        provider.complete();
    },

    profileTimeout: function (params, isRecursion) {
        browserAPI.log("[Current URL] -> " + document.location.href);
        browserAPI.log("profileTimeout");
        var counter = 0;
        var profileTimeout = setInterval(function () {
            browserAPI.log("waiting... " + counter);

            // Your profile has changed
            if ($('div.bwc-o-display-2:contains("Your profile has changed"):visible').length > 0) {
                browserAPI.log("Your profile has changed");
                clearInterval(profileTimeout);
                if (!isRecursion) {
                    $('button[mitagclick="profile.cutover_page.continue_new"]:contains("Got it"):visible').get(0).click();
                }
                plugin.profileTimeout(params, true);
            } else {
                // if the page completely loaded
                if (
                    $('h1.bwc-o-body-variant').text() !== ''
                    || $('span.header__user__identity').text() !== ''
                    || $('span.bwc-logo-header__user-name').text() !== ''
                    || $('button:contains("Join Flying Blue"):visible, button:contains("Become a Flying Blue member"):visible').length > 0
                ) {
                    clearInterval(profileTimeout);
                    provider.logBody("parse");
                    plugin.parse(params);
                }
            }
            if ( counter > 120 ) {
                clearInterval(profileTimeout);
                // Please enter the PIN code below.
                /*if (params.autologin)
                    provider.setError(['It seems that Air France (Flying Blue) needs to identify this computer before you can log in. Please follow the instructions on the new tab (the one that shows your Airfrance authentication options) to get this computer authorized and then please try to auto-login again.', util.errorCodes.providerError], true);
                else {
                    provider.setError(['It seems that Air France (Flying Blue) needs to identify this computer before you can update this account. Please follow the instructions on the new tab (the one that shows your Airfrance authentication options) to get this computer authorized and then please try to update this account again.', util.errorCodes.providerError], true);
                }*/
            }
            if (document.location.href === 'https://wwws.airfrance.us/trip/overview') {
                if (!isRecursion) {
                    document.location.href = 'https://wwws.airfrance.us/profile/flying-blue/dashboard';
                }
                plugin.profileTimeout(params, true);
            }
            counter++;
        }, 3000);
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.logBody("parse");
        provider.updateAccountMessage();
        var data = {};

        // Name
        var name = $('span.bwc-logo-header__user-name');
        if (name.length > 0) {
            name = util.beautifulName(util.trim(name.text()));
            browserAPI.log("Name: " + name);
            data.Name = name;
        } else {
            // mobile
            if (typeof (params.data.Name) != 'undefined') {
                data.Name = params.data.Name;
                browserAPI.log("Name from data: " + data.Name);
            }
            else
                browserAPI.log("Name not found");
        }
        // Balance - Award Miles balance
        var balance = $('#bw-fb__miles-overview-miles');
        if (balance.length > 0 && balance.text() !== '') {
            data.Balance = util.findRegExp(balance.text(), /([-\d.,\s]+)/);
            browserAPI.log("Balance: " + balance.text());
        }// if (balance.length > 0)
        else {
            browserAPI.log("Balance not found");
            if (
                $('button:contains("Join Flying Blue"):visible, button:contains("Become a Flying Blue member"):visible').length > 0
            ) {
                let notMember = 'You are not a member of this loyalty program.';
                browserAPI.log(notMember);
                provider.setWarning(notMember);

                params.account.properties = params.data.properties;
                // console.log(params.account.properties);//todo
                provider.saveProperties(params.account.properties);

                if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {
                    provider.setNextStep('waitItineraryList', function () {
                        document.location.href = 'https://wwws.airfrance.us/trip/overview';
                    });
                }// if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries)
                else {
                    browserAPI.log(">>> complete");
                    provider.complete();
                }

                return;
            }// if ($('a:contains("Join Flying Blue"):visible').length > 0)
        }
        // Status
        let status = $('img[class *= "bwc-logo--flyingblue"][alt *= "Flying Blue"]:eq(0)');
        if (status.length > 0) {
            status = util.findRegExp(status.attr('alt'), /Flying Blue\s+(.+)/);
            if (status.length > 0) {
                var level = util.filter(util.beautifulName(status));
                browserAPI.log("Status: " + level);
                data.Status = level;
            }
            else browserAPI.log("Status not found 2");
        } else browserAPI.log("Status not found");
        // Card number
        var number = $('.bw-fb-membership-card__number-text > strong');
        if (number.length > 0) {
            number = util.filter(number.text());
            browserAPI.log("Card number: " + number);
            data.Number = number;
        }
        else
            browserAPI.log("Card number not found");

        // 164,690 Miles, 84 XP
        var experiencePoints = $('.bw-profile-recognition-box__info--amount');
        if (experiencePoints.length > 0) {
            experiencePoints = util.findRegExp( experiencePoints.text(), /Miles, ([\d.,]+) XP/i);
            browserAPI.log("Experience Points: " + experiencePoints);
            data.ExperiencePoints = experiencePoints;
        }
        else
            browserAPI.log("Experience Points not found");

        $.ajax({
            url: "https://wwws.airfrance.us/gql/v1?bookingFlow=LEISURE",
            async: false,
            data: JSON.stringify({"operationName":"ProfileFlyingBlueBenefitsQuery","variables":{"fbNumber":data.Number},"extensions":{"persistedQuery":{"version":1,"sha256Hash":"ee0498f9ac6236f86f09013c8621ab2894e36e17dd0d0d8fb80b856514b23379"}}}),
            type: 'POST',
            contentType: "application/json",
            beforeSend: function(request) {
                request.setRequestHeader("accept-language", 'en');
                request.setRequestHeader("afkl-travel-country", 'us');
                request.setRequestHeader("afkl-travel-host", 'AF');
                request.setRequestHeader("country", 'us');
                request.setRequestHeader("language", 'en');
                request.setRequestHeader("x-xsrf-token", plugin.getCookie('XSRF-TOKEN'));
            },
            dataType: "json",
            success: function (response) {
                browserAPI.log("parse Benefits");
                if (typeof response.data === 'undefined' && typeof response.errors !== 'undefined')
                    return;
                response = response.data.flyingBlueBenefits.currentBenefits;
                //console.log("---------------- data ----------------");
                //console.log(response);
                //console.log("---------------- data ----------------");
                if (typeof response !== 'undefined') {
                    for (var i in response) {
                        if (response.hasOwnProperty(i)) {
                            var row = {};
                            if (typeof(response[i].label) !== 'undefined' && response[i].label == "Flying Blue Petroleum") {
                                browserAPI.log("Experience Points: Yes");
                                data.PetroleumMembership = 'Yes';
                                break;
                            }// if (typeof(response[i].label) !== 'undefined' && response[i].label == "Flying Blue Petroleum")
                        }// if (response[0].statements[0].expirations.hasOwnProperty(expiration))
                    }// for (var expiration in response[0].statements[0].expirations)
                }// if (typeof (response[0].statements[0].expirations) != 'undefined')
            }// success: function (response)
        });// $.ajax({

        params.data.properties = data;
        params.data.properties.HistoryRows = [];
        params.data.endHistory = false;
        provider.saveTemp(params.data);

        // console.log(params.data);//todo
        // Activity overview
        var milesOverview = $('.mat-list-item.ng-star-inserted:contains("Activity overview") button, #bw-fb__miles-overview-title');
        provider.setNextStep('parseExpDatePreLoad', function () {
            milesOverview.get(0).click();
            setTimeout(function () {
                if ($('h1:contains("Activity overview"):visible').length > 0) {
                    plugin.parseExpDatePreLoad(params);
                }
            }, 4500);
        });
    },

    getCookie: function (name) {
        var matches = document.cookie.match(new RegExp(
            "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
        ));
        return matches ? decodeURIComponent(matches[1]) : undefined;
    },

    parseExpDatePreLoad: function(params) {
        browserAPI.log("parseExpDatePreLoad");
        plugin.closeCookiebarModal(params);
        setTimeout(function () {
            plugin.parseExpDate(params);
        }, 1000);
    },

    parseExpDate: function(params) {
        browserAPI.log("parseExpDate");
        if (params.data.properties.Balance != 0) {
            browserAPI.log("Parse Exp Date");
            if (typeof params.data.properties.Status !== undefined && params.data.properties.Status === 'Explorer') {
                // Expiration Date  // refs #4692
                var milesExpiryInformation = $('h5.bw-fb-miles-overview__totals-label.bwc-o-body:contains("valid until")');
                if (milesExpiryInformation.length > 0) {
                    var result = [];
                    $.each(milesExpiryInformation, function (key, value) {
                        browserAPI.log('Expiration String: ' + $(value).text());
                        var until = $(value).text().match(/([\d.,]+)\s+valid until\s+(.+)/);
                        if (until !== null && until[2].trim() !== '0') {
                            var milesAmount = parseFloat(until[1].replace(new RegExp(',', 'g'), ''));
                            var date = new Date(until[2] + ' UTC');
                            var unixtime = date / 1000;
                            if (!isNaN(unixtime)) {
                                browserAPI.log('Expiration Time: ' + date + ' / ' + unixtime);
                                var resultKey = '_' + unixtime;
                                if (typeof result[resultKey] !== 'undefined') {
                                    var item = result[resultKey];
                                    item['unixtime'] = unixtime;
                                    item['milesAmount'] += milesAmount;
                                    result[resultKey] = item;
                                } else
                                    result[resultKey] = {'unixtime': unixtime, 'milesAmount': milesAmount};
                            } else
                                browserAPI.log("Each " + key + " Expiration date not found");
                        }
                    });
                    result = result.sort(function (a, b) {
                        if (a.unixtime < b.unixtime)
                            return -1;
                        if (a.unixtime > b.unixtime)
                            return 1;
                        return 0;
                    });
                    if (result) {
                        result = result[Object.keys(result)[0]];
                        browserAPI.log(`result = ${JSON.stringify(result)}`);
                    } else {
                        browserAPI.log(`result = ${result}`);
                    }

                    if (result && typeof result.unixtime !== 'undefined' && !isNaN(result.unixtime)) {
                        browserAPI.log("Expiration Date: " + result.unixtime);
                        browserAPI.log("Expiring Balance: " + result.milesAmount);
                        params.data.properties.AccountExpirationDate = result.unixtime;
                        params.data.properties.ExpiringBalance = result.milesAmount;
                    }
                } else
                    browserAPI.log("Your Award Miles are valid until not found");
            } else if (typeof params.data.properties.Status !== undefined
                && ['Silver', 'Gold', 'Platinum', 'Ultimate', 'Platinum For Life', 'Ultimate Club 2000'].includes(params.data.properties.Status)) {
                browserAPI.log("expiration date set to never");
                params.data.properties.AccountExpirationDate = 'false';
                params.data.properties.AccountExpirationWarning = 'do not expire with elite status';
                browserAPI.log("clear the old expiration date");
                params.data.properties.ClearExpirationDate = 'Y';
            }
        }

        //console.log(params.data);//todo
        provider.saveTemp(params.data);
        plugin.parseHistory(params);
    },

    parseHistory: function(params) {
        browserAPI.log("parseHistory");
        provider.updateAccountMessage();
        var startDate = params.account.historyStartDate;
        browserAPI.log("historyStartDate: " + startDate);
        // History
        $.ajax({
            url: "https://wwws.airfrance.us/gql/v1?bookingFlow=LEISURE",
            data: JSON.stringify({"operationName":"ProfileFlyingBlueTransactionHistoryQuery","variables":{"size":100,"offset":1,"fbNumber":params.data.properties.Number},"extensions":{"persistedQuery":{"version":1,"sha256Hash":"a4da5deea24960ece439deda2d3eac6c755e88ecfe1dfc15711615a87943fba7"}}}),
            type: 'POST',
            contentType: "application/json",
            beforeSend: function(request) {
                request.setRequestHeader("accept-language", 'en');
                request.setRequestHeader("afkl-travel-country", 'us');
                request.setRequestHeader("afkl-travel-host", 'AF');
                request.setRequestHeader("country", 'us');
                request.setRequestHeader("language", 'en');
                request.setRequestHeader("x-xsrf-token", plugin.getCookie('XSRF-TOKEN'));
            },
            dataType: "json",
            success: function (response) {
                browserAPI.log("parse History");
                if (response.data.flyingBlueTransactionHistory.transactions)
                    response = response.data.flyingBlueTransactionHistory.transactions.transactionsList;
                else response = null;

                // console.log("---------------- data ----------------");
                // console.log(response);
                // console.log("---------------- data ----------------");

                params.data.properties.HistoryRows = [];

                if (response) {
                    for (var i in response) {
                        if (response.hasOwnProperty(i)) {
                            var row = {};

                            var dateStr = util.filter(response[i].transactionDate);
                            var postDate = plugin.dateFormatUTC(dateStr);

                            if (startDate > 0 && postDate < startDate) {
                                browserAPI.log("break at date " + dateStr + " " + postDate);
                                break;
                            }
                            if (postDate === null) {
                                browserAPI.log("Skip bad node");
                                continue;
                            }

                            // description: "My Trip to {#/transactions/transactionsList[0]/finalDestination}"
                            var description = util.filter(response[i].description);
                            var transaction;
                            var key;
                            if (response[i].finalDestination !== null) {
                                transaction = description.replace(/\{.+?finalDestination\}/i, response[i].finalDestination);
                            }
                            // 'description' => 'Car & Taxi - {#/transactions/transactionsList[35]/complementaryDescriptionData[0]}',
                            else if (response[i].complementaryDescriptionData !== null) {
                                for (var j = 0; j < response[i].complementaryDescriptionData.length; j++) {
                                    var re = new RegExp('\\{.+?complementaryDescriptionData\\[' + j + '\\]\\}', 'i');
                                    transaction = description.replace(re, util.trim(response[i].complementaryDescriptionData[j]));
                                }
                            } else
                                transaction = description.replace(/\{.+?\}/i, '');

                            if (typeof (response[i].details) != 'undefined') {
                                for (var detail in response[i].details) {
                                    if (!response[i].details.hasOwnProperty(detail)) {
                                        continue;
                                    }
                                    var complementaryDescription = response[i].details[detail].description;
                                    var complementaryDetailDescriptionData = response[i].details[detail].complementaryDetailDescriptionData;
                                    var ancillaryLabelCategory = response[i].details[detail].ancillaryLabelCategory;
                                    if (
                                        complementaryDescription
                                        && (
                                            complementaryDetailDescriptionData
                                            || ancillaryLabelCategory
                                        )
                                    ) {
                                        if (response[i].details[detail].complementaryDetailDescriptionData) {
                                            for (var j = 0; j < response[i].details[detail].complementaryDetailDescriptionData.length; j++) {
                                                var re = new RegExp('\\{.+?complementaryDetailDescriptionData\\[' + j + '\\]\\}', 'i');
                                                complementaryDescription = complementaryDescription.replace(re, util.trim(response[i].details[detail].complementaryDetailDescriptionData[j]));
                                            }
                                        }

                                        if (/^My Trip to/i.test(description)) {

                                            var transactionDesc = transaction + "; " + complementaryDescription;

                                            if (/Bonus/i.test(transactionDesc))
                                                key = 'Bonus Miles';
                                            else
                                                key = 'Award Miles';

                                            row = {
                                                'Date': postDate,
                                                'Transaction': transactionDesc,
                                                'Travel Date': plugin.dateFormatUTC(response[i].details[detail].activityDate)
                                            };
                                            row[key] = response[i].details[detail].milesAmount;

                                            if (typeof (response[i].details[detail].xpAmount) !== 'undefined' && response[i].details[detail].xpAmount !== null) {
                                                row['Experience Points'] = response[i].details[detail].xpAmount;
                                            }

                                            params.data.properties.HistoryRows.push(row);

                                            continue;
                                        }

                                        transaction += "; " + complementaryDescription;
                                    }
                                    else if (
                                        transaction === ''
                                        && complementaryDescription
                                        && (typeof (complementaryDetailDescriptionData) == 'undefined' || complementaryDetailDescriptionData == null)
                                    ) {
                                        transaction = complementaryDescription;
                                    }
                                }
                            }

                            if (/^My Trip to/i.test(description)) {
                                continue;
                            }

                            if (/Bonus/i.test(transaction))
                                key = 'Bonus Miles';
                            else
                                key = 'Award Miles';

                            row = {
                                'Date': postDate,
                                'Transaction': transaction
                            };
                            row[key] = response[i].milesAmount;

                            if (typeof (response[i].xpAmount) !== 'undefined' && response[i].xpAmount !== null) {
                                row['Experience Points'] = response[i].xpAmount;
                            }

                            // console.log(response[i]);//todo
                            // console.log(row);//todo
                            params.data.properties.HistoryRows.push(row);
                        }// if (response.hasOwnProperty(i))
                    }// for (var i in response)
                }// if (response)

                params.account.properties = params.data.properties;
                // console.log(params.account.properties);//todo
                provider.saveProperties(params.account.properties);

                if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {
                    provider.setNextStep('waitItineraryList', function () {
                        document.location.href = 'https://wwws.airfrance.us/trip/overview';
                    });
                }// if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries)
                else {
                    browserAPI.log(">>> complete");
                    provider.complete();
                }
            }// success: function (response)
        });// $.ajax({
    },

    dateFormatUTC: function (dateStr, isObjectOrUnix) {
        browserAPI.log('dateFormatUTC');
        // 2018-02-14
        var date = dateStr.match(/(\d{4})-(\d+)-(\d+)/);
        if(date === null)
            return null;
        var year = date[1], month = date[2], day = date[3];
        var dateObject = new Date(Date.UTC(year, month - 1, day, 0, 0, 0, 0));
        var unixTime = dateObject.getTime() / 1000;
        if (!isNaN(unixTime)) {
            browserAPI.log('Date: ' + dateObject + ' UnixTime: ' + unixTime);
            return isObjectOrUnix ? dateObject : unixTime;
        }
        return null;
    },

    waitItineraryList: function (params) {
        browserAPI.log('waitItineraryList');
        util.waitFor({
            selector: 'h1:contains("My trips"), h1:contains("Your bookings")',
            success: function (_) {
                plugin.parseItineraries(params);
            },
            fail: function (_) {
                plugin.parseItineraries(params);
            },
        });
    },

    getReservationsJson: function () {
        browserAPI.log('getReservationsJson');
        let data = {
            'operationName': 'TripReservationsQuery',
            'variables': {
                'daysBack': 180
            },
            'extensions': {
                'persistedQuery': {
                    'version': 1,
                    'sha256Hash': plugin.sha256HashReservations
                }
            }
        };
        let result = null;
        $.ajax({
            url: 'https://wwws.airfrance.us/gql/v1?bookingFlow=LEISURE',
            async: false,
            data: JSON.stringify(data),
            type: 'POST',
            contentType: 'application/json',
            beforeSend: function (request) {
                request.setRequestHeader('accept-language', 'en');
                request.setRequestHeader('country', 'us');
                request.setRequestHeader('language', 'en');
                request.setRequestHeader('x-xsrf-token', plugin.getCookie('XSRF-TOKEN'));
            },
            dataType: 'json',
            success: function (response) {
                if (
                    response.errors !== undefined
                    && response.errors[0] !== undefined
                    && response.errors[0].message !== undefined
                ) {
                    browserAPI.log(`[error]: ${response.errors[0].message}`);
                }
                if (
                    response.data === undefined
                    || response.data.reservations === undefined
                    || response.data.reservations.trips === undefined
                ) {
                    browserAPI.log('[error]: failed to get trips');
                } else {
                    result = response.data.reservations.trips;
                }
            },

        });
        return result;
    },

    getReservationJson: function (conf, lastName, isRetry) {
        browserAPI.log('getReservationJson');
        if (!conf || !lastName) {
            return null;
        }
        let result = null;
        let data = {
            'operationName': 'TripReservationQuery',
            'variables': {
                'bookingCode': conf,
                'lastName': lastName
            },
            'extensions': {
                'persistedQuery': {
                    'version': 1,
                    'sha256Hash': plugin.sha256HashReservation
                }
            }
        };
        if (isRetry) {
            data = {
                'operationName': 'TripReservationQuery',
                'variables': {
                    'bookingCode': conf,
                    'lastName': lastName
                },
                'extensions': {
                    'persistedQuery': {
                        'version': 1,
                        'sha256Hash': plugin.sha256HashReservation
                    }
                }
            };
        }
        $.ajax({
            url: 'https://wwws.airfrance.us/gql/v1?bookingFlow=LEISURE',
            async: false,
            data: JSON.stringify(data),
            type: 'POST',
            contentType: 'application/json',
            beforeSend: function (request) {
                request.setRequestHeader('accept-language', 'en');
                request.setRequestHeader('country', 'us');
                request.setRequestHeader('language', 'en');
                request.setRequestHeader('x-xsrf-token', plugin.getCookie('XSRF-TOKEN'));
            },
            dataType: 'json',
            success: function (response) {
                if (
                    response.errors !== undefined
                    && response.errors[0] !== undefined
                    && response.errors[0].message !== undefined
                    && (
                        response.data === undefined
                        || response.data.reservation === undefined
                        || response.data.reservation === null
                    )
                ) {
                    browserAPI.log(`[error]: ${response.errors[0].message}`);
                    result = response.errors[0].message;
                    return;
                }
                if (
                    response.errors !== undefined
                    && response.errors[0] !== undefined
                    && response.errors[0].message === 'Internal server error'
                    && response.errors[0].code === 'INTERNAL_SERVER_ERROR'
                ) {
                    browserAPI.log(`[error]: ${response.errors[0].message}`);
                    result = response.errors[0].message;
                    return;
                }
                if (
                    response.data !== undefined
                    && response.data.reservation !== undefined
                    && response.data.reservation.messages !== undefined
                    && response.data.reservation.messages[0] !== undefined
                    && response.data.reservation.messages[0].name !== undefined
                ) {
                    let message = response.data.reservation.messages[0].name;
                    browserAPI.log(`[error]: ${message}`);
                    if (message === 'A travel voucher has been requested for this reservation.'
                        || message === 'Refund Eligibility'
                        || message === 'PersistedQueryNotFound') {
                        result = message;
                        return;
                    }
                } else {
                    result = response.data.reservation;
                    return;
                }
            }
        });
        return result;
    },

    parseItineraries: function (params) {
        browserAPI.log('parseItineraries');
        // no Itineraries
        if (
            $('span:contains("You have no current reservations"):visible').length > 0
            || $('h3:contains("You have no upcoming trips"):visible').length > 0
        ) {
            browserAPI.log("no Itineraries");
            params.account.properties.Itineraries = [{NoItineraries: true}];
            provider.saveProperties(params.account.properties);
            provider.complete();
            return;
        }
        let itineraries = [];
        let trips = plugin.getReservationsJson();
        if (trips instanceof Array) {
            if (trips === []) {
                itineraries = [{ NoItineraries: true }];
            } else {
                let cntSkipped = 0;
                for (let trip of trips) {
                    let scheduledDate = new Date(`${trip.scheduledReturn}+00:00`);
                    let isPast = scheduledDate < new Date();
                    if (isPast && trip.historical === true) {
                        cntSkipped++;
                        browserAPI.log(`Skiping booking ${trip.bookingCode}: past itinerary`);
                        continue;
                    }
                    let reservation = plugin.getReservationJson(trip.bookingCode, trip.lastName, false);
                    if (reservation === 'PersistedQueryNotFound') {
                        reservation = plugin.getReservationJson(trip.bookingCode, trip.lastName, true);
                    }
                    if (
                        typeof reservation === 'string'
                        || reservation === null
                    ) {
                        continue;
                    }
                    let parsedTrips = plugin.parseReservationJson(trip.bookingCode, reservation);
                    $.each(parsedTrips, function (index, parsedTrip) {
                        itineraries.push(parsedTrip);
                    });
                }
                if (trips.length === cntSkipped && itineraries.length === 0) {
                    itineraries = [{ NoItineraries: true }];
                }
            }
        }
        params.account.properties.Itineraries = itineraries;
        provider.saveProperties(params.account.properties);
        provider.complete();
    },

    parseReservationJson: function (conf, reservation, fromTrip) {
        browserAPI.log('parseReservationJson');
        fromTrip = fromTrip ? true : false;
        let res = {};
        let resTrain = {};
        // RecordLocator
        res.RecordLocator = conf;
        resTrain.RecordLocator = conf;
        resTrain.TripCategory = 3;
        browserAPI.log(`res.RecordLocator = ${res.RecordLocator}`);
        // Passengers, TicketNumbers, AccountNumbers, EarnedAwards
        let passengers = [];
        let ticketNumbers = [];
        let accountNumbers = [];
        let totalMiles = 0;
        for (let passenger of reservation.passengers || []) {
            if (passenger.type !== 'INFANT') {
                let traveller = util.beautifulName(`${passenger.firstName || ''} ${passenger.lastName || ''}`).trim();
                if (traveller) {
                    passengers.push(traveller);
                }
            }
            if (!fromTrip) {
                for (let ticketNumber of passenger.ticketNumber || []) {
                    ticketNumbers.push(ticketNumber);
                }
                for (let membership of passenger.memberships || []) {
                    accountNumbers.push(membership.number);
                }
                if (
                    passenger.earnQuote
                    && passenger.earnQuote.totalMiles !== undefined
                ) {
                    totalMiles += passenger.earnQuote.totalMiles;
                }
            }
        }
        res.Passengers = plugin.arrayUnique(passengers);
        resTrain.Passengers = plugin.arrayUnique(passengers);
        browserAPI.log(`res.Passengers = ${res.Passengers}`);
        res.TicketNumbers = plugin.arrayUnique(ticketNumbers);
        browserAPI.log(`res.TicketNumbers = ${res.TicketNumbers}`);
        res.AccountNumbers = plugin.arrayUnique(accountNumbers);
        browserAPI.log(`res.AccountNumbers = ${res.AccountNumbers}`);
        if (totalMiles) {
            res.EarnedAwards = totalMiles;
            browserAPI.log(`res.EarnedAwards = ${res.EarnedAwards}`);
        }

        // SpentAwards
        // TotalCharge and Currency
        let totalPrice = [];
        if (typeof reservation.ticketInfo.totalPrice !== 'undefined')
            totalPrice = reservation.ticketInfo.totalPrice;

        for (let item of totalPrice) {
            let currencyCode = item.currencyCode;
            //let currency = this.currency(currencyCode);
            if (currencyCode === 'MLS') {
                res.SpentAwards = item.amount;
                browserAPI.log(`res.SpentAwards = ${res.SpentAwards}`);
                continue;
            }
            if (typeof res.TotalCharge === 'undefined' || item.amount > res.TotalCharge) {
                res.TotalCharge = item.amount;
                browserAPI.log(`res.TotalCharge = ${res.TotalCharge}`);
                res.Currency = currencyCode;
                browserAPI.log(`res.Currency = ${res.Currency}`);
            }
        }

        // TripSegments
        let connections = [];
        if (
            reservation.itinerary !== undefined
            && reservation.itinerary.connections !== undefined
        ) {
            connections = reservation.itinerary.connections;
        }
        res.TripSegments = [];
        resTrain.TripSegments = [];
        for (let connection of connections) {
            for (let segment of connection.segments || []) {
                let ts = {};
                // let arrDate2 = new Date(`${segment.flight.arrivalDate}+00:00`);
                // if (isNaN(arrDate2.getTime())) {
                //     ts.ArrDate = null;
                //     if (segment.flight.arrivalDate === null) {
                //         ts.ArrDate = -1;
                //     }
                // } else {
                //     ts.ArrDate = arrDate2.getTime() / 1000;
                // }
                // if (ts.ArrDate === -1) {
                //     console.log('ts.ArrDate = -1 skip');
                //     continue;
                // }
                // DepCode
                ts.DepCode = segment.origin.airportCode;
                browserAPI.log(`ts.DepCode = ${ts.DepCode}`);
                // DepName
                ts.DepName = segment.origin.airportName;
                browserAPI.log(`ts.DepName = ${ts.DepName}`);
                // DepDate
                let depDate;

                if (typeof (segment.flight.newDepartureDate) != 'undefined' && segment.flight.newDepartureDate !== null) {
                    depDate = new Date(`${segment.flight.newDepartureDate}+00:00`);
                } else {
                    depDate = new Date(`${segment.flight.departureDate}+00:00`);
                }

                if (isNaN(depDate.getTime())) {
                    ts.DepDate = null;
                    if (segment.flight.departureDate === null) {
                        ts.DepDate = -1;
                    }
                } else {
                    ts.DepDate = depDate.getTime() / 1000;
                }
                browserAPI.log(`ts.DepDate = ${ts.DepDate} (${depDate})`);
                // ArrCode
                ts.ArrCode = segment.destination.airportCode;
                browserAPI.log(`ts.ArrCode = ${ts.ArrCode}`);
                // ArrName
                ts.ArrName = segment.destination.airportName;
                browserAPI.log(`ts.ArrName = ${ts.ArrName}`);
                // ArrDate
                let arrDate;

                if (typeof (segment.flight.newArrivalDate) != 'undefined' && segment.flight.newArrivalDate !== null) {
                    arrDate = new Date(`${segment.flight.newArrivalDate}+00:00`);
                } else {
                    arrDate = new Date(`${segment.flight.arrivalDate}+00:00`);
                }

                if (isNaN(arrDate.getTime())) {
                    ts.ArrDate = null;
                    if (segment.flight.arrivalDate === null) {
                        ts.ArrDate = -1;
                    }
                } else {
                    ts.ArrDate = arrDate.getTime() / 1000;
                }
                browserAPI.log(`ts.ArrDate = ${ts.ArrDate} (${arrDate})`);
                // AirlineName
                ts.AirlineName = segment.flight.carrierCode;
                browserAPI.log(`ts.AirlineName = ${ts.AirlineName}`);
                // FlightNumber
                if (segment.flight.flightNumber === undefined || segment.flight.flightNumber === null) {
                    ts.FlightNumber = 'UnknownFlightNumber';
                } else {
                    ts.FlightNumber = segment.flight.flightNumber;
                }
                browserAPI.log(`ts.FlightNumber = ${ts.FlightNumber}`);
                // Duration
                let duration = `${Math.floor(segment.flight.duration / 60)}h ${Math.floor(segment.flight.duration % 60)}m`;
                ts.Duration = duration;
                browserAPI.log(`ts.Duration = ${ts.Duration}`);
                // Aircraft
                ts.Aircraft = null;
                if (
                    segment.flight.equipment
                ) {
                    ts.Aircraft = segment.flight.equipment.name || null;
                }
                browserAPI.log(`ts.Aircraft = ${ts.Aircraft}`);
                // Cabin
                ts.Cabin = segment.flight.cabinClass || null;
                browserAPI.log(`ts.Cabin = ${ts.Cabin}`);

                // Cancelled
                if (segment.isCancelled) {
                    ts.Cancelled = true;
                    browserAPI.log(`ts.Cancelled = ${ts.Cancelled} segment`);
                }

                // Seats
                ts.Seats = [];
                if (
                    segment.ancillaries !== undefined
                ) {
                    for (let seat of segment.ancillaries.seats || []) {
                        for (let seatNumber of seat.seatNumbers || []) {
                            if (/^[A-Z\d\-\/]{1,7}$/.test(seatNumber))
                                ts.Seats.push(seatNumber);
                        }
                    }
                    ts.Seats = plugin.arrayUnique(ts.Seats);
                    browserAPI.log(`ts.Seats = ${ts.Seats}`);
                }
                if ((segment.flight.equipment && segment.flight.equipment.code === 'TRN')
                    || ts.DepName.indexOf('Railway Station') !== -1
                    || ts.DepName.indexOf('railway Station') !== -1
                    || ts.ArrName.indexOf('Railway Station') !== -1
                    || ts.ArrName.indexOf('railway Station') !== -1
                ) {
                    ts.Aircraft = null;
                    resTrain.TripSegments.push(ts);
                } else {
                    res.TripSegments.push(ts);
                }
            }
        }

        // Taxes
        let data = {
            "operationName": "TripReservationTicketPriceBreakdownQuery",
            "variables": {"id": reservation.id},
            "extensions": {
                "persistedQuery": {
                    "version": 1,
                    "sha256Hash": '2645ba4eec72a02650ae63c2bd78d14a3f0025dddfca698f570b96a630667fe0',
                },
            },
        };
        $.ajax({
            url: 'https://wwws.airfrance.us/gql/v1?bookingFlow=LEISURE',
            async: false,
            data: JSON.stringify(data),
            type: 'POST',
            contentType: 'application/json',
            beforeSend: function (request) {
                request.setRequestHeader('accept-language', 'en');
                request.setRequestHeader('country', 'us');
                request.setRequestHeader('language', 'en');
                //request.setRequestHeader('x-xsrf-token', plugin.getCookie('XSRF-TOKEN'));
            },
            dataType: 'json',
            success: function (data) {
                //browserAPI.log('TripReservationTicketPriceBreakdownQuery: ' + JSON.stringify(data));
                if (data.data && data.data.reservation && data.data.reservation.ticketInformation
                    && data.data.reservation.ticketInformation.passengersTicketInformation) {
                    let taxes = [];
                    for (const information of data.data.reservation.ticketInformation.passengersTicketInformation) {
                        if (information.taxes && information.taxes.totalPrice && information.taxes.totalPrice.amount)
                            taxes.push(information.taxes.totalPrice.amount);
                    }
                    if (taxes.length) {
                        res.Tax = taxes.reduce((a, b) => a + b, 0);
                        browserAPI.log(`res.Taxes = ${res.Tax}`);
                    }
                }
            }
        });

        if (resTrain.TripSegments.length > 0) {
            //browserAPI.log(JSON.stringify([res, resTrain]));
            return [res, resTrain];
        }
        // browserAPI.log(JSON.stringify([res]));//todo
        // console.log(res);//todo
        return [res];
    },

    parseItinerariesOld: function (params) {
        browserAPI.log("parseItinerariesOld");

        provider.updateAccountMessage();

        params.data.links = [];
        params.data.Itineraries = [];

        provider.saveProperties(params.account.properties);

        browserAPI.log("parseItineraries. step 1");

        var upcomingTrips = $('#button_current > span.number').text();
        browserAPI.log("Upcoming Trips: " + upcomingTrips);
        // no Itineraries
        if (upcomingTrips === '0') {
            browserAPI.log("provider bug fix, try to find itinerary on the other page");
            provider.setNextStep('waitSingleItinerary', function () {
                // save data
                provider.saveTemp(params.data);
                document.location.href = 'https://www.airfrance.us/US/en/local/core/engine/mmb/MmbAction.do';
                // document.location.href = 'https://wwws.airfrance.us/en/trip';
            });
        } else {
            // collect card links
            var links = $('div#block_current').find('button[id *= "link--booking_details_"]');
            browserAPI.log('total ' + links.length + ' links found');
            for (var i = 0; i < links.length; i++) {
                var link = util.findRegExp( links.eq(i).attr('onclick'), /\.location=\'([^\']+)/i );
                browserAPI.log('Link ' + link);
                params.data.links.push('https://www.airfrance.us' + link);
            }// for (var i = 0; i < its.length; i++)
            // filter links
            params.data.links = plugin.arrayUnique(params.data.links);

            browserAPI.log("Found " + params.data.links.length + " reservations");
            if (params.data.links.length > 0) {
                var nextLink = '';
                nextLink = params.data.links.pop();
                params.data.TripSegments = [];
                provider.setNextStep('openItinerary', function () {
                    browserAPI.log(">>> open next Itinerary: " + nextLink);
                    // save data
                    provider.saveTemp(params.data);
                    document.location.href = nextLink;
                });
            }// if (params.data.links.length > 0)
            else
                provider.complete();
        }
    },

    waitSingleItinerary: function(params) {
        util.waitFor({
            selector: 'span:contains("View vouchers"):visible',
            success: function () {
                plugin.parseSingleItinerary(params);
            },
            fail: function () {
                plugin.parseSingleItinerary(params);
            },
            timeout: 7
        });
    },

    parseSingleItinerary: function(params) {
        browserAPI.log("parseSingleItinerary");
        // no Itineraries
        if (
            $('span:contains("You have no current reservations"):visible').length > 0
            || $('h3:contains("You have no upcoming trips"):visible').length > 0
        ) {
            browserAPI.log("no Itineraries");
            params.account.properties.Itineraries = [{NoItineraries: true}];
            // console.log(params.account.properties);//todo
            provider.saveProperties(params.account.properties);
            provider.complete();
        } else {
            // collect card links
            var links = $('a[id *= "button--see_modify"]');
            browserAPI.log('total ' + links.length + ' links found');
            for (var i = 0; i < links.length; i++) {
                var link = links.eq(i).attr('href');
                browserAPI.log('Link ' + link);
                params.data.links.push('https://www.airfrance.us' + link);
            }
            params.data.links = plugin.arrayUnique(params.data.links);

            browserAPI.log("Found " + params.data.links.length + " reservations");
            if (params.data.links.length > 0) {
                var nextLink = '';
                nextLink = params.data.links.pop();
                params.data.TripSegments = [];
                provider.setNextStep('openItinerary', function () {
                    browserAPI.log(">>> open next Itinerary: " + nextLink);
                    // save data
                    provider.saveTemp(params.data);
                    document.location.href = nextLink;
                });
            }// if (params.data.links.length > 0)
            else
                provider.complete();
        }
    },

    openItinerary: function (params) {
        browserAPI.log("openItinerary");
        var counter = 0;
        var openItinerary = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            if ($('div.bookingReference--value').length === 1 || counter > 40) {
                clearInterval(openItinerary);
                plugin.parseItinerary(params);
            }// if ($('div.bookingReference--value').length == 1 || counter > 20)
            counter++;
        }, 500);
    },

    parseItineraryOld: function(params){
        browserAPI.log("parseItineraryOld");
        provider.updateAccountMessage();
        var result = {};
        // RecordLocator
        result.RecordLocator = $('div.bookingReference--value').text();
        browserAPI.log("RecordLocator: " + result.RecordLocator);
        // Passengers
        var passengersInfo = $('#passengersInfos');
        var passengerNodes = passengersInfo.find('div.paxId > div[class *= "paxName"]');
        result.Passengers = [];
        for (var i = 0; i < passengerNodes.length; i++) {
            var text = util.trim(passengerNodes.eq(i).text());
            if (text != "")
                result.Passengers.push(util.beautifulName(text));
        }// for (var i = 0; i < passengerNodes.length; i++)
        browserAPI.log("Passengers: " + JSON.stringify(result.Passengers));
        // AccountNumbers
        var accountNodes = passengersInfo.find('ul.paxNumbers li[id *= "paxFfCard"] > div.t1__text--big');
        result.AccountNumbers = [];
        for (var i = 0; i < accountNodes.length; i++) {
            var text = util.findRegExp(accountNodes.eq(i).text(), /(\d+)/);
            text = util.filter(text);
            if (text != "" && !result.AccountNumbers.include(text)) {
                result.AccountNumbers.push(text);
            }
        }// for (var i = 0; i < accountNodes.length; i++)
        browserAPI.log("AccountNumbers: " + JSON.stringify(result.AccountNumbers));
        // TicketNumbers
        var ticketNodes = passengersInfo.find('ul.paxNumbers li[id *= "paxTicketNumber"] > div.t1__text--big');
        result.TicketNumbers = [];
        for (var i = 0; i < ticketNodes.length; i++) {
            var text = util.findRegExp(ticketNodes.eq(i).text(), /(\d[\d\s\-]+)/);
            text = util.filter(text);
            if (text != "") {
                result.TicketNumbers = result.TicketNumbers.concat(text.split(' - '));
            }
        }// for (var i = 0; i < ticketNodes.length; i++)
        browserAPI.log("TicketNumbers: " + JSON.stringify(result.TicketNumbers));

        // Segments
        var i = 0;
        var airSegments = [];
        var trainSegments = [];
        var segments = $('div.flightContent').find('div.contentBlocFlight:has(div.flightNumber)');
        browserAPI.log(">>> Total segments: " + segments.length);
        segments.each(function () {
            var node = $(this);
            var segment = {};
            var DT = null;
            var unixtime = null;
//            console.log(node);
            browserAPI.log(">>> Segment " + i);

            // FlightNumber
            segment.FlightNumber = util.findRegExp( node.find('div.flightNumber').text() , /[A-Z]+\s*(\d+)/);
            browserAPI.log("FlightNumber: " + segment.FlightNumber);
            // AirlineName
            segment.AirlineName = util.findRegExp( node.find('div.flightNumber').text() , /([A-Z]+)\s*\d+/);
            browserAPI.log("AirlineName: " + segment.AirlineName);
            // Operator
            segment.Operator = util.findRegExp( node.find('span:contains("Provided by")').text() , /Provided by\s*(.*)/);
            browserAPI.log("Operator: " + segment.Operator);
            // Aircraft
            segment.Aircraft = node.find('span:contains("Aircraft") > span').text();
            browserAPI.log("Aircraft: " + segment.Aircraft);
            // Cabin
            segment.Cabin = util.findRegExp( node.find('span:contains("Class")').text(), /Class\s*:\s*(.*)/);
            browserAPI.log("Cabin: " + segment.Cabin);
            // // Duration
            // segment.Duration = util.filter($('div.flightContent').eq(i).find('span:contains("duration")').nextAll('span').text());
            // browserAPI.log("Duration: " + segment.Duration);

            // DepCode
            segment.DepCode = util.findRegExp( node.find('div.flightStopOver > div span[class *= "flightText"]').eq(0).text() , /\(([A-Z]{3})\)/);
            browserAPI.log("DepCode: " + segment.DepCode);
            // DepName
            segment.DepName = util.findRegExp( node.find('div.flightStopOver > div span[class *= "flightText"]').eq(0).text() , /([^\(]+)/);
            browserAPI.log("DepName: " + segment.DepName);
            // DepartureTerminal
            segment.DepartureTerminal = util.findRegExp( node.find('div.flightStopOver > div span[class *= "flightText"]').eq(0).find('span.terminal--Label').text() , /TERMINAL\s*([^<]+)/i);
            browserAPI.log("DepartureTerminal: " + segment.DepartureTerminal);
            var depTime = util.findRegExp( node.find('div.flightStopOver > div span[class *= "time"]').eq(0).text() , /([^\(]+)/);
            browserAPI.log("depTime: " + depTime);

            // ArrCode
            segment.ArrCode = util.findRegExp( node.find('div.flightStopOver > div span[class *= "flightText"]').eq(1).text() , /\(([A-Z]{3})\)/);
            browserAPI.log("ArrCode: " + segment.ArrCode);
            // ArrName
            segment.ArrName = util.findRegExp( node.find('div.flightStopOver > div span[class *= "flightText"]').eq(1).text() , /([^\(]+)/);
            browserAPI.log("ArrName: " + segment.ArrName);
            // ArrivalTerminal
            segment.ArrivalTerminal = util.findRegExp( node.find('div.flightStopOver > div span[class *= "flightText"]').eq(1).find('span.terminal--Label').text() , /TERMINAL\s*([^<]+)/i);
            browserAPI.log("ArrivalTerminal: " + segment.ArrivalTerminal);

            var arrTime = util.findRegExp( node.find('div.flightStopOver > div span[class *= "time"]').eq(1).text() , /([^\(]+)/);
            var nextDay = util.findRegExp( node.find('div.flightStopOver > div span[class *= "time"]').eq(2).text() , /\+(\d)\s*Day/i);
            browserAPI.log("arrTime: " + arrTime + " / next day: +" + nextDay);
            var date = util.findRegExp( node.find('div[class *= "date"]').text() , /\,\s*([^<]+)/);
            browserAPI.log("date: " + date);

            // DepDate
            if (depTime && date) {
                browserAPI.log("depart: " + date + " " + depTime);
                DT = date + ', ' + depTime;
                DT = new Date(DT + ' UTC');
                unixtime = DT / 1000;
                if (!isNaN(unixtime)) {
                    browserAPI.log("DepDate: " + date + ' ' + depTime + " Unixtime: " + unixtime);
                    segment.DepDate = unixtime;
                } else
                    browserAPI.log(">>> Invalid DepDate");
            }// if (depTime && date)
            // ArrDate
            if (date && date) {
                browserAPI.log("arrival: " + date + " " + date);
                DT = date + ', ' + arrTime;
                DT = new Date(DT + ' UTC');
                if (nextDay) {
                    browserAPI.log("day: +" + nextDay);
                    DT.setDate(DT.getDate() + parseFloat(nextDay));
                    browserAPI.log("Date: " + DT);
                }
                unixtime = DT / 1000;
                if (!isNaN(unixtime)) {
                    browserAPI.log("ArrDate: " + date + ' ' + arrTime + " Unixtime: " + unixtime);
                    segment.ArrDate = unixtime;
                } else
                    browserAPI.log(">>> Invalid ArrDate");
            }// if (date && date)

            // Status
            segment.Status = node.find('div.flightStopOver').next('ul').find('li').eq(0).text().trim();
            browserAPI.log('status: ' + segment.Status);
            if (util.findRegExp(segment.Status, /(cancell?ed)$/i)) {
                segment.Cancelled = true;
                browserAPI.log('Cancelled: ' + segment.Cancelled);
            }
            // console.log(segment);//todo
            browserAPI.log("<<< Segment " + i);
            i++;
            if (util.findRegExp(segment.Aircraft, /(train)/i)) {
                segment.Aircraft = null;
                segment.Operator = null;
                trainSegments.push(segment);
            } else {
                airSegments.push(segment);
            }
        });
        if (trainSegments.length > 0) {
            var air = JSON.parse(JSON.stringify(result));
            var train = JSON.parse(JSON.stringify(result));
            train.TripCategory = 3;
            air.TripSegments = airSegments;
            train.TripSegments = trainSegments;
            params.data.Itineraries.push(air);
            params.data.Itineraries.push(train);
        } else {
            result.TripSegments = airSegments;
            params.data.Itineraries.push(result);
        }

        if (params.data.links.length == 0) {
            params.account.properties.Itineraries = params.data.Itineraries;
            // console.log(params.account.properties);// todo
            provider.saveProperties(params.account.properties);
            provider.complete();
        }// if (params.data.links.length == 0)
        else {
            var nextLink = '';
            nextLink = params.data.links.pop();
            // save data
            provider.saveTemp(params.data);

            provider.setNextStep('openItinerary', function () {
                browserAPI.log(">>> open next Itinerary: " + nextLink);
                document.location.href = nextLink;
            });
        }
    },

    arrayUnique: function (array) {
        var unique = [];
        for (var i = 0; i < array.length; ++i) {
            if (unique.indexOf(array[i]) == -1)
                unique.push(array[i]);
        }
        return unique;
    }

};