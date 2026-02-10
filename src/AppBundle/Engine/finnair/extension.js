var plugin = {
    clearCache: true,
    hosts: {'www.finnair.com': true, 'auth.finnair.com': true},

    getStartingUrl: function (params) {
        return 'https://www.finnair.com/en/my-finnair-plus';
    },
    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
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
                        provider.complete();
                    else
                        plugin.logout(params);
                }
                else {
                    if ($('label:contains("Email address or Finnair Plus number"):visible').length)
                        plugin.login(params);
                    else
                        provider.setNextStep('login', function () {

                            var close = $('h2:contains("Log in to Finnair Plus"):visible').closest('.text-content').next('.mfp-close');
                            util.waitFor({
                                selector: close,
                                success: function () {
                                    close.click();
                                    setTimeout(function () {
                                        $('a.menu_item.js-open-login').get(0).click();
                                    }, 500);
                                },
                                fail: function () {
                                    setTimeout(function () {
                                        $('a.menu_item.js-open-login').get(0).click();
                                    }, 500);
                                },
                                timeout: 4
                            });
                        });
                }
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 1000);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        // header__mobile-login.header__mobile-login--logged-in
        if ($('span:contains("Membership number:"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('label:contains("Email address or Finnair Plus number"):visible, a.header__nav-login:contains("Log in"):visible, .header__mobile-login.js-cas-login:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = $('.header__user-name > a').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
        && (typeof(account.properties.Name) != 'undefined')
        && (account.properties.Name != '')
        && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            util.waitFor({
                selector: 'button[title="Display my profile information"]',
                success: function(elem) {
                    elem.get(0).click();
                    $('ul li a:contains("Log out"):visible').get(0).click();
                }
            });
        });
    },

    login: function (params) {
        browserAPI.log("login");
        setTimeout(function () {
            var form = $('form.form');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[formcontrolname="username"]').val(params.account.login);
                util.sendEvent(form.find('input[formcontrolname="username"]').get(0), 'input');
                form.find('input[formcontrolname="password"]').val(params.account.password);
                util.sendEvent(form.find('input[formcontrolname="password"]').get(0), 'input');

                provider.setNextStep('checkLoginErrors', function () {
                    //form.find('button[type = "submit"]').prop('disabled', false);
                    form.find('button[type = "submit"]').click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 5000);
                });
            }// if (form.length > 0)
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 2000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $(".error:visible");
        if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(errors.text());
        else
            provider.complete();
    }

};