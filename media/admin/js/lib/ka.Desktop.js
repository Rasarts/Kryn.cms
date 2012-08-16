var icons = [];

ka.Desktop = new Class({

    initialize: function (pContainer) {

        this.container = new Element('div', {
            style: 'position: absolute; left: 0px; right: 0px; top: 0px; bottom: 0px;'
        }).inject(pContainer);
        this.widgets = [];

        this.icons = []; //dom object
        this._icons = []; //data object

        this.container.addEvent('mousedown', function (e) {
            this.closeContext();

            if (this.icons) {
                this.icons.each(function (icon) {
                    icon.removeClass('ka-desktop-icon-active');
                });
            }

            if (e.rightClick) {
                this.onContext(e, true);
            }

        }.bind(this));
    },

    closeContext: function () {
        if (this.oldContext) this.oldContext.destroy();
    },

    addWidget: function (pWidget) {

        pWidget.desktop = 1;
        var widget = new ka.Widget(pWidget, this.container);

        widget.addEvent('change', function () {
            this.saveWidgets();
        }.bind(this));

        widget.addEvent('close', function () {
            this.widgets.erase(widget);
            this.widgets.clean();
            this.saveWidgets();
        }.bind(this));

        this.widgets.include(widget);

    },

    onContext: function (pEvent, pOnWorkspace) {
        this.closeContext();

        this.oldContext = new Element('div', {
            'class': 'ka-desktop-context',
            styles: {
                left: pEvent.client.x,
                'top': pEvent.client.y
            }
        }).inject(document.body);


        if (!pOnWorkspace) {
            new Element('a', {
                html: t('Open')
            }).addEvent('click', function () {
                this.openSelected();
            }.bind(this)).inject(this.oldContext);
        }

        /*
         new Element('a', {
         ///text: _('New')
         }).inject( this.oldContext );
         */

        var choosenIcons = false;
        var count = 0;
        this._icons.each(function (icon) {
            if (icon.icon.get('class').search('active') > 0) {
                choosenIcons = true;
                count++;
            }
        });

        if (choosenIcons) {
            new Element('a', {
                html: _('Remove')
            }).addEvent('click', function () {
                this.deleteSelected();
            }.bind(this)).inject(this.oldContext);
        }

        if (count == 1) {
            new Element('a', {
                html: _('Rename')
            }).addEvent('click', function () {
                this.renameIcon();
            }.bind(this)).inject(this.oldContext);
        }

        if (pOnWorkspace) {
            this.btnSettings = new Element('a', {
                html: _('Settings')
            }).addEvent('click', function () {
                this.closeContext();
                ka.wm.openWindow('admin', 'system/desktopSettings');
            }.bind(this)).inject(this.oldContext);
        }

    },

    openSelected: function () {
        this.closeContext();
        this._icons.each(function (item, id) {
            if (item.icon && item.icon.get('class').search('-active') > 0) {
                item.icon.fireEvent('dblclick');
            }
        });
    },

    renameIcon: function () {

        var myitem = null;
        this._icons.each(function (item, id) {
            if (item.icon && item.icon.get('class').search('-active') > 0) {
                myitem = item;
            }
        });
        this.closeContext();
        var name = prompt(_('Name:'), myitem.title);
        if (!name) return;
        myitem.title = name;
        myitem.icon.getElement('div.ka-desktop-icon-title').set('text', name);
        this.save();
    },

    deleteSelected: function () {
        this.closeContext();
        if (!confirm(_('Really remove?'))) return;
        this._icons.each(function (item, id) {
            if (item.icon && item.icon.get('class').search('-active') > 0) {
                item.icon.destroy();
                this._icons.erase(item);
            }
        }.bind(this));
        this.save();
    },

    loadIcons: function (pIcons) {
        this.icons = []; //dom object
        this._icons = []; //data object

        if (pIcons == null) return;
        var myicons = pIcons;

        if (myicons.each) {
            myicons.each(function (icon) {
                this.addIcon(icon);
            }.bind(this));
        }
    },

    clear: function(){
      this.container.empty();
    },

    load: function () {
        if (this.lastLoad) {
            this.lastLoad.cancel();
        }
        if (this.lastWLoad) {
            this.lastLoad.cancel();
        }

        this.clear();

        this.lastLoad = new Request.JSON({url: _path + 'admin/backend/desktop', noCache: 1, onComplete: function (pRes) {
            this.loadIcons(pRes.data);
        }.bind(this)}).get();

        this.lastWLoad = new Request.JSON({url: _path + 'admin/backend/widgets', noCache: 1, onComplete: function (pRes) {
            this.loadWidgets(pRes.data);
        }.bind(this)}).get();
    },

    loadWidgets: function (pWidgets) {
        if (pWidgets && pWidgets.each) {
            pWidgets.each(function (widget) {

                widget.desktop = true;
                this.addWidget(widget);

            }.bind(this));
        }
    },

    saveWidgets: function () {
        if (this.lastWSave) {
            this.lastWSave.cancel()
        }

        var widgets = [];
        this.widgets.each(function (item) {
            widgets.include(item.getValue());
        });

        this.lastWSave = new Request.JSON({url: _path + 'admin/backend/widgets', noCache: 1, onComplete: function () {

        }}).post({widgets: JSON.encode(widgets)});
    },

    save: function () {
        if (this.lastSave) {
            this.lastSave.cancel();
        }

        this._icons.each(function (item, id) {
            if (!item.icon || !item.icon.getStyle) {
                this._icons[id] = null;
            }
        }.bind(this));

        this._icons.clean();
        var nIcons = Array.clone(this._icons);

        nIcons.each(function (item, i) {
            delete item.icon;
        });

        var icons = JSON.encode(nIcons);

        this.lastSave = new Request.JSON({url: _path + 'admin/backend/desktop', noCache: 1, onComplete: function () {

        }}).post({icons: icons});
    },

    addIcon: function (pIcon) {
        var _this = this;

        //todo
        pIcon.icon = 'admin/images/icon-default.png';

        var m = new Element('div', {
            'class': 'ka-desktop-icon icon-link-5'
        }).inject(this.container);

        m.addEvent('mousedown', function(e){
            this.mousedown.call(this, e, m);
        }.bind(this));


        m.addEvent('dblclick', function () {
            ka.wm.openWindow(pIcon.module, pIcon.code, null, null, pIcon.params);
        });

        pIcon.icon = m;
        this._icons.include(pIcon);


        //todo
        // set position
        if (pIcon.left > 0 || pIcon['top'] > 0) {
            m.setStyle('left', pIcon.left);
            m.setStyle('top', pIcon['top']);
        } else {
            m.setStyle('left', 0);
            m.setStyle('top', 0);
        }

        m.makeDraggable({
            container: this.container,
            snap: 1,
            grid: 10,
            onComplete: function (el) {
                pIcon.left = el.getStyle('left').toInt();
                pIcon['top'] = el.getStyle('top').toInt();
                this.save();
            }.bind(this)
        });

        this.icons.include(m);

        new Element('div', {
            'class': 'ka-desktop-icon-title',
            text: pIcon.title
        }).inject(m);
    },

    mousedown: function (pEvent, pElement) {
        this.closeContext();

        var count = 0;
        this._icons.each(function (item, id) {
            if (item.icon && item.icon.hasClass('ka-desktop-icon-active')) {
                count++;
            }
        });


        if (!pEvent.control && !pEvent.rightClick) {
            this.icons.each(function (icon) {
                icon.removeClass('ka-desktop-icon-active');
            });
        }

        if (!pEvent.rightClick && pElement.hasClass('ka-desktop-icon-active')) {
            pElement.removeClass('ka-desktop-icon-active');
        } else {
            pElement.addClass('ka-desktop-icon-active');
        }

        if (pEvent.rightClick) {
            this.onContext(pEvent);
        }

        pEvent.stop();
        return false;
    }
});
