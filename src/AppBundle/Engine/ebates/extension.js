var plugin = {

    hosts: {
        'www.rakuten.com': true,
        'www.rakuten.ca' : true,
        'secure.rakuten.com': true,
        'ap.accounts.global.rakuten.com': true,
        'www.rakuten.de': true,
        'www.rakuten.co.uk': true,
        'rakuten.co.uk': true,
        'login.account.rakuten.com': true,
    },

    getStartingUrl: function (params) {
        let url = 'https://www.rakuten.com/my-account.htm';

        switch (params.account.login2) {
            case 'Canada':
                url = 'https://www.rakuten.ca/member/dashboard';
                break;
            case 'Germany':
                url = 'https://login.account.rakuten.com/sso/authorize?client_id=am_de&redirect_uri=https://www.rakuten.de/club-everywhere&r10_audience=cat:refresh&response_type=code&scope=openid&prompt=login#/sign_in';
                break;
            case 'UK':
                url = 'https://login.account.rakuten.com/sso/authorize?client_id=am_uk&redirect_uri=https://rakuten.co.uk&r10_audience=cat:refresh&response_type=code&scope=openid&state=%2F';
                break;
            default:
                // for USA
                url = 'https://www.rakuten.com/my-account.htm';
                break;
        }

        return url;
    },

    loadLoginForm: function(params) {
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
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            else {
                const noThanks = $('#nothanks:visible input#no');
                if (noThanks.length) {
                    noThanks.get(0).click();
                    plugin.loadLoginForm(params);
                    return;
                }
            }
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        switch (params.account.login2) {
            case 'Canada':
                if ($('form[id="login-form"]:visible').length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                if ($('a[href*="/member/pending-cash-back"]:visible').length) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                break;
            case 'Germany':
            case 'UK':
                if ($('input[id = "elementToFocus"], input[id = "user_id"]').length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                if (
                    $('div.userModal').find('a[href *= logout]:visible').length > 0
                    || $('section[data-qa-id="logged-in-homepage-welcome-message-title"]').length > 0
                ) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                break;
            default:
                if ($('iframe[src*="/auth/v2/login"]:visible').contents().find('form:contains("Sign In")').length) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }

                if (provider.isMobile && $('form#loginForm').length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }

                if ($('a[href*="/account-settings.htm"]').length) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                break;
        }
        return null;
    },

    isSameAccount: function (params) {
        browserAPI.log("isSameAccount");
        let name;
        switch (params.account.login2) {
            case 'Canada':
                name = $('a[href*="/member/dashboard"] .f-bsrm').text();
                browserAPI.log("name: " + name);
                return ((typeof (params.account.properties) != 'undefined')
                    && (typeof (params.account.properties.Name) != 'undefined')
                    && (params.account.properties.Name !== '')
                    && (name.toLowerCase() === params.account.properties.Name.toLowerCase()));
                break;
            case 'Germany':
            case 'UK':
                return false;
                break;
            default:
                name = util.filter($('.dashboard-aside .member-name').text());
                if (name === '') {
                    name = util.filter($('.account-menu .user-name').text());
                }

                if (provider.isMobile) {
                    name = util.filter($('div[class *= "lifetime-cb"]').prev('div').text());
                }

                browserAPI.log("name: " + name);
                return name !== ''
                    && (typeof params.account !== 'undefined')
                    && (typeof params.account.properties !== 'undefined')
                    && (typeof params.account.properties.Name !== 'undefined')
                    && name.toLowerCase() === params.account.properties.Name.toLowerCase()
                ;
        }
    },

    logout: function (params) {
        browserAPI.log("logout");
        switch (params.account.login2) {
            case 'Canada':
                provider.setNextStep('loadLoginForm', function () {
                    document.getElementById('logout-id').submit();
                });
                break;
            case 'Germany':
                provider.setNextStep("start", function () {
                    document.location.href = 'https://www.rakuten.de/kundenkonto/uebersicht/logout';
                });
                break;
            case 'UK':
                provider.setNextStep("loadLoginForm", function () {
                    document.location.href = 'https://login.account.rakuten.com/sso/logout?post_logout_redirect_uri=https://rakuten.co.uk';
                });
                break;
            default:
                provider.setNextStep('loadLoginForm', function () {
                    $('a:contains("Sign Out")').get(0).click();
                });
                break;
        }
    },

    login: function (params) {
        browserAPI.log("login");
        var form;
        var login;
        switch (params.account.login2) {
            case 'Canada':
                form = $('form[id = "login-form"]');
                browserAPI.log("waiting... " + login);
                if (form.length === 0) {
                    provider.setError(util.errorMessages.loginFormNotFound);
                    return;
                }

                browserAPI.log("submitting saved credentials");
                form.find('input[name = "fe_member_uname"]').val(params.account.login);
                form.find('input[name = "fe_member_pw"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    const captcha = util.findRegExp(form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]').attr('src'), /k=([^&]+)/i);
                    if (captcha && captcha.length > 0) {
                        provider.reCaptchaMessage();
                        let counter = 0;
                        let login = setInterval(function () {
                            browserAPI.log("waiting... " + counter);
                            let error = $('li .error.invalid:eq(0):visible');
                            if (error.length > 0 && util.filter(error.text()) !== '') {
                                clearInterval(login);
                                plugin.checkLoginErrors(params);
                            }
                            if (counter > 120) {
                                clearInterval(login);
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }// if (counter > 120)
                            counter++;
                        }, 1000);
                    }// if (captcha.length > 0)
                    else {
                        browserAPI.log("captcha is not found");
                        form.submit();
                    }
                });
                break;
            case 'Germany':
            case 'UK':
                login = $('input[aria-label="User ID or email"]');

                if (login.length === 0) {
                    provider.setError(util.errorMessages.loginFormNotFound);
                    return;
                }

                login.val(params.account.login);
                util.sendEvent(login.get(0), 'input');
                $('div:contains("Next")').click();

                setTimeout(function () {
                    let errors = $('div[class *= "fc-207-68-68-255"]');
                    if (errors.length > 0) {
                        provider.setError(errors.text());
                        return;
                    }

                    let pass = $('input[aria-label="Password"]');
                    if (pass.length === 0) {
                        provider.setError(util.errorMessages.passwordFormNotFound);
                        return;
                    }
                    pass.val(params.account.password);
                    util.sendEvent(pass.get(0), 'input');
                    provider.setNextStep('checkLoginErrors', function () {
                        $('div[class *= "button__submit"] > div:contains("Sign in"):visible').click();
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 5000)
                    });
                }, 3000);
                break;
            default:
                form = $('iframe[src*="/auth/v2/login"]:visible').contents().find('form:contains("Sign In")');

                if (provider.isMobile) {
                    form = $('form#loginForm');
                }

                if (form.length === 0) {
                    provider.setError(util.errorMessages.loginFormNotFound);
                    return;
                }

                browserAPI.log("submitting saved credentials");

                login = form.find('input[name="emailAddress"]');
                if (login.length === 0) {
                    provider.setError(util.errorMessages.loginFormNotFound);
                    return;
                }
                login.val(params.account.login);
                util.sendEvent(login.get(0), 'input');

                let pass = form.find('input[name="password"]');
                if (pass.length === 0) {
                    provider.setError(util.errorMessages.passwordFormNotFound);
                    return;
                }
                pass.val(params.account.password);
                util.sendEvent(pass.get(0), 'input');

                provider.setNextStep('checkLoginErrors', function () {
                    setTimeout(function () {
                        const captcha = util.findRegExp(form.find('iframe[src^="https://www.google.com/recaptcha/enterprise/anchor"]').attr('src'), /k=([^&]+)/i);
                        if (captcha && captcha !== '') {
                            provider.reCaptchaMessage();
                            let counter = 0;
                            let login = setInterval(function () {
                                browserAPI.log("waiting... " + counter);
                                const error = $('li .error.invalid:eq(0):visible');
                                if (error.length > 0 && util.filter(error.text()) !== '') {
                                    clearInterval(login);
                                    plugin.checkLoginErrors(params);
                                }
                                if (counter > 120) {
                                    clearInterval(login);
                                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                                }
                                counter++;
                            }, 1000);
                        } else {
                            browserAPI.log("captcha is not found");

                            if (provider.isMobile) {
                                $('button#login').get(0).click();

                                setTimeout(function () {
                                    plugin.checkLoginErrors(params);
                                }, 7000)
                            } else {
                                form.find('*[type="submit"]:contains("Sign In"):visible').click();
                            }
                        }
                    }, 1000);
                });
                break;
        }
    },


    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let error;
        switch (params.account.login2) {
            case 'Canada':
                error = $('.signup_signin_msg_fail:visible');
                break;
            case 'Germany':
            case 'UK':
                error = $('div[class *= "fc-207-68-68-255"]');
                break;
            default:
                const loginIframe = $('iframe[src*="/auth/v2/login"]:visible').contents();
                error = loginIframe.find('div[class*="_error-message"]:visible, div[class*="auth-web-error"]:visible');
                
                if (error.length === 0 || util.filter(error.text()) === '') {
                    error = $('li .error.invalid:eq(0):visible, div.errormsge:visible');
                }

                break;
        }

        if (error.length > 0 && util.filter(error.text()) !== '') {
            provider.setError(error.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }
};