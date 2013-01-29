ka.WindowEdit = new Class({

    Implements: [Events, Options],

    Binds: ['showVersions'],

    inline: false,

    options: {

        saveLabel: ''

    },

    fieldToTabOIndex: {}, //index fieldkey to main-tabid

    winParams: {}, //copy of pWin.params in constructor

    initialize: function (pWin, pContainer) {
        this.win = pWin;

        this.winParams = Object.clone(this.win.getParameter()); //copy

        if (!this.winParams.item && this.winParams.values)
            this.winParams.item = this.winParams.values; //compatibility

        if (!this.windowAdd && !this.winParams.item){
            this.win.alert('No item given. A edit object window can not be called directly.', function(){
                this.win.close();
            }.bind(this));
            return;
        }

        if (!pContainer) {
            this.container = this.win.content;
            this.container.setStyle('overflow', 'visible');
        } else {
            this.inline = true;
            this.container = pContainer;
        }

        this.container.empty();

        this.bCheckClose = this.checkClose.bind(this);
        this.bCheckTabFieldWidth = this.checkTabFieldWidth.bind(this);

        this.win.addEvent('close', this.bCheckClose);
        this.win.addEvent('resize', this.bCheckTabFieldWidth);

        if (this.win.getEntryPoint())
            this.load();
    },

    getContentContainer: function(){
        return this.container;
    },

    destroy: function () {

        this.win.removeEvent('close', this.bCheckClose);
        this.win.removeEvent('resize', this.bCheckTabFieldWidth);

        if (this.languageTip){
            this.languageTip.stop();
            delete this.languageTip;
        }

        if (this.headerLayout)
            this.headerLayout.getLayout().destroy();

        delete this.headerLayout;
        delete this.tabPane;

        Object.each(this._buttons, function (button, id) {
            button.stopTip();
        });

        if (this.topTabGroup) {
            this.topTabGroup.destroy();
        }

        if (this.actionsNavi) {
            this.actionsNavi.destroy();
        }

        if (this.actionsNaviDel) {
            this.actionsNaviDel.destroy();
        }

        if (this.versioningSelect) {
            this.versioningSelect.destroy();
        }

        if (this.languageSelect) {
            this.languageSelect.destroy();
        }

        delete this.versioningSelect;
        delete this.languageSelect;

        this.container.empty();

    },

    getModule: function(){
        if (!this.module){
            if (this.getEntryPoint().indexOf('/') > 0){
                this.module = this.getEntryPoint().substr(0, this.getEntryPoint().indexOf('/'));
            } else {
                this.module = this.getEntryPoint();
            }
        }
        return this.module;
    },

    getEntryPoint: function(){
        return this.win.getEntryPoint();
    },

    load: function () {

        this.container.set('html', '<div style="text-align: center; padding: 50px; color: silver">'+t('Loading definition ...')+'</div>');

        new Request.JSON({url: _path + 'admin/' + this.getEntryPoint(), noCache: true, onComplete: function(pResponse){

            if (!pResponse.error && pResponse.data && pResponse.data._isClassDefinition)
                this.render(pResponse.data);
            else
                this.container.set('html', '<div style="text-align: center; padding: 50px; color: red">'+t('Failed. No correct class definition returned. %s').replace('%s', 'admin/' + this.getEntryPoint()+'?_method=options')+'</div>');

        }.bind(this)}).get({_method: 'options'});
    },

    generateItemParams: function (pVersion) {
        var req = {};

        if (pVersion) {
            req.version = pVersion;
        }

        if (this.winParams && this.winParams.item) {
            this.classProperties.primary.each(function (prim) {
                req[ prim ] = this.winParams.item[prim];
            }.bind(this));
        }

        return req;
    },

    loadItem: function () {
        var _this = this;


        var id = ka.getObjectUrlId(this.classProperties['object'], this.winParams.item);

        if (this.lastRq)
            this.lastRq.cancel();

        this.win.setLoading(true, null, this.container.getCoordinates(this.win));

        this.lastRq = new Request.JSON({url: _path + 'admin/' + this.getEntryPoint()+'/'+id,
        noCache: true, onComplete: function (res) {
            this._loadItem(res.data);
        }.bind(this)}).get();
    },

    _loadItem: function (pItem) {
        this.item = pItem;

        this.setValue(pItem);

        this.renderVersionItems();

        this.win.setLoading(false);
        this.fireEvent('load', pItem);

        this.ritem = this.retrieveData(true);
    },

    setValue: function(pValue){

        this.fieldForm.setValue(pValue);

        if (this.getTitleValue())
            this.win.setTitle(this.getTitleValue());

        if (this.languageSelect && this.languageSelect.getValue() != pValue.lang) {
            this.languageSelect.setValue(pValue.lang);
            this.changeLanguage();
        }
    },

    /**
     * Returns the vlaue of the field for the window title.
     * @return {String}
     */
    getTitleValue: function(){

        var value = this.fieldForm.getValue();

        var titleField = this.classProperties.titleField;
        if (!this.classProperties.titleField){

            Object.each(this.fields, function (field, fieldId) {
                if (field.type != 'tab' && field.type != 'childrenSwitcher')
                    if (!titleField) titleField = fieldId;
            });
        }

        if (!this.fields[titleField]){
            logger(tf('Field %s ($titleField) for the window title does not exists in the $fields variable', titleField));
        }

        if (titleField && this.fields[titleField]){

            var value = ka.getObjectFieldLabel(
                value,
                this.fields[titleField],
                titleField,
                this.classProperties['object']
            );
            return value;
        }
        return '';
    },

    renderPreviews: function () {

        if (!this.classProperties.previewPlugins) {
            return;
        }

        //this.previewBtn;

        this.previewBox = new Element('div', {
            'class': 'ka-Select-chooser'
        });

        this.previewBox.addEvent('click', function (e) {
            e.stop();
        });

        this.previewBox.inject(this.headerLayout.getColumn(2));

        this.previewBox.setStyle('display', 'none');

        //this.classProperties.previewPlugins

        document.body.addEvent('click', this.closePreviewBox.bind(this));

        if (!this.classProperties.previewPluginPages) {
            return;
        }

        Object.each(this.classProperties.previewPlugins, function (item, pluginId) {

            var title = ka.settings.configs[this.getModule()].plugins[pluginId][0];

            new Element('div', {
                html: title,
                href: 'javascript:;',
                style: 'font-weight:bold; padding: 3px; padding-left: 15px;'
            }).inject(this.previewBox);

            var index = pluginId;
            if (pluginId.indexOf('/') === -1) {
                index = this.getModule() + '/' + pluginId;
            }

            Object.each(this.classProperties.previewPluginPages[index], function (pages, domain_id) {

                Object.each(pages, function (page, page_id) {

                    var domain = ka.getDomain(domain_id);
                    if (domain) {
                        new Element('a', {
                            html: '<span style="color: gray">[' + domain.lang + ']</span> ' + page.path,
                            style: 'padding-left: 21px',
                            href: 'javascript:;'
                        }).addEvent('click', this.doPreview.bind(this, page_id, index)).inject(this.previewBox);
                    }


                }.bind(this));

            }.bind(this));

        }.bind(this));

    },

    preview: function (e) {
        this.togglePreviewBox(e);
    },

    doPreview: function (pPageRsn, pPluginId) {
        this.closePreviewBox();

        if (this.lastPreviewWin) {
            this.lastPreviewWin.close();
        }

        var url = this.previewUrls[pPluginId][pPageRsn];

        if (this.versioningSelect.getValue() != '-') {
            url += '?kryn_framework_version_id=' + this.versioningSelect.getValue() + '&kryn_framework_code=' + pPluginId;
        }

        this.lastPreviewWin = window.open(url, '_blank');

    },

    setPreviewValue: function () {
        this.closePreviewBox();
    },

    closePreviewBox: function () {
        this.previewBoxOpened = false;
        this.previewBox.setStyle('display', 'none');
    },

    togglePreviewBox: function (e) {

        if (this.previewBoxOpened == true) {
            this.closePreviewBox();
        } else {
            if (e && e.stop) {
                document.body.fireEvent('click');
                e.stop();
            }
            this.openPreviewBox();
        }
    },

    openPreviewBox: function () {

        this.previewBox.setStyle('display', 'block');

        this.previewBox.position({
            relativeTo: this.previewBtn,
            position: 'bottomRight',
            edge: 'upperRight'
        });

        var pos = this.previewBox.getPosition();
        var size = this.previewBox.getSize();

        var bsize = window.getSize($('desktop'));

        if (size.y + pos.y > bsize.y) {
            this.previewBox.setStyle('height', bsize.y - pos.y - 10);
        }

        this.previewBoxOpened = true;
    },

    loadVersions: function () {

        var req = this.generateItemParams();
        new Request.JSON({url: _path + 'admin/' + this.getEntryPoint(), noCache: true, onComplete: function (res) {

            if (res && res.data.versions) {
                this.item.versions = res.data.versions;
                this.renderVersionItems();
            }

        }.bind(this)}).get(req);

    },

    renderVersionItems: function () {
        if (this.classProperties.versioning != true) return;

        this.versioningSelect.empty();
        this.versioningSelect.chooser.setStyle('width', 210);
        this.versioningSelect.add('-', _('-- LIVE --'));

        /*new Element('option', {
         text: _('-- LIVE --'),
         value: ''
         }).inject( this.versioningSelect );*/

        if (typeOf(this.item.versions) == 'array') {
            this.item.versions.each(function (version, id) {
                this.versioningSelect.add(version.version, version.title);
            }.bind(this));
        }

        if (this.item.version) {
            this.versioningSelect.setValue(this.item.version);
        }

    },

    render: function (pValues) {
        this.classProperties = pValues;

        this.container.empty();

        this.win.setLoading(true, null, {left: 265});

        this.fields = {};

        this.headerLayout = new ka.LayoutHorizontal(this.win.getTitleGroupContainer(), {
            columns: [null, 250],
            fixed: false
        });

        this.renderMultilanguage();

        this.renderVersions();

        this.renderPreviews();

        this.renderSaveActionBar();
        
        this.renderFields();

        this.fireEvent('render');

        if (this.winParams){
            this.loadItem();
        }
    },

    renderFields: function () {

        if (this.classProperties.fields && typeOf(this.classProperties.fields) != 'array') {

            this.form = new Element('div', {
                'class': 'ka-windowEdit-form'
            }).inject(this.getContentContainer());

            if (this.classProperties.layout) {
                this.form.set('html', this.classProperties.layout);
            }

            this.tabPane = new ka.TabPane(this.form, true, {addSmallTabGroup: function(){
                return new ka.SmallTabGroup(this.headerLayout.getColumn(1));
            }.bind(this)});

            this.fieldForm = new ka.FieldForm(this.form, this.classProperties.fields, {firstLevelTabPane: this.tabPane}, {win: this.win});
            this.fields = this.fieldForm.getFields();

            this._buttons = this.fieldForm.getTabButtons();

            if (this.fieldForm.firstLevelTabBar)
                this.topTabGroup = this.fieldForm.firstLevelTabBar.buttonGroup;

        }


        //generate index, fieldkey => main-tabid
        Object.each(this.classProperties.fields, function(item, key){
            if (item.type == 'tab')
                this.setFieldToTabIdIndex(item.depends, key);
        }.bind(this));


        //generate index, fieldkey => main-tabid
        Object.each(this.classProperties.tabFields, function(items, key){
            this.setFieldToTabIdIndex(items, key);
        }.bind(this));


    },

    setFieldToTabIdIndex: function(childs, tabId){
        Object.each(childs, function(item, key){
            this.fieldToTabOIndex[key] = tabId;
            if (item.depends){
                this.setFieldToTabIdIndex(item.depends, tabId);
            }
        }.bind(this));
    },

    renderVersions: function () {

        if (this.classProperties.versioning == true) {

            var versioningSelectRight = 5;
            if (this.languageSelect) {
                versioningSelectRight = 150;
            }

            this.versioningSelect = new ka.Select(this.headerLayout.getColumn(2));
            this.versioningSelect.setStyle('width', 120);

            this.versioningSelect.addEvent('change', this.changeVersion.bind(this));

        }

    },

    renderMultilanguage: function () {

        if (this.classProperties.multiLanguage) {

            if (this.classProperties.asNested) return false;

            this.win.extendHead();

            this.languageSelect = new ka.Select();
            this.languageSelect.inject(this.headerLayout.getColumn(2));
            this.languageSelect.setStyle('width', 120);


            this.languageSelect.addEvent('change', this.changeLanguage.bind(this));

            this.languageSelect.add('', t('-- Please Select --'));

            Object.each(ka.settings.langs, function (lang, id) {

                this.languageSelect.add(id, lang.langtitle + ' (' + lang.title + ', ' + id + ')');

            }.bind(this));

            if (this.winParams && this.winParams.item) {
                this.languageSelect.setValue(this.winParams.item.lang);
            }

        }

    },

    changeVersion: function () {
        var value = this.versioningSelect.getValue();
        if (value == '-') {
            value = null;
        }

        this.loadItem(value);
    },

    changeLanguage: function () {
        Object.each(this.fields, function (item, fieldId) {

            if (item.field.type == 'select' && item.field.multiLanguage) {
                item.field.lang = this.languageSelect.getValue();
                item.renderItems();
            }
        }.bind(this));


        if (this.languageTip && this.languageSelect.getValue() != ''){
            this.languageTip.stop();
            delete this.languageTip;
        }
    },

    changeTab: function (pTab) {
        this.currentTab = pTab;
        Object.each(this._buttons, function (button, id) {
            button.setPressed(false);
            this._panes[ id ].setStyle('display', 'none');
        }.bind(this));
        this._panes[ pTab ].setStyle('display', 'block');
        this._buttons[ pTab ].setPressed(true);

        this._buttons[ pTab ].stopTip();
    },

    reset: function(){

        this.setValue(this.item);
    },

    remove: function(){


        this.win.confirm(tf('Really delete %s?', this.getTitleValue()), function(answer){


            this.win.setLoading(true, null, this.container.getCoordinates(this.win));

            var object = ka.getObjectUrlId(this.classProperties['object'], this.winParams.item);
            var objectId = '?object='+object;

            this.lastDeleteRq = new Request.JSON({url: _path + 'admin/' + this.getEntryPoint() +'/'+objectId,
            onComplete: function(pResponse){

                logger(pResponse);

            }}).delete();


        }.bind(this));

    },

    renderSaveActionBar: function () {
        var _this = this;


        this.actionBar = new Element('div', {
            'class': 'kwindow-win-buttonBar'
        }).inject(this.container);


        this.removeBtn = new ka.Button([t('Remove'), '#icon-warning'])
        .addEvent('click', this.remove.bind(this))
        .inject(this.actionBar);

        document.id(this.removeBtn).addClass('ka-Button-red');
        document.id(this.removeBtn).addClass('ka-windowEdit-removeButton');

        this.resetBtn = new ka.Button([t('Reset'), '#icon-escape'])
        .addEvent('click', this.reset.bind(this))
        .inject(this.actionBar);


        /*if (this.classProperties.versioning == true){

            this.saveAndPublishBtn = new ka.Button([t('Save'), '#icon-checkmark-6'])
            .addEvent('click', this._save.bind(this, [false,true]))
            .inject(this.actionBar);
            // this.saveAndPublishBtn = this.actionsNavi.addButton(t('Save and publish'), '#icon-disk-2', function () {
            //     _this._save(false, true);
            // }.bind(this));
        }*/


        this.saveBtn = new ka.Button([t('Save'), '#icon-checkmark-6'])
        .addEvent('click', function(){ this.save(); }.bind(this))
        .inject(this.actionBar);

        document.id(this.saveBtn).addClass('ka-Button-blue');


        if (true) {

            this.previewBtn = new ka.Button([t('Preview'), '#icon-eye'])
            //.addEvent('click', this._save.bind(this))
            .inject(this.headerLayout.getColumn(2));
            document.id(this.previewBtn).setStyle('float', 'right')

            //this.previewBtn = this.actionsNavi.addButton(t('Preview'), '#icon-eye-3', this.preview.bind(this));
        }

        if (this.classProperties.workspace){


            this.showVersionsBtn = new ka.Button([t('Versions'), '#icon-history'])
            .addEvent('click', this.showVersions)
            .inject(this.headerLayout.getColumn(2));
            document.id(this.showVersionsBtn).setStyle('float', 'right')
        }

        this.checkTabFieldWidth();
    },

    showVersions: function(){

        //for now, we use a dialog

        var dialog = this.win.newDialog();


        new ka.ObjectVersionGraph(dialog.content, {
            object: ka.getObjectUrlId(this.classProperties['object'], this.winParams.item)
        });


    },

    checkTabFieldWidth: function(){

        if (!this.topTabGroup) return;

        if (!this.cachedTabItems)
            this.cachedTabItems = document.id(this.topTabGroup).getElements('a');

        var actionsMaxLeftPos = 5;
        if (this.versioningSelect)
            actionsMaxLeftPos += document.id(this.versioningSelect).getSize().x+10

        if (this.languageSelect)
            actionsMaxLeftPos += document.id(this.languageSelect).getSize().x+10

        var actionNaviWidth = this.actionsNavi ? document.id(this.actionsNavi).getSize().x : 0;

        var fieldsMaxWidth = this.win.titleGroups.getSize().x - actionNaviWidth - 17 - 20 -
                             (actionsMaxLeftPos + document.id(this.topTabGroup).getPosition(this.win.titleGroups).x);


        if (this.tooMuchTabFieldsButton)
            this.tooMuchTabFieldsButton.destroy();

        this.cachedTabItems.removeClass('ka-tabGroup-item-last');
        this.cachedTabItems.inject(document.hiddenElement);
        this.cachedTabItems[0].inject(document.id(this.topTabGroup));
        var curWidth = this.cachedTabItems[0].getSize().x;

        var itemCount = this.cachedTabItems.length-1;

        if (!this.overhangingItemsContainer)
            this.overhangingItemsContainer = new Element('div', {'class': 'ka-windowEdit-overhangingItemsContainer'});

        var removeTooMuchTabFieldsButton = false, atLeastOneItemMoved = false;

        this.cachedTabItems.each(function(button,id){
            if (id == 0) return;

            curWidth += button.getSize().x;
            if ((curWidth < fieldsMaxWidth && id < itemCount) || (id == itemCount && curWidth < fieldsMaxWidth+20)) {
                button.inject(document.id(this.topTabGroup));
            } else {
                atLeastOneItemMoved = true;
                button.inject(this.overhangingItemsContainer);
            }

        }.bind(this));

        this.cachedTabItems.getLast().addClass('ka-tabGroup-item-last');

        if (atLeastOneItemMoved){

            this.tooMuchTabFieldsButton = new Element('a', {
                'class': 'ka-tabGroup-item ka-tabGroup-item-last'
            }).inject(document.id(this.topTabGroup));

            new Element('img', {
                src: _path+ PATH_MEDIA + '/admin/images/ka.mainmenu-additional.png',
                style: 'left: 1px; top: 6px;'
            }).inject(this.tooMuchTabFieldsButton);

            this.tooMuchTabFieldsButton.addEvent('click', function(){
                if (!this.overhangingItemsContainer.getParent()){
                    this.overhangingItemsContainer.inject(this.win.border);
                    ka.openDialog({
                        element: this.overhangingItemsContainer,
                        target: this.tooMuchTabFieldsButton,
                        offset: {y: 0, x: 1}
                    });

                    /*ka.openDialog({
                        element: this.chooser,
                        target: this.box,
                        onClose: this.close.bind(this)
                    });*/
                }
            }.bind(this));

        } else {

            this.cachedTabItems.getLast().addClass('ka-tabGroup-item-last');
        }

    },

    removeTooltip: function(){
        this.stopTip();
        this.removeEvent('click', this.removeTooltip);
    },

    retrieveData: function (pWithoutEmptyCheck) {

        if (!pWithoutEmptyCheck && !this.fieldForm.checkValid()){
            var invalidFields = this.fieldForm.getInvalidFields();

            Object.each(invalidFields, function (item, fieldId) {

                var properTabKey = this.fieldToTabOIndex[fieldId];
                if (!properTabKey) return;
                var tabButton = this.fields[properTabKey];

                if (tabButton && !tabButton.isPressed()){

                    tabButton.startTip(t('Invalid input!'));
                    tabButton.toolTip.loader.set('src', _path + PATH_MEDIA + '/admin/images/icons/error.png');
                    tabButton.toolTip.loader.setStyle('position', 'relative');
                    tabButton.toolTip.loader.setStyle('top', '-2px');
                    document.id(tabButton.toolTip).setStyle('top', document.id(tabButton.toolTip).getStyle('top').toInt()+2);

                    tabButton.addEvent('click', this.removeTooltip);
                } else {
                    tabButton.stopTip();
                }

                item.highlight();
            }.bind(this));

            return false;
        }

        var req = this.fieldForm.getValue();

        if (this.languageSelect) {
            if (!pWithoutEmptyCheck && this.languageSelect.getValue() == ''){

                if (!this.languageTip){
                    this.languageTip = new ka.Tooltip(this.languageSelect, _('Please fill!'), null, null,
                        _path + PATH_MEDIA + '/admin/images/icons/error.png');
                }
                this.languageTip.show();

                return false;
            } else if (!pWithoutEmptyCheck && this.languageTip){
                this.languageTip.stop();
            }
            req['lang'] = this.languageSelect.getValue();
        }

        return req;

    },

    hasUnsavedChanges: function () {

        if (!this.ritem) return false;

        var currentData = this.retrieveData(true);
        if (!currentData) return true;

        return JSON.encode(currentData) == JSON.encode(this.ritem) ? false: true;
    },

    checkClose: function () {

        var hasUnsaved = this.hasUnsavedChanges();


        if (hasUnsaved) {
            this.win.interruptClose = true;
            this.win._confirm(t('There are unsaved data. Want to continue?'), function (pAccepted) {
                if (pAccepted) {
                    this.win.close();
                }
            }.bind(this));
        } else {
            this.win.close();
        }

    },

    buildRequest: function(){

        var req = {};

        var data = this.retrieveData();

        if (!data) return;

        this.ritem = data;

        if (this.winParams.item) {
            req = Object.merge(this.winParams.item, data);
        } else {
            req = data;
        }

        return req;
    },

    save: function (pClose) {

        if (this.lastSaveRq) this.lastSaveRq.cancel();

        var request = this.buildRequest();

        if (typeOf(request) != 'null') {

            this.saveBtn.startTip(t('Saving ...'));

            var objectId = ka.getObjectUrlId(this.classProperties['object'], this.winParams.item);

            this.lastSaveRq = new Request.JSON({url: _path + 'admin/' + this.getEntryPoint()+'/'+objectId,
            noErrorReporting: ['DuplicateKeysException', 'ObjectItemNotModified'],
            noCache: true, onComplete: function (res) {

                if (res.error == 'RouteNotFoundException'){
                    this.saveBtn.stopTip(t('Failed'));
                    return this.win.alert(t('RouteNotFoundException. You setup probably the wrong `editEntrypoint`'));
                }

                if(res.error == 'DuplicateKeysException'){
                    this.win.alert(t('Duplicate keys. Please change the values of marked fields.'));

                    Array.each(res.fields, function(field){
                        if (this.fields[field])
                            this.fields[field].showInvalid();
                    }.bind(this));

                    this.saveBtn.stopTip(t('Failed'));
                    return;
                }

                if (typeOf(res.data) == 'object'){
                    this.winParams.item = res.data; //our new primary keys
                } else {
                    this.winParams.item = ka.getObjectPk(this.classProperties['object'], request); //maybe we changed some pk
                }

                this.saveBtn.stopTip(t('Saved'));

                if (!pClose && this.saveNoClose) {
                    this.saveNoClose.stopTip(t('Done'));
                }

                if (this.classProperties.loadSettingsAfterSave == true) ka.loadSettings();

                this.fireEvent('save', [request, res]);

                window.fireEvent('softReload', this.getEntryPoint());

                if ((!pClose || this.inline ) && this.classProperties.versioning == true) this.loadVersions();

                if (pClose) {
                    this.win.close();
                }

            }.bind(this)}).put(request);
        }
    }
});