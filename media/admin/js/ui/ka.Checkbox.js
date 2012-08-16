ka.Checkbox = new Class({

    Implements: [Events],

    initialize: function(pContainer){

        this.box = new Element('div', {
            'class': 'ka-Checkbox ka-Checkbox-off'
        });

        new Element('div', {
            text: 'l',
            'class': 'ka-Checkbox-text-on'
        }).inject(this.box);

        new Element('div', {
            text: 'O',
            'class': 'ka-Checkbox-text-off'
        }).inject(this.box);

        var knob = new Element('div', {
            'class': 'ka-Checkbox-knob'
        }).inject(this.box);

        this.value = false;

        knob.addEvent('click', function () {
            this.setValue(this.value == false ? true : false);
            this.fireEvent('change');
        }.bind(this));


        if (pContainer)
            this.box.inject(pContainer);
    },

    toElement: function(){
        return this.box;
    },

    getValue: function () {
        return this.value == false ? false : true;
    },

    setValue:function (p) {
        if (typeOf(p) == 'null') p = false;
        if (p == 0 || p == "0") p = false;
        if (p == 1) p = true;
        this.value = p;
        if (this.value == 1) {
            this.box.addClass('ka-Checkbox-on');
            this.box.removeClass('ka-Checkbox-off');
        } else {
            this.box.addClass('ka-Checkbox-off');
            this.box.removeClass('ka-Checkbox-on');
        }
    }

});