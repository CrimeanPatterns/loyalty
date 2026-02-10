var plugin = {

    hosts: {
        'www.americanexpress.com': true,
        'global.americanexpress.com': true,
        'secure.americanexpress.com.bh': true,
        'id.nedbank.co.za': true,
        'rewardshop.americanexpress.ch': true,
        'sso.swisscard.ch': true,
        'he.americanexpress.co.il': true,
        'online.americanexpress.com.sa': true,
    },

    cashbackLink : '',

    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        if (params.account.login2 === 'South Africa') {
            return 'https://secure.membershiprewards.co.za/signin.aspx';
        }

        if (["Bahrain", "Egypt", "Lebanon", "Jordan", "Kuwait", "Oman", "UAE", "United Arab Emirates"].indexOf(params.account.login2) !== -1) {
            return 'https://secure.americanexpress.com.bh/wps/portal/lebanon?location=globalsplash';
        }

        if (params.account.login2 === 'Saudi Arabia') {
            return 'https://online.americanexpress.com.sa/consweb/#/en/login';
        }

        if (params.account.login2 === 'Schweiz') {
            params.account.login2 = 'Switzerland';
        }
        if (params.account.login2 === 'Switzerland') {
            return 'https://rewardshop.americanexpress.ch/home';
        }

        if (params.account.login2 === 'ישראל') {
            return 'https://he.americanexpress.co.il/personalarea/login/#/logonPage';
        }

        return 'https://global.americanexpress.com/login?DestPage=%2Fdashboard%3Fomnlogin%3Dus_homepage_myca';
    },

    LoadLoginForm: function (params) {
        provider.setNextStep('start', function () {
            window.location.href = plugin.autologin.getStartingUrl(params);
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
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                } else {
                    if (params.account.login2 === 'Switzerland') {
                        provider.setNextStep('login', function () {
                            $('a#loginFormRex').get(0).click();
                        });
                        return;
                    }
                    plugin.login(params);
                }
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
        browserAPI.log("isLoggedIn");
        switch (params.account.login2) {
            case 'South Africa':
                if ($('a[href *= signout]').attr('href')) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                if ($('input[name = "username"]:visible').length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                break;
            case 'Saudi Arabia':
                if ($('form.v-form').length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                break;
            case 'Switzerland':
                if ($('a#logoutHandler').length > 0) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                if ($('a#loginFormRex').length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                break;
            case 'ישראל':
                // if ($('a#logoutHandler').length > 0) {
                //     browserAPI.log("LoggedIn");
                //     return true;
                // }
                if ($('form#otpLobbyFormPassword').length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                break;
            case 'Bahrain':
            case 'Egypt':
            case 'Lebanon':
            case 'Jordan':
            case 'Kuwait':
            case 'Oman':
            case 'UAE':
                if ($('a:contains("Log out")').length > 0) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                if (
                    $('form#onlsLoginForm:visible').length > 0
                    || $('a.log-in-out-btn').length > 0
                ) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                break;
            default:
                if ($('#LoginComponent form:visible').length) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }

                if ($('a[href *= logout]').length) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
            break;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");

        browserAPI.log("Region => " + account.login2);
        let name;
        switch (account.login2) {
            case 'Saudi Arabia':
                break;
            case 'ישראל':
                break;
            case 'Switzerland':
                name = util.filter($('span.label-user').text());
                browserAPI.log("name: " + name);
                return ((typeof(params.properties) != 'undefined')
                        && (typeof(params.properties.Name) != 'undefined')
                        && (params.properties.Name !== '')
                        && (name !== '')
                        && (name.toLowerCase() === params.properties.Name.toLowerCase()));
            case 'Bahrain':
            case 'Egypt':
            case 'Lebanon':
            case 'Jordan':
            case 'Kuwait':
            case 'Oman':
            case 'UAE':
                name = util.filter($('span[name = "userName"]').text());
                browserAPI.log("name: " + name);
                return ((typeof(account.properties) != 'undefined')
                        && (typeof(account.properties.Name) != 'undefined')
                        && (account.properties.Name !== '')
                        && (name !== '')
                        && (name.toLowerCase() === account.properties.Name.toLowerCase()));
            default:
                // let number = util.findRegExp($('li:contains("Account #")').text(), /Account\s*#\s*([^<]+)/i);
                // browserAPI.log("number: " + number);
                // return ((typeof (account.properties) != 'undefined')
                //     && (typeof (account.properties.Number) != 'undefined')
                //     && (account.properties.Number != '')
                //     && number
                //     && (number == account.properties.Number));
                break;
        }

        return false;
    },

    logout: function (params) {
        browserAPI.log("logout");
        browserAPI.log("Region => " + params.account.login2);
        switch (params.account.login2) {
            case 'South Africa':
                provider.setNextStep('LoadLoginForm', function () {
                    window.location.href = 'https://www.membershiprewards.co.za/signout.aspx';
                });
                break;
            case 'Saudi Arabia':
                break;
            case 'Switzerland':
                $('a#logoutHandler')[0].click();
                break;
            case 'ישראל':
                break;
            case 'Bahrain':
            case 'Egypt':
            case 'Lebanon':
            case 'Jordan':
            case 'Kuwait':
            case 'Oman':
            case 'UAE':
                $('a:contains("Log out")').get(0).click();
                break;
            default:
                provider.setNextStep('start', function () {
                    document.location.href = 'https://www.americanexpress.com/en-us/account/logout';
                });
        }
    },

    login: function (params) {
        browserAPI.log("login");
        browserAPI.log("Region => " + params.account.login2);
        let form;
        switch (params.account.login2) {
            case 'South Africa':
                form = $('form:has(input[name = "username"])');

                if (form.length === 0) {
                    provider.setError(['Login form not found [' + params.account.login2 + ']', util.errorCodes.engineError]);
                    return false;
                }

                browserAPI.log("submitting saved credentials");
                form.find('input[name = "username"]').val(params.account.login);
                form.find('input[name = "password"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button.logonbtn').get(0).click();
                });

                break;
            case 'Saudi Arabia':
                form = $('form.v-form');

                if (form.length === 0) {
                    provider.setError(['Login form not found [' + params.account.login2 + ']', util.errorCodes.engineError]);
                    return;
                }

                browserAPI.log("submitting saved credentials");
                form.find('input[placeholder="Enter your User ID"]').val(params.account.login);
                form.find('input[placeholder="Enter your Password"]').val(params.account.password);

                // vue.js
                provider.eval(`
                    let email = document.querySelector('input[placeholder="Enter your User ID"]');
                    email.dispatchEvent(new Event('input'));
                    email.dispatchEvent(new Event('change'));
                    email.dispatchEvent(new Event('keyup'));
                    
                    let pass = document.querySelector('input[placeholder="Enter your Password"]');
                    pass.dispatchEvent(new Event('input'));
                    pass.dispatchEvent(new Event('change'));
                    pass.dispatchEvent(new Event('keyup'));
                `);

                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button:contains("Login")').get(0).click();

                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 5000)
                });

                break;
            case 'Switzerland':
                form = $('form:has(input[name = "username"])');

                if (form.length === 0) {
                    provider.setError(['Login form not found [' + params.account.login2 + ']', util.errorCodes.engineError]);
                    return;
                }

                browserAPI.log("submitting saved credentials");
                // form.find('input[name = "username"]').val(params.account.login);
                form.find('input[name = "password"]').val(params.account.password);

                // reactjs
                provider.eval(
                    "var FindReact = function (dom) {" +
                    "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                    "        return dom[key];" +
                    "    }" +
                    "    return null;" +
                    "};" +
                    "FindReact(document.querySelector('input[name = \"username\"]')).onChange({target:{value:'" + params.account.login + "'}, preventDefault:function(){}});" +
                    "FindReact(document.querySelector('input[name = \"password\"]')).onChange({target:{value:'" + params.account.password + "'}, preventDefault:function(){}});"
                );

                provider.setNextStep('checkLoginErrors', function () {

                    setTimeout(function () {
                        form.find('button[type="submit"]').get(0).click();
                    }, 1000);

                    setTimeout(function () {
                        browserAPI.log("search error on login page...");
                        let errors = $('p.Mui-error:visible, p.MuiTypography-colorError:visible');
                        if (errors.length > 0) {
                            provider.setError(errors.text(), true);
                        }
                    }, 10000)
                });
                break;
            case 'ישראל':
                form = $('form[id = "otpLobbyFormPassword"]');

                if (form.length === 0) {
                    provider.setError(['Login form not found [' + params.account.login2 + ']', util.errorCodes.engineError]);
                    return;
                }

                browserAPI.log("submitting saved credentials");

                // angularjs 10
                function triggerInput(selector, enteredValue) {
                    const input = document.querySelector(selector);
                    var createEvent = function(name) {
                        var event = document.createEvent('Event');
                        event.initEvent(name, true, true);
                        return event;
                    };
                    input.dispatchEvent(createEvent('focus'));
                    input.value = enteredValue;
                    input.dispatchEvent(createEvent('change'));
                    input.dispatchEvent(createEvent('input'));
                    input.dispatchEvent(createEvent('blur'));
                }
                triggerInput('input[name = "otpLoginId_ID"]', '' + params.account.login );
                triggerInput('input[name = "otpLoginLastDigits_ID"]', '' + params.account.login2 );
                triggerInput('input[name = "otpLoginPwd"]', '' + params.account.password );

                // form.find('input[id = "otpLoginId_ID"]').val(params.account.login);
                // form.find('input[id = "otpLoginLastDigits_ID"]').val(params.account.login2);
                // form.find('input[id = "otpLoginPwd"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button.btn-send').get(0).click();

                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 7000)
                });
                break;
            case 'Bahrain':
            case 'Egypt':
            case 'Lebanon':
            case 'Jordan':
            case 'Kuwait':
            case 'Oman':
            case 'UAE':
                form = $('form[name = "onlsLoginForm"]');

                if (form.length === 0) {
                    provider.setError(['Login form not found [' + params.account.login2 + ']', util.errorCodes.engineError]);
                    return;
                }

                browserAPI.log("submitting saved credentials");
                form.find('input[name = "UserID"]').val(params.account.login);
                provider.setNextStep('passwordForm', function () {
                    form.find('input[name = "btnLoginNowChild"]').get(0).click();
                });

                break;
            default:
                form = $('#LoginComponent form:visible');

                if (form.length === 0) {
                    provider.setError(util.errorMessages.loginFormNotFound);
                    return;
                }

                browserAPI.log("submitting saved credentials");
                form.find('input[id = "eliloUserID"]').val(params.account.login);
                // form.find('input[id = "eliloPassword"]').val(params.account.password);

                function triggerInput(selector, enteredValue) {
                    const input = document.querySelector(selector);
                    var createEvent = function(name) {
                        var event = document.createEvent('Event');
                        event.initEvent(name, true, true);
                        return event;
                    };
                    input.dispatchEvent(createEvent('focus'));
                    input.value = enteredValue;
                    input.dispatchEvent(createEvent('change'));
                    input.dispatchEvent(createEvent('input'));
                    input.dispatchEvent(createEvent('blur'));
                }
                triggerInput('input[id = "eliloPassword"]', '' + params.account.password );

                provider.setNextStep('checkLoginErrors', function () {
                    form.find('#loginSubmit').get(0).click();

                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 7000)
                });
                break;
        }
    },

    passwordForm: function (params) {
        browserAPI.log("passwordForm");
        let form = $('form[name = "enterPasswordForm"]');
        if (form.length === 0) {
            browserAPI.log("submitting saved credentials");
            let error = $('span.errorMessage:visible');
            if (error.length > 0 && util.filter(error.text()) !== '')
                provider.setError(util.filter(error.text()));
            else
                provider.setError(['Password form not found [' + params.account.login2 + ']', util.errorCodes.engineError]);
            return;
        }
        form.find('input[name = "Password"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('input[name = "btnLogin"]').get(0).click();
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        browserAPI.log("Region => " + params.account.login2);

        let error = $('div.alert-warn:visible div');

        switch (params.account.login2) {
            case 'South Africa':
                error = $('div.error-container:visible');
                break;
            case 'Switzerland':
                error = $('p.MuiTypography-colorError:visible');
                break;
            case 'ישראל':
                error = $('h6.error-msg:visible');
                if (error.length === 0) {
                    error = $('.form__error-msg:visible:eq(0)');
                }
                break;
            case 'Saudi Arabia':
                error = $('div.v-alert__content:visible, div[role="alert"].error--text:visible');
                break;
            case 'Bahrain':
            case 'Egypt':
            case 'Lebanon':
            case 'Jordan':
            case 'Kuwait':
            case 'Oman':
            case 'UAE':
                error = $('span.errorMessage:visible');
                break;
        }

        if (error.length > 0 && util.filter(error.text()) !== '') {
            provider.setError(util.filter(error.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");

        provider.complete();
    }

};