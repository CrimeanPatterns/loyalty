var plugin = {
    autologin: {

        getStartingUrl: function (params) {
            switch (params.account.login2) {
                case 'Australia':
                    return 'https://mobile.citibank.com.au/australia/middleware/p#_frmLogin';
                    break;
                case 'India':
//                    return 'https://mobile.citibank.co.in/mweb/Login.do?method=login';
                    return 'https://mobile.citibank.co.in/mweb/Login.do?method=getPhoneNo&app=CT';
                    break;
                case 'Brazil':
                    return 'https://mobilelatam.citibankonline.com/lamob/thin?country=BR#_frmLogin';
                    break;
                case 'Thailand':
                    return 'https://mobile.citibank.co.th/GMP/JSO/presignon/launch.action#signon';
                    break;
                case 'Taiwan':
                    return 'https://mobile.citibank.com.tw/GMP/JSO/presignon/launch.action#signon';
                    break;
                case 'Singapore':
                    return 'https://mobile.citibank.com.sg/GMP/JSO/presignon/launch.action#signon';
                    break;
                case 'Mexico':
                    return 'https://bancanet.banamex.com/MXGCB/JPS/portal/LocaleSwitch.do?locale=es_MX';
                    break;
                case 'Malaysia':
                    return 'https://mobile.citibank.com.my/GMP/JSO/presignon/launch.action#signon';
                    break;
                case 'HongKong':
                    return 'https://mobile.citibank.com.hk/GMP/JSO/presignon/launch.action#signon';
                    break;
                case 'USA': default:
                    return 'https://mobile.citibankonline.com/GLMOB/p#_frmGMSignon';
                    break;
            }
        },

        start: function (params) {
            if (this.isLoggedIn()) {
                if (this.isSameAccount())
                    this.finish();
                else
                    this.logout();
            }
            else
                this.login();
        },

        login: function () {
            browserAPI.log("login");
            var form;
            var counter;
            var login;
            switch (params.account.login2) {
                case 'Brazil':
                    counter = 0;
                    login = setInterval(function () {
                        form = $('form[id = frmLogin]');
                        browserAPI.log("waiting...");
                        if (form.length > 0) {
                            clearInterval(login);
                            browserAPI.log("submitting saved credentials");
                            form.find('input[name = "txtUserId"]').val(params.account.login);
                            form.find('input[name = "txtPassword"]').val(params.account.password);
                            api.setNextStep('checkLoginErrors', function () {
                                form.find('input#btnSignUpevent_').get(0).click();
                            });
                        }
                        if (counter > 10) {
                            clearInterval(login);
                            provider.setError(util.errorMessages.loginFormNotFound);
                        }
                        counter++;
                    }, 500);
                    break;
                case 'India':
                    setTimeout(function() {
                        form = $('form[name = wapform]');
                        if (form.length > 0) {
                            form.find('input[name = "text_tempuserid"]').val(params.account.login);
                            form.find('input[name = "text_temppasswd"]').val(params.account.password);
                            api.setNextStep('checkLoginErrors', function () {
                                $('input[name = "ser_LG"]').get(0).click();
                            });
                        }
                        else
                            provider.setError(util.errorMessages.loginFormNotFound);
                    }, 2000);
                    break;
                case 'Mexico':
                    form = $('form[name = "preSignonForm"]');
                    if (form.length > 0) {
                        browserAPI.log("submitting saved credentials");
                        form.find('input[name = "username"]').val(params.account.login);
                        form.find('input[name = "username1"]').val(params.account.login);
                        provider.setNextStep('loginMexico', function () {
                            provider.eval('validarUserNumber();');
                        });
                    }
                    else
                        provider.setError(['Login form not found [Code: MX]', util.errorCodes.providerError]);
                    break;
                case 'Australia':
                case 'Thailand':
                case 'Taiwan':
                case 'Singapore':
                case 'Malaysia':
                case 'HongKong':
                case 'USA': default:
                    counter = 0;
                    login = setInterval(function () {
                        form = $('form[id = signOnForm], form[id = "logInForm"]');
                        browserAPI.log("waiting...");
                        let signOnBtn = $('a.signOnBtn:visible');
                        if (signOnBtn.length > 0) {
                            signOnBtn.get(0).click();
                        }
                        if (form.length > 0) {
                            clearInterval(login);
                            browserAPI.log("submitting saved credentials");
                            form.find('input[name = "username"]').val(params.account.login);
                            form.find('input[name = "userid"]').val(params.account.login);
                            form.find('input[name = "password"]').val(params.account.password);
                            api.setNextStep('checkLoginErrors', function () {
                                if (form.find('input[id = "signInBtn"]').length > 0) {
                                    form.find('input[id = "signInBtn"]').get(0).click();
                                }
                                else
                                window.GM.signon.callSignonFunc();
                            });
                        }
                        if (counter > 10) {
                            clearInterval(login);
                            provider.setError(util.errorMessages.loginFormNotFound);
                        }
                        counter++;
                    }, 500);
                    break;
            }
        },

        loginMexico: function (params) {
            browserAPI.log("loginMexico");
            var form = $('form[name = "preSignonForm2"]');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "password1"]').val(params.account.password);
                form.find('input[name = "password"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    provider.eval('validarUserKey();');
                });
            }
            else
                provider.setError(['Login2 form not found [Code: MX]', util.errorCodes.providerError]);
        },

        isSameAccount: function () {
            switch (params.account.login2) {
                case 'Australia':
                    break;
                case 'Singapore':
                    break;
                case 'Mexico':
                    break;
                case 'Malaysia':
                    break;
                case 'India':
                    break;
                case 'Brazil':
                    break;
                case 'HongKong':
                    break;
                case 'USA': default:
//                    var name = $.trim($('a.username').text());
//                    return ((typeof(params.properties) != 'undefined')
//                        && (typeof(params.properties.Name) != 'undefined')
//                        && (params.properties.Name != '')
//                        && (name.toLowerCase() == params.properties.Name.toLowerCase()));
                    break;
            }
            return false;
        },

        isLoggedIn: function () {
            browserAPI.log("region -> " + params.account.login2);
            switch (params.account.login2) {
                case 'India':
                    return false;
                    break;
                case 'Brazil':
                    var counter = 0;
                    var login = setInterval(function () {
                        browserAPI.log("[isLoggedIn]: waiting...");
                        if ($('form[id = frmLogin]:visible').length > 0) {
                            clearInterval(login);
                            browserAPI.log("not LoggedIn");
                            return false;
                        }
                        if (counter > 10) {
                            clearInterval(login);
                            provider.setError(util.errorMessages.unknownLoginState);
                        }
                        counter++;
                    }, 500);
                    return false;
                    break;
                case 'Mexico':
                    if ($('form[name = "preSignonForm"]').length > 0) {
                        browserAPI.log("not LoggedIn");
                        return false;
                    }
                    // if ($('a#link_lkLogoffWithSummaryRecord')) {
                    //     browserAPI.log("LoggedIn");
                    //     return true;
                    // }
                    break;
                case 'Thailand':
                case 'Taiwan':
                case 'Australia':
                case 'Singapore':
                case 'Malaysia':
                case 'HongKong':
                case 'USA': default:
                    var counter = 0;
                    var login = setInterval(function () {
                        browserAPI.log("[isLoggedIn]: waiting...");
                        if (
                            $('form[id = signOnForm], form[id = "logInForm"]').length > 0
                            || $('a.signOffBtn:visible, a#signOffmainAnchor:visible').length > 0
                        ) {
                            clearInterval(login);
                            return;
                        }
                        if (counter > 10) {
                            clearInterval(login);
                            provider.setError(util.errorMessages.unknownLoginState);
                        }
                        counter++;
                    }, 500);
                    if ($('form[id = signOnForm], form[id = "logInForm"]').length > 0) {
                        clearInterval(login);
                        browserAPI.log("not LoggedIn");
                        return false;
                    }
                    if ($('a.signOffBtn:visible, a#signOffmainAnchor:visible').length > 0) {
                        clearInterval(login);
                        browserAPI.log("LoggedIn");
                        return true;
                    }
                    return false;
                    break;
            }
            provider.setError(util.errorMessages.unknownLoginState);
            throw "can't determine login state";
        },

        logout: function () {
            switch (params.account.login2) {
                case 'Australia':
                    api.setNextStep('LoadLoginForm', function () {
                        document.location.href = 'https://www.citibank.com.au/AUGCB/JSO/signoff/SummaryRecord.do?logOff=true';
                    });
                    break;
                case 'Singapore':
                    api.setNextStep('LoadLoginForm', function () {
                        document.location.href = 'https://mobile.citibank.com.sg/GMP/JSO/signon/signon.action?viewmode=page';
                    });
                    break;
                case 'India':
                    api.setNextStep('LoadLoginForm', function () {
                        document.location.href = 'https://mobile.citibank.co.in/mweb/redirect.do?method=redirect';
                    });
                    break;
                case 'Mexico':
                    api.setNextStep('LoadLoginForm', function () {
                        document.location.href = 'https://bancanet.banamex.com/MXGCB/apps/logout/flow.action?logOutType=manual&source=singleTab';
                    });
                    break;
                case 'Taiwan':
                    document.location.href = 'https://mobile.citibank.com.tw/TWGCB/JSO/signoff/SummaryRecord.do?logOff=true';
                    break;
                case 'Malaysia':
                    api.setNextStep('LoadLoginForm', function () {
                        document.location.href = 'https://mobile.citibank.com.my/GMP/JSO/signon/signon.action?viewmode=page';
                    });
                    break;
                case 'HongKong':
                    api.setNextStep('LoadLoginForm', function () {
                        document.location.href = 'https://mobile.citibank.com.hk/GMP/JSO/signon/signon.action?viewmode=page';
                    });
                    break;
                case 'Brazil':
                    api.setNextStep('LoadLoginForm', function () {
                        $('input[name = "app.headers.hbxPostLoginHeader.btnSignOffevent_"]').get(0).click();
                    });
                    break;
                case 'USA': default:
                    api.setNextStep('LoadLoginForm', function () {
                        let logout = $('input[value = "Sign off"]');
                        if (logout.length == 0)
                            logout = $('a.signOffBtn:visible, a#signOffmainAnchor:visible');
                        logout.get(0).click();
                    });
                    break;
            }
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var error;
            switch (params.account.login2) {
                case 'Australia':
                    error = $('td:contains("The information entered is not recognised")');
                    break;
                case 'Singapore':
                    error = $('div#signonValidationErrordiv');
                    break;
                case 'India':
                    error = $('p:contains("Your login attempt has failed")');
                    break;
                case 'Mexico':
                    error = $("div#errorMsg:visible");
                    if (error.length == 0)
                        error = $('p:contains("Esta información no esta disponible en este momento."):visible');
                    break;
                case 'USA': default:
                    error = $('b:contains("Info not recognized")');
                    if (error.length == 0) {
                        error = $('span#signOnLoginError:visible');
                    }
                break;
            }
            if (error.length > 0 && util.filter(error.text()) != '')
                api.error(util.filter(error.text()));
            else
                this.findSecurityQuestion();
        },

        LoadLoginForm: function (params) {
            api.setNextStep('login', function () {
                window.location.href = plugin.autologin.getStartingUrl(params);
            });
        },

        findSecurityQuestion: function () {
            browserAPI.log("findSecurityQuestion");
            // for debug only
            //if ((typeof(params) != 'undefined'))
            //    browserAPI.log("answers: " + JSON.stringify(params));
            var form = $('form[id = "frmGMMfaChallengeQuestion"]');
            if (form.length > 0) {
                var questions = form.find('div[id *= "frmGMMfaChallengeQuestion_lblQ"]:visible');
                if (questions.length > 0) {
                    var i = 0;
                    questions.each(function () {
                        var question = $(this);
                        browserAPI.log("question #" + i + ": " + question.text());
                        browserAPI.log("question ID: " + question.attr('id'));
                        var inputID = question.attr('id').replace(/.+_lblQ/, '');
                        browserAPI.log("input ID: " + inputID);
                        if ((typeof(params.account.answers) !== 'undefined')
                            && (typeof(params.account.answers[question.text()]) !== 'undefined')
                            && (typeof(inputID) !== 'undefined')) {
                            var answer = params.account.answers[question.text()];
                            //browserAPI.log("answer: " + answer);
                            //browserAPI.log("frmGMMfaChallengeQuestion.txtQ" + inputID + ".text");

                            api.eval('frmGMMfaChallengeQuestion.txtQ' + inputID + '.text = "' + answer + '";');
                        }
                        i++;
                    });
                    api.setNextStep('finish', function () {
                        //browserAPI.log("Click");
                        form.find('input[id = "frmGMMfaChallengeQuestion_btnQuesSubmit"]').click();
                    });
                }
                else {
                    browserAPI.log("Security Questions are not found");
                    this.finish();
                }
            }
            else {
                browserAPI.log("Security Question form not found");
                this.finish();
            }
        },

        finish: function () {
            browserAPI.log("finish");
            api.complete();
        }

    }
};