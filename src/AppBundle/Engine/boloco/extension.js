var plugin = {


    hosts: {'boloco.com': true},

    getStartingUrl: function(){
        return 'http://boloco.com/keep-up/the-card/';
    },

    start: function(params){
        browserAPI.log("login");
        var form = $('form[id = "checkYrBalanceForm"]');
        if(form.length > 0){
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "cardnum"]').val(params.account.login);
            form.find('input[name = "regcode"]').val(params.account.password);

            if($('form[id = checkYrBalanceForm] :button').length > 0){
                $('form[id = checkYrBalanceForm] :button').click();
            }
        }
        else
            provider.setError('Login form not found');
    }
}