var plugin = {
    flightStatus:{
        url: 'https://mobile.airfrance.fr/FR/en/local/resainfovol/infovols/actualiteDesVols.do',
        match: /^(?:AF)?\d+/i,

        start: function () {
            $('div:contains("By flight")').click();
            var input = $('#idNumvol');
            var button = $('#idValidateButton');
            if (input.length == 1 && button.length == 1){
                input.val(params.flightNumber.replace(/AF/gi, ''));
                //$('#departDate').val($.format.date(api.getDepDate(), 'yyyy-MM-dd'));
                api.setNextStep('finish', function(){
                    button.click()
                });
            }
        },

        finish: function () {
            var error = $('div#errorList > span');
            if (error.length > 0 && error.text() != "")
                api.error(error.text().trim());
            else
                api.complete();
        }
    },

    autologin: {

        url: "https://mobile.airfrance.fr/FR/en/local/myafb/profile.do",

        /*start: function () {
            browserAPI.log("start");
            var start = setInterval(function () {
                var btn = $('a:contains("Log in")');
                if ($('form#loginForm').length > 0 || $('a:contains("Log out")').length > 0 || btn.length > 0) {
                    if (btn.length > 0) {
                        api.setNextStep('start2', function(){
                            btn.get(0).click();
                        });
                    }
                    clearInterval(start);
                    plugin.autologin.start2();
                }
            }, 500);
        },
*/        start: function () {
            browserAPI.log("start");
            if (this.isLoggedIn())
                if (this.isSameAccount())
                    this.finish();
                else
                    this.logout();
            else
                this.login();
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('a:contains("Log in")').length > 0 || $('form#loginForm').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            if ($('a:contains("Log out")').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            browserAPI.log("Can't determine login state");
            api.error("Can't determine login state");
            throw "can't determine login state";
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            return (typeof(params.properties) != 'undefined' &&
                typeof(params.properties.AccountNumber) != 'undefined' &&
                params.properties.AccountNumber != '' &&
                $('div:contains("' + params.properties.AccountNumber + '")').length > 0);
        },

        login: function () {
            browserAPI.log("login");
            var form = $('form#loginForm');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "login"]').val(params.login);
                form.find('input[name = "password"]').val(params.pass);

                api.eval('AF.callLoginWebservice('+params.login+','+params.pass+');');
                $('#loginBtnValidate').get(0).click();
                var counter = 0;
                var login = setInterval(function () {
                    browserAPI.log("waiting...");
                    if ($('a:contains("Log out")').length > 0) {
                        clearInterval(login);
                    }
                    if (counter > 10) {
                        browserAPI.log("timeout...");
                        clearInterval(login);
                        plugin.autologin.checkLoginErrors();
                    }
                    counter++;
                }, 500);
            }else{
                api.error("can't find login form");
            }
        },

        logout: function () {
            browserAPI.log("logout");
            api.setNextStep('toLoginPage', function () {
                $('a:contains("Log out")').get(0).click();
            });
        },

        toLoginPage: function (){
            browserAPI.log("toLoginPage");
            api.setNextStep('login', function () {
                document.location.href = plugin.autologin.url;
            });
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var error = $('div.onError');
            if (error.length == 0)
                error = $('div#errorList > span');
            if (error.length > 0 && error.text() != "")
                api.error(error.text().trim());
            else
                this.finish();
        },

        finish: function () {
            browserAPI.log("finish");
            api.complete();
        }
    }
};