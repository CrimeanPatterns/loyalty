/**
 * @param {AbstractFormExtension} extension
 */
function addFormExtension(extension) {

    /**
     * will be called on field change
     * @param {FormInterface} form
     * @param {string} fieldName in lower case
     */
    extension.onFieldChange = function (form, fieldName) {
        if (fieldName == 'login2') {
            var visible = ['Saudi Arabia', 'ישראל'].indexOf(form.getValue('login2')) !== -1;
            form.showField("login3", visible);
            form.requireField("login3", visible);
            if (form.getValue('login2') == 'ישראל') {
                form.setFieldCaption("login", "Passport #");
                form.setFieldCaption("login3", "Last 6 digits on your card");
            }
            else {
                form.setFieldCaption("login", "User ID");
                form.setFieldCaption("login3", "Last 4 digits on your card");
            }
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

