var plugin = {
    flightStatus:{
        url: 'http://mobile.usablenet.com/mt/www.britishairways.com/rtad/travel/public/en_gb',
        match: /^(?:BA)?\d+/i,

        start: function () {
            var input = document.getElementById('flightNumber');
            var form = document.getElementById('byFlight');
            if (input !== null && form !== null){
                input.value = params.flightNumber.replace(/BA/i, '');

                var dateInput = $('#date');
                var depDateElem = dateInput.find('option[value*="' + $.format.date(api.getDepDate(), 'yyyy-MM-dd') + '"]');
                if(depDateElem.length == 1){
                    dateInput.val(depDateElem.val());
                    api.setNextStep('finish', function () {
                        form.submit();
                    });
                }else{
                    api.errorDate();
                }
            }
        },

        finish: function () {
            if($('.flight-status-pod').length > 0){
                api.complete();
            }else{
                api.error($('.errorList').text());
            }
        }
    },
    autologin: {

        getStartingUrl: function (params) {
            var country = 'US';
            if (typeof(params.login2) !== 'undefined' && params.login2)
                country = params.login2.toUpperCase();
            return 'https://www.britishairways.com/travel/home/public/' + country + '/device-mobile';
        },

        start: function(){
            api.setNextStep('start2', function(){
                var country = 'US';
                if (typeof(params.login2) !== 'undefined' && params.login2)
                    country = params.login2.toUpperCase();
                document.location.href = 'https://www.britishairways.com/travel/loginr/public/' + country;
            });
        },

        start2: function(){
            browserAPI.log("start2");
            if (this.isLoggedIn())
                if (this.isSameAccount())
                    this.finish();
                else
                    this.logout();
            else
                this.login();
        },

        login: function () {
            browserAPI.log("login");
            var form = $('form#execLoginrForm');
            if (form.length === 1) {
                form.find('input[name = "membershipNumber"]').val(params.login);
                form.find('input[name = "password"]').val(params.pass);
                api.setNextStep('checkLoginErrors', function(){
                    form.find('button#ecuserlogbutton').click();
                });
            } else {
                api.error("can't find login form");
            }
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            return (typeof(params.properties) !== 'undefined' &&
                typeof(params.properties.Number) !== 'undefined' &&
                params.properties.Number != '' &&
                $('div.detailsStyle:contains("'+ params.properties.Number +'")').length > 0);
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('form#execLoginrForm').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            if ($('a:contains("Log out")').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            api.error("can't determine login state");
            return false;
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var error = $('#blsErrosContent:visible li');
            if (error.length > 0 && util.trim(error.text()) !== '') {
                api.error(error.text());
            } else {
                api.complete();
            }
        },

        logout: function () {
            browserAPI.log("logout");
            api.setNextStep('start', function () {
                document.location.href = $('a:contains("Log out")').attr('href');
            });
        },

        finish: function () {
            browserAPI.log("complete");
            api.complete();
        }
    }
};