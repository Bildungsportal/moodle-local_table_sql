define(
    ['jquery', 'core/ajax', 'core/modal_factory', 'core/modal_events', 'core/notification'],
    function ($, Ajax, ModalFactory, ModalEvents, Notification){
        return {
            debug: false,
            /**
             * load the form into a modal by use of ajax.
             * @param {DOMobject} sender
             * @param {string} formid
             */
            loadModal: function(sender, formid) {
                let SELF = this;

                // Parameters needed to construct the table_sql_form.
                let cb = $(sender).closest('div.table_sql_form_controlbox');
                let constructor = cb.data('constructor');
                constructor = atob(cb.data('constructor'));
                let params = {
                    'classname': atob(cb.data('classname')),
                    'constructor': JSON.parse(constructor),
                    'col': $(sender).closest('.table-sql-modal').find('.table-sql-modal-value').data('col'),
                    'formid': formid
                };

                // Values needed to construct the moodleform.
                let pane = $(sender).closest('.table-sql-modal');
                params['rowids'] = {};

                $(pane).find('.table-sql-modal-rowids .dataset').each(function(){
                    let field = $(this).data('field');
                    let value = $(this).html();
                    params['rowids'][field] = value;
                });
                if (SELF.debug) {
                    console.log('Loading modal for params', params);
                }
                let ajaxdata = { 'rowdata': JSON.stringify(params) };
                Ajax.call([{
                    methodname: 'local_table_sql_receiver',
                    args: ajaxdata,
                    done: function (response) {
                        if (SELF.debug) {
                            console.log('Response', response);
                        }
                        ModalFactory.create(
                            {
                                body: response.form,
                                large: true,
                                removeOnClose: true,
                                title: $(pane).data('title'),
                                type: ModalFactory.types.SAVE_CANCEL,
                            }
                        ).then(
                            function (modal) {
                                SELF.updateModal(params, modal, response, pane);
                                modal.show();
                                modal.getRoot().on(ModalEvents.save, (e) => {
                                    e.preventDefault();
                                    SELF.send(params, modal, pane);
                                });
                            }
                        );
                    },
                    fail: Notification.exception
                }]);
            },
            /**
             * Update modal if a form was loaded into it.
             * @param {object} params
             * @param {object} modal
             * @param {object} response
             * @param {DOMobject} pane
             */
            updateModal: function(params, modal, response, pane) {
                let SELF = this;
                // Update modal content or close it.
                if (response.form == '') {
                    $(modal.getRoot()).find('.modal-body').html('');
                    $(pane).replaceWith($(response.colreplace));
                    modal.hide();
                } else if (response.form != '') {
                    $(modal.getRoot()).find('.modal-body').html(response.form);
                }
                // Hide fields we do not need.
                let showfields = JSON.parse(atob($(pane).data('fields')));
                if (SELF.debug) {
                    console.log('showfields', showfields);
                }
                let form = $(modal.getRoot()).find('form');
                $(form).find('#id_submitbutton').closest('.form-group.row').addClass('hidden');
                if (showfields.length > 0) {
                    $(form).find('[name]').each(function () {
                        let name = $(this).attr('name');
                        if (showfields.indexOf(name) === -1) {
                            $(this).closest('.form-group.row').addClass('hidden');
                        }
                    });
                }
                // Run any required javascript.
                if (typeof response.pageendcode !== 'undefined') {
                    let script = $('<script>').data('added', Date.now()).html(response.pageendcode);
                    if (SELF.debug) {
                        console.log('appending page end code', script);
                    }
                    $('body').append(script);
                }
            },
            /**
             * Actually send the data. Depending on the result. update the form or close the modal and update the cell.
             * @param {object} params
             * @param {object} modal
             * @param {DOMobject} pane
             *
             *
             */
            send: function(params, modal, pane) {
                let SELF = this;
                let sendparams = params;
                let form = $(modal.getRoot()).find('form');
                sendparams['formdata'] = $(form).serializeArray();
                if (SELF.debug) {
                    console.log('Store row', sendparams);
                }
                sendparams = { 'rowdata': JSON.stringify(sendparams) };
                Ajax.call([{
                    methodname: 'local_table_sql_receiver',
                    args: sendparams,
                    done: function (response) {
                        if (SELF.debug) {
                            console.log('Response', response);
                        }
                        SELF.updateModal(params, modal, response, pane);
                    },
                    fail: Notification.exception
                }]);
            }
        };
    }
);
