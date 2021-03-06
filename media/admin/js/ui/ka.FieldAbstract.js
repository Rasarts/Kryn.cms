ka.FieldAbstract = new Class({
    
    Implements: [Options, Events],


    /**
     * Here you can define some default options.
     *
     * @type {Object}
     */
    options: {

    },


    /**
     * The reference to the current (parent) ka.Field instance.
     *
     * @type {ka.Field}
     */
    fieldInstance: null,


    /**
     * The reference to the current ka.Window instance.
     * Can be empty, if a script created a ka.Field without container inside of a ka.Window.
     * @type {ka.Window}
     */
    win: null,


    /**
     * Constructor.
     *
     * @internal
     * @param  {Object} pFieldObject The instance of ka.Field
     * @param  {Object} pOptions
     */
    initialize: function(pFieldInstance, pOptions){
        this.fieldInstance = pFieldInstance;
        this.win = this.fieldInstance.win;
        this.setOptions(pOptions);
        this.createLayout();
    },


    /**
     * Use this method to create your field layout.
     * Please do not the constructor for this job.
     *
     * Inject your elements to this.fieldInstance.fieldPanel.
     */
    createLayout: function(){
        /* Override it to your needs */
    },


    /**
     * This method is called, when the option 'disabled' is true and there this field
     * should act as a disabled one.
     *
     * @param {Boolean} pDisabled
     */
    setDisabled: function(pDisabled){
        /* Override it to your needs */
    },


    /**
     * Renders the UI with the new value.
     * Do not call this function in your code.
     *
     * If you UI element received a value change,
     * call it the ka.Field instance with this.fieldInstance.fireChange().
     *
     * @param {Mixed} pValue
     */
    setValue: function(pValue){
        /* Override it to your needs */
    },


    /**
     * Returns the current value of this field.
     *
     * @return {Mixed}
     */
    getValue: function(){
        /* Override it to your needs */
        return null;
    },

    /**
     * Returns the main element of this field.
     * This is not necessary.
     */
    toElement: function(){
        return this.main;
    },

    /**
     * If a field is empty but required and the user wanna save,
     * then the frameworkWindows use this method to say the user 'hey it\'s required'.
     *
     * Take a look into the code, to get a idea behind.
     *
     */
    highlight: function(){

        //example of using highlight
        //this calls toElement() and highlight the background of it.
        if (document.id(this))
            document.id(this).highlight();

        //or
        //document.id(this.input).highlight();

    },

    /**
     * Checks if the value the user entered(or not entered)
     * is a valid one. If it's not valid, then for example the
     * window framesworks won't continue the saving and fire this.highlight()
     * and this.showNotValid(true).
     *
     * @return {Boolean}
     */
    isValid: function(){

        if (this.fieldInstance.options.required && this.getValue() === '')
            return false;

        if (this.fieldInstance.options.requiredRegex){
            var rx = new RegExp(this.fieldInstance.options.requiredRegex);
            if (!rx.test(this.getValue().toString())){
                return false;
            }
        }

        return true;
    },

    /**
     * If the entered data is no valid, this will be fired. (possible with each 'change' event)
     *
     * @param  {String} pText text to display
     */
    showNotValid: function(pText){

        if (this.invalidIcon) return; //we're already displaying invalid stuff

        this.invalidIcon = new Element('div', {
            'class': 'ka-Field-invalid-icon icon-warning blink'
        }).inject(this.wrapper || this.input, 'after');

        var text = pText || this.options.notValidText || t('The current value is invalid.');

        this.invalidText = new Element('div', {
            'class': 'ka-Field-invalid-text',
            text: text
        }).inject(this.wrapper || this.input, 'after');

        if (this.input && this.input.get('tag') == 'input')
            this.input.addClass('ka-Input-invalid');

    },

    /**
     * If the entered data is valid, this will be fired. (possible with each 'change' event)
     *
     */
    showValid: function(){

        if (this.invalidIcon){
            //we was invalid before, highlight a smooth green
            this.input.highlight('green');

            if (this.input && this.input.get('tag') == 'input')
                this.input.removeClass('ka-Input-invalid');

            //remove the invalid icon and text
            this.invalidIcon.destroy();
            this.invalidText.destroy();
        }

    }

});