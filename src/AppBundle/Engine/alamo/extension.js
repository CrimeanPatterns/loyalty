var plugin = {

    hosts: {'www.alamo.com': true},

    cashbackLink : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.alamo.com/en/alamo-insiders/profile.html#/my_profile';
    },

    start: function (params) {
        browserAPI.log("start");
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
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form.signin__form:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        let signIn = $('button[aria-label="Sign In"]:visible');
        if (signIn.length > 0) {
            browserAPI.log("not LoggedIn");
            signIn.get(0).click();
            return false;
        }
        if ($('button[aria-label="Sign Out"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let name = util.findRegExp($('p:contains("Name")').text(), /Name:\s*(.+)/);
        browserAPI.log("name: " + name);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && name
            && (name.toLowerCase() === account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('button[aria-label="Sign Out"]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function(){
                document.location.href = 'https://www.alamo.com/en/reserve/view-modify-cancel.html';
            });
            return;
        }
        let counter = 0;
        let login = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let form = $('form.signin__form:visible');
            if (form.length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");
                // reactjs
                provider.eval(
                    "function triggerInput(selector, enteredValue) {\n" +
                    "      let input = document.querySelector(selector);\n" +
                    "      input.dispatchEvent(new Event('focus'));\n" +
                    "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
                    "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
                    "      nativeInputValueSetter.call(input, enteredValue);\n" +
                    "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
                    "      input.dispatchEvent(inputEvent);\n" +
                    "}\n" +
                    "triggerInput('input[name = \"username\"]', '" + params.account.login + "');\n" +
                    "triggerInput('input[name = \"password\"]', '" + params.account.password + "');"
                );
                // form.find('input[name = "remember_credentials"]').prop('checked', true);// not working
                return provider.setNextStep('checkLoginErrors', function () {
                    /*
                    let captcha = form.find('div#_content_alamo_en_US_modals_sign_in_jcr_content_contentPar_signin_captcha:visible');
                    if (captcha && captcha.length > 0) {
                        provider.reCaptchaMessage();
                        $('#awFader').remove();
                        provider.setTimeout(function () {
                            waiting();
                        }, 0);
                    }// if (captcha && captcha.length > 0)
                    else {
                        browserAPI.log("captcha is not found");
                    */
                        form.find('.signin__button').click();
                        provider.setTimeout(function () {
                            waiting();
                        }, 0);
                    /*
                    }
                    */
                });
            }
            if (counter > 10) {
                clearInterval(login);
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }// if (counter > 10)
            counter++;
        }, 500);

        function waiting() {
            browserAPI.log("waiting...");
            let counter = 0;
            let waiting = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                let error = $('div.service-error > p:visible');
                if (error.length > 0 && util.filter(error.text()) !== '') {
                    clearInterval(waiting);
                    plugin.checkLoginErrors(params);
                    return;
                }// if (error.length > 0 && util.filter(error.text()) !== '')
                if (counter > 120) {
                    clearInterval(waiting);
                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                }
                counter++;
            }, 500);
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div.service-error > p:visible');
        if (errors.length > 0 && util.trim(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.alamo.com/en/alamo-insiders/profile.html#/my_trips';
                setTimeout(function () {
                    plugin.toItineraries(params);
                }, 3000)
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            let confNo = params.account.properties.confirmationNumber;
            let link = $('div:has(span:contains("' + confNo + '")) button.trip-card__cta');
            if (link.length === 0) {
                provider.setError(util.errorMessages.itineraryNotFound);
                return;
            }// if (link.length === 0)
            provider.setNextStep('itLoginComplete', function () {
                link.get(0).click();
            });
        }, 2000);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        let properties = params.account.properties.confFields;
        let form = $('form.rental-lookup-form:visible');
        if (form.length === 0) {
            provider.setError(util.errorMessages.itineraryFormNotFound);
            return;
        }
        browserAPI.log("sending confNo data...");
        // reactjs
        provider.eval(
            "function triggerInput(selector, enteredValue) {\n" +
            "      let input = document.querySelector(selector);\n" +
            "      input.dispatchEvent(new Event('focus'));\n" +
            "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
            "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
            "      nativeInputValueSetter.call(input, enteredValue);\n" +
            "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
            "      input.dispatchEvent(inputEvent);\n" +
            "}\n" +
            "triggerInput('input[name = \"reservationData.firstName\"]', '" + properties.firstName + "');\n" +
            "triggerInput('input[name = \"reservationData.lastName\"]', '" + properties.lastName + "');\n" +
            "triggerInput('input[name = \"reservationData.confirmationNumber\"]', '" + properties.ConfNo + "');"
        );
        provider.setNextStep('itLoginComplete', function() {
            form.find('button:contains("Search"):visible').click();
            setTimeout(function () {
                let error = $('div.service-error > p:visible');
                if (error.length > 0 && util.filter(error.text()) !== '') {
                    provider.setError(util.filter(error.text()));
                }// if (error.length > 0 && util.filter(error.text()) !== '')
            }, 3000)
        });
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
