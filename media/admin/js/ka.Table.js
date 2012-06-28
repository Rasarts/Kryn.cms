ka.Table = new Class({

    Implements: [Options, Events],


    tableBody: false,
    safe: false,

    options: {
        absolute: true, //full size
        selectable: false,
        multi: false
    },

    initialize: function (pColumns, pOpts) {

        this.setOptions(pOpts);

        if (this.options.absolute == false) {
            this.main = new Element('div', {
                style: 'position: relative;'
            });
        } else {
            this.main = new Element('div', {
                style: 'position: absolute; left: 0px; right: 0px; bottom: 0px; top: 0px;'
            });
        }

        if (this.options.hover || typeOf(this.options.hover) == 'null'){
            this.main.addClass('ka-Table-hover');
        }

        if (this.options.alignTop || typeOf(this.options.alignTop) == 'null'){
            this.main.addClass('ka-Table-alignTop');
        }

        if (this.options.selectable == true){
            this.main.addEvent('click', function(e){

                if (!e.target) return;

                if (e.target.get('tag') == 'td'){
                    if (!this.multi || (this.multi && (e.ctrl != true && e.meta != true)))
                        e.target.getParent().getParent().getChildren('tr').removeClass('ka-table-body-item-active');
                    e.target.getParent().addClass('ka-table-body-item-active');
                    this.fireEvent('select');
                } else {
                    e.target.getElements('tr').removeClass('ka-table-body-item-active');
                    this.fireEvent('deselect');
                }

            }.bind(this));
        }

        if (pColumns && typeOf(pColumns) == 'array') {
            this.setColumns(pColumns);
        }

    },

    deselect: function () {
        this.tableBody.getElements('tr').removeClass('active');
        this.tableBody.getElements('tr').removeClass('ka-table-body-item-active');
    },

    selected: function () {
        return this.tableBody.getElement('tr.active') || this.tableBody.getElement('tr.ka-table-body-item-active');
    },

    inject: function (pTo, pWhere) {
        this.main.inject(pTo, pWhere);
        return this;
    },

    hide: function () {
        this.main.setStyle('display', 'none');
    },

    show: function () {
        this.main.setStyle('display', 'block');
    },

    toElement: function () {
        return this.main;
    },

    loading: function (pActivate) {

        if (!pActivate && this.loadingOverlay) {

            if (this.tableBody) {
                this.tableBody.setStyle('opacity', 1);
            }

            this.loadingOverlay.destroy();

        } else if (pActivate) {

            if (this.tableBody) {
                this.tableBody.setStyle('opacity', 0.5);
            }

            if (this.loadingOverlay) {
                this.loadingOverlay.destroy();
            }

            this.loadingOverlay = new Element('div', {
                style: 'position: absolute; left: 0px; top: 21px; right: 0px; bottom: 0px; text-align: center;'
            }).inject(this.body);

            new Element('img', {
                src: _path + PATH_MEDIA + '/admin/images/ka-tooltip-loading.gif'
            }).inject(this.loadingOverlay);

            new Element('div', {
                html: _('Loading ...')
            }).inject(this.loadingOverlay);

            var size = this.loadingOverlay.getSize();
            this.loadingOverlay.setStyle('padding-top', (size.y / 2) - 50);

        }


    },

    setColumns: function (pColumns) {
        this.columns = pColumns;

        if (!pColumns && typeOf(pColumns) != 'array') {
            return;
        }

        if (this.head) this.head.destroy();

        this.head = new Element('div', {
            'class': 'ka-Table-head-container'
        }).inject(this.main);

        if (this.options.absolute == false) {
            this.head.setStyle('position', 'relative');
        }

        this.tableHead = new Element('table', {
            'class': 'ka-Table-head',
            cellpadding: 0, cellspacing: 0
        }).inject(this.head, 'top');

        var tr = new Element('tr').inject(this.tableHead);
        pColumns.each(function (column, index) {

            new Element('th', {
                html: column[0],
                'class': 'gradient',
                width: (column[1]) ? column[1] : null
            }).inject(tr);

        }.bind(this));


        if (this.body) this.body.destroy();

        if (this.options.absolute == false) {
            this.body = new Element('div', {
                style: 'position: relative;'
            }).inject(this.main);
        } else {
            this.body = new Element('div', {
                'class': 'ka-Table-body-container'
            }).inject(this.main);
        }

        this.tableBody = new Element('table', {
            'class': 'ka-Table-body',
            cellpadding: 0, cellspacing: 0
        }).inject(this.body, 'bottom');

    },

    addRow: function (pValues, pIndex) {

        if (!pIndex) {
            pIndex = this.tableBody.getElements('tr').length + 1;
        }

        if (!this.classTr || this.classTr == 'two') {
            this.classTr = 'one';
        } else {
            this.classTr = 'two';
        }

        var row = pValues;

        var tr = new Element('tr', {
            'class': this.classTr
        }).inject(this.tableBody);
        tr.store('rowIndex', pIndex);

        var count = 0;
        this.columns.each(function (column, index) {

            var html = "";
            if (($type(row[count]) == 'string' || $type(row[count]) == 'number') && !row[count].inject) {
                html = row[count];
            }

            if (index > 0) {
                width = index;
            }


            var td = new Element('td', {
                width: (column[1]) ? column[1] : null
            }).inject(tr);

            //TODO, make default 'text'
            if (this.safe) {
                if (column[2] == 'html') {
                    td.set('html', html);
                } else if(typeOf(column[2]) == 'string' && column[2].substr(0,11) == 'javascript:'){
                    try {
                        var value = html;
                        html = eval(column[2].substr(11));
                    } catch(e) {
                        throw _('Error in column %id (%label) of ka.Table column definition:')
                                  .replace('%id', index)
                                  .replace('%label', column[0])+' '+e;
                    }
                    td.set('html', html);
                } else {
                    td.set('text', html);
                }
            } else {
                td.set('html', html);
            }

            td.store('rowIndex', pIndex);

            if (row[count] && row[count].inject) {
                row[count].inject(td);
            }

            count++;

        }.bind(this));

        return tr;
    },

    empty: function () {

        this.tableBody.empty();

    },

    setValues: function (pValues) {

        this.tableBody.empty();

        if (typeOf(pValues) == 'array') {
            pValues.each(function(row){
                this.addRow(row);
            }.bind(this));
        }
    }


});
