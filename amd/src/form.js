/* eslint-disable */
import $ from 'jquery';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import {get_strings} from 'core/str';

var debug = false;


async function xhrRequest(xhrdata) {
  let ret;
  try {
    ret = await $.post(document.location.href, xhrdata);
  } catch (e) {
    var error = '';

    // if the error is a html output get the error from the html code
    if (e.responseText && typeof e.responseText == 'string' && e.responseText[0] === '<') {
      error = $(e.responseText).find('.errormessage').text();
    }

    if (!error) {
      error = 'Unknown error!';
    }

    Notification.exception(new Error(error));

    throw e;
  }

  if (ret.error) {
    if (ret.exception) {
      Notification.exception(ret.exception);

      // TODO: like this?
      throw ret.exception;
    } else {
      alert(ret.error);

      // TODO: like this?
      throw ret;
    }
  } else {
    return ret;
  }
}

/**
 * load the form into a modal by use of ajax.
 * @param {DOMobject} sender
 * @param {object} modaldata
 */
export async function loadModal(sender, modaldata = null) {
  const $pane = $(sender).closest('.table-sql-modal');
  modaldata = modaldata || $pane.data('modaldata');

  const xhrdata = {
    ...modaldata.xhrdata,
    is_xhr: 1,
    table_sql_action: 'form_show'
  };

  if (debug) {
    console.log('Loading modal for params', xhrdata);
  }

  var response = await xhrRequest(xhrdata);

  ModalFactory.create({
    // body gets set in updateModal()
    body: '', // response.form,
    large: true,
    removeOnClose: true,
    title: response.modal_title,
    type: ModalFactory.types.SAVE_CANCEL,
  }).then(function (modal) {
    updateModal(modaldata, modal, response, $pane);
    modal.show();
    modal.getRoot().on(ModalEvents.save, (e) => {
      e.preventDefault();
      send(modaldata, modal, $pane);
    });

    // TODO: on cancel remove modal?!?
  });
}

/**
 * Update modal if a form was loaded into it.
 * @param {object} modaldata
 * @param {object} modal
 * @param {object} response
 * @param {DOMobject} pane
 */
function updateModal(modaldata, modal, response, $pane) {
  $(modal.getRoot()).find('.modal-body').html(response.form);

  // set default values
  for (const [key, value] of Object.entries(modaldata.values || [])) {
    $(modal.getRoot()).find('input[type="text"], select, textarea').filter('[name=\"' + key + '\"], select[name=\"' + key + '\"]').val(value);
    $(modal.getRoot()).find('radio[name=\"' + key + '\"][value=\"' + value + '\"').prop('checked', true);
  }

  let showfields = modaldata.showfields;

  let $form = $(modal.getRoot()).find('form');

  // only show the field of the current column
  // if (showfields && showfields.length == 0) {
  //   // Find the closest element and filter for the class starting with 'local_table_sql-column-'
  //   var closestNode = $pane.closest('[class*="local_table_sql-column-"]');
  //
  //   // Extract the column name by matching the class pattern
  //   var columnName = closestNode.attr('class')?.match(/local_table_sql-column-(\S+)/)?.[1];
  //
  //   if (columnName) {
  //     if ($form.find('input[name="' + columnName + '"]').length) {
  //       // column exists, hide all others
  //       showfields = [columnName];
  //     }
  //   }
  // }

  if (showfields && showfields.length > 0) {
    $form.find('[name]').each(function () {
      let name = $(this).attr('name');
      if (showfields.indexOf(name) === -1) {
        $(this).closest('.row')
          .not('.has-danger') // bei einem Fehler dieses Feld nicht ausblenden
          .addClass('hidden');
      }
    });
  }

  // remove the button row
  $form.find('#id_submitbutton').closest('.row').remove();
  // add own submit, so the form can get submitted
  $form.append('<input type="submit"  class="hidden"/>');
  $form.submit(function (e) {
    // prevent submit post
    e.preventDefault();

    // trigger modal event
    modal.getRoot().trigger(ModalEvents.save);
  });

  // Run any required javascript.
  if (response.pageendcode) {
    let script = $('<script>').data('added', Date.now()).html(response.pageendcode);
    if (debug) {
      console.log('appending page end code', script);
    }
    $('body').append(script);
  }
}

/**
 * Actually send the data. Depending on the result. update the form or close the modal and update the cell.
 * @param {object} modaldata
 * @param {object} modal
 * @param {DOMobject} $pane
 *
 *
 */
async function send(modaldata, modal, $pane) {
  let form = $(modal.getRoot()).find('form');

  const xhrdata = {
    ...modaldata.xhrdata,
    ...$(form).serializeArray().reduce(function (obj, item) {
      // for arrays (multiselect etc.) use an array
      if (item.name.match(/\[\]$/)) {
        if (!obj[item.name]) {
          obj[item.name] = [];
        }
        obj[item.name].push(item.value);
      } else {
        obj[item.name] = item.value;
      }

      return obj;
    }, {}),
    is_xhr: 1,
    table_sql_action: 'form_save'
  };
  if (debug) {
    console.log('Store row', xhrdata);
  }

  var response = await xhrRequest(xhrdata);
  if (debug) {
    console.log('Response', response);
  }

  if (response.form) {
    // there was an error, show form again
    updateModal(modaldata, modal, response, $pane);
    return;
  }

  // remove the modal content (the form), so it doesn't trigger any "do you want to save?" alerts
  $(modal.getRoot()).find('.modal-body').html('');

  if ($pane.length && response.colreplace) {
    // replacing single value in table
    $pane.replaceWith($(response.colreplace));
  } else {
    // reload the table
    window.table_sql_reload();
  }

  modal.hide();
}

export async function deleteRow(event, row) {
  const [
    str_confirmdeleterecord
  ] = await get_strings([
    {
      key: 'confirmdeleterecord',
      component: 'local_table_sql'
    }
  ]);

  if (!confirm(str_confirmdeleterecord)) {
    return;
  }

  console.log(row);

  const xhrdata = {
    rowid: row.original.id,
    is_xhr: 1,
    table_sql_action: 'delete_row'
  };

  await xhrRequest(xhrdata);

  window.table_sql_reload();
}
