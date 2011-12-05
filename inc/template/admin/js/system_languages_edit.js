var admin_system_languages_edit = new Class({

   initialize: function( pWin ){
       this.win = pWin;

       this.mod = this.win.params.module;
       this.createLayout();
   },

    createLayout: function(){

        this.bar = this.win.addButtonGroup();
        this.saveBtn = this.bar.addButton(_('Save'), _path+'admin/images/button-save.png', this.save.bind(this));

        this.languageSelect = new ka.Select();
        this.languageSelect.addEvent('change', this.extractLanguage.bind(this));
        this.languageSelect.inject(this.win.titleGroups);
        this.languageSelect.setStyle('top', 0);
        this.languageSelect.setStyle('margin-left', 8);

        Object.each(ka.settings.langs, function (lang, id){
            this.languageSelect.add(id, lang.langtitle + ' (' + lang.title + ', ' + id + ')');
        }.bind(this));

        if (this.win.params && this.win.params.lang) {
            this.languageSelect.setValue(this.win.params.lang);
        }

        if (this.win.params && this.win.params.module) {
            if (!ka.settings.configs[this.win.params.module]) {
                this.win._alert(_('Extension %s not found').replace(this.win.params.module));
                return;
            }
            var title = ka.settings.configs[this.win.params.module]['title']['en'];
            if (ka.settings.configs[this.win.params.module][_session.lang] )
                title = ka.settings.configs[this.win.params.module]['title'][_session.lang];

            this.win.clearTitle();
            this.win.setTitle(title);
        }

        this._extractLanguage();
    },

    extractLanguage: function () {
        this.win._confirm(_('Really change language ? Unsaved data will be lost.'), function (res) {
            if (res) this._extractLanguage();
        }.bind(this));
    },

    save: function () {

        var translations = {};

        Object.each(this.langInputs, function (translation, key) {
            if (typeOf(translation) == 'object') {
                translations[key] = {};
                Object.each(translation, function (subinput, id) {
                    if (typeOf(subinput) == 'element') {
                        translations[key][id] = subinput.value;
                    } else {
                        translations[key][id] = subinput;
                    }
                });
            } else if (translation && typeOf(translation.value) == 'string') {
                translations[ key ] = translation.value;
            }
        });

        this.saveBtn.startTip(_('Saving ...'));
        translations = JSON.encode(translations);
        this.lr = new Request.JSON({url: _path + 'admin/system/module/saveLanguage', noCache: 1, onComplete: function (res) {
            if (!res) {
                this.win._alert(_('Permission denied to the language file. Please check your permissions.'));
            }
            this.saveBtn.stopTip(_('Saved'));
        }.bind(this)}).post({name: this.mod, lang: this.languageSelect.getValue(), langs: translations});
    },

    _extractLanguage: function () {
        this.lr = new Request.JSON({url: _path + 'admin/system/module/extractLanguage', noCache: 1, onComplete: function (res) {
            if( res.error == 'access_denied' ){
                this.win._alert(_('Access denied to administration extension manager'), function(res){
                    this.win.close();
                }.bind(this));
                return;
            }
            this.extractedLanguages = res;
            this.loadLanguage(true);
        }.bind(this)}).post({name: this.mod});
    },

    loadLanguage: function (pRenderExtractedLangs) {
        this.lr = new Request.JSON({url: _path + 'admin/system/module/getLanguage', noCache: 1, onComplete: function (res) {
            this._renderLangs(res, pRenderExtractedLangs);
        }.bind(this)}).post({name: this.mod, lang: this.languageSelect.getValue()});
    },

    _renderLangs: function (pLangs, pRenderExtractedLangs) {
        var input, context, value, lkey, inputLi, inputOl, keyOl, keyLi, i;

        var p = this.win.content;
        p.empty();

        if (!pRenderExtractedLangs && pLangs.translations.length == 0) {

            new Element('div', {
                'text': _('There is no translated content for selected language. You should press Extract to find some.'),
                style: 'text-align: center; color: gray; padding: 15px;'
            }).inject(p);
            return;
        }

        this.langTable = new ka.Table([
            [_('Key'), 250],
            [_('Context'), 150],
            [_('Translation')]
        ]).inject(p);
        rows = [];

        this.langInputs = {};

        var langs = pLangs.translations;
        if (pRenderExtractedLangs) {
            langs = this.extractedLanguages;
        }

        Object.each(langs, function (translation, key) {

            context = '';
            lkey = key;
            value = translation;

            if (key.indexOf("\004") > 0) {
                context = key.split("\004")[0];
                lkey = key.split("\004")[1];
            }

            if (pRenderExtractedLangs) {
                value = pLangs.translations[ key ];
            }

            if (typeOf(translation) == 'array') {
                // plural


                this.langInputs[key] = {};

                inputOl = new Element('ol', {style: 'padding-left: 15px'});
                keyOl = new Element('ol', {style: 'padding-left: 15px'});

                if (!pRenderExtractedLangs) {
                    //we had a .po file already
                    new Element('li', {
                        text: lkey
                    }).inject(keyOl);

                    new Element('li', {
                        text: pLangs.plurals[key]
                    }).inject(keyOl);

                    this.langInputs[key]['plural'] = pLangs.plurals[key];

                } else {
                    //was extracted

                    Array.each(translation, function (l, k) {

                        new Element('li', {
                            text: l
                        }).inject(keyOl);
                        this.langInputs[key]['plural'] = l;

                    }.bind(this));

                }

                for (i = 0; i < pLangs.pluralCount; i++) {

                    value = (pLangs.translations[key] && pLangs.translations[key][i]) ? pLangs.translations[key][i] : '';

                    inputLi = new Element('li').inject(inputOl);
                    this.langInputs[key][i] = new Element('input', {
                        'class': 'text',
                        'style': 'width: 95%',
                        value: value
                    }).inject(inputLi);
                }

                rows.include([
                    keyOl, context, inputOl
                ]);

            } else {

                this.langInputs[key] = new Element('input', {
                    'class': 'text',
                    'style': 'width: 95%',
                    value: value
                });

                rows.include([
                    lkey, context, this.langInputs[key]
                ]);
            }
        }.bind(this));
        this.langTable.setValues(rows);
    }

});