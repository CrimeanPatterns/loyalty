var plugin = {

    flightStatus: {
        url: 'http://mobile.aircanada.com/portal-web/mobile/fs',
        match: /^(?:AC)?\d+/i,

        start: function () {
            var input = document.getElementById('flightNumber');
            var form = document.getElementById('flightStatusSearch');
            if (input !== null && form !== null){
                input.value = params.flightNumber.replace(/AC/gi, '');

                var depDateElem = $('option[value*="' + $.format.date(api.getDepDate(), 'MMMM d, yyyy') + '"]');
                if(depDateElem.length == 1){
                    $('#date').val(depDateElem.val());
                    api.setNextStep('finish', function () {
                        document.getElementsByName('_eventId_interactiveSearch')[0].click();
                    });
                }else{
                    api.errorDate();
                }
            }
        },

        finish: function () {
            if($('.flightNumber').length > 0){
                api.complete();
            }else{
                var error = $('#error').text();
                if(error)
                    error = error.trim();
                api.error(error);
            }
        }
    },

    autologin: {

        url: 'https://www.aeroplan.com/mobile/YourAccountCard.do',

        start: function() {
            browserAPI.log('start');
            if (this.isLoggedIn()) {
                browserAPI.log('isLoggedIn = true');
                if (this.isSameAccount()) {
                    browserAPI.log('isSameAccount = true');
                    this.loginComplete();
                } else {
                    browserAPI.log('isSameAccount = false');
                    this.logout();
                }
            } else {
                browserAPI.log('isLoggedIn = false');
                this.login();
            }
        },

        login: function () {
            browserAPI.log('login');
            var form = $('form[name = loginForm]');
            if (form.length > 0) {
                var login = params.account.login.replace(/\D/g, "");
                form.find('input[name = "CUST1"]').val(login.substring(0, 3));
                form.find('input[name = "CUST2"]').val(login.substring(3, 6));
                form.find('input[name = "CUST3"]').val(login.substring(6, 9));
                form.find('input[name = "pin"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function() {
                    form.submit();
                });
            }
        },

        isSameAccount: function () {
            browserAPI.log('isSameAccount');
            var loginAw = params.account.login.replace(/\D/g, "");
            var loginProvider = $('td[style = "text-align:center;padding-bottom:10px;"]').text();
            return (
                loginAw == loginProvider
            );
        },

        isLoggedIn: function () {
            browserAPI.log('isLoggedIn');
            if ($('form[name = loginForm]').length > 0) {
                return false;
            }
            if ($('div[class = "loginBtn"][onclick *= "Login"]').length > 0) {
                return false;
            }

            if ($('div[class = "loginBtn"][onclick *= "Logout"]').length > 0) {
                return true;
            }
            if ($('#accountSelTable').length > 0) {
                return true;
            }

            provider.setError(util.errorMessages.unknownLoginState);
        },

        checkLoginErrors: function () {
            browserAPI.log('checkLoginErrors');
            var error = $('td[class = "errorIcon"] + td[class = "message"]');

            if (error.length > 0) {
                provider.error(error.text());
            } else {
                this.loginComplete();
            }
        },

        logout: function () {
            browserAPI.log('logout');
            provider.setNextStep('toLoginPage', function () {
                $('div[class = "loginBtn"][onclick *= "Logout"]').click();
            });
        },

        toLoginPage: function () {
            browserAPI.log('toLoginPage');
            provider.setNextStep('login', function() {
                document.location.href = this.url;
            });
        },

        loginComplete: function () {
            browserAPI.log('loginComplete');
            provider.complete();
        }
    }
};
