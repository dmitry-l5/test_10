<?php

function controller_user($act, $d) {
    if ($act == 'delete_window') return User::delete_window($d);
    if ($act == 'delete_user') return User::delete_user($d);
    if ($act == 'edit_window') return User::user_edit_window($d);
    if ($act == 'edit_update') return User::user_edit_update($d);
    return '';
}