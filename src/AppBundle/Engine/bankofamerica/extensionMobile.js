var plugin = {

    clearCache: true,

    autologin: {

        // Bankamericard
        url: "https://staticweb.bankofamerica.com/cavmwebbactouch/common/index.html#home?app=signon",

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + start);
                if ($('form[id = "frmCustomOnlineId"]').length > 0 || $('input[name = "btnAccountLogout"]').length > 0) {
                    clearInterval(start);
                    plugin.autologin.start2();
                }
                if (counter > 10) {
                    clearInterval(start);
                    api.error("Can't determine state");
                }
                counter++;
            }, 500);
        },

        start2: function () {
            browserAPI.log("start2");
            if (this.isLoggedIn()) {
                if (this.isSameAccount())
                    this.finish();
                else
                    this.logout();
            }
            else
                this.login();
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('input[name = "btnAccountLogout"]').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form[id = "frmCustomOnlineId"]').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            browserAPI.log("Can't determine login state");
            api.error("Can't determine login state");
            throw "can't determine login state";
        },

        login: function () {
            browserAPI.log("login");
            var counter = 0;
            var login = setInterval(function () {
                var form = $('form[id = "frmCustomOnlineId"]');
                browserAPI.log("waiting... " + login);
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[id = "btCustomOnlineId"]').val(params.login);
                    // todo
                    form.find('input[id = "btCustomOnlineId"]').val('1234');

                    clearInterval(login);

                    api.setNextStep('enteringPassword', function () {
                        setTimeout(function() {
                            browserAPI.log("clicking...");
                            //api.eval("$('a#btCustomOnlineIdContinue').get(0).click()");
                            //api.eval("$('#customOnlineIdSubmit').get(0).click()");
                            //document.getElementById('customOnlineIdSubmit').click();

                            form.find('input[id = "customOnlineIdSubmit"]').click();
                            //form.find('input[id = "customOnlineIdSubmit"]').get(0).click();

                            setTimeout(function() {
                                plugin.autologin.checkLoginErrors();
                            }, 5000)
                        }, 2000)
                    });
                }
                if (counter > 10) {
                    clearInterval(login);
                    browserAPI.log("can't find login form");
                    api.error("can't find login form");
                }
                counter++;
            }, 500);
        },

        enteringPassword: function () {
            browserAPI.log("enteringPassword");
            var counter = 0;
            var enteringPassword = setInterval(function () {
                var form = $('form[id = "frmCustomOnlineId"]');
                var nextButton = $('a[id = "continueChallenge"]');
                browserAPI.log("waiting... " + enteringPassword);
                if (form.length > 0 && nextButton.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "efPin"]').val(params.pass);

                    clearInterval(enteringPassword);

                    api.setNextStep('checkLoginErrors', function () {
                        //$('a[id = "continueChallenge"]').get(0).click();
                    });
                }
                if (counter > 10) {
                    clearInterval(enteringPassword);
                    browserAPI.log("can't find password form");
                    if ($('input[id = "answer"]').length > 0)
                        plugin.autologin.checkLoginErrors();
                    else
                        api.error("can't find password form");
                }
                counter++;
            }, 500);
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            return false;
            //return (typeof(params.properties) !== 'undefined')
            //    && (typeof(params.properties.Number) !== 'undefined')
            //    && ($('span:contains("' + params.properties.Number + '")').length > 0);
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var counter = 0;
            var checkLoginErrors = setInterval(function () {
                browserAPI.log("waiting... " + checkLoginErrors);
                var error = $('div.messaging:visible');
                if (error.length > 0) {
                    clearInterval(checkLoginErrors);
                    api.error(error.text().trim());
                }
                if (counter > 3) {
                    clearInterval(checkLoginErrors);
                    plugin.autologin.finish();
                }
                counter++;
            }, 500);
        },

        logout: function () {
            browserAPI.log("logout");
            api.setNextStep('start', function () {
                $('input[name = "btnAccountLogout"]').get(0).click();
            });
        },

        finish: function () {
            browserAPI.log("finish");
            api.complete();
        }
    }
};