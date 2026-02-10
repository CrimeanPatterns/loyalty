var plugin = {
    flightStatus:{
        fakeflightStatus: true,
        url: 'https://awardwallet.com/contact.php',
        match: /.*/i,

        start: function(params){
            var d = new Date();
            d.setTime(d.getTime()+(3*60*1000));
            var expires = "expires="+d.toGMTString();
            document.cookie = "PwdHash" + "=" + params.session + "; " + expires;
            document.cookie = "SavePwd" + "=1; " + expires;
            api.setNextStep('finish', function(){
                location.reload();
            });
        },

        finish: function(){
            api.complete();
        }
    }
};