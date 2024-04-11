function local_table_sql_form_action(fingerprint, formid) {
    let sender = $('#table-sql-' + fingerprint + '>a');
    if (sender.length === 0) {
        console.error('No table-sql-modal for fingerprint found', fingerprint);
    }
    require(['local_table_sql/form'], function (f) {
        f.rowAction(sender, formid);
    });
}