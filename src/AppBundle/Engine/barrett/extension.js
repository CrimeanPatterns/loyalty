var plugin = {

    hosts  : {
        'www.hollandandbarrett.ie'  : true,
        'www.hollandandbarrett.com' : true,
        'www.hollandandbarrett.nl' : true,
        'auth.hollandandbarrett.ie' : true,
        'auth.hollandandbarrett.com' : true,
        'auth.hollandandbarrett.nl' : true,
    },
    domain : 'https://www.hollandandbarrett.com',

    getStartingUrl: function (params) {
        if ('Ireland' === params.account.login2)
            this.domain = 'https://www.hollandandbarrett.ie';
        if ('Netherlands' === params.account.login2)
            this.domain = 'https://www.hollandandbarrett.nl';
        return this.domain + '/my-account/login.jsp?myaccount=true';
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
                        provider.complete();
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

    isLoggedIn : function() {
        browserAPI.log('isLoggedIn');
        const logout = $('a:contains("Logout"):visible, a:contains("Uitloggen"):visible, button:contains("Sign Out")');
        if (logout.length || document.location.pathname === '/') {
            browserAPI.log('isLoggedInd: true');
            return true;
        }
        if ($('form[action*="/login.jsp"]').length || !logout.length) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        const number = util.findRegExp($('.rfl-voucher-list:eq(0)').html(), /"rewards_card"\s*:\s*"(\d+)",/i);
        browserAPI.log("number: " + number);
        return typeof(account.properties) !== 'undefined'
            && typeof(account.properties.CardNumber) !== 'undefined'
            && account.properties.CardNumber !== ''
            && number === account.properties.CardNumber;
    },

    logout : function(params) {
        browserAPI.log('logout');
        provider.setNextStep('loadLoginForm', function() {
            let logout = $('a#header-acc-logout');
            if (logout.length > 0) {
                logout.get(0).click();
            }else if ($("a:contains('Logout')").length) {
                $("a:contains('Logout')").get(0).click();
            } else {
                document.location.href = 'https://' + document.location.host + '/auth/logout';
            }
        });
    },

    loadLoginForm : function(params) {
        provider.setNextStep('login', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log('login');
        let form = $('form[action*="/login.jsp"]');
        if (form.length) {
            browserAPI.log("submitting saved credentials");
            $('#frm_login_email', form).val(params.account.login);
            $('#frm_login_password', form).val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                $('input[type="submit"]', form).get(0).click();
                setTimeout(function () {
                    if ($('iframe[src *= "https://www.google.com/recaptcha/api2/bframe?"]').closest('div[style*="visibility: visible;"]').length > 0) {
                        provider.reCaptchaMessage();
                        var counter = 0;
                        var login = setInterval(function () {
                            browserAPI.log("waiting... " + counter);
                            if (counter > 160) {
                                clearInterval(login);
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }
                            counter++;
                        }, 500);
                    }
                }, 2500);
            });

            return;
        }

        form = $('form:has(input[name = "state"])')

        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "username"]').val(params.account.login);
            util.sendEvent(form.find('input[name = "username"]').get(0), 'input');
            form.find('input[name = "password"]').val(params.account.password);
            util.sendEvent(form.find('input[name = "password"]').get(0), 'input');
            provider.setNextStep('checkLoginErrors', function () {
                $('button[name = "action"]', form).get(0).click();
            });

            return;
        }

        provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors : function() {
        browserAPI.log('checkLoginErrors');
        const error = $('.form-errors.handled-error:visible, #error-element-password:visible');

        if (error.length && util.filter(error.text()) !== '') {
            provider.setError(util.filter(error.text()));
            return;
        }

        provider.complete();
    }

};