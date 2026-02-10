var plugin = {

    mobileUserAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.84 Safari/537.36',
    hosts: {
        'dominos.com': true,
        'www.dominos.com': true,
        'dominos.ca': true,
        'www.dominos.ca': true,
    },

    getStartingUrl: function (params) {

        switch (params.account.login2) {
            case 'Canada':
                return 'https://' + plugin.getHost(params) + '/en/';
            default:
                return 'https://' + plugin.getHost(params) + '/en/pages/customer/#!/customer/settings/';
        }
    },

    getHost: function (params) {
        // host
        var host = 'https://www.dominos.com/';
        switch (params.account.login2) {
            case 'Canada':
                host = 'www.dominos.ca';
                break;
            default:
                host = 'www.dominos.com';
                break;
        }
        return host;
    },

    start: function (params) {
        browserAPI.log("start");
        switch (params.account.login2) {
            case 'Canada':
                if (provider.isMobile) {
                    provider.setNextStep('start2', function () {
                        document.location.href = 'https://' + plugin.getHost(params) + '/en/';
                    });
                } else {
                    plugin.start2(params);
                }
                break;
            default:
                provider.setNextStep('start2', function () {
                    document.location.href = 'https://' + plugin.getHost(params) + '/en/pages/customer/#!/customer/settings/';
                });
                break;
        }

        if (provider.isMobile) {
            provider.setNextStep('start2', function () {
                switch (params.account.login2) {
                    case 'Canada':
                        document.location.href =  'https://' + plugin.getHost(params) + '/en/';
                        break;
                    default:
                        document.location.href =  'https://' + plugin.getHost(params) + '/en/pages/customer/#!/customer/settings/';
                        break;
                }
            });
        }// if (!provider.isMobile)
        else
            plugin.start2(params);
    },

    start2: function (params) {
        browserAPI.log("start2");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        provider.complete();
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
        browserAPI.log('isLoggedIn');
        if ($('a[href*="/en/pages/customer/#!/customer/login/"]:visible').length) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        if ($('a[href*="/en/pages/customer/#!/customer/logout/"]:visible').length || $('a[href*="/en/pages/customer/#!/customer/profile/"]:visible').length) {
            browserAPI.log('isLoggedIn: true');
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = $('.js-userName:first').text();
        browserAPI.log("name: " + name);
        return typeof(account.properties) !== 'undefined'
            && typeof(account.properties.Name) !== 'undefined'
            && account.properties.Name != ''
            && name
            && account.properties.Name.indexOf(name) !== -1;
    },

    logout: function (params) {
        browserAPI.log('logout');
        provider.setNextStep('start', function () {
            document.location.href = 'https://' + plugin.getHost(params) + '/en/pages/customer/#/customer/logout/';
            setTimeout(function () {
                plugin.start(params);
            }, 4000);
        });
    },

    login: function (params) {
        browserAPI.log('login');
        var signLink = $('.js-login.js-homeResponsiveMenuBtn, a[href*="/en/pages/customer/#!/customer/login/"]:visible');
        if (signLink.length)
            signLink.get(0).click();

        setTimeout(function () {
            var form = $('#pizzaProfileLoginOverlay');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "Email"]').val(params.account.login);
                form.find('input[name = "Password"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button:contains("Sign In & Keep Me Signed In")').get(0).click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 7000);
                });
            }// if (form.length > 0)
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 7000);
    },

    checkLoginErrors: function () {
        browserAPI.log('checkLoginErrors');
        var errors = $('p:contains("not locate a Pizza Profile with that e-mail and password")');
        if (errors.length && util.filter(errors.text()) !== '') {
            provider.setError(errors.text());
        } else {
            provider.complete();
        }
    }

};