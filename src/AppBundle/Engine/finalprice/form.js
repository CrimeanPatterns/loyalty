/**
 * @param {AbstractFormExtension} extension
 */
function addFormExtension(extension){

    /**
     * will be called on field change
     * @param {FormInterface} form
     * @param {string} fieldName in lower case
     */
    extension.onFieldChange = function (form, fieldName){
        if (fieldName == 'login2') {
            var visible = ['cell_number'].indexOf(form.getValue('login2')) !== -1;
            form.showField("login3", visible);
            form.requireField("login3", visible);
        }
    };

    /**
     * will be called when form loaded and ready
     * @param {FormInterface} form
     * @param {string} fieldName in lower case
     */
    extension.onFormReady = function(form, fieldName){
        this.onFieldChange(form, 'login2');
    };

}

