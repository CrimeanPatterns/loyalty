var plugin = {

    hosts: {
        'thebodyshop.com': true,
        'www.thebodyshop.com': true
    },

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        let url = 'https://www.thebodyshop.com/en-us/my-account';
        switch (params.account.login2) {
            case 'UK':
                url = 'https://www.thebodyshop.com/en-gb/my-account';
                break;
            case 'CA':
                url = 'https://www.thebodyshop.com/en-ca/my-account';
                break;
        }

        return url;
    },

    loadLoginForm: function (params) {
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
            let isLoggedIn = plugin.isLoggedIn(params);
            let errorOccurred = $('div.alert-danger:visible');
            if (errorOccurred.length > 0) {
                clearInterval(start);
                provider.setNextStep('login', function () {
                    let href = 'https://www.thebodyshop.com/en-us/login';
                    switch (params.account.login2) {
                        case 'UK':
                            href = 'https://www.thebodyshop.com/en-gb/login';
                            break;
                        case 'CA':
                            href = 'https://www.thebodyshop.com/en-ca/login';
                            break;
                    }
                    document.location.href = href;
                });
            }
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
            if (isLoggedIn === null && counter > 30) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if ($('#accountMainContent:visible').length > 0 && $("span[user-email]:visible").length > 0 ) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#gigya-login-form:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var email = $("span[user-email]").text();
        browserAPI.log("email "+email);
        return typeof (account.properties) !== 'undefined'
            && email
            && email === account.login;
    },

    logout: function (params) {
        browserAPI.log("logout");
        var href;
        switch (params.account.login2) {
            case 'UK':
                href = 'https://www.thebodyshop.com/en-gb/logout';
                break;
            case 'CA':
                href = 'https://www.thebodyshop.com/en-ca/logout';
                break;
            default:// 'USA'
                href = 'https://www.thebodyshop.com/en-us/logout';
                break;
        }
        provider.setNextStep('loadLoginForm', function () {
            if (provider.isMobile) {
                document.location.href = href;
            }else {
                $('a[href*="/logout"]:visible').get(0).click();
                setTimeout(function () {
                    plugin.loadLoginForm(params);
                }, 5000);
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let counter = 0;
        let login = setInterval(function () {
            let form = $('form#gigya-login-form');
            counter++;
            if (form.length === 0) {
                if (counter > 10) {
                    clearInterval(login);
                    provider.setError(util.errorMessages.loginFormNotFound);
                    return;
                }
                return;
            }
            clearInterval(login);
            browserAPI.log("submitting saved credentials");
            $('input[name="username"]', form).val(params.account.login);
            $('input[name="password"]', form).val(params.account.password);
            form.find('input#gigya-checkbox-remember').prop('checked', true);
            return provider.setNextStep('checkLoginErrors', function () {
                form.find('input.gigya-input-submit').get(0).click();
                setTimeout(function () {
                    plugin.checkLoginErrors();
                }, 10000);
            });
        }, 500);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let $error = $('.error p', '#globalMessages','.gigya-form-error-msg.gigya-error-msg-active:visible');
        if ($error.length && util.filter($error.text()) !== '') {
            provider.setError(util.filter($error.text()));
            return;
        }
        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }

};
