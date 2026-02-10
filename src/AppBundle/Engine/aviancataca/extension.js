var plugin = {

    hosts: {
        'www.lifemiles.com': true,
        'serviciosenlinea.avianca.com': true,
        'cambiatuitinerario.avianca.com': true,
        'sso.lifemiles.com': true,
        'oauth.lifemiles.com': true,
    },

    getStartingUrl: function (params) {
		return 'https://www.lifemiles.com/account/overview';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (counter > 3 && isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        provider.complete();
                    else
                        plugin.logout(params);
                }
                else {
                    plugin.preLogin(params);
                }
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 20) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if (
            $('a[class *= "Menu_login"]:visible').length > 0
            || $('form[class *= "_loginForm"]').length > 0
            || $('a[id = "social-Lifemiles"]:visible').length > 0
        ) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('div[class *= "AccountActivityCard_userId"]:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        return typeof(account.properties) != 'undefined'
            && typeof(account.properties.Number) != 'undefined'
            && account.properties.Number !== ''
            && $('div:contains("' + account.properties.Number + '")').length;
    },

    logout: function (params) {
        browserAPI.log("logout");
        document.querySelector('div[class *= "menu-ui-Menu_headerSideButtonHolder"] span:not([class]), button[class *= "menu-ui-MobileMenu_button"] span[class *= "menu-ui-MobileMenu_bold"]').click();
        provider.setNextStep('start', function () {
            setTimeout(function() {
                document.querySelector('button[class *= "ProfileTooltip_logoutButton"]').click();

                if (provider.isMobile) {
                    setTimeout(function() {
                        plugin.start(params);
                    }, 3000);
                }
            }, 500);
        });
    },

    preLogin: function (params) {
        browserAPI.log("preLogin");
        const loginWithUsername = $('a[id = "social-Lifemiles"]:visible');

        if (loginWithUsername.length > 0) {
            return provider.setNextStep('login', function() {
                loginWithUsername.get(0).click();
            });
        }

        provider.setNextStep('login', function() {
            document.location.href = 'https://www.lifemiles.com/integrator/v1/authentication/oauth/authorize?client_id=lm_website&redirect_uri=https%3A%2F%2Fwww.lifemiles.com%2Foauth-signin&scope=read&response_type=token&state=%7B%27Access-Level%27%3A%20%270%27%2C%20%27Redirect-Uri%27%3A%20%27%27%7D';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (
            typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId === 0
        ) {
            plugin.getConfNoItinerary(params);
            return;
        }

        setTimeout(function() {
            const form = $('input#username').closest('.authentication-ui-Lifemiles_loginForm');

            if (form.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }

            browserAPI.log("submitting saved credentials");
            // form.find('input#username').val(params.account.login);
            // form.find('input#password').val(params.account.password);
            // reactjs
            provider.eval(
                "var FindReact = function (dom) {" +
                "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                "        return dom[key];" +
                "    }" +
                "    return null;" +
                "};" +
                "FindReact(document.querySelector('input[id = \"username\"]')).onChange({target:{value:'" + params.account.login + "'}, preventDefault:function(){}});" +
                "FindReact(document.querySelector('input[id = \"password\"]')).onChange({target:{value:'" + params.account.password + "'}, preventDefault:function(){}});"
            );

            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function() {
                    $('button#Login-confirm').get(0).click();
                    setTimeout(function() {
                        plugin.checkLoginErrors();
                    }, 10000);
                }, 500);
            });
        }, 2000);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        provider.setNextStep('itLoginComplete', function() {
            document.location.href = 'https://cambiatuitinerario.avianca.com/ATC/redirectServlet?pais=EU&lan=en&pnr=' + properties.ConfNo + '&apellido=' + properties.LastName;
        });
        /*util.waitFor({
            selector: 'div.search-by-pnr-form > form',
            success: function (form) {
                form.find('input[name = "recordLocatorOrETicket"]').val(properties.ConfNo);
                form.find('input[name = "passengerLastName"]').val(properties.LastName);
                provider.setNextStep('itLoginComplete', function() {
                    form.find('button[type = "submit"]').click();
                });
            },
            fail: function () {
                provider.setError(util.errorMessages.itineraryFormNotFound);
            },
            timeout: 15
        });*/
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        const errors = $('p[class *= "ErrorModal_description__"]:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        provider.complete();
    }
};
