var plugin = {
    flightStatus: {
        url: 'about:blank',
        match: /^(?:SU ?)?\d+/i,

        start: function () {
            var formatDDMMYYYY = function(d){
                var date = new Date(d), month = date.getMonth()+1;
                return [date.getFullYear(),month<=9?'0'+month:month,date.getDate()<=9?'0'+date.getDate():date.getDate()].join('');
            };
            var depDate = params.depDate;
            api.setNextStep('checkErrors', function(){
                document.location.href = 'http://onlineboard.aeroflot.ru/m/en#/detail/'+params.flightNumber.replace(/([\d\s])/g, "")+'/'+params.flightNumber.replace(/([^\d])/g, "")+'/'+formatDDMMYYYY(depDate.ts * 1000);
            });
        },

        checkErrors: function () {
            var counter = 0;
            var intervalId = setInterval(function(){
                if($('.header:eq(1):contains("'+params.flightNumber.replace(/([^\d])/g, "")+'")').length > 0){
                    clearInterval(intervalId);
                    api.complete();
                }
                if(counter > 20){
                    clearInterval(intervalId);
                    api.error();
                }
                counter++;
            }, 500);
        }
    },

    autologin: {

        url: 'https://www.aeroflot.ru/personal/pda',

        start: function() {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                var isLoggedIn = plugin.autologin.isLoggedIn();
                if (isLoggedIn !== null) {
                    clearInterval(start);
                    if (isLoggedIn) {
                        if (plugin.autologin.isSameAccount())
                            plugin.autologin.finish();
                        else
                            plugin.autologin.logout();
                    }
                    else
                        plugin.autologin.login();
                }// if (isLoggedIn !== null)
                if (isLoggedIn === null && counter > 10) {
                    clearInterval(start);
                    provider.setError(util.errorMessages.unknownLoginState);
                    return;
                }// if (isLoggedIn === null && counter > 10)
                counter++;
            }, 500);
        },

        login: function () {
            browserAPI.log("login");
            var form = $('form[id = form]');
            if (form.length > 0) {
                setTimeout(function () {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "login"]').val(params.account.login);
                    form.find('input[name = "password"]').val(params.account.password);

                    var submitButton = form.find('input[name = "submit0"]');
                    if ($('div.g-recaptcha:visible').length > 0) {
                        provider.reCaptchaMessage();
                        var events = submitButton.data("events");
                        var originalFn = events[0];
                        submitButton.unbind('click');
                        submitButton.bind('click', function (event) {
                            provider.setNextStep('checkLoginErrors', function(){
                                browserAPI.log("captcha entered by user");
                                submitButton.unbind('click');
                                submitButton.bind('click', originalFn);
                            });
                            event.preventDefault();
                        });
                    }// if ($('div.g-recaptcha:visible').length > 0)
                    else {
                        api.setNextStep('checkLoginErrors', function(){
                            submitButton.click();
                        });
                    }
                }, 2000)
            }// if (form.length > 0)
            else {
                form = $('form.login__form:visible');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    // reactjs
                    provider.eval(
                        "function triggerInput(selector, enteredValue) {\n" +
                        "      let input = document.querySelector(selector);\n" +
                        "      input.dispatchEvent(new Event('focus'));\n" +
                        "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
                        "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
                        "      nativeInputValueSetter.call(input, enteredValue);\n" +
                        "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
                        "      input.dispatchEvent(inputEvent);\n" +
                        "}\n" +
                        "triggerInput('input[placeholder=\"Aeroflot Bonus number, email or phone number\"]', '" + params.account.login + "');\n" +
                        "triggerInput('input[placeholder=\"Password\"]', '" + params.account.password + "');"
                    );
                    provider.setNextStep('checkLoginErrors', function() {
                        form.find('button[type = "submit"]').click();
                        setTimeout(function() {
                            plugin.checkLoginErrors(params);
                        }, 7000);
                    });
                }
                else {
                    provider.setError(util.errorMessages.loginFormNotFound);
                }
            }
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            return (typeof(params.properties) !== 'undefined')
                && (typeof(params.properties.Number) !== 'undefined')
                && ($('u:contains("#' + params.properties.Number + '")').length > 0);
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('form[id = "form"], form.login__form:visible').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            if ($('a[href *= "logout"]:visible, form#id_mobilesmssubscribeform:visible').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            return null;
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var error = $('div.error');
            if (error.length > 0) {
                // retries
                if ($('p:contains("confirm that you are not a robot"):visible, p:contains("Пожалуйста, подтвердите, что вы не робот"):visible').length > 0) {
                    this.login();
                    return;
                }
                api.error(error.text());
            } else {
                this.finish();
            }
        },

        logout: function () {
            browserAPI.log("logout");
            api.setNextStep('start', function () {
                var logout = $('a[href *= "logout"]:visible');
                if (logout.length > 0)
                    logout.get(0).click();
                else
                    document.location.href = 'https://www.aeroflot.ru/personal/pda/logout';
            });
        },

        finish: function () {
            browserAPI.log("finish");
            api.complete();
        }
    }
};