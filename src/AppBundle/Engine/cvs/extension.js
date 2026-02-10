var plugin = {

    hosts: {'www.cvs.com': true},

    cashbackLink : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.cvs.com/account/account-management.jsp?icid=cvsheader:myaccount';
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

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if (document.getElementsByTagName('cvs-login-container').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if (document.getElementsByTagName('cvs-my-profile').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.findRegExp($('p[class ^= "ExtraCare_extraCare__cardInfo__"]').text(), /(\d+)/);
        browserAPI.log("number: " + number);
            return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.ExtraCareNumber) != 'undefined')
            && (account.properties.ExtraCareNumber != '')
            && number
            && (number == account.properties.ExtraCareNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        let counter = 0;
        function tryToLogout() {
            try {
                document.querySelector('cvs-header-desktop').shadowRoot.querySelectorAll('cvs-header-utility-bar')[1].shadowRoot.querySelector('a[aria-label="Sign out"]').click();
            } catch (TypeError) {
                setTimeout(function () {
                    counter++;
                    if (counter < 10) tryToLogout();
                }, 500)
            }
        }
        provider.setNextStep('loadLoginForm', function () {
            tryToLogout();
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
        // open login form
        let signIn = $('#signInOverlay');
        if (signIn.length)
            signIn.get(0).click();
        // wait login form
        let counter = 0;
        let login = setInterval(function () {
            let form = $('form#login_val');
            let angularForm = $('cvs-login-container');
            browserAPI.log("waiting... " + counter);
            if (form.length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");
                form.find('#login_new').val(params.account.login);
                form.find('#login_new').val(params.account.login);
                form.find('#password_new').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button#login').click();
                });
            }
            if (angularForm.length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");
                angularForm.find('#emailField').val(params.account.login);
                util.sendEvent(angularForm.find('#emailField').get(0), 'input');
                $('button.continue-button.primary').click();
                provider.setNextStep('checkLoginErrors', function () {
                    util.waitFor({
                        selector: 'input#cvs-password-field-input:visible',
                        success: function() {
                            setTimeout(function () {
                                $('#cvs-password-field-input').val(params.account.password);
                                util.sendEvent($('#cvs-password-field-input').get(0), 'input');
                                $('div.button.primary').click();
                                setTimeout(function () {
                                    plugin.checkLoginErrors(params);
                                }, 5000);
                            }, 1000);
                        },
                        fail: function() {
                            plugin.checkLoginErrors(params);
                        },
                        timeout: 5
                    });
                });
            }
            if (counter > 20) {
                clearInterval(login);
                provider.setError(util.errorMessages.loginFormNotFound);
            }
            counter++;
        }, 500);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        let errors = $('div#formerrors:visible');
        if (errors.length === 0)
            errors = $('#pswdError:visible, p#emailErroraccess:visible');
        if (errors.length === 0)
            errors = $('h2#error-title:visible, p.alert-description:visible');
        if (errors.length > 0) {
            provider.setError(util.filter(errors.text()));
            return;
        }

        provider.complete();
    }
};