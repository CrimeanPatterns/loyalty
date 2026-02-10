var plugin = {
    hosts: {
        'www.bambooairways.com': true
    },

    cashbackLink: '',

    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.bambooairways.com/vn/en/bbc/flight-histories';
    },

    start: function (params) {
        browserAPI.log('start');
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log('waiting... ' + counter);
            let isLoggedIn = plugin.isLoggedIn(params);

            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                } else
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

    isLoggedIn: function (params) {
        browserAPI.log('isLoggedIn');

        if ($('form[id = "loginForm"]:visible').length) {
            browserAPI.log('not LoggedIn');
            return false;
        }

        if ($('#logout-button').length) {
            browserAPI.log('LoggedIn');
            return true;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        let number = util.findRegExp($('span:contains("Membership Number") + span').text(), /([\d*]+)/i);
        browserAPI.log('number: ' + number);

        return ((typeof (account.properties) != 'undefined')
                && (typeof (account.properties.LoyaltyNumber) != 'undefined')
                && (account.properties.LoyaltyNumber !== '')
                && number
                && (number === account.properties.LoyaltyNumber));
    },

    logout: function (params) {
        browserAPI.log('logout');
        provider.setNextStep('start', function () {
            provider.eval('' +
                  '$.ajax({\n' +
                  '                url: "https://www.bambooairways.com/en/bbc/flight-histories?p_p_id=com_bav_header_languages_BavHeaderLanguagesPortlet&p_p_lifecycle=1&p_p_state=normal&p_p_mode=view&_com_bav_header_languages_BavHeaderLanguagesPortlet_javax.portlet.action=processLogout&p_auth=1RBTd24B",\n' +
                  '                type: "POST",\n' +
                  '\n' +
                  '                error: function() {\n' +
                  '                    console.log(\'error happened\')\n' +
                  '                },\n' +
                  '                success: function(result) {\n' +
                  '                    if (result == "success") {\n' +
                  '                        sessionStorage.clear();\n' +
                  '                        window.location.reload();\n' +
                  '                    }\n' +
                  '                }\n' +
                  '            });' +
                  '');
        });
    },

    login: function (params) {
        browserAPI.log('login');

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId === 0) {
            provider.setNextStep('getConfNoItinerary', function(){
                document.location.href = 'https://www.bambooairways.com/reservation/ibe/modify';
            });
            return;
        }

        const form = $('form[id = "loginForm"]:visible');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log('submitting saved credentials');

        function triggerInput(selector, enteredValue) {
            let input = document.querySelector(selector);
            input.dispatchEvent(new Event('focus'));
            input.dispatchEvent(new KeyboardEvent('keypress', {'key': 'a'}));
            let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
            nativeInputValueSetter.call(input, enteredValue);
            let inputEvent = new Event("input", {bubbles: true});
            input.dispatchEvent(inputEvent);
        }
        triggerInput('#login-username', params.account.login);
        triggerInput('#login-password', params.account.password);
        provider.eval('document.getElementById(\'buttonlogin\').click();');

        provider.setNextStep('checkLoginErrors', function () {
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 5000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        let errors = $('#loginAlertError .text-error-alert:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log('loginComplete');
        provider.complete();
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        const properties = params.account.properties.confFields;
        const form = $('form#retrieveItineraryForm');

        if (form.length === 0) {
            provider.setError(util.errorMessages.itineraryFormNotFound);
            return;
        }

        form.find('input[name = "confirmationCode"]').val(properties.ConfNo);
        form.find('input[name = "lastName"]').val(properties.LastName);
        provider.setNextStep('loginComplete', function() {
            form.find('button#btnSearch').click();
        });
    },
};