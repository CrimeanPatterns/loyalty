var plugin = {
    autologin: {

        getStartingUrl: function (params) {
            if (params.account.login2 === 'South Africa') {
                return 'https://secure.membershiprewards.co.za/signin.aspx';
            }
            if (params.account.login2 === 'Saudi Arabia') {
                return 'https://online.americanexpress.com.sa/consweb/Login.aspx';
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
            if (["Bahrain", "Egypt", "Lebanon", "Jordan", "Kuwait", "Oman", "UAE"].indexOf(params.account.login2) !== -1) {
                return 'https://secure.americanexpress.com.bh/wps/portal/lebanon?location=globalsplash';
            }

            return 'https://global.americanexpress.com/login?inav=iNavLnkLog';
        },

        start: function (params) {
            browserAPI.log("start");
            browserAPI.log("Region => " + params.account.login2);
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                var isLoggedIn = plugin.autologin.isLoggedIn(params);
                if (isLoggedIn !== null) {
                    clearInterval(start);
                    if (isLoggedIn) {
                        if (plugin.autologin.isSameAccount(params))
                            provider.complete();
                        else
                            plugin.autologin.logout(params);
                    }
                    else {
                        if (params.account.login2 == 'Switzerland') {
                            provider.setNextStep('redirectPage', function () {
                                $('a#loginFormRex').get(0).click();
                            });
                            return;
                        }
                        if (["Bahrain", "Egypt", "Lebanon", "Jordan", "Kuwait", "Oman", "UAE"].indexOf(params.account.login2) !== -1) {
                            provider.setNextStep('login', function () {
                                $('a.log-in-out-btn').get(0).click();
                            });
                            return;
                        }
                        plugin.autologin.login(params);
                    }
                }// if (isLoggedIn !== null)
                if (isLoggedIn === null && counter > 10) {
                    clearInterval(start);
                    provider.setError(['Can\'t determine login state [' + params.account.login2 + ']', util.errorCodes.engineError]);
                    return;
                }// if (isLoggedIn === null && counter > 10)
                counter++;
            }, 500);
        },

        redirectPage: function (params) {
            browserAPI.log("redirectPage");
            provider.setNextStep('login', function () {
            // provider.setNextStep('switchToEng', function () {
            });
        },

        switchToEng: function (params) {
            browserAPI.log("switchToEng");
            provider.setNextStep('login', function () {
                var counter = 0;
                var start = setInterval(function () {
                    browserAPI.log("waiting... " + counter);
                    var enLang = $('a#aenx1, a[href="?lang=en"]');
                    if (enLang.length > 0) {
                        clearInterval(start);
                        enLang[0].click();
                    }// if (isLoggedIn !== null)
                    if (enLang.length === 0 && counter > 10) {
                        clearInterval(start);
                        enLang[0].click();
                    }// if (enLang.length === 0 && counter > 10)
                    counter++;
                }, 500);
            });
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
                    if ($('form:has(input[id = "eliloUserID"])').length > 0) {
                        browserAPI.log("not LoggedIn");
                        return false;
                    }
                    if ($('a[class *= "closedLogout"]').length > 0) {
                        browserAPI.log("LoggedIn");
                        return true;
                    }
                    break;
            }

            return null;
        },

        login: function (params) {
            browserAPI.log("login");
            browserAPI.log("Region => " + params.account.login2);
            var form;
            switch (params.account.login2) {
                case 'South Africa':
                    form = $('form:has(input[name = "username"])');
                    if (form.length > 0) {
                        browserAPI.log("submitting saved credentials");
                        form.find('input[name = "username"]').val(params.account.login);
                        form.find('input[name = "password"]').val(params.account.password);
                        provider.setNextStep('checkLoginErrors', function () {
                            form.find('button.logonbtn').get(0).click();
                        });
                    }
                    else
                        provider.setError(['Login form not found [' + params.account.login2 + ']', util.errorCodes.engineError]);
                    break;
                case 'Saudi Arabia':
                    break;
                case 'Switzerland':
                    form = $('form:has(input[name = "username"])');
                    if (form.length > 0) {
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
                    }
                    else
                        provider.setError(['Login form not found [' + params.account.login2 + ']', util.errorCodes.engineError]);
                    break;
                case 'ישראל':
                    form = $('form[id = "otpLobbyFormPassword"]');
                    if (form.length > 0) {
                        browserAPI.log("submitting saved credentials");
                        form.find('input[id = "otpLoginId_ID"]').val(params.account.login);
                        form.find('input[id = "otpLoginLastDigits_ID"]').val(params.account.login2);
                        form.find('input[id = "otpLoginPwd"]').val(params.account.password);
                        provider.setNextStep('checkLoginErrors', function () {
                            form.find('button.btn-send').get(0).click();
                        });
                    }
                    else
                        provider.setError(['Login form not found [' + params.account.login2 + ']', util.errorCodes.engineError]);
                    break;
                case 'Bahrain':
                case 'Egypt':
                case 'Lebanon':
                case 'Jordan':
                case 'Kuwait':
                case 'Oman':
                case 'UAE':
                    form = $('form[name = "onlsLoginForm"]');
                    if (form.length > 0) {
                        browserAPI.log("submitting saved credentials");
                        form.find('input[name = "UserID"]').val(params.account.login);
                        provider.setNextStep('passwordForm', function () {
                            form.find('input[name = "btnLoginNowChild"]').get(0).click();
                        });
                    }
                    else
                        provider.setError(['Login form not found [' + params.account.login2 + ']', util.errorCodes.engineError]);
                    break;
                default:
                    form = $('form:has(input[id = "eliloUserID"])');
                    if (form.length > 0) {
                        browserAPI.log("submitting saved credentials");
                        form.find('input[id = "eliloUserID"]').val(params.account.login);
                        form.find('input[id = "eliloPassword"]').val(params.account.password);
                        provider.setNextStep('checkLoginErrors', function () {
                            form.find('button[id = "loginSubmit"]').trigger('click');
                        });
                    }
                    else
                        provider.setError(['Login form not found [' + params.account.login2 + ']', util.errorCodes.engineError]);
                    break;
            }
        },

        passwordForm: function (params) {
            browserAPI.log("passwordForm");
            var form = $('form[name = "enterPasswordForm"]');
            if (form.length === 0) {
                browserAPI.log("submitting saved credentials");
                var error = $('span.errorMessage:visible');
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

        isSameAccount: function (params) {
            browserAPI.log("isSameAccount");
            browserAPI.log("Region => " + params.account.login2);
            var name;
            switch (params.account.login2) {
                case 'South Africa':
                    name = util.filter($('a.username').text());
                    browserAPI.log("name: " + name);
                    return ((typeof(params.properties) != 'undefined')
                        && (typeof(params.properties.Name) != 'undefined')
                        && (params.properties.Name !== '')
                        && (name.toLowerCase() === params.properties.Name.toLowerCase()));
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
                    return ((typeof(params.properties) != 'undefined')
                        && (typeof(params.properties.Name) != 'undefined')
                        && (params.properties.Name !== '')
                        && (name !== '')
                        && (name.toLowerCase() === params.properties.Name.toLowerCase()));
                default:
//                var number = plugin.findRegExp(params, /Member\s*#:\s*(\d+)/i);
//                browserAPI.log("number: " + number);
//                return ((typeof(params.properties) != 'undefined')
//                    && (typeof(params.properties.Number) != 'undefined')
//                    && (params.properties.Number != '')
//                    && (number == params.properties.Number));
                    break;
            }

            return false;
        },

        checkLoginErrors: function (params) {
            browserAPI.log("checkLoginErrors");
            var error;
            browserAPI.log("Region => " + params.account.login2);
            switch (params.account.login2) {
                case 'South Africa':
                    error = $('div.error-container:visible');
                case 'Saudi Arabia':
                    break;
                case 'Switzerland':
                    error = $('p.MuiTypography-colorError:visible');
                    break;
                case 'ישראל':
                    error = $('h6.error-msg:visible');
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
                default:
                    error = $('div.error');
            }

            if (error.length > 0 && util.filter(error.text()) !== '')
                provider.setError(util.filter(error.text()));
            else
                provider.complete();
        },

        logout: function (params) {
            browserAPI.log("logout");
            browserAPI.log("Region => " + params.account.login2);
            switch (params.account.login2) {
                case 'South Africa':
                    provider.setNextStep('LoadLoginForm', function () {
                        window.location.href = 'https://www.membershiprewards.co.za/signout.aspx';
                    });
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
                    provider.setNextStep('LoadLoginForm', function () {
                        $('a[class *= "closedLogout"]').click();
                    });
            }
        },

        LoadLoginForm: function (params) {
            provider.setNextStep('login', function () {
                window.location.href = plugin.autologin.getStartingUrl(params);
            });
        }

    }
};