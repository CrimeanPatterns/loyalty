var plugin = {
    hosts: {'www.eddiebauer.com': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function(params){
        return 'https://www.eddiebauer.com/user/rewards';
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn(params);
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
        browserAPI.log("function isLoggedIn");
        if ($('button.signout_btn').length > 0
            || $('div.page_content').find('span:contains("Member #")').length > 0) {
            browserAPI.log('Logged in');
            return true;
        }

        var signin = $('#sign_in_msg');
        if (provider.isMobile) {
            $('div.menu_icon').click();
            signin = $('div[class ^= styles__StyledCategoriesContainer]').find('a[href="/acc-login"]:visible');
        }

        if (signin.length > 0) {
            browserAPI.log('Not logged in');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("function isSameAccount");
        var number = util.findRegExp($('div.page_content').find('span:contains("Member #")').text(), /^Member\s*#(\d+)$/i);
        browserAPI.log("number: " + number);
        return (
            (typeof(account.properties) !== 'undefined')
            && (typeof(account.properties.Number) !== 'undefined')
            && (account.properties.Number !== '')
            && number
            && (number === account.properties.Number)
        );
    },

    logout: function(params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function(){
            $("button.signout_btn").click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (document.location.href !== 'https://www.eddiebauer.com/acc-login') {
            document.location.href = 'https://www.eddiebauer.com/acc-login';
        }
        setTimeout(function () {
            if ($('form#login').length !== 1) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }
            // reactjs
            provider.eval(
                "function triggerInput(enteredName, enteredValue) {\n" +
                "  const input = document.getElementById(enteredName);\n" +
                "  const lastValue = input.value;\n" +
                "  input.value = enteredValue;\n" +
                "  const event = new Event(\"input\", { bubbles: true });\n" +
                "  const tracker = input._valueTracker;\n" +
                "  if (tracker) {\n" +
                "    tracker.setValue(lastValue);\n" +
                "  }\n" +
                "  input.dispatchEvent(event);\n" +
                "}\n" +
                "triggerInput('email-signin', '" + params.account.login + "');" +
                "triggerInput('password', '" + params.account.password + "');"
            );

            setTimeout(function () {
                $("button.continue_button").click();
                provider.setNextStep('checkLoginErrors', function () {
                    setTimeout(function () {
                        $('#keepMeLoggedIn').prop('checked', true);
                        $("button.login_button").click();
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 1000);
                    }, 500);
                });
            }, 2000);
        },2000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div[class *= StyledError]:visible > span:visible');
        if (errors.length > 0)
            provider.setError(errors.text().trim());
        else {
            plugin.loginComplete(params);
        }
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    },
};
