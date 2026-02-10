var plugin = {
    // clearCache: true,

    hosts: {
        'www.cathaypacific.com': true,
    },

    getStartingUrl: function (params) {
        return "https://www.cathaypacific.com/cx/en_HK/frequent-flyers/my-account.html";
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null && counter > 2) {
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
            if (isLoggedIn === null && counter > 20) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('span:contains("Sign out")').length > 0 && $('p.mpo_card-subheading:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if (
            $('div.signIn form:visible').length > 0
            || (provider.isMobile && $('span[title="Sign in / up"]').length)
        ) {
            browserAPI.log('not logged in');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.findRegExp($('p.mpo_card-subheading').text(), /\|\s*(\d+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.MembershipNumber) != 'undefined')
            && (account.properties.MembershipNumber !== '')
            && number
            && (number === account.properties.MembershipNumber));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('span:contains("Sign out")').click();
        });
    },

    login: function (params) {
        browserAPI.log("login");

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId === 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "https://www.cathaypacific.com/cx/en_US/manage-booking/manage-booking/manage-booking-now.html";
            });
            return;
        }// if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0)

        if (provider.isMobile) {
            $('span[title="Sign in / up"]').click();
        }

        if (/@/.test(params.account.login)) {
            browserAPI.log(">>> Switch to Sign in with email");
            $('button:contains("Sign in with email")').click();

            util.waitFor({
                selector: 'input[name = "email"]',
                success: function (elem) {
                    browserAPI.log("Sign in with email");
                },
                timeout: 7
            });
        } else if (/^\d+$/.test(params.account.login)) {
            browserAPI.log(">>> Switch to Sign in with membership number");
            $('button:contains("Sign in with membership number")').click();

            util.waitFor({
                selector: 'input[name = "membernumber"]',
                success: function (elem) {
                    browserAPI.log("Sign in with membership number");
                },
                timeout: 7
            });
        } else {
            browserAPI.log(">>> Switch to Sign in with username");
            $('button:contains("Sign in with username")').click();

            util.waitFor({
                selector: 'input[name = "username"]',
                success: function (elem) {
                    browserAPI.log("Sign in with username");
                },
                timeout: 7
            });
        }

        const form = $('div.signIn form');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

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
            "triggerInput('input[name = \"email\"], input[name = \"username\"], input[name = \"membernumber\"]', '" + params.account.login + "');\n" +
            "triggerInput('input[name = \"password\"]', '" + params.account.password + "');"
        );

        // form.find('input[name = "email"], input[name = "username"], input[name = "membernumber"]').val(params.account.login);
        // form.find('input[name = "password"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('button:contains("Sign in")').click();

            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('label.textfield__errorMessage:visible:eq(0), div.serverSideError__messages:visible');

        if (errors.length > 0) {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.cathaypacific.com/cx/en_US/manage-booking/manage-booking.html';
            });
            return;
        }

        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        const confNo = params.account.properties.confirmationNumber;
        let counter = 0;
        let toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            const element = $('span.rloc-num:contains("' + confNo + '")');
            // var element = $('input[onclick *= "gotoDetailsPage(\'' + confNo + '\'"]');
            if (element.length > 0) {
                clearInterval(toItineraries);
                provider.setNextStep('itLoginComplete', function () {
                    element.click();
                });
                return;
            }
            if (counter > 20) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log('getConfNoItinerary');
        const properties = params.account.properties.confFields;
        const rightTab = $('.right-non-member-login');

        if (rightTab.length > 0) {
            rightTab.click()
        }

        setTimeout(function () {
            const form = $('input[name="givenName"]').closest('form');
            if (form.length > 0) {
                let input = form.find('input[name="givenName"]');
                input.val(properties.givenName);
                //input.trigger('input'); // Use for Chrome/Firefox/Edge
                //input.trigger('change'); // Use for Chrome/Firefox/Edge + IE11
                //input[0].dispatchEvent(new Event("input", { bubbles: true }));
                util.sendEvent(input.get(0), 'input');

                input = form.find('input[name="familyName"]');
                input.val(properties.familyName);
                //input.trigger('input'); // Use for Chrome/Firefox/Edge
                //input.trigger('change'); // Use for Chrome/Firefox/Edge + IE11
                //input[0].dispatchEvent(new Event("input", { bubbles: true }));
                util.sendEvent(input.get(0), 'input');

                input = form.find('input[name="rloc-and-eticket"]');
                input.val(properties.ConfNo);
                //input.trigger('input'); // Use for Chrome/Firefox/Edge
                //input.trigger('change'); // Use for Chrome/Firefox/Edge + IE11
                //input[0].dispatchEvent(new Event("input", { bubbles: true }));
                util.sendEvent(input.get(0), 'input');

                // form.find('input[name = "eticketNumber"]').val(properties.eticketNumber);
                provider.setNextStep('itLoginComplete', function () {
                    form.find('button:contains("Find my booking")').prop('disabled', false);
                    form.find('button:contains("Find my booking")').get(0).click();
                });
            }// if (form.length > 0)
            else
                provider.setError(util.errorMessages.itineraryFormNotFound);
        }, 2000);
    },

    itLoginComplete: function (params) {
        browserAPI.log('itLoginComplete');
        provider.complete();
    }
};