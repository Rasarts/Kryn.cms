var users_users_acl = new Class({

    groupDivs: {},
    userDivs: {},

    objectDivs: {},

    loadedAcls: [],
    currentObject: false,
    currentConstraint: false,

    currentAcls: [],

    initialize: function(pWin){
        this.win = pWin;
        this.createLayout();
    },

    createLayout: function(){

        this.win.extendHead();

        this.win.content.setStyle('overflow', 'hidden');

        this.left = new Element('div', {
            'class': 'users-acl-left'
        }).inject(this.win.content);

        this.right = new Element('div', {
            'class': 'users-acl-right'
        }).inject(this.win.content);

        this.query = new Element('input', {
            'class': 'text gradient users-acl-query',
            type: 'text'
        })
        .addEvent('keyup', function(){
            if (this.timeout) clearTimeout(this.timeout);
            this.timeout = this.loadList.delay(100, this);
        }.bind(this))
        .addEvent('mousedown', function(e){
            e.stopPropagation();
        })
        .inject(this.win.titleGroups);

        this.qImage = new Element('img', {
            src: _path+ PATH_MEDIA + '/admin/images/icon-search-loupe.png',
            style: 'position: absolute; left: 11px; top: 8px;'
        }).inject(this.win.titleGroups);

        this.tabs = new ka.TabPane(this.right, true, this.win);

        this.entryPointTab = this.tabs.addPane(t('Entry points'), '');
        this.objectTab = this.tabs.addPane(t('Objects'), '');

        this.actions = new ka.ButtonGroup(this.win.titleGroups);
        this.btnSave = this.actions.addButton(t('Save'), 'media/admin/images/button-save.png', this.save.bind(this));

        this.tabs.hide();
        this.actions.hide();

        this.loadEntryPoints();
        this.loadObjects();

        document.id(this.tabs.buttonGroup).setStyle('margin-left', 215);

        this.loadList();
    },

    loadObjectRules: function(pObjectKey){


        //ka.getObjectDefinition(pObjectKey);
        this.currentObject = pObjectKey;
        logger('loadObjectRules: '+this.currentObject);

        this.btnAddExact.setStyle('display', 'none');

        this.objectsExactContainer.empty();

        this.objectList.getElements('.ka-list-combine-item').removeClass('active');
        this.objectDivs[pObjectKey].addClass('active');

        this.currentDefinition = ka.getObjectDefinition(pObjectKey);

        if (this.currentDefinition.nested){

            var options = {
                type: 'tree',
                object: pObjectKey,
                openFirstLevel: true,
                move: false,
                withContext: false,
                noWrapper: true,
                onReady: function(){
                    logger('hi');
                    this.renderTreeRules();
                }.bind(this),
                onChildrenLoaded: function(){
                    this.renderTreeRules();
                }.bind(this)
            };

            if (this.currentDefinition.nestedRootObject){

                var objectChooser = new Element('div').inject(this.objectsExactContainer);
                var objectTreeContainer = new Element('div').inject(this.objectsExactContainer);

                var field = new ka.Select(objectChooser, {
                    object: this.currentDefinition.nestedRootObject
                });

                field.addEvent('change', function(){
                    objectTreeContainer.getChildren().destroy();

                    options.scope = field.getValue();
                    this.lastObjectTree = new ka.Field(options, objectTreeContainer);

                    this.mapObjectTreeEvent();

                }.bind(this));

                field.addEvent('ready', function(){
                    field.fireEvent('change', field.getValue());
                });
            } else {

                this.lastObjectTree = new ka.Field(options, this.objectsExactContainer);

                this.mapObjectTreeEvent();

            }

        } else {
            this.btnAddExact.setStyle('display', 'inline');
        }

        //todo, if nested, we'd also display rules of parent object which have sub=1

        this.currentConstraint = -1;

        this.renderObjectRules();
        this.showRules();

    },

    mapObjectTreeEvent: function(){

        this.lastObjectTree.fieldObject.tree.main.addEvent('mouseover', function(pEvent){

            var target = pEvent.target;
            if (!target) return false;

            if (!target.hasClass('ka-objectTree-item') && !(target = target.getParent('.ka-objectTree-item')))
                return false;

            delete target.isMouseOut;

            if (target.lastPlusSign) return target.lastPlusSign.fade('show');

            target.lastPlusSign = new Element('a', {
                href: 'javascript:;',
                html: '&#xe42e;',
                style: 'position: absolute; right: 5px; top: 2px;' +
                    'font-family: Icomoon; font-size: 12px; color: black; text-decoration: none;'
            }).inject(target);

            target.set('fade', {link: 'cancel'});

            target.lastPlusSign.addEvent('click', function(e){
                e.stopPropagation();
                this.openEditRuleDialog(this.currentObject, {constraint_type: 1, constraint_code: target.id});
            }.bind(this));

        }.bind(this));

        this.lastObjectTree.fieldObject.tree.main.addEvent('mouseout', function(pEvent){

            var target = pEvent.target;
            if (!target) return false;

            if (!target.hasClass('ka-objectTree-item') && !(target = target.getParent('.ka-objectTree-item')))
                return false;

            target.isMouseOut = true;

            target.lastTimer = (function(){
                if (target.lastPlusSign && target.isMouseOut){
                    target.lastPlusSign.fade('hide');
                }
            }).delay(30);

        });

        this.lastObjectTree.fieldObject.tree.addEvent('selection', function(item, dom){

            if (!dom.rules) {
                this.lastObjectTree.deselect();
                this.filterRules();
            } else {
                this.filterRules(1, dom.id, dom);
            }

        }.bind(this));

    },

    renderTreeRules: function(){

        logger(this.currentAcls);

        Array.each(this.currentAcls, function(rule){
            if (rule.object != this.currentObject) return;

            logger(rule);
            if (rule.constraint_type == 1){

                var item = this.lastObjectTree.fieldObject.tree.getItem(rule.constraint_code);

                logger(item);
                if (!item) return false;

                if (!item.rules) item.rules = [];

                if (item.rules.contains(rule)) return;

                item.rules.push(rule);

                if (rule.sub && !item.usersAclSubLine){
                    item.usersAclSubLine = new Element('div', {
                        style: 'position: absolute;top: 15px; bottom: 0px; border-right: 1px solid gray;'
                    }).inject(item);

                    item.usersAclSubLineChildren = new Element('div', {
                        style: 'position: absolute; top: 0px; bottom: 0px; border-right: 1px solid gray;'
                    }).inject(item.getNext());

                    [item.usersAclSubLine, item.usersAclSubLineChildren].each(function(dom){
                        dom.setStyle('left', item.getStyle('padding-left').toInt()+7);
                    });
                }

                if (!item.usersAclCounter){
                    item.usersAclCounter = new Element('span', {
                        text: '(1)',
                        style: 'color: gray;'
                    }).inject(item.span);
                    item.usersAclCounter.ruleCount = 1;
                } else {
                    item.usersAclCounter.set('text', '('+(item.usersAclCounter.ruleCount++)+')');
                }

            }

        }.bind(this));

    },

    renderObjectRules: function(){

        this.currentAcls.sortOn('prio', Array.DESCENDING);

        this.objectRulesContainer.empty();

        var ruleCounter = {
            all: 0,
            custom: 0,
            exact: 0
        };

        var modeCounter = {
            0: 0, 1: 0, 2: 0, 3: 0, 4: 0, 5: 0
        };

        var ruleGrouped = [true, {}, {}];

        Array.each(this.currentAcls, function(rule){
            if (rule.object != this.currentObject) return;

            modeCounter[rule.mode]++;

            if (rule.constraint_type == 2){
                ruleCounter.custom++;
            } else if (rule.constraint_type == 1){
                ruleCounter.exact++;
            } else
                ruleCounter.all++;

            if (rule.constraint_type >= 1){

                if (!ruleGrouped[rule.constraint_type][rule.constraint_code])
                    ruleGrouped[rule.constraint_type][rule.constraint_code] = [];

                ruleGrouped[rule.constraint_type][rule.constraint_code].push(rule);
            }

        }.bind(this));

        this.selectModes.setLabel(-1,  tc('usersAclModes', 'All rules')+' ('+this.currentAcls.length+')');
        this.selectModes.setLabel(0,  tc('usersAclModes', 'Combined')+' ('+modeCounter[0]+')');
        this.selectModes.setLabel(1,  tc('usersAclModes', 'List')+' ('+modeCounter[1]+')');
        this.selectModes.setLabel(2,  tc('usersAclModes', 'View')+' ('+modeCounter[2]+')');
        this.selectModes.setLabel(3,  tc('usersAclModes', 'Add')+' ('+modeCounter[3]+')');
        this.selectModes.setLabel(4,  tc('usersAclModes', 'Edit')+' ('+modeCounter[4]+')');
        this.selectModes.setLabel(5,  tc('usersAclModes', 'Delete')+' ('+modeCounter[5]+')');

        if (!this.currentDefinition.nested){
            this.objectsExactContainer.empty();

            Object.each(ruleGrouped[1], function(rules, code){

                var div = new Element('div', {
                    'class': 'ka-list-combine-item'
                }).inject(this.objectsExactContainer);

                div.addEvent('click', function(){this.filterRules(1, code, null)}.bind(this));

                var title = new Element('span', {
                    text: 'object://'+this.currentObject+'/'+code
                }).inject(div);

                this.loadObjectLabel(title);
                title = new Element('span', {
                    text: ' ('+rules.length+')'
                }).inject(div);

            }.bind(this));
        }

        this.objectsCustomContainer.empty();
        Object.each(ruleGrouped[2], function(rules, code){

            var div = new Element('div', {
                'class': 'ka-list-combine-item'
            }).inject(this.objectsCustomContainer);
            div.addEvent('click', function(){ this.filterRules(2, code, div)}.bind(this));

            var span = new Element('span').inject(div);
            this.humanReadableCondition(code, span);
            span = new Element('span', {text: ' ('+rules.length+')'}).inject(div);

        }.bind(this));


        this.objectsAllCount.set('text', '('+ruleCounter.all+')');
        this.objectsCustomSplitCount.set('text', '('+ruleCounter.custom+')');
        this.objectsExactSplitCount.set('text', '('+ruleCounter.exact+')');

        Array.each(this.currentAcls, function(rule){
            if (rule.object != this.currentObject) return;

            if (this.currentConstraint == -1){
                this.renderObjectRulesAdd(rule);
            }

        }.bind(this));

        if (this.rulesSort)
            delete this.rulesSort;

        this.rulesSort = new Sortables(this.objectRulesContainer, {
                handle: '.users-acl-object-rule-mover',
                clone: true,
                constrain: true,
                revert: true,
                opacity: 1
            }
        );

    },

    filterRules: function(pConstraintType, pConstraintCode, pDomObject){

        logger(pConstraintType+' - '+pConstraintCode+' - '+pDomObject);
        if (pDomObject){
            this.objectConstraintsContainer.getElements('.active').removeClass('active');

            if (pDomObject){
                if (pDomObject.hasClass('ka-list-combine-item')){
                    pDomObject.addClass('active');
                    if (this.lastObjectTree)
                        this.lastObjectTree.deselect();
                }
            } else {
                if (this.lastObjectTree)
                    this.lastObjectTree.deselect();
            }

            if (typeOf(pConstraintType) != 'null'){
                this.lastConstraintType = pConstraintType;
                this.lastConstraintCode = pConstraintCode;
            } else if (this.lastConstraintType){
                pConstraintType = this.lastConstraintType;
                pConstraintCode = this.lastConstraintCode;
            }
        } else {
            delete this.lastConstraintType;
            this.objectConstraintsContainer.getElements('.active').removeClass('active');
        }

        this.objectRulesContainer.getChildren().each(function(child){

            var show = false;
            var completelyHide = false;

            if (typeOf(pConstraintType) != 'null'){
                if (pConstraintType === false || child.rule.constraint_type == pConstraintType){

                    if (pConstraintType === false || pConstraintType == 0 || (pConstraintType >= 1 && pConstraintCode == child.rule.constraint_code)){
                        show = true;
                    }
                }
            } else {
                show = true;
            }

            if (this.lastRulesModeFilter !== false){
                if (this.lastRulesModeFilter != child.rule.mode){
                    show = false;
                    completelyHide = true;
                }
            }

            if (show){
                if (child.savedHeight){
                    child.morph({
                        'height': child.savedHeight,
                        paddingTop: 6,
                        paddingBottom: 6
                    });
                } else
                    child.savedHeight = child.getSize().y-12;

                child.addClass('ka-list-combine-item');

            } else {

                if (!child.savedHeight)
                    child.savedHeight = child.getSize().y-12;

                if (completelyHide)
                    child.removeClass('ka-list-combine-item');

                child.morph({
                    'height': completelyHide==true?0:1,
                    paddingTop: 0,
                    paddingBottom: 0
                });
            }

        }.bind(this));

    },

    renderObjectRulesAdd: function(pRule){

        var div = new Element('div', {
            'class': 'ka-list-combine-item users-acl-object-rule'
        }).inject(this.objectRulesContainer);

        div.rule = pRule;

        new Element('img', {
            'class': 'users-acl-object-rule-mover',
            src: _path+'media/users/admin/images/users-acl-item-mover.png'
        }).inject(div);


        var status = 'accept';
        if (pRule.access == 0){
            status = 'exclamation';
        } else if(pRule.access == 2){
            status = 'arrow_turn_bottom_left';
        }

        new Element('img', {
            'class': 'users-acl-object-rule-status',
            src: _path+'media/admin/images/icons/'+status+'.png'
        }).inject(div);

        var mode = 'arrow_in'; //0, combined

        switch(pRule.mode){
            case '1': mode = 'application_view_list'; break; //list
            case '2': mode = 'application_form'; break; //view detail
            case '3': mode = 'application_form_add'; break; //add
            case '4': mode = 'application_form_edit'; break; //edit
            case '5': mode = 'application_form_delete'; break; //delete
        }

        new Element('img', {
            'class': 'users-acl-object-rule-mode',
            src: _path+'media/admin/images/icons/'+mode+'.png'
        }).inject(div);


        var title = t('All objects');

        if (pRule.constraint_type == 1)
            title = 'object://'+this.currentObject+'/'+pRule.constraint_code;
        if (pRule.constraint_type == 2)
            title = '';

        var title = new Element('span', {
            text: title
        }).inject(div);

        if (pRule.constraint_type == 2){
            var span = new Element('span').inject(title);
            this.humanReadableCondition(pRule.constraint_code, span);
        } else if (pRule.constraint_type == 1){
            this.loadObjectLabel(title);
        }

        if (pRule.mode != 1 && pRule.mode <= 4){

            var fieldSubline = new Element('div', {
                'class': 'users-acl-object-rule-subline'
            }).inject(div);

            var comma;

            if (pRule.fields && pRule.fields != ''){

                var definition = ka.getObjectDefinition(this.currentObject);

                var fieldsObj = JSON.decode(pRule.fields);

                var primaries = ka.getPrimaryListForObject(this.currentObject);
                if (primaries){
                    var primaryField = primaries[0];
                    var primaryLabel = definition.fields[primaryField].label || primaryField;
                }

                Object.each(fieldsObj, function(def, key){

                    field = key;
                    if(definition && definition.fields[field] && definition.fields[field].label){

                        field = definition.fields[field].label;

                        new Element('span', {text: field}).inject(fieldSubline);

                        var imgSrc;
                        var subcomma;

                        if (typeOf(def) == 'object' || typeOf(def) == 'array'){

                            new Element('span', {text: '['}).inject(fieldSubline);

                            var span = new Element('span').inject(fieldSubline);

                            if (typeOf(def) == 'array'){
                                Array.each(def, function(rule){

                                    var span = new Element('span').inject(fieldSubline);
                                    this.humanReadableCondition(rule.condition, span);
                                    if (rule.access == 1){
                                        new Element('img', {src: _path+'media/admin/images/icons/accept.png'}).inject(span);
                                    } else {
                                        new Element('img', {src: _path+'media/admin/images/icons/exclamation.png'}).inject(span);
                                    }
                                    subcomma = new Element('span', {text: ', '}).inject(fieldSubline);

                                }.bind(this));
                            } else {

                                var primaryLabel = '';
                                Object.each(def, function(access, id){

                                    var span = new Element('span', {
                                        text: primaryLabel+' = '+id
                                    }).inject(span);

                                    if (access == 1){
                                        new Element('img', {src: _path+'media/admin/images/icons/accept.png'}).inject(span);
                                    } else {
                                        new Element('img', {src: _path+'media/admin/images/icons/exclamation.png'}).inject(span);
                                    }

                                    new Element('img', {src: imgSrc}).inject(span);
                                    subcomma = new Element('span', {text: ', '}).inject(fieldSubline);

                                }.bind(this));
                            }

                            if (subcomma)
                                subcomma.destroy();

                            new Element('span', {text: ']'}).inject(fieldSubline);

                        } else if (def == 0){
                            imgSrc = _path+'media/admin/images/icons/exclamation.png';
                        } else if(def){
                            imgSrc = _path+'media/admin/images/icons/accept.png';
                        }

                        if (imgSrc)
                            new Element('img', {src: imgSrc}).inject(fieldSubline);
                    }

                    comma = new Element('span', {text: ', '}).inject(fieldSubline);

                }.bind(this));

                comma.destroy();

            } else {
                new Element('span', {text: t('All fields')}).inject(fieldSubline);
            }
        }

        var actions = new Element('div', {
            'class': 'users-acl-object-rule-actions'
        }).inject(div);

        new Element('img', {
            src: _path+'media/admin/images/icons/pencil.png',
            title: t('Edit rule')
        })
        .addEvent('click', function(){this.openEditRuleDialog(this.currentObject, div); }.bind(this))
        .inject(actions);

        new Element('img', {
            src: _path+'media/admin/images/icons/delete.png',
            title: t('Delete rule')
        })
        .addEvent('click', this.deleteObjectRule.bind(this, div))
        .inject(actions);


    },

    loadObjectLabel: function(pDomObject){

        var uri = pDomObject.get('text');
        var objectKey = ka.getObjectKey(uri);
        var objectId  = ka.getObjectId(uri);

        //todo, maybe we have a template and extra fields for the label
        var definition = ka.getObjectDefinition(objectKey);
        var fields = definition.labelField;

        new Request.JSON({url: _path+'admin/backend/object/'+objectKey+'/'+objectId, onComplete: function(pResult){

            if (!pResult || pResult.error || !pResult.data){
                pDomObject.set('text', 'Object not found. '+uri);
                return;
            };

            var sFields = fields.split(',');
            var title = [];
            Array.each(sFields, function(field){
                title.push(pResult.data[field]);
            });

            pDomObject.set('text', title.join(', '));

        }}).get({fields: fields});

        //http://ilee/admin/backend/objectGetLabel?url=object://news/3
    },

    humanReadableCondition: function(pCondition, pDomObject){

        if (typeOf(pCondition) == 'string')
            pCondition = JSON.decode(pCondition);

        if (typeOf(pCondition) != 'array') return;

        var field = '';
        var definition = ka.getObjectDefinition(this.currentObject);

        var span = new Element('span');

        if (pCondition.length > 0){

            Array.each(pCondition, function(condition){

                if (typeOf(condition) == 'string'){
                    new Element('span', {text: ' '+((condition.toLowerCase()=='and')?t('and'):t('or'))+' '}).inject(span);
                } else {

                    if (typeOf(condition[0]) == 'array'){
                        //group
                        new Element('span', {text: '('}).inject(span);
                        var sub= new Element('span').inject(span);
                        this.humanReadableCondition(condition, sub);
                        new Element('span', {text: ')'}).inject(span);

                    } else {

                        field = condition[0];
                        if(definition && definition.fields[field] && definition.fields[field].label)
                            field = definition.fields[field].label;

                        new Element('span', {text: field+' '+condition[1]+' '+condition[2]}).inject(span);
                    }
                }

            }.bind(this));

        } else {
            span.set('text', t('-- Nothing --'));
        }

        pDomObject.empty();
        span.inject(pDomObject);

    },

    addObjectsToList: function(pConfig, pExtKey){

        new Element('div', {
            'class': 'ka-list-combine-splititem',
            text: ka.getExtensionTitle(pExtKey)
        }).inject(this.objectList);

        Object.each(pConfig.objects, function(object, objectKey){

            var div = new Element('div', {
                'class': 'ka-list-combine-item'
            })
            .addEvent('click', function(){this.loadObjectRules(pExtKey+'\\'+objectKey)}.bind(this))
            .inject(this.objectList);

            var h2 = new Element('h2', {
                text: object.label || objectKey
            }).inject(div);

            div.count = new Element('span', {
                style: 'font-weight: normal; color: silver;'
            }).inject(h2);

            if (object.desc){
                new Element('div',{
                    'class': 'subline',
                    text: object.desc
                }).inject(div);
            }

            this.objectDivs[pExtKey+'\\'+objectKey] = div;

        }.bind(this));

    },

    loadObjects: function(){

        this.objectList = new Element('div', {
            'class': 'users-acl-object-list'
        })
        .inject(this.objectTab.pane);

        this.objectConstraints = new Element('div', {
            'class': 'users-acl-object-constraints'
        })
        .inject(this.objectTab.pane);

        this.objectRulesFilter = new Element('div', {
            'class': 'users-acl-object-constraints-title'
        }).inject(this.objectConstraints);

        new Element('div', {
            'class': 'ka-list-combine-splititem',
            text: t('Constraints')
        }).inject(this.objectRulesFilter);

        var div = new Element('div', {
            style: 'padding-top: 12px;'
        }).inject(this.objectRulesFilter);

        new ka.Button(t('Deselect'))
        .addEvent('click', function(){this.filterRules()}.bind(this))
        .inject(div);

        this.objectConstraintsContainer = new Element('div', {
            'class': 'users-acl-object-constraints-container'
        })
        .inject(this.objectConstraints);

        var allDiv = new Element('div', {
            'class': 'ka-list-combine-item'
        }).inject(this.objectConstraintsContainer);

        allDiv.addEvent('click', function(){this.filterRules(0, null, allDiv)}.bind(this));

        var h2 = new Element('div', {
            text: t('All objects')
        }).inject(allDiv);

        this.objectsAllCount = new Element('span',{
            style: 'padding-left: 5px;',
            text: '(0)'
        }).inject(h2);

        new Element('img' ,{
            src: _path+ PATH_MEDIA + '/admin/images/icons/add.png',
            style: 'cursor: pointer; position: relative; top: -1px; float: right;',
            title: t('Add')
        })
        .addEvent('click', function(e){
            this.openEditRuleDialog(this.currentObject, {constraint_type: 0});
            e.stop();
        }.bind(this))
        .inject(h2);

        this.objectsCustomSplit = new Element('div', {
            'class': 'ka-list-combine-splititem',
            text: t('Custom')
        }).inject(this.objectConstraintsContainer);

        this.objectsCustomSplitCount = new Element('span',{
            style: 'color: gray; padding-left: 5px;',
            text: '(0)'
        }).inject(this.objectsCustomSplit);

        this.objectsCustomContainer = new Element('div',{
        }).inject(this.objectConstraintsContainer);

        new Element('img' ,{
            src: _path+ PATH_MEDIA + '/admin/images/icons/add.png',
            style: 'cursor: pointer; position: relative; top: -1px; float: right;',
            title: t('Add')
        })
        .addEvent('click', function(){
            this.openEditRuleDialog(this.currentObject, {constraint_type: 2});
        }.bind(this))
        .inject(this.objectsCustomSplit);

        this.objectsExactSplit = new Element('div', {
            'class': 'ka-list-combine-splititem',
            text: t('Exact')+' '
        }).inject(this.objectConstraintsContainer);

        this.objectsExactContainer = new Element('div', {
        }).inject(this.objectConstraintsContainer);

        this.objectsExactSplitCount = new Element('span',{
            style: 'color: gray; padding-left: 5px;',
            text: '(0)'
        }).inject(this.objectsExactSplit);

        this.btnAddExact = new Element('img' ,{
            src: _path+ PATH_MEDIA + '/admin/images/icons/add.png',
            style: 'cursor: pointer; position: relative; top: -1px; float: right;',
            title: t('Add')
        })
        .addEvent('click', function(){ this.openEditRuleDialog(this.currentObject, {constraint_type: 1})}.bind(this))
        .inject(this.objectsExactSplit);

        this.objectRules = new Element('div', {
            'class': 'users-acl-object-rules'
        })
        .inject(this.objectTab.pane);

        this.objectRulesFilter = new Element('div', {
            'class': 'users-acl-object-rules-filter'
        }).inject(this.objectRules);

        new Element('div', {
            'class': 'ka-list-combine-splititem',
            text: t('Rules')
        }).inject(this.objectRulesFilter);

        this.objectRulesInfo = new Element('div', {
            'class': 'users-acl-object-rules-info'
        }).inject(this.objectRulesFilter);

        new Element('div',{
            text: t('Most important rule shall be on the top.')
        }).inject(this.objectRulesInfo);

        var div = new Element('div', {
            text: t('Filter modes')+': ',
            style: 'line-height: 24px;'
        }).inject(this.objectRulesInfo);

        this.selectModes = new ka.Select(div);

        document.id(this.selectModes).setStyle('width', 120);

        this.selectModes.addImage(-1, tc('usersAclModes', 'All rules'), 'media/admin/images/icons/tick.png');
        this.selectModes.addImage(0,  tc('usersAclModes', 'Combined'), 'media/admin/images/icons/arrow_in.png');
        this.selectModes.addImage(1,  tc('usersAclModes', 'List'), 'media/admin/images/icons/application_view_list.png');
        this.selectModes.addImage(2,  tc('usersAclModes', 'View'), 'media/admin/images/icons/application_form.png');
        this.selectModes.addImage(3,  tc('usersAclModes', 'Add'), 'media/admin/images/icons/application_form_add.png');
        this.selectModes.addImage(4,  tc('usersAclModes', 'Edit'), 'media/admin/images/icons/application_form_edit.png');
        this.selectModes.addImage(5,  tc('usersAclModes', 'Delete'), 'media/admin/images/icons/application_form_delete.png');

        this.lastRulesModeFilter = false;

        this.selectModes.addEvent('change', function(value){

            if (value == -1)
                this.lastRulesModeFilter = false;
            else
                this.lastRulesModeFilter = value;

            this.filterRules();

        }. bind(this));

        this.objectRulesContainer = new Element('div', {
            'class': 'users-acl-object-rules-container'
        })
        .inject(this.objectRules);

        this.addObjectsToList(ka.settings.configs.admin, 'admin');
        this.addObjectsToList(ka.settings.configs.users, 'users');

        Object.each(ka.settings.configs, function(config, extKey){

            if (!config.objects || extKey == 'admin' || extKey == 'users' || typeOf(config.objects) != 'object') return;
            this.addObjectsToList(config, extKey);

        }.bind(this));

    },

    applyEditRuleDialog: function(){

        var value = this.editRuleKaObj.getValue();

        var oldScrollTop = this.objectRulesContainer.getScroll().y;

        if (value.constraint_type == 2){
            value.constraint_code = JSON.encode(value.constraint_code_condition);
            delete value.constraint_code_condition;
        }
        if (value.constraint_type == 1){
            value.constraint_code = value.constraint_code_exact;
            delete value.constraint_code_exact;
        }

        if (!Object.getLength(value.fields)){
            delete value.fields;
        }  else {
            value.fields = JSON.encode(value.fields);
        }

        value.object = this.currentObject;
        value.target_type = this.currentTargetType;
        value.target_id = this.currentTargetRsn;

        if (this.currentRuleDiv){
            var pos = this.currentAcls.indexOf(this.currentRuleDiv.rule);
            value.prio = this.currentRuleDiv.rule.prio;
            this.currentAcls[pos] = value;
        } else {

            var newPrio = 1;
            if (this.currentAcls.length > 0)
                newPrio = this.currentAcls[0].prio+1;

            oldScrollTop = 0;
            value.prio = newPrio;

            this.currentAcls.push(value);

        }

        this.renderObjectRules();
        this.updateObjectRulesCounter();


        this.unsavedContent = true;

        this.editRuleDialog.close();
        this.objectRulesContainer.scrollTo(0, oldScrollTop);

        delete this.editRuleDialog;

    },

    deleteObjectRule: function(pDiv){

        var oldScrollTop = this.objectRulesContainer.getScroll().y;

        var pos = this.currentAcls.indexOf(pDiv.rule);
        this.currentAcls.splice(pos, 1)
        pDiv.destroy();

        this.renderObjectRules();
        this.updateObjectRulesCounter();

        this.objectRulesContainer.scrollTo(0, oldScrollTop);

        this.unsavedContent = true;

    },

    openEditRuleDialog: function(pObject, pRuleDiv){


        this.currentRuleDiv = typeOf(pRuleDiv)=='element'?pRuleDiv:null;

        this.editRuleDialog = this.win.newDialog('', true);

        this.editRuleDialog.setStyles({
            width: '90%',
            height: '90%'
        });

        this.editRuleDialog.center();

        //this.editRuleDialog.content
        new ka.Button(t('Cancel'))
        .addEvent('click', function(){
            this.editRuleDialog.close();
        }.bind(this))
        .inject(this.editRuleDialog.bottom);

        var applyTitle = t('Apply');
        var title = t('Edit rule');

        if (typeOf(pRuleDiv) == 'object'){
            applyTitle = t('Add');
            title = t('Add rule');
        }

        new ka.Button(applyTitle)
        .setButtonStyle('blue')
        .addEvent('click', this.applyEditRuleDialog.bind(this))
        .inject(this.editRuleDialog.bottom);

        new Element('h2', {
            text: title
        }).inject(this.editRuleDialog.content);

        var fields = {

            constraint_type: {
                label: t('Constraint type'),
                type: 'select',
                inputWidth: 140,
                items: {
                    '0': t('All objects'),
                    '1': t('Exact object'),
                    '2': t('Custom condition')
                }
            },

            constraint_code_condition: {
                label: t('Constraint'),
                needValue: '2',
                againstField: 'constraint_type',
                type: 'condition',
                object: pObject,
                startWith: 1
            },

            constraint_code_exact: {
                label: t('Object'),
                needValue: '1',
                fieldWidth: 250,
                againstField: 'constraint_type',
                type: 'object',
                withoutObjectWrapper: true,
                object: pObject
            },

            access: {
                label: t('Access'),
                type: 'select',
                inputWidth: 140,
                'default': '2',
                items: {
                    '2': [t('Inherited'), 'media/admin/images/icons/arrow_turn_bottom_left.png'],
                    '0': [t('Deny'), 'media/admin/images/icons/exclamation.png'],
                    '1': [t('Allow'), 'media/admin/images/icons/accept.png']
                }

            },

            sub: {
                type: 'checkbox',
                label: t('With sub-items'),
                'default': 1
            },

            mode: {
                label: t('Mode'),
                type: 'select',
                inputWidth: 140,
                'default': '0',
                items: {
                    '0': [tc('usersAclModes', 'Combined'), 'media/admin/images/icons/arrow_in.png'],
                    '1': [tc('usersAclModes', 'List'), 'media/admin/images/icons/application_view_list.png'],
                    '2': [tc('usersAclModes', 'View'), 'media/admin/images/icons/application_form.png'],
                    '3': [tc('usersAclModes', 'Add'), 'media/admin/images/icons/application_form_add.png'],
                    '4': [tc('usersAclModes', 'Edit'), 'media/admin/images/icons/application_form_edit.png'],
                    '5': [tc('usersAclModes', 'Delete'), 'media/admin/images/icons/application_form_delete.png']
                }
            },

            __fields__: {
                label: t('Fields'),
                needValue: ['0','2','3','4'],
                againstField: 'mode',
                type: 'label'
            },

            fields: {
                noWrapper: true,
                needValue: ['0','2','3','4'],
                againstField: 'mode',
                type: 'usersAclRuleFields',
                object: pObject
            }

        };

        if (!this.currentDefinition.nested)
            delete fields.sub;

        this.editRuleKaObj = new ka.Parse(this.editRuleDialog.content, fields, {
            allTableItems:1,
            tableitem_title_width: 180,
            returnDefault: true
        }, {win: this.win});

        var rule = Object.clone(typeOf(pRuleDiv) == 'element'? pRuleDiv.rule : typeOf(pRuleDiv) == 'object'?pRuleDiv:{});

        if (rule.constraint_type == 2){
            rule.constraint_code_condition = rule.constraint_code;
        }
        if (rule.constraint_type == 1){
            rule.constraint_code_exact = rule.constraint_code;
        }

        this.editRuleKaObj.setValue(rule);

    },

    clickEntrypoint: function(pEvent){

        this.entryPointList.getElements('a').removeClass('users-acl-entrypoint-rule-active');
        this.entryPointRuleContainer.empty();

        if (!pEvent.target) return;

        var element = pEvent.target;
        if (element.get('tag') != 'a'){
            element = element.getParent('a');
            if (!element) return;
        }

        element.addClass('users-acl-entrypoint-rule-active');

        this.clickEntryPointRule(element);

    },

    loadEntryPoints: function(){

        this.currentEntrypointDoms = {};

        this.entryPointList = new Element('div', {
            'class': 'users-acl-entrypoint-list'
        })
        .inject(this.entryPointTab.pane);

        this.entryPointListContainer = new Element('div', {
            'style': 'padding-left: 15px;'
        })
        .inject(this.entryPointList);

        this.entryPointListContainer.addEvent('click', this.clickEntrypoint.bind(this));

        this.entryPointRuleContainer = new Element('div', {
            'class': 'users-acl-entrypoint-rule-container'
        })
        .inject(this.entryPointTab.pane);

        this.adminEntryPointDom = this.addEntryPointTree(ka.settings.configs['admin'], 'admin');

        Object.each(ka.settings.configs, function(ext, extCode){
            if( extCode != 'admin' && ext.admin ){
                this.addEntryPointTree( ext, 'admin/'+extCode );
            }
        }.bind(this));
    },

    getEntryPointTitle: function(pNode){

        switch(pNode.type){

            case 'iframe':
            case 'custom':
                return ('Window %s').replace('%s', pNode.type);

            case 'list':
            case 'edit':
            case 'add':
            case 'combine':
                return ('Framework window %s').replace('%s', pNode.type);

            case 'function':
                return t('Background function call');

            case 'store':
                return t('Type store');

            default:
                return t('Default entry point');
        }

    },

    getEntryPointIcon: function(pNode){

        /*

         '': t('Default'),
         store: t('Store'),
         'function': t('Background function'),
         custom: t('[Window] Custom'),
         iframe: t('[Window] iFrame'),
         list: t('[Window] Framework list'),
         edit: t('[Window] Framework edit'),
         add: t('[Window] Framework add'),
         combine: t('[Window] Framework Combine')

         */
        switch(pNode.type){

            case 'list':
                return 'admin/images/icons/application_view_list.png';

            case 'edit':
                return 'admin/images/icons/application_form_edit.png';

            case 'add':
                return 'admin/images/icons/application_form_add.png';

            case 'combine':
                return 'admin/images/icons/application_side_list.png';

            case 'function':
                return 'admin/images/icons/script_code.png';

            case 'iframe':
            case 'custom':
                return 'admin/images/icons/application.png';

            case 'store':
                return 'admin/images/icons/database.png';

            default:
                return 'admin/images/icons/folder.png'
        }

    },

    addEntryPointTree: function(pExtensionConfig, pExtensionKey){


        var title = ka.getExtensionTitle( pExtensionKey=='admin'?pExtensionKey:pExtensionKey.substr(6));

        var target = new Element('div', {
            style: 'padding-top: 5px; margin-top: 5px; border-top: 1px dashed silver;'
        }).inject( pExtensionKey=='admin'?this.entryPointListContainer:this.adminEntryPointDom.childContainer );

        var a = new Element('a', { href: 'javascript:;', text: title, title: '#'+pExtensionKey, style: 'font-weight: bold;'}).inject( target );

        var childContainer = new Element('div', {'class': 'users-acl-tree-childcontainer', style: 'padding-left: 25px;'}).inject( a, 'after' );

        if(pExtensionKey == 'admin')
            this.extContainer = childContainer;

        var path = pExtensionKey;

        a.entryPath = path;
        a.childContainer = childContainer;
        this.currentEntrypointDoms[path] = a;
        this.loadEntryPointChildren(pExtensionConfig.admin, path, childContainer);

        return a;

    },

    loadEntryPointChildren: function(pAdmin, pCode, pChildContainer){

        Object.each(pAdmin, function(item, index){

            if(item.acl == false) return;

            var element = new Element('a', {
                href: 'javascript:;',
                text: t(item.title),
                title: this.getEntryPointTitle(item)+', '+pCode+index
            }).inject(pChildContainer);

            new Element('img', {
                src: _path+'media/'+this.getEntryPointIcon(item)
            }).inject(element, 'top');

            var code = pCode+'/'+index;
            element.entryPath = code;
            this.currentEntrypointDoms[code] = element;
            var childContainer = new Element('div', {'class': 'users-acl-tree-childcontainer', style: 'padding-left: 25px;'}).inject( pChildContainer );

            this.loadEntryPointChildren(item.children, code, childContainer);

        }.bind(this));
    },

    loadList: function(){

        var q = this.query.value;

        this.left.empty();

        new Element('div', {
            'class': 'ka-list-combine-itemloader',
            text: t('Loading ...')
        }).inject(this.left);

        var req = {};
        if (q)
            req.q = q;

        if (this.lastRq)
            this.lastRq.cancel();

        this.lastRq = new Request.JSON({url: _path+'admin/users/acl/search', noCache: 1,
            onComplete: this.renderList.bind(this)
        }).get(req);


    },

    renderList: function(pItems){

        if (pItems && typeOf(pItems) == 'object'){

            this.left.empty();

            if (typeOf(pItems.users) == 'array' && pItems.users.length > 0){
                new Element('div', {
                    'class': 'ka-list-combine-splititem',
                    text: t('Users')
                }).inject(this.left);

                Array.each(pItems.users, function(item){

                    var div = new Element('div', {
                        'class': 'ka-list-combine-item'
                    })
                    .addEvent('click', this.loadRules.bind(this, 'user', item, false))
                    .inject(this.left);

                    this.userDivs[item.id] = div;

                    var h2 = new Element('h2', {
                        text: item.username
                    }).inject(div);

                    new Element('span', {
                        text: ' ('+item.ruleCount+')',
                        style: 'color: silver; font-size: 12px; font-weight: normal;'
                    }).inject(h2);

                    var subline = new Element('div', {
                        'class': 'subline'
                    }).inject(div);

                    new Element('span', {
                        text: item.first_name+' '+item.last_name
                    }).inject(subline);

                    new Element('span', {
                        text: ' ('+item.email+')'
                    }).inject(subline);

                    var subline = new Element('div', {
                        'class': 'subline',
                        style: 'color: silver',
                        text: item.groups_name
                    }).inject(div);

                }.bind(this));
            }

            if (typeOf(pItems.groups) == 'array' && pItems.groups.length > 0){

                new Element('div', {
                    'class': 'ka-list-combine-splititem',
                    text: t('Groups')
                }).inject(this.left);

                Array.each(pItems.groups, function(item){

                    var div = new Element('div', {
                        'class': 'ka-list-combine-item'
                    })
                    .addEvent('click', this.loadRules.bind(this, 'group', item, false))
                    .inject(this.left);

                    this.groupDivs[item.id] = div;

                    var h2 = new Element('h2', {
                        text: item.name
                    }).inject(div);

                    new Element('span', {
                        text: ' ('+item.ruleCount+')',
                        style: 'color: silver; font-size: 12px; font-weight: normal;'
                    }).inject(h2);


                }.bind(this));

            }

        }

    },

    loadRules: function(pType, pItem, pForce){

        if (!pForce && typeOf(this.currentTargetType) != 'null' && this.unsavedContent){
            this.win._confirm(t('There is unsaved content. Continue?'), function(a){
                if (a)
                    this.loadRules(pType, pItem, true);
            }.bind(this));
            return;
        }

        var div = pType=='group'? this.groupDivs[pItem.id]:this.userDivs[pItem.id];
        if (!div) return;

        this.left.getElements('.ka-list-combine-item').removeClass('active');
        div.addClass('active');

        var title;
        if (pType == 'user')
            title = t('User %s').replace('%s', pItem['username']);
        else
            title = t('Group %s').replace('%s', pItem['name']);

        this.win.setTitle(title);

        this.loadAcls(pType, pItem.id);

    },

    loadAcls: function(pType, pId){

        if (this.lastOverlay)
            this.lastOverlay.destroy();

        this.hideRules();

        if (pId == 1){

            this.tabs.hide();
            this.actions.hide();

            this.lastOverlay = new Element('div', {
                style: 'position: absolute; left: 0px; right: 0px; top: 0px; bottom: 0px; background-color: #bbb;',
                styles: {
                    opacity: 0.7,
                    paddingTop: 50,
                    textAlign: 'center'
                },
                text: t('User admin and the administration group has full access to anything.')
            }).inject(this.right);

            this.win.setLoading(false);
            return;
        }

        this.win.setLoading(true, null, {left: 216});

        if (this.lrAcls)
            this.lrAcls.cancel();

        this.currentTargetType = pType=='user'?0:1;
        this.currentTargetRsn = pId;

        this.lrAcls = new Request.JSON({
            url: _path+'admin/users/acl',
            noCache: true,
            onComplete: this.setAcls.bind(this)
        }).get({type: pType, id: pId});

    },

    hideRules: function(){
        this.objectConstraints.setStyle('display', 'none');
        this.objectRules.setStyle('display', 'none');

        this.entryPointList.getElements('a').removeClass('users-acl-entrypoint-rule-active');
        this.entryPointRuleContainer.empty();
    },

    showRules: function(){
        this.objectConstraints.setStyle('display', 'block');
        this.objectRules.setStyle('display', 'block');
    },

    updateEntryPointRules: function(){

        Object.each(this.currentEntrypointDoms, function(dom){
            if (dom.ruleIcon) dom.ruleIcon.destroy();
            if (dom.ruleLine) dom.ruleLine.destroy();
            if (dom.ruleLineChildern) dom.ruleLineChildern.destroy();
            delete dom.rule;
        });

        Array.each(this.currentAcls, function(rule){

            if (rule.object != 'system_entrypoint') return;

            if (this.currentEntrypointDoms[rule.constraint_code]){
                this.addEntryPointRuleToTree(rule);
            }

        }.bind(this));

    },

    clickEntryPointRule: function(pDom){

        if (pDom.rule){

            this.showEntrypointRule(pDom);

        } else {

            var rule = {
                object: 'system_entrypoint',
                constraint_type: 2,
                sub: 1,
                constraint_code: pDom.entryPath,
                access: 1,
                target_type: this.currentTargetType,
                target_id: this.currentTargetRsn
            };
            this.currentEntrypointDoms[pDom.entryPath] = pDom;
            this.currentAcls.push(rule);
            this.addEntryPointRuleToTree(rule);

            this.clickEntryPointRule(pDom);
        }

    },

    showEntrypointRule: function(pDom){

        this.entryPointRuleContainer.empty();

        var div = new Element('div', {
            'class': 'users-acl-entrypoint-rule'
        })
        .inject(this.entryPointRuleContainer);

        var title = new Element('div',{
            text: pDom.get('text'),
            style: 'line-height: 14px; font-weight: bold; padding: 2px;'
        }).inject(div);

        pDom.getElement('img').clone().setStyles({
            position: 'relative',
            top: 4,
            marginRight: 1
        }).inject(title, 'top');

        var fieldContainer = new Element('div').inject(div);

        var fields = {

            access: {
                label: t('Access'),
                'default': 1,
                type: 'checkbox'
            },

            sub: {
                type: 'checkbox',
                'default': 1,
                label: t('With sub-items')
            }
        };

        var kaFields = new ka.Parse(fieldContainer, fields, {allTableItems:1, tableitem_title_width: 180}, {win: this.win});

        var deleteRule = new ka.Button([t('Delete rule'), '#icon-minus-5']).inject(fieldContainer);

        deleteRule.addEvent('click', this.deleteEntrypointRule.bind(this, pDom));

        kaFields.addEvent('change', function(){

            Array.each(this.currentAcls, function(acl, index){
                if (acl.object != 'system_entrypoint') return;

                if (acl.constraint_code != pDom.entryPath) return;

                this.currentAcls[index] = Object.merge(pDom.rule, kaFields.getValue());
                pDom.rule = this.currentAcls[index];
            }.bind(this));

            this.updateEntryPointRules();

        }.bind(this));

    },

    deleteEntrypointRule: function(pDom){

        this.entryPointList.getElements('a').removeClass('users-acl-entrypoint-rule-active');
        this.entryPointRuleContainer.empty();

        var index = this.currentAcls.indexOf(pDom.rule);
        this.currentAcls.splice(index, 1);

        delete pDom.rule;

        this.updateEntryPointRules();

    },

    addEntryPointRuleToTree: function(pRule){

        var dom = this.currentEntrypointDoms[pRule.constraint_code];

        if (dom.ruleIcon) dom.ruleIcon.destroy();
        if (dom.ruleLine) dom.ruleLine.destroy();
        if (dom.ruleLineChildern) dom.ruleLineChildern.destroy();

        var accessIcon = pRule.access==1?'accept':'exclamation';
        var accessColor = pRule.access==1?'green':'red';

        dom.rule = pRule;

        dom.ruleIcon = new Element('img', {
            src: _path + 'media/admin/images/icons/'+accessIcon+'.png',
            style: 'position: absolute; left: -13px; top: 4px; width: 10px;'
        }).inject(dom);

        if (pRule.sub == 1){

            dom.ruleLine = new Element('div', {
                style: 'position: absolute; left: -9px; height: 4px; top: 14px; width: 1px; border-right: 1px solid '+accessColor
            }).inject(dom);

            var childContainer = dom.getNext();
            if (!childContainer) return;

            dom.ruleLineChildern = new Element('div', {
                style: 'position: absolute; left: -4px; bottom: 0px; top: 0px; width: 1px; border-right: 1px solid '+accessColor
            }).inject(childContainer);
        }

    },

    updateObjectRulesCounter: function(){


        var counter = {};

        Array.each(this.currentAcls, function(acl){

            if (counter[acl.object])
                counter[acl.object]++;
            else
                counter[acl.object] = 1;

        }.bind(this));

        Object.each(this.objectDivs, function(dom, key){
            if (!counter[key]) counter[key] = 0;
            dom.count.set('text', ' ('+counter[key]+')');
        });


    },

    setAcls: function(pResponse){

        if (pResponse.error) throw pResponse.error;

        if (!pResponse.data) pResponse.data = [];

        this.currentAcls = pResponse.data;
        this.loadedAcls = pResponse.data.clone(this.currentAcls);

        this.updateObjectRulesCounter();
        this.updateEntryPointRules();

        this.objectList.getElements('.ka-list-combine-item').removeClass('active');

        this.tabs.show();
        this.actions.show();
        this.win.setLoading(false);
        this.unsavedContent = false;
    },

    save: function(){

        if (this.lastSaveRq) this.lastSaveRq.cancel();

        this.btnSave.startTip(t('Saving ...'));
        this.win.setBlocked(true);

        var req = {
            targetType: this.currentTargetType,
            targetId: this.currentTargetRsn,
            rules: this.currentAcls
        };

        this.lastSaveRq = new Request.JSON({url: _path+'admin/users/acl', onComplete: function(){

            this.unsavedContent = false;
            this.btnSave.stopTip(t('Saved'));
            this.win.setBlocked(false);

        }.bind(this)}).post(req);

    }


});
