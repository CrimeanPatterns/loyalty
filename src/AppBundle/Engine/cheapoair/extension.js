var plugin = {
    keepTabOpen: false,
    mobileUserAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.84 Safari/537.36',
    hosts: {'www.cheapoair.com': true},

    getStartingUrl: function (params) {
        return 'https://www.cheapoair.com/profiles/#/my-rewards/redeem';
    },

    start: function (params) {
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

        let login = document.querySelector('.login__form input[name=email]');
        const pwd = document.querySelector('.login__form input[name=password]');
        if (login != null && pwd == null) {
            login.value = params.account.login;
            login.dispatchEvent(new Event('input', { bubbles: true }));
            login.dispatchEvent(new Event('change', { bubbles: true }));
            document.querySelector('.login__form .btn-primary').click();
        }

        if (pwd != null) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        if ($('.main__user span.user-name').length) {
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let name = $('.main__user span.user-name').text();
        browserAPI.log("name: " + name);
        return typeof(account.properties) == 'object'
            && typeof(account.properties.Name) == 'string'
            && account.properties.Name.length
            && name.length
            && util.beautifulName(account.properties.Name).includes(util.beautifulName(name));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            let logout = $('a.dropdown__link:contains("Sign Out")');
            if (logout.length == 0) return;
            logout.get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");

        const pwd = Array.from(document.querySelectorAll('.login__form input[name=password]')).pop();
        pwd.value = params.account.password;
        pwd.dispatchEvent(new Event('input', { bubbles: true }));
        pwd.dispatchEvent(new Event('change', { bubbles: true }));

        provider.setNextStep('checkLoginErrors', () => document.querySelector('.login__form .btn-primary').click());
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('.sign_error_msg h3:visible');
        if (errors.length === 0) errors = $('.val_error:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '')
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.cheapoair.com/profiles/#/my-trips/upcoming';
            });
            return;
        }
        if (!provider.isMobile) {
            provider.setNextStep('itLoginComplete', function() {
                document.location.href = plugin.getStartingUrl(params);
            });
        } else
            plugin.itLoginComplete(params);
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        util.waitFor({
            selector: '.upcomingTripsBox:visible',
            success: function() {
                var confNo = params.account.properties.confirmationNumber;
                var link = $('.upcomingTripsBox:contains("' + confNo + '") h4.openConfirm:contains("View Details")');
                if (link.length > 0) {
                    provider.setNextStep('itLoginComplete', function () {
                        //link.get(0).click();
                        document.location.href = 'https://www.cheapoair.com/confirmation?guid=' + link.attr('data-value');
                    });
                }// if (link.length > 0)
                else
                    provider.setError(util.errorMessages.itineraryNotFound);
            },
            fail: function() {
                provider.setError(util.errorMessages.itineraryNotFound);
            },
            timeout: 10
        });
        plugin.itLoginComplete(params);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};