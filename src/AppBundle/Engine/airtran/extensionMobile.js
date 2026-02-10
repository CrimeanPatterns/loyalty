var plugin = {
    flightStatus:{
    url: 'http://www.airtran.com/flight-schedules/flight-info.aspx',
    match: /^(?:TS)?\d+/i,

    start: function () {
        var input = $('#txtFlight');
       	var form = $('#FLform');
       	if (input.length == 1 && form.length == 1){
       		input.val(params.flightNumber.replace(/TS/gi, ''));

            var depDateElem = $('option[value*="' + $.format.date(api.getDepDate(), 'yyyy-MM-dd') + '"]');
            if(depDateElem.length == 1){
                $('#ddlDate').val(depDateElem.val());
                api.setNextStep('finish', function(){
                    form.submit();
                });
            }else{
                api.errorDate();
            }
       	}
    },

    finish: function () {
        if($('#lblFlightInfo').length > 0){
            api.complete();
        }
        else{
            api.error($('div#pnlError2 > p').eq(0).text().trim());
        }
    }
    }
};