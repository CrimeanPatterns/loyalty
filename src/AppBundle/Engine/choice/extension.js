var plugin = {
    //keepTabOpen: true, //todo
    hideOnStart: true/*(typeof(applicationPlatform) != 'undefined' && applicationPlatform == 'android') ? false : true*/,
    hosts: {'www.choicehotels.com': true, 'secure.choicehotels.com': true},
    cashbackLink: '', // Dynamically filled by extension controller
    reservationsLimk: 'https://www.choicehotels.com/choice-privileges/account/recent',
    loyaltyProgramId: 'GP',

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function(){
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getFocusTab: function(account, params){
        return true;
    },

    getStartingUrl: function (params) {
        return 'https://www.choicehotels.com/choice-privileges/account';
    },

    startFromChase: function (params) {
        plugin.start(params);
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));

        // for desktop
        let switchToUS = $('a[id = "en-us"], img[src *= "us-flag"] + ul a:contains("English")');
        browserAPI.log("switchToUS -> " + switchToUS.length);

        let langSelector = $('button#site-lang-selector');

        if (langSelector.length && langSelector.text() !== 'English') {
            browserAPI.log("langSelector -> " + langSelector.text());
            langSelector.click();
            switchToUS = $('a[id = "en-us"], img[src *= "us-flag"] + ul a:contains("English")');
        }

        if (
            document.location.href.indexOf('.com/login') === -1
            && document.location.href.indexOf('.com/choice') === -1
            && switchToUS.length === 1
        ) {
            browserAPI.log('Force switch to english');
            browserAPI.log('Current URL: ' + document.location.href);
            provider.logBody("switchToEnglishPageOne");
            switchToUS.get(0).click();
        }
        // for mobile
        let lang = util.findRegExp(document.location.href, /\.com\/(\w{2}-\w{2})\//);
        browserAPI.log('Lang: ' + lang);
        if (
            provider.isMobile
            && lang
            && lang !== 'en-us'
        ) {
            browserAPI.log('Force switch to english');
            browserAPI.log('Current URL: ' + document.location.href);
            provider.logBody("switchToEnglishPageTwo");
            document.querySelector('#hamburgerMenuButton').click();
            setTimeout(function () {
                provider.eval("" +
                              "document.querySelector('#siteSelector').selectedIndex = document.querySelector('option[value=\"/en-us\"]').index;\n" +
                              "            function createNewEvent(eventName) {\n" +
                              "                var event;\n" +
                              "                if (typeof(Event) === \"function\") {\n" +
                              "                    event = new Event(eventName);\n" +
                              "                } else {\n" +
                              "                    event = document.createEvent(\"Event\");\n" +
                              "                    event.initEvent(eventName, true, true);\n" +
                              "                }\n" +
                              "                return event;\n" +
                              "            }\n" +
                              "            let siteSelector = document.getElementById('siteSelector');\n" +
                              "            siteSelector.dispatchEvent(createNewEvent('change'));" +
                              "");
            }, 500);
            return;
        }

        browserAPI.log('Current URL: ' + document.location.href);

        var counter = 0;
        setTimeout(function() {
            var start = setInterval(function () {
                browserAPI.log("[start]: waiting... " + counter);
                var isLoggedIn = plugin.isLoggedIn();
                // location services are disabled
                var popup = $('.location-services-modal:visible');
                if (popup.length > 0)
                    popup.find('button').get(0).click();
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
                // switch language
                if ($('a:contains("Sélectionner une langue"):visible, a:contains("Seleccione un idioma"):visible').length > 0
                    && $('a:contains("Anglais"), a:contains("Inglés")').length > 0) {
                    browserAPI.log("Switch language to English");
                    clearInterval(start);
                    $('a:contains("Anglais"):eq(1), a:contains("Inglés"):eq(1)').get(0).click();
                    return;
                }
                if (isLoggedIn === null && counter > 15) {
                    clearInterval(start);
                    browserAPI.log("Current URL: " + document.location.href);
                    provider.logBody("lastPage");
                    // ChoiceHotels.com is Temporarily Unavailable.
                    var errors = $('h1:contains("is Temporarily Unavailable."):visible');
                    if (errors.length == 0 && $('body:contains("Banned: Detecting too many failed attempts from your IP. Access is denied until the ban expires."):visible').length > 0)
                        errors = "Banned: Detecting too many failed attempts from your IP. Access is denied until the ban expires.";
                    if (errors.length > 0)
                        provider.setError([errors.text(), util.errorCodes.providerError], true);
                    else {
                        if (errors.length == 0 && $('p:contains("net::ERR_TIMED_OUT"):visible, p:contains("net::ERR_UNKNOWN_URL_SCHEME"):visible').length > 0 && $('h2:contains("Webpage not available"):visible').length > 0) {
                            provider.setError(util.errorMessages.providerErrorMessage, true);
                            return;
                        }
                        provider.setError(util.errorMessages.unknownLoginState);
                    }
                    return;
                }// if (isLoggedIn === null && counter > 10)
                counter++;
            }, 500);
        }, 2000)
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if (
            $('.account-title:contains("Welcome,")').length > 0
            || $('dt.my-account:visible').length > 0
            || $('span.full-name').length > 0
            || $('span[data-seleniumid = "firstNamePopup"]').length > 0
            || $('div.member-loyalty-account:visible').length > 0
            || (provider.isMobile && $('a.sign-in-status:visible div:contains("pts")').length > 0)) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('button:contains("Sign In"):visible, button:contains("Sign in"):visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.findRegExp($('p span:contains("Member Number")').closest('p').text(), /:\s*(\w+)/);
        browserAPI.log("number " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && number
            && (number === account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            let menu = $('nav button.ch-hamburger.btn-reset, #SignInFlyoutBTN');
            if (menu.length) {
                browserAPI.log("open menu");
                menu.get(0).click();
            }

            setTimeout(function() {
                $('a:contains("Sign Out"), button:contains("Sign out"), button:contains("Sign Out")').get(0).click();
                setTimeout(function() {
                    plugin.loadLoginForm(params);
                }, 2000)
            }, 500)
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function(){
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function() {
                document.location.href = "https://www.choicehotels.com/reservations";
            });
            return;
        }
        // open login form
        /*$('span:contains("Sign In")').click();
        if (provider.isMobile) {
            $('ch-login-modal').remove();
        }*/
        if (!!navigator.userAgent.match(/Trident\/\d\./)) {
            $('div.modal-dialog').parent('div').remove();
            $('div.modal-backdrop').removeClass('modal-backdrop');
        }
        // wait login form
        var counter = 0;
        var login = setInterval(function() {
            var form = $('form[name = "loginForm"], form[name = "chUserLoginForm"], div.login-form > form, div.sign-in-form > form');
            browserAPI.log("[login]: waiting... " + counter);
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");

                clearInterval(login);
                provider.setNextStep('checkLoginErrors', function() {
                    form.find('input[id = "cpSignInPassword"]').val(params.account.password);
                    util.sendEvent(form.find('input[id = "cpSignInPassword"]').get(0), 'input');
                    // util.sendEvent(form.find('input[id = "cpSignInPassword"]').get(0), 'change');
                    // util.sendEvent(form.find('input[id = "cpSignInPassword"]').get(0), 'blur');
                    setTimeout(function () {
                        form.find('input[id = "cpSignInUsername"]').val(params.account.login);
                        util.sendEvent(form.find('input[id = "cpSignInUsername"]').get(0), 'input');
                        setTimeout(function () {
                            form.find('button.btn-login:contains("Sign In")').get(0).click();
                            setTimeout(function () {
                                plugin.checkLoginErrors(params);
                            }, 7000);
                        }, 300);
                    }, 300);
                });



                //plugin.submitLoginForm(params);
                /*provider.setNextStep('submitLoginForm', function(){
                    // angularjs
                    // provider.eval("angular.reloadWithDebugInfo();");
                    provider.eval("window.name = 'NG_ENABLE_DEBUG_INFO!' + window.name;");
                    browserAPI.log('location: ' + document.location.href);
                    // document.location.href = plugin.getStartingUrl(params);
                    document.location.href = 'https://www.choicehotels.com';
                    browserAPI.log('location: ' + document.location.href);
                });*/
                // angularjs
                // provider.eval("var scope = angular.element(document.querySelector('[name=chUserLoginForm]')).scope();"
                //     + "scope.$apply(function(){scope.username = '" + params.account.login + "';"
                //     + "scope.password = '" + params.account.password + "';"
                //     + "});");

                // provider.setNextStep('checkLoginErrors', function(){
                //     clearInterval(login);
                //     // if (provider.isMobile) {
                //     //     form.find('button[type = "submit"]').click();
                //     // }
                //     // else
                //     // if (!provider.isMobile)
                //     //     provider.eval("var scope = angular.element(document.querySelector('[name=chUserLoginForm]')).scope();"
                //     //         + "scope.$apply(function(){"
                //     //         + "scope.login({preventDefault:function(){}})});");
                //     setTimeout(function() {
                //         // refs #12574
                //         var form = $('form[name = "loginForm"]');
                //         if (provider.isMobile || (form.length == 0 && $('form[name = "chUserLoginForm"]:visible').length > 0))
                //             form = $('form[name = "chUserLoginForm"]');
                //         browserAPI.log("waiting...  second form");
                //         if (form.length > 0) {
                //             browserAPI.log("submitting saved credentials -> second form");
                //             form.find('input[name = "username"]').val(params.account.login);
                //             form.find('input[name = "password"]').val(params.account.password);
                //             // refs #11326
                //             util.sendEvent(form.find('input[name = "username"]').get(0), 'input');
                //             util.sendEvent(form.find('input[name = "password"]').get(0), 'input');
                //             provider.setNextStep('checkLoginErrors', function(){
                //                 form.find('button[type = "submit"]').click();
                //                 setTimeout(function() {
                //                     plugin.checkLoginErrors(params);
                //                 }, 2000);
                //             });
                //         }
                //         else
                //             plugin.checkLoginErrors(params);
                //     }, 2000);
                // });
            }
            if (counter > 80) {
                clearInterval(login);
                provider.logBody("loginPage");
                provider.setError(util.errorMessages.loginFormNotFound);
            }
            if ($('div.member-loyalty-account > span:eq(0)').length > 0) {
                clearInterval(login);
                plugin.loginComplete(params);
            }// if ($('div.text-uppercase:has(strong:contains("Member Number")) + div').length > 0 || $('div.cp-user-name:visible').length > 0)
            if ($('p:contains("net::ERR_TIMED_OUT"):visible').length > 0 && $('h2:contains("Webpage not available"):visible').length > 0) {
                clearInterval(login);
                provider.setError(util.errorMessages.providerErrorMessage, true);
                return;
            }
            counter++;
        }, 500);
    },

    /**
     * @deprecated
     * @param params
     */
    submitLoginForm: function (params) {
        browserAPI.log("submitLoginForm");
        // open login form
        $('#header-sign-in-button').click();
        if (provider.isMobile) {
            $('ch-login-modal').remove();
        }
        if (!!navigator.userAgent.match(/Trident\/\d\./)) {
            $('div.modal-dialog').parent('div').remove();
            $('div.modal-backdrop').removeClass('modal-backdrop');
        }
        // wait login form
        let counter = 0;
        let login = setInterval(function () {
            const form = $('form[name = "loginForm"], form[name = "chUserLoginForm"], div.login-form > form, div.sign-in-form > form');
            browserAPI.log("[submitLoginForm]: waiting... " + counter);

            if (
                provider.isMobile
                && form.length === 0
                && counter === 20
                && $('button.sign-in-button:visible').length > 0
            ) {
                browserAPI.log("Force open login form");
                $('button.sign-in-button:visible').click();
            }

            if (form.length > 0 && counter > 3) {

                clearInterval(login);
                provider.setNextStep('checkLoginErrors', function(){
                    if (provider.isMobile) {
                        $('div.sign-in').remove();
                    }
                    // angularjs
                    /*provider.eval("var scope = angular.element(document.querySelector('[name=loginForm], [name = chUserLoginForm]')).scope();"
                        + "scope.$apply(function(){scope.username = '" + params.account.login + "';"
                        + "scope.password = '" + params.account.password + "';"
                        + "});");*/
                    if (provider.isMobile || $('form[name = "loginForm"]').length == 0) {
                        form.find('button[type = "submit"]').click();
                    } else if (!provider.isMobile)
                        /*provider.eval("var scope = angular.element(document.querySelector('[name=loginForm], [name = chUserLoginForm]'')).scope();"
                                + "scope.$apply(function(){"
                                + "scope.login({preventDefault:function(){}})});");*/

                    setTimeout(function () {
                        // refs #12574
                        var form = $('form[name = "loginForm"]');
                        if (provider.isMobile || (form.length == 0 && $('form[name = "loginForm"]:visible').length > 0))
                            form = $('form[name = "chUserLoginForm"]');

                        browserAPI.log("waiting...  second form");

                        if (form.length === 0 && $('div.login-form > form:visible, div.sign-in-form > form:visible').length > 0) {
                            form = $('div.login-form > form, div.sign-in-form > form');
                            browserAPI.log("submitting saved credentials -> second react form");

                            function triggerInput(selector, enteredValue) {
                                const input = document.querySelector(selector);
                                const createEvent = function(name) {
                                    var event = document.createEvent('Event');
                                    event.initEvent(name, true, true);
                                    return event;
                                };
                                input.dispatchEvent(createEvent('focus'));
                                input.value = enteredValue;
                                input.dispatchEvent(createEvent('change'));
                                input.dispatchEvent(createEvent('input'));
                                input.dispatchEvent(createEvent('blur'));
                            }
                            triggerInput('#cpSignInUsername', '' + params.account.login );
                            triggerInput('#cpSignInPassword', '' + params.account.password);

                            provider.setNextStep('checkLoginErrors', function () {
                                form.find('button.submit-button').click();
                                setTimeout(function () {
                                    plugin.checkLoginErrors(params);
                                }, 2000);
                            });

                            return;
                        }

                        if (form.length > 0) {
                            browserAPI.log("submitting saved credentials -> second form");
                            form.find('input[name = "username"]').val(params.account.login);
                            form.find('input[name = "password"]').val(params.account.password);
                            // refs #11326
                            util.sendEvent(form.find('input[name = "username"]').get(0), 'input');
                            util.sendEvent(form.find('input[name = "password"]').get(0), 'input');
                            provider.setNextStep('checkLoginErrors', function () {
                                form.find('button[type = "submit"]').click();
                                setTimeout(function () {
                                    plugin.checkLoginErrors(params);
                                }, 2000);
                            });
                        }
                        else
                            plugin.checkLoginErrors(params);
                    }, 2000);
                });
                counter++;
            }
            if (counter > 80) {
                clearInterval(login);

                provider.logBody("submitLoginFormPage");

                provider.setError(util.errorMessages.loginFormNotFound);
            }
            // ChoiceHotels.com is Temporarily Unavailable.
            const errors = $('h1:contains("is Temporarily Unavailable."):visible');

            if (errors.length > 0) {
                clearInterval(login);
                provider.setError([errors.text(), util.errorCodes.providerError], true);
            }

            const number = $('div.text-uppercase:has(strong:contains("Member Number")) + div:visible');
            const name = $('.cp-user-name:visible');
            if (number.length > 0
                || (name.length > 0 && counter > 20)) {
                clearInterval(login);
                browserAPI.log("number: '" + number.text() + "'");
                browserAPI.log("name: '" + name.text() + "'");
                plugin.loginComplete(params);
            }// if ($('div.text-uppercase:has(strong:contains("Member Number")) + div:visible').length > 0 ...)
            counter++;
        }, 500);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let counter = 0;
        let naxCounter = 10;
        let checkLoginErrors = setInterval(function () {
            browserAPI.log("[checkLoginErrors]: waiting... " + counter);
            let errors = $('div.ng-errors:visible, form[name = loginForm] > p.text-danger:visible, div.error-header:visible, small#cpSignInUsername-error:visible > span, div.error-message-body > p:visible');
            var number = $('div.member-loyalty-account > span:eq(0)');
            const headerName = $('.cp-user-name:visible');
            var reCaptcha = $('span:contains("Please check the box below to verify your login"):visible, div.validation-message:contains("Please check the box below to verify your login"):visible');
            const needToUpdateProfile = $('h1:contains("Complete two easy steps to start using your email as your username."):visible');
            const trouble = $('h1:contains("We\'re having some trouble connecting to your Choice Privileges account."):visible');
            var form = $('form[name = "loginForm"]'),
                submit = form.find('button[type="submit"]');
            if (provider.isMobile || (form.length == 0 && $('form[name = "loginForm"]:visible').length > 0))
                form = $('form[name = "chUserLoginForm"]');
            var logout;
            if (reCaptcha.length > 0) {
                clearInterval(checkLoginErrors);
                // if (provider.isMobile && !params.autologin){
                if (provider.isMobile){
                    provider.command('show', function(){
                        provider.reCaptchaMessage();
                        browserAPI.log("wait captcha entering...");
                        form.bind('submit.captcha', function(event){
                            browserAPI.log("captcha entered by user");
                            form.unbind('submit.captcha');
                            browserAPI.log("go to -> loginComplete");
                            if (    typeof(applicationPlatform) != 'undefined' &&
                                    applicationPlatform == 'android'    ) {
                                submit.click();
                            } else {
                                if (params.autologin)
                                    submit.click();
                                else
                                    provider.command('hide', function() {
                                        submit.click();
                                    });
                            }
                            event.preventDefault();
                        });
                    });
                }else if(!provider.isMobile){
                    provider.reCaptchaMessage();
                    $('#awFader').remove();
                }
                var counter2 = 0;
                logout = setInterval(function () {
                    browserAPI.log("login waiting... " + counter2);
                    if ($('dt.my-account:visible').length > 0
                        || $('span.full-name, .cp-user-name').length > 0
                        // mobile
                        || $('strong:contains("Hi, ")').length > 0
                        || counter2 > 60) {
                        browserAPI.log("go to -> loginComplete");
                        provider.logBody("checkLoginErrorsPage");
                        clearInterval(logout);
                        plugin.loginComplete(params);
                    }
                    var errors = $('div.ng-errors:visible, form[name = loginForm] > p.text-danger:visible, div.error-header:visible');
                    if (errors.length > 0 && reCaptcha.length === 0) {
                        clearInterval(logout);
                        browserAPI.log("error -> " + errors.text());
                        provider.setError(errors.text(), true);
                    }
                    if (counter2 > 65) {
                        clearInterval(logout);
                        browserAPI.log(">>> something went wrong");
                    }
                    counter2++;
                }, 500);
            }// if (reCaptcha.length > 0)
            /*
            if (needToUpdateProfile.length > 0) {
                clearInterval(checkLoginErrors);
                provider.setError(["Choice Hotels (Choice Privileges) website needs you to update your profile, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);
                return;
            }
            */

            if ($('h2:contains("How should we send you the code?"):visible').length) {
                naxCounter = 90;
                if (counter < 2)
                    provider.showFader('It seems that Choice Hotels (Choice Privileges) needs to identify this computer before you can update this account. Please follow the instructions on the new tab to get this computer authorized and then please try to update this account again.');
            }

            if (trouble.length > 0) {
                clearInterval(checkLoginErrors);
                provider.setError([trouble.text(), util.errorCodes.providerError], true);
                return;
            }

            if (errors.length > 0 && reCaptcha.length === 0 && util.filter(errors.text()) !== "") {
                clearInterval(checkLoginErrors);
                browserAPI.log("error -> " + errors.text());
                if (
                    /Temporarily unable to sign in./.test(errors.text())
                    || /We're sorry, an unexpected error occurred/.test(errors.text())
                ) {
                    provider.setError([errors.text(), util.errorCodes.providerError], true);
                    return;
                }

                if (/Your account is locked/.test(errors.text())) {
                    provider.setError([errors.text(), util.errorCodes.lockout], true);
                    return;
                }

                provider.setError(errors.text(), true);
            }// if (errors.length > 0 && reCaptcha.length == 0)
            if (number.length > 0 || (headerName.length > 0 && counter > 10)) {
                clearInterval(checkLoginErrors);
                plugin.loginComplete(params);
            }// if (number.length > 0 || headerName.length > 0)
            if (counter > naxCounter) {
                browserAPI.log("error -> unknown");
                clearInterval(checkLoginErrors);

                if ($('h2:contains("How should we send you the code?"):visible').length) {
                    if (params.autologin)
                        provider.setError(['It seems that Choice Hotels (Choice Privileges) needs to identify this computer before you can log in. Please follow the instructions on the new tab (the one that shows your Choice Hotels (Choice Privileges) authentication options) to get this computer authorized and then please try to auto-login again.', util.errorCodes.providerError], true);
                    else {
                        provider.setError(['It seems that Choice Hotels (Choice Privileges) needs to identify this computer before you can update this account. Please follow the instructions on the new tab (the one that shows your Choice Hotels (Choice Privileges) authentication options) to get this computer authorized and then please try to update this account again.', util.errorCodes.providerError], true);
                    }
                    return;
                }

                if (naxCounter === 90) {
                    let question = $('strong:contains("Keep this window open.")').parent().first().contents().eq(1);
                    if (question.length)
                        provider.setError([question.text(), util.errorCodes.question], true);
                }

                /*if (/@/.test(params.account.login)) {
                    provider.setError(['Username cannot include \'@\' symbol', util.errorCodes.invalidPassword], true);
                    return;
                }*/

                plugin.loginComplete(params);
            }
            counter++;
        }, 1000);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function(){
                document.location.href = plugin.reservationsLimk;
            });
            return;
        }
        // if (typeof(applicationPlatform) != 'undefined' && applicationPlatform == 'android') {
        //     provider.command('hide', function() {
        //         plugin.loadAccount(params);
        //     });
        // } else
            plugin.loadAccount(params);
    },

	toItineraries: function(params) {
        browserAPI.log("toItineraries");
        var counter = 0;
        var toItineraries = setInterval(function () {
            browserAPI.log("[toItineraries]: waiting... " + counter);
            var link = $('td:has(span:contains("' + params.account.properties.confirmationNumber + '")) + td + td > a:contains("View")');
            if (link.length > 0) {
                clearInterval(toItineraries);
                provider.setNextStep('itLoginComplete', function(){
                    link.get(0).click();
                    setTimeout(function() {
                        plugin.itLoginComplete(params);
                    }, 3000)
                });
            }
            if (counter > 20) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
            counter++;
        }, 500);
	},

	getConfNoItinerary: function(params) {
        browserAPI.log("getConfNoItinerary");
		var properties = params.account.properties.confFields;
		var form = $('form[name = "reservationsConfirmationForm"]');
		if (form.length > 0) {
			form.find('input[name = "confirmationNumber"]').val(properties.ConfNo);
			form.find('input[name = "confirmationLastName"]').val(properties.LastName);
			provider.setNextStep('itLoginComplete', function(){
                // refs #11326
                util.sendEvent(form.find('input[name = "confirmationNumber"]').get(0), 'input');
                util.sendEvent(form.find('input[name = "confirmationLastName"]').get(0), 'input');

                form.find('button').get(0).click();
                setTimeout(function() {
                    plugin.itLoginComplete(params);
                }, 3000)
            });
		}// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
	},

	itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
		provider.complete();
	},

    loadAccount: function (params) {
        browserAPI.log("loadAccount");

        if (params.autologin) {
            provider.complete();
            browserAPI.log("Only auto login");
            return;
        }

        const myAccountUrl = 'https://www.choicehotels.com/choice-privileges/account';

        if (document.location.href.indexOf('/choice-privileges/account') === -1) {
            provider.setNextStep('waitingAccountLoading', function () {
                //document.location.href = myAccountUrl;// location change will break session
                let myAccountLink = $('a[href = "/choice-privileges/account"]');

                if (myAccountLink.length === 0) {
                    browserAPI.log("force redirect to my account");

                    document.location.href = myAccountUrl;
                    return;
                }

                if (myAccountLink.length === 0) {
                    browserAPI.log("My account link not found");
                    provider.complete();
                    return;
                }// if (!myAccountLink.length === 0)

                myAccountLink.get(0).click();
                setTimeout(function() {
                    plugin.waitingAccountLoading(params);
                }, 2000);
            });

            return;
        }// if (document.location.href.indexOf('/choice-privileges/account') === -1)

        plugin.parse(params);
    },

    waitingAccountLoading: function (params) {
        browserAPI.log("waitingAccountLoading");
        let counter = 0;
        let waitingAccountLoading = setInterval(function () {
            browserAPI.log("waiting account loading... " + counter);
            const number = $('div.text-uppercase:has(strong:contains("Member Number")) + div, div.member-loyalty-account > span:eq(0)');

            if (number.length > 0 || counter > 20) {
                clearInterval(waitingAccountLoading);
                plugin.loginComplete(params);
            }
            counter++;
        }, 500);
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.updateAccountMessage();
        let data = {};
        /*
        // Name
        var name = $('span.full-name');
        if (name.length > 0) {
            name = util.beautifulName(name.text());
            browserAPI.log("Name: " + name);
            data.Name = name;
        } else
            browserAPI.log(">>> Name not found");
        // Member Since
        var memberSince = $('div.text-uppercase:has(strong:contains("Member Since")) + div');
        if (memberSince.length > 0) {
            browserAPI.log("Member Since: " + util.trim(memberSince.text()));
            data.MemberSince = util.trim(memberSince.text());
        } else
            browserAPI.log(">>> Member Since not found");
        // Member Number
        var number = $('div.text-uppercase:has(strong:contains("Member Number")) + div');
        if (number.length > 0) {
            number = number.text().replace(/\s*
            /g, '');
            browserAPI.log("Member Number: " + number);
            data.Number = number;
        } else
            browserAPI.log(">>> Member Number not found");
        // Balance - Choice Privileges Points
        var balance = $('span.points-big');
        if (balance.length > 0 && balance.text() != '') {
            browserAPI.log("Balance: " + balance.text());
            data.Balance = util.trim(balance.text());
        }// if (balance.length > 0 && balance.text() != '')
        else {
            browserAPI.log(">>> Balance not found");

            var error = $('p:contains("We\'re sorry, an unexpected error has occurred."):visible:eq(0)');
            if (balance.text() == '' && typeof (data.Name) != 'undefined' && error.length > 0) {
                provider.setError([error.text(), util.errorCodes.providerError], true);
                return;
            }// if (balance.text() == '' && typeof (data.Name) != 'undefined' && error.length > 0)

            if (plugin.isLoggedIn()) {
                browserAPI.log(">>> Something went wrong");
                provider.complete();
                return;
            }

            // retries
            var retry = $.cookie("choicehotels.com_aw_retry_"+params.account.login);
            browserAPI.log(">>> retry " + retry);
            if ((typeof(retry) === 'undefined' || retry === null) || retry < 2) {
                if (typeof(retry) === 'undefined' || retry === null)
                    retry = 0;
                retry++;
                $.cookie("choicehotels.com_aw_retry_"+params.account.login, retry, { expires: 0.01, path:'/', domain: '.choicehotels.com', secure: true });
                plugin.login(params);
                return;
            }// if (retry == null || retry < 2)
            else
                return;
        }
        // Elite Status
        // var status = $('div.cp-user-level');
        // Elite Status // refs #16667
        var status = $('div.nights-stayed-container div.ch-icon-check-mark').last().next('div').next('div.ellipsis').children('strong');
        if (status.length > 0) {
            status = status.text();
            if (/\d+\s*Nights/ig.test(status))
                status = 'None';
            browserAPI.log("Status: " + util.trim(status));
            data.ChoicePrivileges = util.trim(status);
        }
        else
            browserAPI.log(">>> Elite Status not found");
        // Nights to next status
        var eligible = util.findRegExp( $('div:contains("To reach")').prev('div:contains("More Nights")').text(), /(\d+)\s*More/i );
        if (eligible && eligible.length > 0) {
            browserAPI.log("Nights to next status: " + eligible);
            data.Eligible = eligible;
        } else
            browserAPI.log(">>> Nights to next status not found");
        // Exp date // refs #12872
        var expDate = $('span:contains("Keep your points active by completing one of many") + strong');
        if (expDate.length > 0) {
            expDate = expDate.text();
            browserAPI.log("Account Expiry Date: " + expDate);
            var date = new Date(expDate + ' UTC');
            var unixtime = date / 1000;
            if (unixtime != 'NaN') {
                browserAPI.log("Expiration Date: " + date + " Unixtime: " + unixtime);
                if (typeof(data.AccountExpirationDate) != 'undefined')
                    browserAPI.log("Expiration Date set already");
                else
                    data.AccountExpirationDate = unixtime;
            } else
                browserAPI.log(">>> Expiration Date not found");
        }// if (expDate.length > 0)
        */

        $.ajax({
            url: 'https://www.choicehotels.com/webapi/user-account?include=year_to_date_nights%2Cloyalty_account_forfeiture_date%2Cppc_status&preferredLocaleCode=en-us&siteName=us',
            async: false,
            success: function(response) {
                browserAPI.log("parse properties");
                response = $(response);
                browserAPI.log("---------------- data ----------------");
                browserAPI.log(response[0]);
                browserAPI.log("---------------- data ----------------");
                let guestProfile = response[0].guestProfile;
                if (typeof guestProfile.loyaltyProgramId != 'undefined') {
                    plugin.loyaltyProgramId = guestProfile.loyaltyProgramId;
                }


                // Name
                let middleName = '';

                if (typeof (guestProfile.middleName) != 'undefined') {
                    middleName = guestProfile.middleName;
                }

                let name = util.beautifulName(util.filter(guestProfile.firstName + ' ' + middleName + ' ' + guestProfile.lastName));
                browserAPI.log("Name: " + name);
                data.Name = name;
                // Member Number
                if (typeof (guestProfile.choicePrivilegeAccountNumber) != 'undefined') {
                    data.Number = guestProfile.choicePrivilegeAccountNumber;
                    browserAPI.log("Member Number: " + data.Number);
                } else
                    browserAPI.log(">>> Member Number not found");

                let loyaltyAccounts = [];
                let loyaltyAccount = null;
                let accountBalanceUnits = 0;

                if (typeof (response[0].loyaltyAccounts) !== 'undefined') {
                    loyaltyAccounts = response[0].loyaltyAccounts;
                }

                for (let loyaltyAcc in loyaltyAccounts) {
                    if (!loyaltyAccounts.hasOwnProperty(loyaltyAcc)) {
                        continue;
                    }

                    let accountBalanceUnit = loyaltyAccounts[loyaltyAcc].accountBalanceUnits;

                    if (
                        accountBalanceUnit === 'POINTS'
                        && ['AT', 'VB'].indexOf(loyaltyAccounts[loyaltyAcc].loyaltyProgramId) === -1
                    ) {
                        loyaltyAccount = loyaltyAccounts[loyaltyAcc];

                        break;
                    } else if (accountBalanceUnit === 'MILES') {
                        accountBalanceUnits++;
                    }
                }

                if (loyaltyAccount === null) {
                    // AccountID: 413254
                    // We're sorry, an unexpected error has occurred.
                    if (
                        typeof response[0].outputInfo.UNAVAILABLE_LOYALTY_ACCOUNT !== 'undefined'
                        && response[0].outputInfo.UNAVAILABLE_LOYALTY_ACCOUNT === 'No loyalty account associated to this guest profile.'
                    ) {
                        provider.setError(["We're sorry, an unexpected error has occurred.", util.errorCodes.providerError], true);
                        return;
                    }
                    // AccountID: 1334959 / 4441805 / 612540
                    else if (accountBalanceUnits >= 1) {
                        browserAPI.log('set Balance NA, accountBalanceUnits >= 1');
                        data.Balance = 'null';
                    }
                    // AccountID: 3714455
                    else if (
                        loyaltyAccounts.length === 1
                        && typeof (loyaltyAccounts[0]) !== 'undefined'
                        && typeof (loyaltyAccounts[0].loyaltyProgramId) !== 'undefined'
                        && loyaltyAccounts[0].loyaltyProgramId === 'AT'
                    ) {
                        browserAPI.log('set Balance NA, loyaltyProgramId === "AT"');
                        data.Balance = 'null';
                    }
                    // AccountID: 4543263, 3646209
                    else if (
                        typeof (data.Name) !== 'undefined'
                        && typeof (data.Number) !== 'undefined'
                        && data.Name !== ''
                        && data.Number !== ''
                        && loyaltyAccounts.length === 0
                    ) {
                        browserAPI.log('set Balance NA, loyaltyAccounts.length === 0');
                        data.Balance = 'null';
                    }

                    return;
                }// if (loyaltyAccount === null) {

                // Balance - Choice Privileges Points
                if (typeof (loyaltyAccount.accountBalance) !== 'undefined') {
                    data.Balance = loyaltyAccount.accountBalance;
                    browserAPI.log("Balance: " + data.Balance);
                }// if (balance.length > 0 && balance.text() != '')
                else
                    browserAPI.log(">>> Balance not found");

                // Member Since
                let memberSince = null;

                if (typeof (loyaltyAccount.memberSince) !== 'undefined') {
                    memberSince = loyaltyAccount.memberSince;
                }

                if (memberSince) {
                    browserAPI.log("Member Since: " + memberSince);
                    let memberSinceDate = new Date (memberSince);
                    const month = memberSinceDate.toLocaleString('en-us', { month: 'short' });
                    data.MemberSince = month + " " + memberSinceDate.getUTCDate() + ", " + memberSinceDate.getUTCFullYear();
                    browserAPI.log("Member Since: " + data.MemberSince);
                } else
                    browserAPI.log(">>> Member Since not found");

                // Exp date // refs #12872
                // Keep your points active by completing one of many
                let exp = null;

                if (typeof (response[0].loyaltyAccountForfeitureDate) !== 'undefined') {
                    exp = response[0].loyaltyAccountForfeitureDate;
                }

                browserAPI.log("Exp date from Profile: " + exp);

                if (exp) {
                    let dateStr = exp.replace(/-/ig, '/');
                    browserAPI.log('Exp date from Profile: ' + dateStr + ' (' + exp + ') ');
                    if ((typeof(dateStr) != 'undefined') && (dateStr !== '')) {
                        let date = new Date(dateStr + ' UTC');
                        let unixtime =  date / 1000;
                        browserAPI.log('Expiration Date: ' + dateStr + ' (' + exp + '),' + ' Unixtime: ' + unixtime);
                        if (unixtime !== 'NaN') {
                            browserAPI.log(">>> Set exp date from Profile");
                            data.AccountExpirationDate = unixtime;
                        }// if (unixtime != 'NaN')
                    }// if ((typeof(dateStr) != 'undefined') && (dateStr != ''))
                }// if (exp)

                // Nights to next status
                let yearToDateEliteNights = null;

                if (typeof (response[0].yearToDateEliteNights) !== 'undefined') {
                    yearToDateEliteNights = response[0].yearToDateEliteNights;
                }

                browserAPI.log("YTD Elite Nights: " + yearToDateEliteNights);
                let status = '';
                let nightsNeeded = 0;

                if (yearToDateEliteNights === 0) {
                    browserAPI.log("Set Elite Status by default");
                    status = "None";
                    nightsNeeded = 10;
                } else {
                    browserAPI.log("Set Elite Status by progress");
                    if (yearToDateEliteNights < 10) {
                        status = "None";
                        nightsNeeded = 10;
                    } else if ((yearToDateEliteNights >= 10) && (yearToDateEliteNights < 20)) {
                        status = "Gold";
                        nightsNeeded = 20;
                    } else if ((yearToDateEliteNights >= 20) && (yearToDateEliteNights < 40)) {
                        status = "Platinum";
                        nightsNeeded = 40;
                    } else if (yearToDateEliteNights >= 40) {
                        status = "Diamond";
                        nightsNeeded = 0;
                    } else {
                        browserAPI.log("something went wrong");
                    }
                }

                browserAPI.log(">>> nightsNeeded = " + nightsNeeded + " / status = " + status);

                // Elite Status
                if (typeof (loyaltyAccount.eliteLevel) !== 'undefined' && loyaltyAccount.eliteLevel !== null) {
                    data.ChoicePrivileges = loyaltyAccount.eliteLevel;
                    browserAPI.log("Status: " + data.ChoicePrivileges);
                }
                else {
                    browserAPI.log(">>> eliteLevel not found, set status from progress bar");
                    data.ChoicePrivileges = status;
                    browserAPI.log("Status: " + data.ChoicePrivileges);
                }
                // Nights to next status
                if (nightsNeeded > 0) {
                    data.Eligible = nightsNeeded - parseInt(yearToDateEliteNights);
                    browserAPI.log("Nights to next status (Eligible): " + data.Eligible);
                } else
                    browserAPI.log(">>> Nights to next status not found");
            },// success: function (data)
            error: function (data, textStatus, error) {
                browserAPI.log(`fail: data status = ${data.status}`);
                browserAPI.log('Error: ' + JSON.stringify(data));
            }
        });

        // exp date info
        if (typeof (data.Number) != 'undefined') {
            $.ajax({
                url: 'https://www.choicehotels.com/webapi/user-account/loyalty-statement-summaries?loyaltyAccountNumber=' + data.Number + '&loyaltyProgramId=' + plugin.loyaltyProgramId + '&preferredLocaleCode=en-us&siteName=us',
                async: false,
                success: function(response) {
                    browserAPI.log("parse exp date info");
                    response = $(response);
                    // console.log("---------------- data ----------------");
                    // console.log(response[0]);
                    // console.log("---------------- data ----------------");

                    if (typeof (response[0].statements[0]) != 'undefined') {
                        // Points expiring
                        if (typeof (response[0].statements[0].expirations) != 'undefined') {
                            for (var expiration in response[0].statements[0].expirations) {
                                if (response[0].statements[0].expirations.hasOwnProperty(expiration)) {
                                    // Points expiring
                                    var pointsExpiring = "" + response[0].statements[0].expirations[expiration];
                                    browserAPI.log("Points expiring: " + pointsExpiring);
                                    data.PointsExpiring = pointsExpiring;

                                    // safari hack
                                    var expDate = expiration.replace(/-/ig, '/');
                                    browserAPI.log("Account Expiry Date: " + expiration + ' (' + expDate + ')');
                                    var date = new Date(expDate + ' UTC');
                                    var unixtime = date / 1000;
                                    if (unixtime !== 'NaN') {
                                        browserAPI.log("Expiration Date: " + date + " Unixtime: " + unixtime);
                                        if (typeof(data.AccountExpirationDate) != 'undefined')
                                            browserAPI.log("Expiration Date set already");
                                        else
                                            data.AccountExpirationDate = unixtime;
                                    } else
                                        browserAPI.log(">>> Expiration Date not found");

                                    break;
                                }// if (response[0].statements[0].expirations.hasOwnProperty(expiration))
                            }// for (var expiration in response[0].statements[0].expirations)
                        }// if (typeof (response[0].statements[0].expirations) != 'undefined')
                        // Beginning Balance
                        if (typeof (response[0].statements[0].beginningBalance) != 'undefined') {
                            browserAPI.log("Beginning Balance: " + response[0].statements[0].beginningBalance);
                            data.BeginningBalance = "" + response[0].statements[0].beginningBalance;
                        } else
                            browserAPI.log(">>> Beginning Balance not found");
                        // Points Earned
                        if (typeof (response[0].statements[0].earned) != 'undefined') {
                            browserAPI.log("Points Earned: " + response[0].statements[0].earned);
                            data.PointsEarned = "" + response[0].statements[0].earned;
                        } else
                            browserAPI.log(">>> Points Earned not found");
                        // Points Redeemed
                        if (typeof (response[0].statements[0].redeemed) != 'undefined') {
                            browserAPI.log("Points Redeemed: " + response[0].statements[0].redeemed);
                            data.PointsRedeemed = "" + response[0].statements[0].redeemed;
                        } else
                            browserAPI.log(">>> Points Redeemed not found");
                        // Points Adjusted
                        if (typeof (response[0].statements[0].adjusted) != 'undefined') {
                            browserAPI.log("Points Adjusted: " + response[0].statements[0].adjusted);
                            data.PointsAdjusted = "" + response[0].statements[0].adjusted;
                        } else
                            browserAPI.log(">>> Points Adjusted not found");
                    }// if (typeof (response[0].statements[0]) != 'undefined')


                    // refs #11977
                    if (typeof (response[0].statements[0]) != 'undefined') {
                        browserAPI.log("parseHistory");
                        var stop = false;
                        var startDate = params.account.historyStartDate;
                        data.HistoryRows = [];
                        browserAPI.log("historyStartDate: " + startDate);

                        $.ajax({
                            timeout: 15000,
                            url: 'https://www.choicehotels.com/webapi/user-account?include=year_to_date_nights%2Cloyalty_account_forfeiture_date%2Cppc_status%2Cdgc_status&preferredLocaleCode=en-us&siteName=us',
                            async: false,
                            success: function(profileInfo) {
                                browserAPI.log("parse profile info");
                                profileInfo = $(profileInfo);
                                // console.log("---------------- profileInfo ----------------");
                                // console.log(profileInfo[0]);
                                // console.log("---------------- profileInfo ----------------");
                                if (typeof (profileInfo[0].guestProfile.lastName) != 'undefined') {
                                    var lastName = profileInfo[0].guestProfile.lastName;
                                    browserAPI.log("find StartDate in old statements...");
                                    for (var statement in response[0].statements) {

                                        if (stop) {
                                            browserAPI.log(">>> stop");
                                            break;
                                        }

                                        browserAPI.log("statements #" + statement);
                                        if (response[0].statements.hasOwnProperty(statement) && typeof (response[0].statements[statement].startDate) != 'undefined') {
                                            browserAPI.log("Loading old statements...");
                                            $.ajax({
                                                url: 'https://www.choicehotels.com/webapi/user-account/loyalty-statement',
                                                data: {
                                                    loyaltyAccountLastName: lastName,
                                                    loyaltyAccountNumber: data.Number,
                                                    loyaltyProgramId: plugin.loyaltyProgramId,
                                                    preferredLocaleCode: 'en-us',
                                                    statementPeriodStartDate: response[0].statements[statement].startDate,
                                                    siteName: 'us'
                                                },
                                                type: "POST",
                                                async: false,
                                                success: function(statement) {
                                                    statement = $(statement);
                                                    // console.log("---------------- statement ----------------");
                                                    // console.log(statement[0]);
                                                    // console.log("---------------- statement ----------------");

                                                    // POINTS EARNED
                                                    if (typeof (statement[0].earned) != 'undefined') {
                                                        browserAPI.log("POINTS EARNED");
                                                        for (var earned in statement[0].earned) {
                                                            if (statement[0].earned.hasOwnProperty(earned)) {
                                                                var row = {};
                                                                var startDate = util.filter(statement[0].earned[earned].startDate);
                                                                // safari hack
                                                                var dateStr = startDate.replace(/-/ig, '/');
                                                                browserAPI.log("Date: " + dateStr + ' (' + startDate + ')');
                                                                var postDate = null;
                                                                browserAPI.log("date: " + dateStr );
                                                                if ((typeof(dateStr) != 'undefined') && (dateStr != '')) {
                                                                    postDate = dateStr;
                                                                    var date = new Date(postDate + ' UTC');
                                                                    var unixtime =  date / 1000;
                                                                    if (unixtime != 'NaN') {
                                                                        browserAPI.log("Date: " + date + " Unixtime: " + unixtime );
                                                                        postDate = unixtime;
                                                                    }// if (unixtime != 'NaN')
                                                                }// if ((typeof(dateStr) != 'undefined') && (dateStr != ''))
                                                                else
                                                                    postDate = null;

                                                                if (startDate > 0 && postDate <= startDate) {
                                                                    browserAPI.log(">>> stop");
                                                                    stop = true;
                                                                }
                                                                if (startDate > 0 && postDate < startDate) {
                                                                    browserAPI.log("break at date " + dateStr + " " + postDate);
                                                                    break;
                                                                }

                                                                var activity;
                                                                if ((typeof(statement[0].earned[earned].hotelId) != 'undefined')) {
                                                                    var hotelId = statement[0].earned[earned].hotelId;
                                                                    browserAPI.log("hotelId: " + hotelId );
                                                                    // console.log(statement[0].hotels[hotelId]);
                                                                    activity = hotelId;
                                                                    if (typeof(statement[0].hotels) != 'undefined'
                                                                        && typeof(statement[0].hotels[hotelId]) != 'undefined') {
                                                                        if (typeof(statement[0].hotels[hotelId].name) != 'undefined')
                                                                            activity = activity + '; ' + statement[0].hotels[hotelId].name;
                                                                        if (typeof(statement[0].hotels[hotelId].address.city) != 'undefined')
                                                                            activity = activity + '; ' + statement[0].hotels[hotelId].address.city;
                                                                        if (typeof(statement[0].hotels[hotelId].address.subdivision) != 'undefined')
                                                                            activity = activity + ', ' + statement[0].hotels[hotelId].address.subdivision;
                                                                    }// if (typeof(statement[0].hotels[hotelId]) != 'undefined')
                                                                }// if ((typeof(statement[0].earned[earned].hotelId) != 'undefined'))
                                                                else
                                                                    activity = statement[0].earned[earned].description;

                                                                row = {
                                                                    'Activity Dates': postDate,
                                                                    'Description': activity,
                                                                    'Points': statement[0].earned[earned].points
                                                                };

                                                                data.HistoryRows.push(row);
                                                                browserAPI.log('>>> ' + JSON.stringify(row));
                                                                // console.log(row);//todo
                                                            }// if (statement[0].earned.hasOwnProperty(earned))
                                                        }// for (var earned in statement[0].earned)
                                                    }// if (typeof (statement[0].earned) != 'undefined')

                                                    // POINTS REDEEMED
                                                    if (typeof (statement[0].redeemed) != 'undefined') {
                                                        browserAPI.log("POINTS REDEEMED");
                                                        for (var redeemed in statement[0].redeemed) {
                                                            if (statement[0].redeemed.hasOwnProperty(redeemed)) {
                                                                var row = {};
                                                                var startDate = util.filter(statement[0].redeemed[redeemed].startDate);
                                                                // safari hack
                                                                var dateStr = startDate.replace(/-/ig, '/');
                                                                browserAPI.log("Date: " + dateStr + ' (' + startDate + ')');
                                                                var postDate = null;
                                                                browserAPI.log("date: " + dateStr );
                                                                if ((typeof(dateStr) != 'undefined') && (dateStr != '')) {
                                                                    postDate = dateStr;
                                                                    var date = new Date(postDate + ' UTC');
                                                                    var unixtime =  date / 1000;
                                                                    if (unixtime != 'NaN') {
                                                                        browserAPI.log("Date: " + date + " Unixtime: " + unixtime );
                                                                        postDate = unixtime;
                                                                    }// if (unixtime != 'NaN')
                                                                }// if ((typeof(dateStr) != 'undefined') && (dateStr != ''))
                                                                else
                                                                    postDate = null;

                                                                if (startDate > 0 && postDate <= startDate) {
                                                                    browserAPI.log(">>> stop");
                                                                    stop = true;
                                                                }
                                                                if (startDate > 0 && postDate < startDate) {
                                                                    browserAPI.log("break at date " + dateStr + " " + postDate);
                                                                    break;
                                                                }

                                                                var activity;
                                                                if ((typeof(statement[0].redeemed[redeemed].hotelId) != 'undefined')) {
                                                                    var hotelId = statement[0].redeemed[redeemed].hotelId;
                                                                    browserAPI.log("hotelId: " + hotelId );
                                                                    // console.log(statement[0].hotels[hotelId]);
                                                                    activity = hotelId;
                                                                    if (typeof(statement[0].hotels) != 'undefined'
                                                                        && typeof(statement[0].hotels[hotelId]) != 'undefined') {
                                                                        if (typeof(statement[0].hotels[hotelId].name) != 'undefined')
                                                                            activity = activity + '; ' + statement[0].hotels[hotelId].name;
                                                                        if (typeof(statement[0].hotels[hotelId].address.city) != 'undefined')
                                                                            activity = activity + '; ' + statement[0].hotels[hotelId].address.city;
                                                                        if (typeof(statement[0].hotels[hotelId].address.subdivision) != 'undefined')
                                                                            activity = activity + ', ' + statement[0].hotels[hotelId].address.subdivision;
                                                                    }// if (typeof(statement[0].hotels[hotelId]) != 'undefined')

                                                                    if ((typeof(statement[0].redeemed[redeemed].cancellation) != 'undefined')
                                                                        && statement[0].redeemed[redeemed].cancellation)
                                                                        activity = activity + ' (cancelled)';
                                                                }// if ((typeof(statement[0].redeemed[redeemed].hotelId) != 'undefined'))
                                                                else
                                                                    activity = statement[0].redeemed[redeemed].description;

                                                                row = {
                                                                    'Activity Dates': postDate,
                                                                    'Description': activity,
                                                                    'Points': statement[0].redeemed[redeemed].points
                                                                };

                                                                data.HistoryRows.push(row);
                                                                browserAPI.log('>>> ' + JSON.stringify(row));
                                                                // console.log(row);//todo
                                                            }// if (statement[0].redeemed.hasOwnProperty(redeemed))
                                                        }// for (var redeemed in statement[0].redeemed)
                                                    }// if (typeof (statement[0].redeemed) != 'undefined')

                                                    // POINTS ADJUSTED
                                                    if (typeof (statement[0].adjusted) != 'undefined') {
                                                        browserAPI.log("POINTS ADJUSTED");
                                                        for (var adjusted in statement[0].adjusted) {
                                                            if (statement[0].adjusted.hasOwnProperty(adjusted)) {
                                                                var row = {};
                                                                var startDate = util.filter(statement[0].adjusted[adjusted].startDate);
                                                                // safari hack
                                                                var dateStr = startDate.replace(/-/ig, '/');
                                                                browserAPI.log("Date: " + dateStr + ' (' + startDate + ')');

                                                                var postDate = null;
                                                                browserAPI.log("date: " + dateStr );
                                                                if ((typeof(dateStr) != 'undefined') && (dateStr != '')) {
                                                                    postDate = dateStr;
                                                                    var date = new Date(postDate + ' UTC');
                                                                    var unixtime =  date / 1000;
                                                                    if (unixtime != 'NaN') {
                                                                        browserAPI.log("Date: " + date + " Unixtime: " + unixtime );
                                                                        postDate = unixtime;
                                                                    }// if (unixtime != 'NaN')
                                                                }// if ((typeof(dateStr) != 'undefined') && (dateStr != ''))
                                                                else
                                                                    postDate = null;

                                                                if (startDate > 0 && postDate <= startDate) {
                                                                    browserAPI.log(">>> stop");
                                                                    stop = true;
                                                                }
                                                                if (startDate > 0 && postDate < startDate) {
                                                                    browserAPI.log("break at date " + dateStr + " " + postDate);
                                                                    break;
                                                                }

                                                                row = {
                                                                    'Activity Dates': postDate,
                                                                    'Description': statement[0].adjusted[adjusted].description,
                                                                    'Points': statement[0].adjusted[adjusted].points
                                                                };

                                                                data.HistoryRows.push(row);
                                                                browserAPI.log('>>> ' + JSON.stringify(row));
                                                                // console.log(row);//todo
                                                            }// if (statement[0].adjusted.hasOwnProperty(adjusted))
                                                        }// for (var adjusted in statement[0].adjusted)
                                                    }// if (typeof (statement[0].adjusted) != 'undefined')

                                                }// success: function (statement)
                                            });
                                        }// if (response[0].statements.hasOwnProperty(statement))
                                    }// for (var statement in response[0].statements)
                                }// if (typeof (profileInfo[0].guestProfile.lastName) != 'undefined')
                            },// success: function (profileInfo)
                            error: function (data, textStatus, error) {
                                browserAPI.log(`fail: profile info data status = ${data.status}`);
                                browserAPI.log('Error: ' + JSON.stringify(data));
                            }
                        });
                    }// if (typeof (data.AccountExpirationDate) == 'undefined')

                },// success: function (data)
                error: function (data, textStatus, error) {
                    browserAPI.log(`fail: loyalty-statement-summaries data status = ${data.status}`);
                    browserAPI.log('Error: ' + JSON.stringify(data));
                }
            });
        }// if (typeof (data.Number) != 'undefined')

        //// You are not a member of this loyalty program.
        //if (typeof(params.data.properties.Balance) == 'undefined' && ($('a div.btn-wrp-2').text() == 'Join now'))
        //    provider.setError('You are not a member of this loyalty program.');

        // save properties
        params.account.properties = data;
        // console.log(params.account.properties);//todo
        provider.saveProperties(params.account.properties);//todo

        if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {
            provider.setNextStep('parseItineraries', function(){
                document.location.href = plugin.reservationsLimk;
            });
            return;
        }// if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries)

        provider.complete();
    },

    parseItineraries: function (params) {
        browserAPI.log("parseItineraries");
        provider.updateAccountMessage();

        params.data.Reservations = [];
        params.data.linkIndex = 0;

        var counter = 0;
        var parseItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var reservations = $('.recent-reservations:contains("Upcoming Stays")').find('button:contains("Manage"):visible');
            if (reservations.length > 0) {
                clearInterval(parseItineraries);
                // canceled reservations
                var reservationsCancelled = $('button:contains("Manage"):visible').parent('div').prev('div:contains("confirmation #")').find('div.status-block:contains("Cancelled")').parent('div').parent('div').find('div:contains("confirmation #")');
                browserAPI.log(">>> Total " + reservationsCancelled.length + " cancelled reservations were found");
                reservationsCancelled.each(function (index, el) {
                    el = $(el);
                    var result = {
                        ConfirmationNumber : el.text().replace('confirmation #',''),
                        Status : 'Cancelled',
                        Cancelled : true
                    };
                    params.data.Reservations.push(result);
                });
                // console.log(params.data.Reservations);// todo

                plugin.loadReservation(params, 0);
            }// if (reservations.length > 0)
            if (counter > 15) {
                browserAPI.log("error -> itineraries aren't found //parseItineraries");
                clearInterval(parseItineraries);
                plugin.loadReservation(params, 0);
            }// if (counter > 15)
            counter++;
        }, 500);
    },

    loadReservation: function (params, maxCount) {
        browserAPI.log("loadReservation");
        if (typeof(maxCount) == 'undefined')
            maxCount = 10;
        var counter = 0;
        var loadRes = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            // confirmed reservations
            var btns = $('button:contains("Manage"):visible').parent('div').parent('div:contains("Confirmed")').find('button:contains("Manage"):visible');
            browserAPI.log(">>> Total " + btns.length + " reservations were found");
            browserAPI.log(">>> before were parsed " + params.data.linkIndex + " confirmed reservations");
            if (btns.length > 0) {
                clearInterval(loadRes);
                if (params.data.linkIndex < btns.length) {
                    browserAPI.log('clicking ' + params.data.linkIndex + ' button');
                    btn = btns.eq(params.data.linkIndex).get(0);
                    params.data.linkIndex++;
                    btn.click();
                    var counterLink = 0;
                    var intervalId = setInterval(function () {
                        var hDetails = $('.hotel-name a');
                        if (hDetails && hDetails.text().length > 0) {
                            clearInterval(intervalId);
                            plugin.parseItinerary(params);
                        }
                        if (counterLink > 10) {
                            browserAPI.log("error -> reservation aren't found //loadReservation");
                            clearInterval(intervalId);
                            plugin.parsingComplete(params);
                        }
                        counterLink++;
                    }, 500);
                }// if (params.data.linkIndex < btns.length)
                else
                    plugin.parsingComplete(params);
            }// if (btns.length > 0)
            if (counter > maxCount) {
                browserAPI.log("error -> reservations aren't found //loadReservation");
                clearInterval(loadRes);
                if (params.data.Reservations.length === 0 && $('h2:contains("No Upcoming Stays"):visible').length === 1) {
                    browserAPI.log("NoItineraries: true");
                    params.data.Reservations = [{NoItineraries: true}];
                }
                plugin.parsingComplete(params);
            }
            counter++;
        }, 500);
    },

    parsingComplete: function(params) {
        browserAPI.log("parsingComplete");
        params.account.properties.Reservations = params.data.Reservations;
        //console.log(params.data.Reservations);
        //console.log(params.account.properties);
        provider.saveProperties(params.account.properties);
        provider.complete();
    },

    parseItinerary: function(params){
        browserAPI.log("parseItinerary");
        provider.updateAccountMessage();
        var result = {};
        // Confirmation Number: 71813303
        result.ConfirmationNumber =  util.findRegExp($('h3.confirmation-number-heading').text(), /:\s*(\w+)/);
        browserAPI.log("ConfirmationNumber: "+ result.ConfirmationNumber);
        // HotelName
        result.HotelName = $('.hotel-name a').text();
        browserAPI.log("HotelName: "+ result.HotelName);
        // Address
        result.Address = $('address').text();
        browserAPI.log("Address: "+ result.Address);
        // DetailedAddress
        result.DetailedAddress = [{
            "AddressLine": $('address span:first').text(),
            "CityName": $('address div span:first').text(),
            "PostalCode": $('address div span:eq(2)').text().replace(', ',''),
            "StateProv": $('address div span:eq(1)').text().replace(', ',''),
            "Country": $('address div span:eq(3)').text().replace(', ','')
        }];
        console.log(result.DetailedAddress);
        // Phone
        result.Phone = $('address').parent('a').next('p').text();
        browserAPI.log("Phone: "+ result.Phone);
        // AccountNumbers
        result.AccountNumbers = $('p:contains("Membership Number:") span:first:visible').text();
        browserAPI.log("AccountNumbers: " + result.AccountNumbers);
        // October 11 - October 14
        let date = $('h2.your-stay-header span')
        if (date.length) {
            date = date.text();
            let year = new Date().getFullYear();
            let checkIn = util.findRegExp(date, /(\w+ \d+) - \w+ \d+/);
            let checkOut = util.findRegExp(date, /\w+ \d+ - (\w+ \d+)/);
            if (checkIn && checkOut) {
                browserAPI.log("CheckIn Date: " + checkIn + ' ' + year + ', UTC');
                checkIn = new Date(checkIn + ' ' + year + ', UTC');
                if (checkIn)
                    result.CheckInDate = checkIn / 1000;

                browserAPI.log("CheckOut Date: " + checkOut + ' ' + year + ', UTC');
                checkOut = new Date(checkOut + ' ' + year + ', UTC');
                if (checkOut)
                    result.CheckOutDate = checkOut / 1000;
            }

        } else {
            // CheckInDate
            var checkInDate = util.findRegExp( $('span:contains("Check-in:") + div > p:first:visible').text(), /\,\s*([^<]+)/i);
            var checkInTime = $('span:contains("Check-in:") + div > p:eq(1):visible').text().replace('Check-in time: ','').replace(/\(|\)/ig, '');
            if (checkInTime === '24-hour') {
                checkInTime = '';
            }
            checkInDate += ' ' + checkInTime;
            checkInDate = plugin.removeInvisibleSymbols( checkInDate );
            browserAPI.log("CheckIn Date: " + checkInDate);
            checkInDate = new Date(checkInDate  + ' UTC');
            if (checkInDate)
                result.CheckInDate = checkInDate / 1000;
            browserAPI.log("CheckInDate: "+ result.CheckInDate);
            // CheckOutDate
            var checkOutDate = util.findRegExp( $('span:contains("Check-out:") + div > p:first:visible').text(), /\,\s*([^<]+)/i);
            var checkOutTime = $('span:contains("Check-out:") + div > p:eq(1):visible').text().replace('Check-out time: ','').replace(/\(|\)/ig, '');
            if (checkOutTime === '24-hour') {
                checkOutTime = '';
            }
            checkOutDate += ' ' + checkOutTime;
            browserAPI.log("CheckOutDate: " + checkOutDate);
            checkOutDate = plugin.removeInvisibleSymbols( checkOutDate );
            browserAPI.log("CheckOutDate: " + checkOutDate);
            checkOutDate = new Date(checkOutDate  + ' UTC');
            if (checkOutDate)
                result.CheckOutDate = checkOutDate / 1000;
            browserAPI.log("CheckOutDate: "+ result.CheckOutDate);
        }



        // Rooms
        var roomDescription = $('div.room-card');
        result.Rooms = roomDescription.length;
        browserAPI.log("Rooms: "+ result.Rooms);
        // Guests
        result.Guests = plugin.sumArray( roomDescription.find('span.adults-count') );// .text().replace(/[adults]/ig,'').trim().split(' ')
        browserAPI.log("Guests: "+ result.Guests);
        // RoomType
        result.RoomType = plugin.unionArray( roomDescription.find('div.room-description'), ' | ');
        browserAPI.log("RoomType: "+ result.RoomType);
        // RoomTypeDescription
        result.RoomTypeDescription = plugin.unionArray( roomDescription.find('div.features-description'), ' | ');
        browserAPI.log("RoomTypeDescription: "+ result.RoomTypeDescription);
        // Rate
        result.Rate = util.trim( plugin.unionArray(roomDescription.find('div.price-labels'), ' | ') );
        browserAPI.log('Rate: ' + result.Rate);
        var currency = $('div.grand-total-price span span:eq(1)').text().trim().toUpperCase();
        browserAPI.log('Currency: ' + result.Currency);
        if (currency === 'PTS' || currency === 'PTSPOINTS') {
            // SpentAwards
            var spent = $('div.grand-total-price').text().trim();
            if (spent.match(/^([\d\s]+)\bPTS$/i)) {
                result.SpentAwards = spent;
                browserAPI.log('SpentAwards: ' + result.SpentAwards);
            }
        } else {
            // Cost
            result.Cost = util.findRegExp( $('div[data-seleniumid="charges-subtotals"]').find('.total-price').text(), /(\d+.\d+)/i );
            browserAPI.log('Cost: ' + result.Cost);
            // Taxes
            result.Taxes = util.findRegExp( $('div[data-seleniumid="charges-estimated-tax"]').find('.total-price').text(), /(\d+.\d+)/i );
            browserAPI.log('Taxes: ' + result.Taxes);
            // Total
            result.Total = util.findRegExp( $('div.grand-total-price span:first').text(), /(\d+.\d+)/i );
            browserAPI.log('Total: ' + result.Total);
            // Currency
            result.Currency = currency;
        }
        // CancellationPolicy
        result.CancellationPolicy = util.findRegExp( $('span:contains("Cancellation Policy:")').parent('p').text(), /:\s*([^<]+)/ );
        browserAPI.log('CancellationPolicy: ' + result.CancellationPolicy);

        params.data.Reservations.push(result);

        //console.log(result);
        // console.log(params.data.Reservations);

        // save data
        provider.saveTemp(params.data);

        provider.setNextStep('loadReservation', function () {
            document.location.href = plugin.reservationsLimk;
        });
		browserAPI.log('stop');
    },

    removeInvisibleSymbols: function (string) {
        string = string.replace(/[^a-z0-9\:\, ]/ig, ' ');
        string = string.replace(/\s\s/ig, ' ');

        return string;
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
    },

    sumArray: function (elem) {
        // reduce not working in IE 8, so iterating through items
        var result = 0;
        for (var i = 0; i < elem.length; i++) {
            var text = util.trim(elem.eq(i).text());
            result += parseInt(text);
        }
        return result;
    }

};