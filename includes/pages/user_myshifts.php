<?php

use Engelsystem\Database\DB;

/**
 * @return string
 */
function myshifts_title()
{
    return _('My shifts');
}

/**
 * Zeigt die Schichten an, die ein Benutzer belegt
 *
 * @return string
 */
function user_myshifts()
{
    global $user, $privileges;
    $request = request();

    if (
        $request->has('id')
        && in_array('user_shifts_admin', $privileges)
        && preg_match('/^\d{1,}$/', $request->input('id'))
        && count(DB::select('SELECT `UID` FROM `User` WHERE `UID`=?', [$request->input('id')])) > 0
    ) {
        $shift_entry_id = $request->input('id');
    } else {
        $shift_entry_id = $user['UID'];
    }

    $shifts_user = DB::selectOne('SELECT * FROM `User` WHERE `UID`=? LIMIT 1', [$shift_entry_id]);
    if ($request->has('reset')) {
        if ($request->input('reset') == 'ack') {
            User_reset_api_key($user);
            success(_('Key changed.'));
            redirect(page_link_to('users', ['action' => 'view', 'user_id' => $shifts_user['UID']]));
        }
        return page_with_title(_('Reset API key'), [
            error(
                _('If you reset the key, the url to your iCal- and JSON-export and your atom feed changes! You have to update it in every application using one of these exports.'),
                true
            ),
            button(page_link_to('user_myshifts', ['reset' => 'ack']), _('Continue'), 'btn-danger')
        ]);
    } elseif ($request->has('edit') && preg_match('/^\d+$/', $request->input('edit'))) {
        $shift_entry_id = $request->input('edit');
        $shift = DB::selectOne('
                SELECT
                    `ShiftEntry`.`freeloaded`,
                    `ShiftEntry`.`freeload_comment`,
                    `ShiftEntry`.`Comment`,
                    `ShiftEntry`.`UID`,
                    `ShiftTypes`.`name`,
                    `Shifts`.*,
                    `Room`.`Name`,
                    `AngelTypes`.`name` AS `angel_type`
                FROM `ShiftEntry`
                JOIN `AngelTypes` ON (`ShiftEntry`.`TID` = `AngelTypes`.`id`)
                JOIN `Shifts` ON (`ShiftEntry`.`SID` = `Shifts`.`SID`)
                JOIN `ShiftTypes` ON (`ShiftTypes`.`id` = `Shifts`.`shifttype_id`)
                JOIN `Room` ON (`Shifts`.`RID` = `Room`.`RID`)
                WHERE `ShiftEntry`.`id`=?
                AND `UID`=?
                LIMIT 1
            ',
            [
                $shift_entry_id,
                $shifts_user['UID'],
            ]
        );
        if (count($shift) > 0) {
            $freeloaded = $shift['freeloaded'];
            $freeload_comment = $shift['freeload_comment'];

            if ($request->has('submit')) {
                $valid = true;
                if (in_array('user_shifts_admin', $privileges)) {
                    $freeloaded = $request->has('freeloaded');
                    $freeload_comment = strip_request_item_nl('freeload_comment');
                    if ($freeloaded && $freeload_comment == '') {
                        $valid = false;
                        error(_('Please enter a freeload comment!'));
                    }
                }

                $comment = strip_request_item_nl('comment');
                $user_source = User($shift['UID']);

                if ($valid) {
                    ShiftEntry_update([
                        'id'               => $shift_entry_id,
                        'Comment'          => $comment,
                        'freeloaded'       => $freeloaded,
                        'freeload_comment' => $freeload_comment
                    ]);

                    engelsystem_log(
                        'Updated ' . User_Nick_render($user_source) . '\'s shift ' . $shift['name']
                        . ' from ' . date('Y-m-d H:i', $shift['start'])
                        . ' to ' . date('Y-m-d H:i', $shift['end'])
                        . ' with comment ' . $comment
                        . '. Freeloaded: ' . ($freeloaded ? 'YES Comment: ' . $freeload_comment : 'NO')
                    );
                    success(_('Shift saved.'));
                    redirect(page_link_to('users', ['action' => 'view', 'user_id' => $shifts_user['UID']]));
                }
            }

            return ShiftEntry_edit_view(
                User_Nick_render($shifts_user),
                date('Y-m-d H:i', $shift['start']) . ', ' . shift_length($shift),
                $shift['Name'],
                $shift['name'],
                $shift['angel_type'],
                $shift['Comment'],
                $shift['freeloaded'],
                $shift['freeload_comment'],
                in_array('user_shifts_admin', $privileges)
            );
        } else {
            redirect(page_link_to('user_myshifts'));
        }
    }

    redirect(page_link_to('users', ['action' => 'view', 'user_id' => $shifts_user['UID']]));
    return '';
}
