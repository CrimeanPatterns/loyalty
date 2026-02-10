var plugin = {

    hosts: {
        'myrewardzone.bestbuy.com': true,
        'my.bestbuy.com': true,
        'www.bestbuy.com': true,
        'www-ssl.bestbuy.com': true,
    },

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.bestbuy.com/profile/c/rwz/overview';
    },

    start: function (params) {
        browserAPI.log("start");
        setTimeout(function () {
		var region = $("select[name='select_locale']");
        if (region.length > 0) {
				region.val(1);
            return provider.setNextStep('loadLoginForm', function () {
                provider.eval("$('.go_button').click();");
            });
        }
            if (document.location.href.indexOf('redirectAfterSessCatType') > 0) {
                return provider.setNextStep('start', function () {
                    document.location.href = 'https://www-ssl.bestbuy.com/site/olspage.jsp?id=pcat17000&type=page';
                });
            }
            let counter = 0;
            let start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                let isLoggedIn = plugin.isLoggedIn(params.account);
                if (isLoggedIn !== null) {
                    clearInterval(start);
                    if (isLoggedIn) {
                        if(plugin.isSameAccount(params.account))
                            plugin.loginComplete(params);
                        else
                            plugin.logout(params);
                    }
                    else
                        plugin.login(params);
                }
                if (counter > 10) {
                    clearInterval(start);
                    provider.setError(util.errorMessages.unknownLoginState);
                }
                counter++;
            }, 500);
        }, 1000);
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        browserAPI.log('Current URL: ' + document.location.href);
		provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
	},

    isLoggedIn: function (account) {
        browserAPI.log("isLoggedIn");
        if ($('form.cia-form').length > 0) {
            let notYou = $('a.js-not-you');
            if (notYou.length > 0)
                notYou.get(0).click();
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('.lf-rewards-overview-page__loyalty-member-id').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.findRegExp($('.lf-rewards-overview-page__loyalty-member-id').text(), /ID\s*:\s*(\d+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('form[name = "logoutForm"]').submit();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let form = $('form.cia-form');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        // refs #11355
        browserAPI.log("submitting saved credentials");
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
            "triggerInput('fld-e', '" + params.account.login + "');" +
            "triggerInput('fld-p1', '" + params.account.password + "');"
        );
        provider.setNextStep('checkLoginErrors', function () {
            form.find('button[type = "submit"]').click();
            setTimeout(function() {
                plugin.checkLoginErrors(params);
            }, 7000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("loginComplete");
        let errors = $('div.c-alert-content:visible, span.tb-validation:visible:eq(0)');
        if (errors.length > 0 && util.trim(errors.text()) !== 'Ready to Pick Up') {
            provider.setError(errors.text());
            return;
        }
        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
		provider.complete();
	},

};
