var plugin = {

    autologin: {

        cashbackLink : '', // Dynamically filled by extension controller
        startFromCashback : function(params) {
            browserAPI.log('startFromCashback');
            api.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        },

        getStartingUrl: function(params) {
            return "https://www.agoda.com/signin/email";
        },

        loadLoginForm: function (params) {
            api.setNextStep('start', function () {
                // var menu = $('[class*="Button__baseButton"][data-action="openMenu"]');
                // if (menu.length) {
                //     menu.get(0).click();
                    setTimeout(function () {
                        var menu = $('a[data-action="signIn"]');
                        if (menu.length) {
                            menu.get(0).click();
                            setTimeout(function () {
                                plugin.autologin.start(params);
                            }, 1000);
                        }
                    }, 1000);
                //}
                //document.location.href = plugin.getStartingUrl(params);
            });
        },

        start: function (params) {
            browserAPI.log('start');
            setTimeout(function () {
                if (plugin.autologin.isLoggedIn()) {
                    if (plugin.autologin.isSameAccount())
                        api.complete();
                    else
                        plugin.autologin.logout(params);
                }
                else
                    plugin.autologin.login(params);
            }, 2000);
        },

        isLoggedIn: function () {
            browserAPI.log('isLoggedIn');
            if ($('form:has(input[name = "email"])').length > 0) {
                browserAPI.log('isLoggedIn = false');
                return false;
            }
            if ($('span#logout').length || $('button[class*="Button__baseButton--"]:contains("Sign out")').length) {
                browserAPI.log('isLoggedIn = true');
                return true;
            }
            provider.setError(util.errorMessages.unknownLoginState);
        },

        login: function (params) {
            browserAPI.log('login');
            var menu = $('#hamburger-menu');
            if (menu.length > 0) {
                menu.get(0).click();
            }
            setTimeout(function () {
                var form = $('form:has(input[name = "email"])');
                var btn = $('button[data-action="signIn"]');
                if (form.length === 1 && btn.length === 1) {
                    browserAPI.log('Submitting saved credentials');
                    // form.find('input#email').val(params.login);
                    // form.find('input#password').val(params.pass);
                    // reactjs
                    provider.eval(
                        "var FindReact = function (dom) {" +
                        "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                        "        return dom[key];" +
                        "    }" +
                        "    return null;" +
                        "};" +
                        "FindReact(document.querySelector('#email')).onChange({target:{value:'" + params.account.login + "', name:'email'}});"+
                        "FindReact(document.querySelector('#password')).onChange({target:{value:'" + params.account.password + "', name:'password'}});"
                    );
                    provider.eval(
                        "function doEvent( obj, event ) {"
                        + "var event = new Event( event, {target: obj, bubbles: true} );"
                        + "return obj ? obj.dispatchEvent(event) : false;"
                        + "};"
                        //+ "var el = document.querySelector('#email'); el.value = \"" + params.account.login + "\"; doEvent(el, 'input' );"
                        + "var el = document.querySelector('#password'); el.value = \"" + params.account.password + "\"; doEvent(el, 'input' );"
                    );

                    api.setNextStep('checkLoginErrors', function () {
                        btn.click();
                        setTimeout(function () {
                            if (document.querySelector('div[class *= "Captcha__captchaErrorMessage"]'))
                                provider.reCaptchaMessage();
                            waiting();
                        }, 3000);
                    });
                    function waiting() {
                        browserAPI.log("waiting...");
                        var counter = 0;
                        var login = setInterval(function () {
                            browserAPI.log("waiting... " + counter);
                            if (!document.querySelector('div[class *= "Captcha__captchaErrorMessage"]')) {
                                clearInterval(login);
                                btn.click();
                                setTimeout(function () {
                                    plugin.autologin.checkLoginErrors();
                                }, 3000);
                            }
                            if (counter > 120) {
                                clearInterval(login);
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }
                            counter++;
                        }, 1000);
                    }
                } else {
                    provider.setError(util.errorMessages.loginFormNotFound);
                }
            }, 0);
        },

        isSameAccount: function () {
            browserAPI.log('isSameAccount');
            return false;
        },

        checkLoginErrors: function () {
            browserAPI.log('checkLoginErrors');
            var error = $('.dg-NavbarMenu-validationMessage:visible');
            if(error.length > 0){
                provider.setError(error.text().trim());
            } else {
                // var menu = $('#hamburger-menu');
                // if (menu.length > 0) {
                //     menu.get(0).click();
                // }
                plugin.autologin.finish();
            }
        },

        logout: function (params) {
            browserAPI.log('logout');
            var jq = document.createElement('script');
            jq.src = "https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js";
            document.getElementsByTagName('head')[0].appendChild(jq);
            setTimeout(function () {
                api.setNextStep('loadLoginForm', function () {
                    var menu = $('button[class*="Button__baseButton"][data-action="openMenu"]');
                    if (menu.length) {
                        menu.get(0).click();
                        setTimeout(function () {
                            menu = $('button[class*="Button__baseButton"][data-action="signout"]');
                            if (menu.length) {
                                menu.get(0).click();
                                setTimeout(function () {
                                    plugin.autologin.loadLoginForm(params);
                                }, 2000);
                            }
                        }, 1000);
                    }
                });
            }, 3000);
        },

        finish: function () {
            browserAPI.log('finish');
            api.complete();
        }
    }
};