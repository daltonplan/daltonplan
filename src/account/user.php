<?php

declare(strict_types=1);

namespace account;

use core\plan;
use core\team;
use core\team_user;

use plan\register;

use DB\SQL;

use frame\base;
use frame\db;
use frame\id;
use frame\log;
use frame\password;
use frame\session;
use frame\time;

abstract class user
{
    const latest = 0;
    const table = 'user';
    const list = 'user_list';
    const student_list = 'student_list';
    const coach_list = 'coach_list';
    const amount = 'amount';
    const students = 'students';
    const coaches = 'coaches';

    const handle_length = 'user_handle_length';

    const id = db::id;
    const handle = db::handle;
    const email = 'email';
    const verified = 'verified';
    const last_name = 'last_name';
    const first_name = 'first_name';
    const absent = 'absent';
    const pin = 'pin';
    const pin_reset = 'pin_reset';
    const pin_fail = 'pin_fail';
    const role = 'role';
    const creator = 'creator';
    const request = 'request';
    const active = db::active;
    const version = db::version;
    const created = db::created;
    const updated = db::updated;

    const all = user::id . ','
        . user::handle  . ','
        . user::email . ','
        . user::verified . ','
        . user::last_name . ','
        . user::first_name . ','
        . user::absent . ','
        . user::pin . ','
        . user::pin_reset . ','
        . user::pin_fail . ','
        . user::role . ','
        . user::creator . ','
        . user::request . ','
        . user::active . ','
        . user::version . ','
        . user::created . ','
        . user::updated;

    // active
    const active_on      = 1;
    const active_off     = 0;

    // role
    const role_student   = 0; // default
    const role_coach     = 1;
    const role_moderator = 2;
    const role_admin     = 3;
    const role_owner     = 4;

    // creator
    const creator_owner  = -1;
    const creator_none   =  0;

    // absent
    const absent_off     = 0;
    const absent_on      = 1;

    // pin_reset
    const pin_reset_off  = 0;
    const pin_reset_on   = 1;

    // email
    const email_none = '';

    // request
    const request_none   = 0;

    // verified
    const verified_off   = 0;
    const verified_on    = 1;

    static function create(\Base $fw): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . db::prefix() . user::table . '` (
                `' . user::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . user::handle . '` varchar(' . $fw->get(user::handle_length) . ') NOT NULL,
                `' . user::email . '` varchar(' . $fw->get('max_email_length') . ') NOT NULL,
                `' . user::verified . '` int(11) NOT NULL DEFAULT 0,
                `' . user::last_name . '` varchar(' . $fw->get('max_name_length') . ') NOT NULL,
                `' . user::first_name . '` varchar(' . $fw->get('max_name_length') . ') NOT NULL,
                `' . user::absent . '` int(11) NOT NULL DEFAULT 0,
                `' . user::pin . '` varchar(255) DEFAULT NULL,
                `' . user::pin_reset . '` int(11) NOT NULL DEFAULT 0,
                `' . user::pin_fail . '` int(11) NOT NULL DEFAULT 0,
                `' . user::role . '` int(11) NOT NULL DEFAULT 0,
                `' . user::creator . '` int(11) NOT NULL DEFAULT 0,
                `' . user::request . '` int(11) NOT NULL DEFAULT 0,
                `' . user::active . '` int(11) NOT NULL DEFAULT 1,
                `' . user::version . '` int(11) NOT NULL DEFAULT ' . user::latest . ',
                `' . user::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                `' . user::updated . '` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`' . user::id . '`) USING BTREE,
                UNIQUE KEY `' . user::handle . '` (`' . user::handle . '`) USING BTREE,
                KEY `' . user::email . '` (`' . user::email . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    // ---

    static function set(\Base $fw, int $user_id): bool
    {
        $value = service::get_user($fw, $user_id);
        if (empty($value))
            return false;

        user::set_register_list($fw, $value, $fw->get(plan::list));

        $fw->set(user::table, $value);

        return true;
    }

    static function set_by_handle(\Base $fw, string $user_handle): bool
    {
        $value = user::get_by_handle($fw->db, $user_handle);
        if (empty($value))
            return false;

        $fw->set(user::table, $value);

        return true;
    }

    static function set_list(\Base $fw): void
    {
        $user_list = user::get_list($fw->db);
        $fw->set(user::list, $user_list);
    }

    static function set_list_detail(\Base $fw): void
    {
        $user_list = user::get_list($fw->db);

        user::set_list_info($fw, $user_list);

        $fw->set(user::list, $user_list);
    }

    static function set_list_detail_by_team(\Base $fw, string $team_handle): bool
    {
        $team = team::get_by_handle($fw->db, $team_handle, team::id . ',' . team::name . ',' . team::icon . ',' . team::active);
        if (empty($team))
            return false;

        if ((int)$team[team::active] !== team::active_on)
            return false;

        $team_id = (int)$team[team::id];

        $fw->set(team::table, $team);

        $user_list = array();

        $team_user_list = team_user::get_list_by_team($fw->db, $team_id, team_user::user);
        foreach ($team_user_list as $tu_value) {
            $user_id = (int)$tu_value[team_user::user];

            $user = service::get_user($fw, $user_id);
            if (empty($user))
                continue;

            if ((int)$user[user::active] !== user::active_on)
                continue;

            $user_list[] = $user;
        }

        user::set_list_info($fw, $user_list);

        if (session::get_sort($fw)) {
            usort($user_list, function ($a, $b) {
                return $a[user::last_name] <=> $b[user::last_name];
            });
        }

        $fw->set(user::list, $user_list);

        return true;
    }

    static function set_register_list(\Base $fw, array &$user, array $plan_list): void
    {
        if (sizeof($plan_list) === 0)
            return;

        $user_id = (int)$user[user::id];

        $register_list = array();

        foreach ($plan_list as $plan) {
            $plan_id = (int)$plan[plan::id];

            $register = register::get_by_user($fw->db, $plan_id, $user_id, register::register);
            if (!empty($register)) {
                $register_time = strtotime($register[register::register]);

                $plan_time = $plan[plan::time];
                $now = time::get($fw, $plan_time);
                if ($now < $register_time) {
                    $register_item = array();

                    $register_item['time'] = time::get_format_time($fw, $register_time);
                    $register_item[plan::table] = $plan;

                    $register_list[] = $register_item;
                }
            }
        }

        if (!empty($register_list))
            $user[register::list] = $register_list;
    }

    static function set_list_info(\Base $fw, array &$user_list): void
    {
        // for register list
        $plan_list = $fw->get(plan::list);

        foreach ($user_list as $u_key => $u_value) {
            $user_id = (int)$u_value[user::id];

            $team_list = array();

            $team_user_list = team_user::get_list_by_user($fw->db, $user_id, team_user::team);
            foreach ($team_user_list as $tu_value) {
                $team_id = (int)$tu_value[team_user::team];

                $team = \core\service::get_team($fw, $team_id);
                if (empty($team))
                    continue;

                if ((int)$team[team::active] !== team::active_on)
                    continue;

                $team_list[] = $team;
            }

            $user_list[$u_key][team::list] = $team_list;

            // register
            user::set_register_list($fw, $user_list[$u_key], $plan_list);
        }
    }

    static function set_student_list(\Base $fw): void
    {
        $student_list = user::get_student_list($fw->db);
        $fw->set(user::student_list, $student_list);
    }

    static function set_coach_list(\Base $fw): void
    {
        $coach_list = user::get_coach_list($fw->db);
        $fw->set(user::coach_list, $coach_list);
    }

    static function set_search(\Base $fw, string $search_query): void
    {
        $fw->set('search_query', $search_query);

        $user_list = user::find($fw->db, $search_query);

        user::set_list_info($fw, $user_list);

        if (session::get_sort($fw)) {
            usort($user_list, function ($a, $b) {
                return $a[user::last_name] <=> $b[user::last_name];
            });
        }

        $fw->set(user::list, $user_list);

        $fw->set('search_count', sizeof($user_list));
    }

    // --- query

    static function hide_user(string &$get): bool
    {
        if ((session::get_user_role_fw() === user::role_owner))
            return false;

        if (!base::hide_user())
            return false;

        $get .= ' AND ' . user::handle . '!="' . base::get_hide_user() . '"';
        return true;
    }

    static function select(string $fields): string
    {
        return 'SELECT ' . (($fields === '') ? user::all : $fields) . ' FROM ' . db::prefix() . user::table . ' ';
    }

    static function new_handle(SQL $sql): string
    {
        while (true) {
            $handle = id::gen();
            if (empty(user::get_by_handle($sql, $handle)))
                return $handle;
        }

        return '';
    }

    static function count(SQL $sql): int
    {
        $get = 'SELECT count(*) as total FROM ' . db::prefix() . user::table . ' WHERE ' . user::active . '=' . user::active_on;

        user::hide_user($get);

        $result = $sql->exec($get);
        return empty($result) ? 0 : (int)$result[0]['total'];
    }

    static function check_active(SQL $sql, int $id): bool
    {
        $get = user::select(user::id) . 'WHERE ' . user::id . '=? AND ' . user::active . '=' . user::active_on;

        return !empty($sql->exec($get, $id));
    }

    static function exists(SQL $sql, string $email): bool
    {
        $get = user::select(user::id) . 'WHERE ' . user::email . '=? AND ' . user::active . '=' . user::active_on;

        return !empty($sql->exec($get, $email));
    }

    static function find(SQL $sql, string $query, string $fields = ''): array
    {
        $query = '%' . $query . '%';

        $get = user::select($fields) . 'WHERE ' . user::active . '=' . user::active_on
            . ' AND LOWER(CONCAT(' . user::last_name . ', "", ' . user::first_name . ', "", ' . user::handle . ', "")) LIKE LOWER(?)';

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . user::last_name . '';
        else
            $get .= ' ORDER BY ' . user::updated . ' DESC, ' . user::handle;

        return $sql->exec($get, $query);
    }

    static function get(SQL $sql, int $id, string $fields = ''): array
    {
        $get = user::select($fields) . 'WHERE ' . user::id . '=?';

        return base::first($sql->exec($get, $id));
    }

    static function get_by_email(SQL $sql, string $email, string $fields = ''): array
    {
        $get = user::select($fields) . 'WHERE ' . user::email . '=?';

        return base::first($sql->exec($get, $email));
    }

    static function get_by_handle(SQL $sql, string $handle, string $fields = ''): array
    {
        $get = user::select($fields) . 'WHERE ' . user::handle . '=?';

        return base::first($sql->exec($get, $handle));
    }

    static function get_by_creator(SQL $sql, int $creator, string $fields = ''): array
    {
        $get = user::select($fields) . 'WHERE ' . user::creator . '=?';

        return $sql->exec($get, $creator);
    }

    static function get_list(SQL $sql, string $fields = ''): array
    {
        $get = user::select($fields) . 'WHERE ' . user::active . '=' . user::active_on;

        user::hide_user($get);

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . user::last_name;
        else
            $get .= ' ORDER BY ' . user::updated . ' DESC, ' . user::handle;

        return $sql->exec($get);
    }

    static function get_student_list(SQL $sql, string $fields = ''): array
    {
        $get = user::select($fields) . 'WHERE ' . user::active . '=' . user::active_on . ' AND ' . user::role . '=' . user::role_student;

        user::hide_user($get);

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . user::last_name;
        else
            $get .= ' ORDER BY ' . user::updated . ' DESC, ' . user::handle;

        return $sql->exec($get);
    }

    static function get_coach_list(SQL $sql, string $fields = ''): array
    {
        $get = user::select($fields) . 'WHERE ' . user::active . '=' . user::active_on . ' AND ' . user::role . '!=' . user::role_student;

        user::hide_user($get);

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . user::last_name;
        else
            $get .= ' ORDER BY ' . user::updated . ' DESC, ' . user::handle;

        return $sql->exec($get);
    }

    // --- event

    const event_revision = 0;

    const event_created        =  1;
    const event_updated        =  2;
    const event_deleted        =  3;
    const event_deactivated    =  4;
    const event_activated      =  5;
    const event_pin_failed     =  6;
    const event_pin_rehashed   =  7;
    const event_name_changed   =  8;
    const event_email_changed  =  9;
    const event_absent_changed = 10;
    const event_pin_reset      = 11;

    static function event_insert(\Base $fw, int $id, int $command, int $event, string $fields = ''): void
    {
        \domain\event::insert(
            $fw,
            \event::user,
            $id,
            $command,
            $event,
            user::get($fw->db, $id, $fields),
            user::event_revision
        );
    }

    // --- action

    static function action_insert_owner(\Base $fw, int $command, string $handle, string $pin): int
    {
        $insert = 'INSERT INTO ' . db::prefix() . user::table . ' ('
            . user::handle . ','
            . user::pin . ','
            . user::role . ','
            . user::creator . ','
            . user::version . ') VALUES (?,?,?,?,?)';

        $fw->db->exec($insert, array(
            1 => $handle,
            2 => $pin,
            3 => user::role_owner,
            4 => user::creator_owner,
            5 => user::latest,
        ));

        $id = db::get_last_inserted_id($fw->db);
        user::event_insert($fw, $id, $command, user::event_created);

        return $id;
    }

    static function action_insert(
        \Base $fw,
        int $command,
        string $handle,
        string $last_name,
        string $first_name,
        string $email,
        int $verified,
        string $pin,
        int $pin_reset,
        int $request_id,
        int $role,
        int $creator
    ): int {
        $insert = 'INSERT INTO ' . db::prefix() . user::table . ' ('
            . user::handle . ','
            . user::last_name . ','
            . user::first_name . ','
            . user::email . ','
            . user::verified . ','
            . user::pin . ','
            . user::pin_reset . ','
            . user::request . ','
            . user::role . ','
            . user::creator . ','
            . user::version . ') VALUES (?,?,?,?,?,?,?,?,?,?,?)';

        $fw->db->exec($insert, array(
            1 => $handle,
            2 => $last_name,
            3 => $first_name,
            4 => $email,
            5 => $verified,
            6 => $pin,
            7 => $pin_reset,
            8 => $request_id,
            9 => $role,
            10 => $creator,
            11 => user::latest,
        ));

        $id = db::get_last_inserted_id($fw->db);
        user::event_insert($fw, $id, $command, user::event_created);

        return $id;
    }

    static function action_pin_fail(\Base $fw, int $command, int $id): void
    {
        $update = 'UPDATE ' . db::prefix() . user::table . ' SET '
            . user::pin_fail . '=' . user::pin_fail . '+1 WHERE '
            . user::id . '=?';

        $fw->db->exec($update, $id);

        user::event_insert($fw, $id, $command, user::event_pin_failed, user::pin_fail . ',' . user::version);
    }

    static function action_update(
        \base $fw,
        int $command,
        int $id,
        string $last_name,
        string $first_name,
        int $role
    ): void {
        $update = 'UPDATE ' . db::prefix() . user::table . ' SET '
            . user::last_name . '=?,'
            . user::first_name . '=?,'
            . user::role . '=? WHERE '
            . user::id . '=?';

        $fw->db->exec($update, array(
            1 => $last_name,
            2 => $first_name,
            3 => $role,
            4 => $id,
        ));

        user::event_insert(
            $fw,
            $id,
            $command,
            user::event_updated,
            user::last_name . ',' . user::first_name . ',' . user::role . ',' . user::version
        );
    }

    static function action_update_absent(\base $fw, int $command, int $id, int $absent): void
    {
        $update = 'UPDATE ' . db::prefix() . user::table . ' SET '
            . user::absent . '=? WHERE '
            . user::id . '=?';

        $fw->db->exec($update, array(
            1 => $absent,
            2 => $id,
        ));

        user::event_insert(
            $fw,
            $id,
            $command,
            user::event_absent_changed,
            user::absent . ',' . user::version
        );
    }

    static function action_update_pin_reset(\base $fw, int $command, int $id, int $pin_reset): void
    {
        $update = 'UPDATE ' . db::prefix() . user::table . ' SET '
            . user::pin_reset . '=? WHERE '
            . user::id . '=?';

        $fw->db->exec($update, array(
            1 => $pin_reset,
            2 => $id,
        ));

        user::event_insert(
            $fw,
            $id,
            $command,
            user::event_pin_reset,
            user::pin_reset . ',' . user::version
        );
    }

    static function action_update_email(\base $fw, int $command, int $id, string $email): void
    {
        $update = 'UPDATE ' . db::prefix() . user::table . ' SET '
            . user::email . '=?,'
            . user::verified . '=1 WHERE '
            . user::id . '=?';

        $fw->db->exec($update, array(
            1 => $email,
            2 => $id,
        ));

        user::event_insert(
            $fw,
            $id,
            $command,
            user::event_email_changed,
            user::email . ',' . user::verified . ',' . user::version
        );
    }

    static function action_update_name(\Base $fw, int $command, int $id, string $last_name, string $first_name): void
    {
        $update = 'UPDATE ' . db::prefix() . user::table . ' SET '
            . user::last_name . '=?,'
            . user::first_name . '=? WHERE '
            . user::id . '=?';

        $fw->db->exec($update, array(
            1 => $last_name,
            2 => $first_name,
            3 => $id,
        ));

        user::event_insert(
            $fw,
            $id,
            $command,
            user::event_name_changed,
            user::last_name . ',' . user::first_name . ',' . user::version
        );
    }

    static function action_update_pin(\Base $fw, int $command, int $id, string $pin): void
    {
        $update = 'UPDATE ' . db::prefix() . user::table . ' SET '
            . user::pin . '=? WHERE '
            . user::id . '=?';

        $fw->db->exec($update, array(
            1 => $pin,
            2 => $id,
        ));

        user::event_insert($fw, $id, $command, user::event_pin_rehashed, user::pin . ',' . user::version);
    }

    static function action_delete(\Base $fw, int $command, int $id): void
    {
        user::event_insert($fw, $id, $command, user::event_deleted);

        $delete = 'DELETE FROM ' . db::prefix() . user::table . ' WHERE ' . user::id . '=?';

        $fw->db->exec($delete, $id);
    }

    static function action_deactivate(\Base $fw, int $command, int $id): void
    {
        user::event_insert($fw, $id, $command, user::event_deactivated);

        $update = 'UPDATE ' . db::prefix() . user::table . ' SET ' . user::active . '=0 WHERE ' . user::id . '=?';

        $fw->db->exec($update, $id);
    }

    // --- command

    static function command_add(\Base $fw, string $last_name, string $first_name, string $email, int $role, array $teams): string // user_handle
    {
        $command = \cmd::begin($fw, \cmd::user_add);

        if ($email !== '') {
            $user = user::get_by_email($fw->db, $email, user::id);
            if (!empty($user)) {
                log::warn($fw)->write('account\user::add - user email exists: ' . $email);
                return '';
            }

            $request = request::get_list_by_email($fw->db, $email, request::id);
            if (!empty($request)) {
                log::warn($fw)->write('account\user::add - request email exists: ' . $email);
                return '';
            }
        }

        // role check
        if (!(($role === user::role_student)
            || ($role === user::role_coach)
            || ($role === user::role_moderator)
            || ($role === user::role_admin)
            || ($role === user::role_owner)))
            return '';

        $current_role = session::get_user_role($fw);

        if (($current_role === user::role_student) || ($current_role === user::role_coach) || ($current_role === user::role_moderator))
            return '';

        if (($current_role === user::role_admin) && ($role === user::role_owner))
            return '';

        $user_handle = user::new_handle($fw->db);
        $user_pin = password::pin($fw);
        $user_pin_hashed = password::hash_pin($fw, $user_pin);

        $user_id = user::action_insert(
            $fw,
            $command,
            $user_handle,
            $last_name,
            $first_name,
            user::email_none,
            user::verified_off,
            $user_pin_hashed,
            user::pin_reset_on,
            user::request_none,
            $role,
            session::get_user_id($fw)
        );

        $result = 1;

        if ($email !== '') {
            if (!request::command_verify($fw, $user_id, $command, $email, 'user_add'))
                log::error($fw)->write('account\user::add - request verify failed: ' . $email);
            else
                $result++;
        }

        foreach ($teams as $t_value) {
            $team = team::get_by_handle($fw->db, $t_value, team::id . ',' .  team::active);
            if (empty($team))
                continue;

            if ((int)$team[team::active] !== team::active_on)
                continue;

            $team_id = (int)$team[team::id];

            if (team_user::action_insert($fw, $command, $team_id, $user_id) !== 0)
                $result++;
        }

        \cmd::end_user($fw, $command, $user_id, $result);

        return $user_handle;
    }

    static function command_absent(\Base $fw, string $handle): void
    {
        $command = \cmd::begin($fw, \cmd::user_absent);

        $user = user::get_by_handle($fw->db, $handle, user::id . ',' . user::absent . ','  . user::active);
        if (empty($user))
            return;

        if ((int)$user[user::active] !== user::active_on)
            return;

        if ((int)$user[user::absent] === user::absent_on)
            return;

        $user_id = (int)$user[user::id];

        user::action_update_absent($fw, $command, $user_id, user::absent_on);

        \cmd::end($fw, $command);
    }

    static function command_present(\Base $fw, string $handle): void
    {
        $command = \cmd::begin($fw, \cmd::user_present);

        $user = user::get_by_handle($fw->db, $handle, user::id . ',' . user::absent . ','  . user::active);
        if (empty($user))
            return;

        if ((int)$user[user::active] !== user::active_on)
            return;

        if ((int)$user[user::absent] === user::absent_off)
            return;

        $user_id = (int)$user[user::id];

        user::action_update_absent($fw, $command, $user_id, user::absent_off);

        \cmd::end($fw, $command);
    }

    static function command_pin_reset(\Base $fw, string $handle): void
    {
        $command = \cmd::begin($fw, \cmd::user_pin_reset);

        $user = user::get_by_handle($fw->db, $handle, user::id . ',' . user::pin_reset . ',' . user::role . ',' . user::active);
        if (empty($user))
            return;

        if ((int)$user[user::active] !== user::active_on)
            return;

        if ((int)$user[user::pin_reset] === user::pin_reset_on)
            return;

        $user_id = (int)$user[user::id];

        // role check
        if (service::admin($fw)) {
            $user_role = (int)$user[user::role];
            if (($user_role === user::role_admin) || ($user_role === user::role_owner))
                return;
        }

        user::action_update_pin_reset($fw, $command, $user_id, user::pin_reset_on);

        \cmd::end($fw, $command);
    }

    static function command_pin_new(\Base $fw, string $handle): bool
    {
        $command = \cmd::begin($fw, \cmd::user_pin_new);

        $user = user::get_by_handle($fw->db, $handle, user::id . ',' . user::pin_reset . ','  . user::active);
        if (empty($user))
            return false;

        if ((int)$user[user::active] !== user::active_on)
            return false;

        if ((int)$user[user::pin_reset] !== user::pin_reset_on)
            return false;

        $user_id = (int)$user[user::id];

        $user_pin = password::pin($fw);
        $user_pin_hashed = password::hash_pin($fw, $user_pin);

        user::action_update_pin($fw, $command, $user_id, $user_pin_hashed);
        user::action_update_pin_reset($fw, $command, $user_id, user::pin_reset_off);

        \cmd::end_user($fw, $command, $user_id, 2);

        auth::set($fw, $handle, $user_pin);

        return true;
    }

    static function command_update(
        \Base $fw,
        string $handle,
        string $last_name,
        string $first_name,
        string $email,
        int $role,
        array $teams
    ): void {
        $command = \cmd::begin($fw, \cmd::user_update);

        $user = user::get_by_handle($fw->db, $handle);
        if (empty($user))
            return;

        if ((int)$user[user::active] !== user::active_on)
            return;

        $user_id = (int)$user[user::id];

        // email check
        $user_email = $user[user::email];
        if ($email !== $user_email) {
            if ($email === '')
                return;

            $user = user::get_by_email($fw->db, $email, user::id);
            if (!empty($user)) {
                log::warn($fw)->write('account\user::update - user email exists: ' . $email);
                return;
            }

            $request = request::get_list_by_email($fw->db, $email, request::id);
            if (!empty($request)) {
                log::warn($fw)->write('account\user::update - request email exists: ' . $email);
                return;
            }
        }

        // role check
        if (!(($role === user::role_student)
            || ($role === user::role_coach)
            || ($role === user::role_moderator)
            || ($role === user::role_admin)
            || ($role === user::role_owner)))
            return;

        $user_role = (int)$user[user::role];
        if ($user_role !== $role) {
            $current_role = session::get_user_role($fw);

            if (($current_role === user::role_student) || ($current_role === user::role_coach) || ($current_role === user::role_moderator))
                return;

            if ($current_role === user::role_admin) {
                if (($user_role === user::role_owner) || ($role === user::role_owner))
                    return;
            }
        }

        $result = 0;

        if (($last_name !== $user[user::last_name])
            || ($first_name !== $user[user::first_name])
            || ($role !== $user_role)
        ) {
            user::action_update(
                $fw,
                $command,
                $user_id,
                $last_name,
                $first_name,
                $role
            );

            // TODO: check if request_id needs to be updated also in the user table

            $result++;
        }

        if ($email !== $user_email) {
            if (!request::command_verify($fw, $user_id, $command, $email, 'user_update'))
                log::error($fw)->write('account\user::update - request verify failed: ' . $email);
            else
                $result++;
        }

        $result += user::command_assign_teams($fw, $command, $user_id, $teams);

        if ($result > 0)
            \cmd::end($fw, $command, $result);
    }

    static function command_assign_teams(\Base $fw, int $command, int $user_id, array $team_handles): int
    {
        $result = 0;

        $team_user_list = team_user::get_list_by_user($fw->db, $user_id);

        if (!empty($team_handles)) {
            $updated = array();

            foreach ($team_handles as $team_handle) {
                $team = team::get_by_handle($fw->db, $team_handle, team::id);
                if (empty($team))
                    continue;

                $team_id = (int)$team[team::id];

                $found = false;
                foreach ($team_user_list as $team_user) {
                    if ($team_user[team_user::team] === $team_id) {
                        $found = true;

                        $updated[] = $team_id;
                        break;
                    }
                }

                if (!$found) {
                    team_user::action_insert($fw, $command, $team_id, $user_id);

                    $updated[] = $team_id;
                    $result++;
                }
            }

            foreach ($team_user_list as $team_user) {
                if (!in_array((int)$team_user[team_user::team], $updated)) {
                    $team_user_id = (int)$team_user[team_user::id];
                    team_user::action_delete($fw, $command, $team_user_id);

                    $result++;
                }
            }
        } else {
            foreach ($team_user_list as $team_user) {
                $team_user_id = (int)$team_user[team_user::id];
                team_user::action_delete($fw, $command, $team_user_id);

                $result++;
            }
        }

        return $result;
    }

    static function command_assign_team(
        \Base $fw,
        string $handle,
        array $teams
    ): void {
        $command = \cmd::begin($fw, \cmd::user_assign_team);

        $user = user::get_by_handle($fw->db, $handle);
        if (empty($user))
            return;

        if ((int)$user[user::active] !== user::active_on)
            return;

        $user_id = (int)$user[user::id];

        $result = 0;
        $result += user::command_assign_teams($fw, $command, $user_id, $teams);

        if ($result > 0)
            \cmd::end($fw, $command, $result);
    }

    static function command_remove(\Base $fw, string $handle): void
    {
        $command = \cmd::begin($fw, \cmd::user_remove);

        $user = user::get_by_handle($fw->db, $handle, user::id . ',' . user::role . ',' . user::active);
        if (empty($user))
            return;

        if ((int)$user[user::active] !== user::active_on)
            return;

        $user_id = (int)$user[user::id];

        // role check
        if (service::admin($fw)) {
            $user_role = (int)$user[user::role];
            if (($user_role === user::role_admin) || ($user_role === user::role_owner))
                return;
        }

        user::action_deactivate($fw, $command, $user_id);

        \cmd::end($fw, $command);
    }

    static function command_register(\Base $fw, string $handle, string $plan_handle): void
    {
        $command = \cmd::begin($fw, \cmd::user_register);

        $user = user::get_by_handle($fw->db, $handle, user::id . ',' . user::active);
        if (empty($user))
            return;

        if ((int)$user[user::active] !== user::active_on)
            return;

        $user_id = (int)$user[user::id];

        $plan = plan::get_by_handle($fw->db, $plan_handle, plan::id . ',' . plan::time . ',' .  plan::register . ',' . plan::active);
        if (empty($plan))
            return;

        if ((int)$plan[plan::active] !== plan::active_on)
            return;

        $plan_id = (int)$plan[plan::id];

        $plan_time = $plan[plan::time];
        $now = time::get($fw, $plan_time);

        $plan_register = (int)date('i', strtotime($plan[plan::register]));

        $addition = "+" . $plan_register . " minutes";
        $next_end_time = strtotime($addition, $now);

        $next_end_date = time::to_db_date_time($next_end_time);

        $register = register::get_by_user($fw->db, $plan_id, $user_id, register::id . ',' . register::register);
        if (!empty($register)) {
            $register_id = (int)$register[register::id];
            $register_time = strtotime($register[register::register]);

            if ($now < $register_time) {
                register::action_update($fw, $command, $plan_id, $register_id, $next_end_date);

                \cmd::end($fw, $command);
                return;
            }

            register::action_delete($fw, $command, $plan_id, $register_id);
        }

        register::action_insert($fw, $command, $plan_id, $user_id, $next_end_date);

        \cmd::end($fw, $command);
    }
}
