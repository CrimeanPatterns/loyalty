var plugin = {
    hideOnStart: true,
    clearCache: true,
    // keepTabOpen: true,//todo
    hosts: {'delta.com': true, 'www.delta.com': true},
    itineraryLink: 'https://www.delta.com/mytrips/findPnrList.action',

    getStartingUrl: function (params) {
        return plugin.getProfileUrl(params);
    },

    getLoginPageUrl: function (params) {
        return 'https://www.delta.com/login/loginPage?stop_mobi=yes&type=mobileweb';
    },

    getProfileUrl: function (params) {
        return 'https://www.delta.com/myskymiles/overview?stop_mobi=yes&type=mobileweb';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log("[Current URL] -> " + document.location.href);
        document.cookie = "stop_mobi=yes; path=/; domain=www.delta.com";
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
                else {
                    var language = $('span.lang-selection:contains("English"):visible');
                    if (language.length > 0) {
                        language.click();
                        return setTimeout(function () {
                            provider.setNextStep('login', function () {
                                document.location.href = plugin.getLoginPageUrl(params);
                            });
                        }, 3000)
                    }

                    if ($('#login-modal-wrapper form:visible').length > 0) {
                        browserAPI.log("force call login");
                        plugin.login(params);
                        return;
                    }

                    provider.setNextStep('login', function () {
                        document.location.href = plugin.getLoginPageUrl(params);
                    });
                }
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 15) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 15)
            counter++;
        }, 500);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if ($('.loginDiv form:visible, #login-modal-wrapper form:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('.logged-in-container:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.filter($('span:contains("SKYMILES #") + span').text());
        browserAPI.log("number: " + number);
        return (number === account.login);
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a.logout-btn').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        browserAPI.log("[Current URL] -> " + document.location.href);
        const form = $('.loginDiv form:visible, #login-modal-wrapper form:visible');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        var loginInput = form.find('input[id = "userId"]');
        var passwordInput = form.find('input[id = "password"]');
        loginInput.val(params.account.login);
        passwordInput.val(params.account.password);
        // refs #11326
        if (loginInput.length > 0)
            util.sendEvent(loginInput.get(0), 'input');
        if (passwordInput.length > 0)
            util.sendEvent(passwordInput.get(0), 'input');

        function triggerInput(selector, enteredValue) {
            const input = document.querySelector(selector);
            var createEvent = function(name) {
                var event = document.createEvent('Event');
                event.initEvent(name, true, true);
                return event;
            }
            input.dispatchEvent(createEvent('focus'));
            input.value = enteredValue;
            input.dispatchEvent(createEvent('change'));
            input.dispatchEvent(createEvent('input'));
            input.dispatchEvent(createEvent('blur'));
        }

        triggerInput('input[aria-label="SkyMiles Number Or Username*"]', params.account.login + '');
        triggerInput('input[aria-label="Password*"]', params.account.password + '');

        provider.setNextStep('checkLoginErrors', function () {
            if (params.account.password === '') {
                provider.complete();
                return;
            }
            return setTimeout(function () {
                $('button.loginButton, .login-button button')[0].click();
            }, 1000)
        });
        plugin.waitForLoginError();

        setTimeout(function () {
            // if we're still on this page - submit last name
            const lastName = $('input[id = "lastName"]');
            if (lastName.length > 0) {
                lastName.val(params.account.login2);
                util.sendEvent(lastName.get(0), 'input');
                provider.setNextStep('checkLoginErrors', function () {
                    $('button.loginButton, .login-button button')[0].click();
                });
            } else if ($('div.companyNameSection:visible, .idp-alert:visible').length) {
                plugin.checkLoginErrors(params);
            }
        }, 2000);
    },

    waitForLoginError: function () {
        browserAPI.log("waitForLoginError");
        setTimeout(function () {
            let errors = $('div:has(span.overlayErrorIcon) > div.overlayText:visible, .idp-alert:visible');
            if (errors.length === 0) {
                errors = $('div.errorMessageDiv:visible:eq(0)');
            }

            if (errors.length > 0 && util.filter(errors.text()) !== '') {
                provider.setError(util.filter(errors.text()));
            }
        }, 1000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div:has(span.overlayErrorIcon) > div.overlayText:visible, .idp-alert:visible');

        if (errors.length === 0) {
            errors = $('div.errorMessageDiv:visible:eq(0)');
        }

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.delta.com/mytrips/findPnrList';
            });
        }

        if ($('div.companyNameSection:visible').length) {
            provider.complete();
            return;
        }

        provider.setNextStep('itLoginComplete', function () {
            document.location.href = plugin.getProfileUrl(params);
        });
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        var confNo = params.account.properties.confirmationNumber;
        var forms = $('form[class*="trips_details_form"]');
        for (var i = 0; i < forms.length; i++) {
            var form = $(forms[i]);
            if (form.find('input[name="confirmationNo"][value="' + confNo + '"]').length > 0) {
                provider.setNextStep('itLoginComplete', function () {
                    form.submit();
                });
            }
        }
        provider.setError(util.errorMessages.itineraryNotFound);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
