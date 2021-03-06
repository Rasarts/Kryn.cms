/**
 *
 * ka.Parse - builds forms from ka.Field definitions.
 *
 * This class handles also the visibility of the children container and
 * the 'needValue' and 'againstField' property.
 *
 * @type {Class}
 */

ka.Parse = new Class({

    Implements: [Events, Options],

    Binds: ['fireChange'],

    fields: {},

    options: {
        allTableItems: false,
        allSmall: false,
        tableitem_title_width: false,
        returnDefault: false,
        tabsInWindowHeader: false
    },

    /**
     * Constructor
     * @param {Element} pContainer
     * @param {Object} pDefinition
     * @param {Object} pOptions
     * @param {Object} pRefs
     */
    initialize: function (pContainer, pDefinition, pOptions, pRefs) {
        var self = this;

        this.mainContainer = pContainer;

        this.setOptions(pOptions);
        this.refs = pRefs;
        this.main = pContainer;
        this.definition = pDefinition;

        this.parseLevel(pDefinition, this.main);

        //parse all fields which have 'againstField'
        Object.each(this.fields, function(obj, id){

            obj.addEvent('change', this.fireChange);

            if(obj.field.againstField){
                if (typeOf(obj.field.againstField) == 'array'){

                    var check = function(){

                        var visible = false;
                        Array.each(obj.field.againstField, function(fieldKey){
                            if(self.getVisibility(self.fields[fieldKey], obj))
                                visible = true;
                        });

                        if (visible) obj.show(); else obj.hide();

                        self.updateChildrenContainerVisibility(this);
                    };

                    Array.each(obj.field.againstField, function(fieldKey){
                        this.fields[fieldKey].addEvent('check-depends', check);
                    }.bind(this));

                    check();

                    Array.each(obj.field.againstField, function(fieldKey){
                        this.updateChildrenContainerVisibility(this.fields[fieldKey]);
                    }.bind(this));

                } else {
                    if (this.fields[obj.field.againstField]){
                        this.fields[obj.field.againstField].addEvent('check-depends', function(){
                            this.updateVisibility(this.fields[obj.field.againstField], obj);
                            if (obj.hasParent())
                                self.updateChildrenContainerVisibility(obj.getParent());
                        }.bind(this));
                        this.fields[obj.field.againstField].fireEvent('check-depends');
                    } else {
                        logger('ka.Field "againstField" does not exist: '+obj.field.againstField);
                    }
                }
            }
        }.bind(this));
    },

    /**
     * Returns all field instances from type 'tab'.
     * @return {Object}
     */
    getTabButtons: function(){

        var res = {};

        Object.each(this.definition, function(item, key){

            if (item.type == 'tab'){
                res[key] = this.fields[key];
            }

        }.bind(this));

        return res;
    },

    /**
     * Returns the main DOM element.
     *
     * @return {Element}
     */
    toElement: function () {
        return this.main;
    },

    /**
     * Fires a change event.
     */
    fireChange: function(){
        this.fireEvent('change');
    },

    /**
     * Goes through every level and serach for ka.Field definitions.
     * Parse it, creates the ka.Field and put'm to this.fields[].
     *
     * @param pLevel
     * @param pContainer
     * @param pDependField
     */
    parseLevel: function (pLevel, pContainer, pDependField) {
        var self = this;

        if (pDependField && !pDependField.children) pDependField.children = {};

        Object.each(pLevel, function (field, id) {

            var obj;

            //json to objects
            Object.each(field, function(item,itemId){
                if(typeOf(item) != 'string') return;
                var newItem = false;

                try {

                    //check if json array
                    if (item.substr(0,1) == '[' && item.substr(item.length-1) == ']'&&
                        item.substr(0,2) != '[[' && item.substr(item.length-2) != ']]')
                        newItem = JSON.decode(item);

                    //check if json object
                    if (item.substr(0,1) == '{' && item.substr(item.length-1,1) == '}')
                        newItem = JSON.decode(item);

                } catch(e){}

                if (newItem)
                    field[itemId] = newItem;

            });

            if (this.options.allTableItems && field.type != 'tab')
                field.tableItem = 1;

            if (this.options.allSmall && field.type != 'tab')
                field.small = 1;

            if (this.options.tableitem_title_width)
                field.tableitem_title_width = this.options.tableitem_title_width;

            var target = pContainer.getElement('*[id=' + field.target + ']') ||
                         pContainer.getElement('*[id=' + id + ']') ||
                         pContainer.getElement('*[id=__default__]');

            if (!target)
                target = pContainer;

            if (field.children)
                field.depends = field.children;
            
            if( field.type == 'tab'){
                var tab;

                if (!pDependField && !this.firstLevelTabBar){
                    if (this.options.tabsInWindowHeader){
                        this.firstLevelTabBar = new ka.TabPane(target, true, this.refs.win);
                    } else {
                        this.firstLevelTabBar = new ka.TabPane(target, field.tabFullPage?true:false);
                    }
                } else if(pDependField){
                    //this tabPane is not on first level
                    if (!target.tabPane)
                        target.tabPane = new ka.TabPane(target, field.tabFullPage?true:false);
                }

                if (pDependField){
                    pDependField.tabPane.addPane(field.label, field.icon);
                } else {
                    tab = this.firstLevelTabBar.addPane(field.label, field.icon);
                }

                if (field.layout){
                    tab.pane.set('html', field.layout);
                }

                obj = tab.button;
                obj.childContainer = tab.pane;
                obj.parent = pDependField;
                obj.depends = {};
                obj.toElement = function(){return tab.button; };

                obj.setValue = function(){return true;};
                obj.getValue = function(){return true;};
                obj.field = field;
                obj.handleChildsMySelf = true;

            } else {

                if (field.tableItem && target.get('tag') != 'table'){

                    if (!pContainer.kaFieldTable){
                        pContainer.kaFieldTable = new Element('table', {width: '100%', 'class': 'ka-parse-table'}).inject(target);
                    }

                    target = pContainer.kaFieldTable;
                }

                obj = new ka.Field(field, target, id);
            }


            if (pDependField) {
                obj.parent = pDependField;
                pDependField.children[id] = obj;
            }

            if (field.depends) {

                if (!obj.childContainer){
                    obj.prepareChildContainer();
                }

                this.parseLevel(field.depends, obj.childContainer, obj);

                if (!obj.handleChildsMySelf){

                    obj.addEvent('check-depends', function () {

                        Object.each(this.children, function (sub, subid) {

                            if (sub.field.againstField && sub.field.againstField != id) return;

                            self.updateVisibility(this, sub);

                        }.bind(this));

                        self.updateChildrenContainerVisibility(this);

                    }.bind(obj));
                }

                obj.fireEvent('check-depends');
            }
            this.fields[ id ] = obj;

            if (pDependField)
                pDependField.children[id] = obj;

        }.bind(this));
    },

    /**
     * Updates the visibility of the children container.
     * @param pObj
     */
    updateChildrenContainerVisibility: function(pObj){

        if (pObj.handleChildsMySelf) return;

        if (!pObj.childContainer) return;

        var hasVisibleChilds = false;

        Object.each(pObj.children, function(sub) {
            if (!sub.isHidden()) {
                hasVisibleChilds = true;
            }
        });

        if (hasVisibleChilds) {
            pObj.childContainer.setStyle('display', 'block');
        } else {
            pObj.childContainer.setStyle('display', 'none');
        }

    },

    /**
     * Updates the visibility of a field.
     *
     * @param pTarget
     * @param pField
     */
    updateVisibility: function(pTarget, pField){

        var visible = this.getVisibility(pTarget, pField);
        if (visible)
            pField.show();
        else
            pField.hide();
    },

    /**
     * Returns whether a field should be visible or not.
     *
     * @param pTarget
     * @param pField
     * @return {Boolean}
     */
    getVisibility: function(pTarget, pField){

        if (pTarget.isHidden()) return false;

        if (typeOf(pField.field.needValue) == 'null') return true;
        if (pField.field.needValue === '') return true;

        if (typeOf(pField.field.needValue) == 'array') {
            if (pField.field.needValue.contains(pTarget.getValue())) {
                return true;
            } else {
                return false;
            }
        } else if (typeOf(pField.field.needValue) == 'function') {
            if (pField.field.needValue.attempt(pTarget.getValue())) {
                return true;
            } else {
                return false;
            }
        } else if (typeOf(pField.field.needValue) == 'string' || typeOf(pField.field.needValue) == 'number') {
            var c = 'javascript:';
            if (typeOf(pField.field.needValue) == 'string' && pField.field.needValue.substr(0,c.length) == c){

                var evalString = pField.field.needValue.substr(c);
                var value = pTarget.getValue();
                var result = eval(evalString);
                return (result)?true:false;

            } else {
                if (pField.field.needValue == pTarget.getValue()) {
                    return true;
                } else {
                    return false;
                }
            }
        }
        return false;
    },

    /**
     * Checks all fields whether they are valid or not. This fires
     * 'checkValid()' on each ka.Field instance, so it displays
     * the user if a field is not valid.
     *
     * @return {Boolean}
     */
    checkValid: function(){

        var ok = true;
        Object.each(this.fields, function (field, id) {

            if (id.substr(0,2) == '__' && id.substr(id.length-2) == '__')
                return;

            if (field.isHidden())
                return;

            if (!field.checkValid()) {
                ok = false;
            }

        });

        return ok;

    },

    /**
     * Invisible validation check. This does not show any information to the user,
     * if a field is invalid.
     *
     * @return {Boolean}
     */
    isValid: function () {

        var ok = true;
        Object.each(this.fields, function (field, id) {

            if (id.substr(0,2) == '__' && id.substr(id.length-2) == '__')
                return;

            if (field.isHidden())
                return;

            if (!field.isValid()) {
                ok = false;
            }

        });

        return ok;
    },

    /**
     * Returns all fields, which are invalid.
     *
     * @return {Object}
     */
    getInvalidFields: function () {

        var fields = {};
        Object.each(this.fields, function (field, id) {

            if (id.substr(0,2) == '__' && id.substr(id.length-2) == '__')
                return;

            if (field.isHidden())
                return;

            if (!field.isValid()) {
                fields[id] = field;
            }

        });

        return fields;
    },

    /**
     * Set the value of all fields.
     *
     * @param pValues
     * @param pInternal
     */
    setValue: function (pValues, pInternal) {

        if (typeOf(pValues) == 'string') {
            pValues = JSON.decode(pValues);
        }

        Object.each(this.fields, function (obj, id) {
            if (id.indexOf('[') != -1) {
                obj.setArrayValue(pValues, id, pInternal);
            } else {
                obj.setValue(pValues ? pValues[id] : null, pInternal);
            }
        });
    },

    /**
     * Returns all fields as a flatten object.
     *
     * @return {Object}
     */
    getFields: function () {
        return this.fields;
    },

    /**
     * Returns a field instance.
     *
     * @param {String} pField
     * @return {ka.Field}
     */
    getField: function (pField) {
        return this.fields[pField];
    },

    /**
     * Returns the value of a field.
     *
     * @param {String} pField
     * @return {Mixed}
     */
    getValue: function (pField) {

        var val;

        var res = {};
        if (pField && this.fields[pField]) {

            res = this.fields[pField].getValue();

        } else {
            Object.each(this.fields, function (obj, id) {

                if (id.substr(0,2) == '__' && id.substr(id.length-2) == '__')
                    return;

                if (obj.isHidden())
                    return;

                if (id.indexOf('[') != -1) {
                    var items = id.split('[');
                    var key = '';
                    var last = {};
                    var newRes = last;

                    items.each(function (item, pos) {
                        key = item.replace(']', '');

                        if (pos == items.length - 1) {
                            val = obj.getValue();
                            if (typeOf(val) !== 'null' && val !== '' && (this.options.returnDefault == true || val !== obj.options['default']))
                                last[key] = val;
                        } else {
                            last[key] = {};
                            last = last[key];
                        }
                    });
                    res = Object.merge(res, newRes);
                } else {
                    val = obj.getValue();
                    if (typeOf(val) !== 'null' && val !== '' && (this.options.returnDefault == true || val !== obj.options['default']))
                        res[id] = val;
                }
            }.bind(this));
        }

        return res;
    }
});
