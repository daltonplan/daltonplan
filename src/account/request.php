<?php

declare(strict_types=1);

namespace account;

use DB\SQL;

use frame\base;
use frame\db;
use frame\log;
use frame\mail;
use frame\id;
use frame\time;

abstract class request
{
    const latest = 0;
    const table = 'request';

    const id = db::id;
    const handle = db::handle;
    const email = 'email';
    const user = 'user';
    const state = 'state';
    const ip = 'ip';
    const re = 're';
    const ag = 'ag';
    const version = db::version;
    const created = db::created;
    const updated = db::updated;

    const all = request::id . ','
        . request::handle . ','
        . request::email . ','
        . request::user . ','
        . request::state . ','
        . request::ip . ','
        . request::re . ','
        . request::ag . ','
        . request::version . ','
        . request::created . ','
        . request::updated;

    // state
    const join_verify = 0;
    const user_verify = 1;

    static function create(\Base $fw): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . db::prefix() . request::table . '` (
                `' . request::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . request::handle . '` varchar(' . $fw->get(base::max_handle_length) . ') NOT NULL,
                `' . request::email . '` varchar(' . $fw->get('max_email_length') . ') NOT NULL,
                `' . request::user . '` int(11) NOT NULL DEFAULT 0,
                `' . request::state . '` int(11) NOT NULL DEFAULT 0,
                `' . request::ip . '` text NOT NULL,
                `' . request::re . '` text DEFAULT NULL,
                `' . request::ag . '` text NOT NULL,
                `' . request::version . '` int(11) NOT NULL DEFAULT ' . request::latest . ',
                `' . request::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                `' . request::updated . '` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`' . request::id . '`) USING BTREE,
                UNIQUE KEY `' . request::handle . '` (`' . request::handle . '`) USING BTREE,
                KEY `' . request::email . '` (`' . request::email . '`) USING BTREE,
                KEY `' . request::user . '` (`' . request::user . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    // ---

    static function get_requests(\Base $fw, int $user_id): array
    {
        $limit = time::get_request_time_limit($fw);
        return request::get_list_by_user_state_after_date($fw->db, $user_id, request::user_verify, $limit);
    }

    static function has_requests(\Base $fw, int $user_id): bool
    {
        return !empty(request::get_requests($fw, $user_id));
    }

    static function set_requests(\Base $fw, int $user_id): bool
    {
        $requests = request::get_requests($fw, $user_id);
        if (empty($requests))
            return false;

        $addition = "+" . time::get_request_time($fw);
        $request_updated = strtotime($requests[0][request::updated]);
        $timeout = strtotime($addition, $request_updated);

        $now = time::get($fw);
        if ($now > $timeout)
            return false;

        $request_timeout = time::get_format($fw, $timeout);

        $value[request::email] = $requests[0][request::email];
        $value['timeout'] = $request_timeout;

        $fw->set('verify_email', $value);
        return true;
    }

    // --- query

    static function select(string $fields): string
    {
        return 'SELECT ' . (($fields === '') ? request::all : $fields) . ' FROM ' . db::prefix() . request::table . ' ';;
    }

    static function new_handle(SQL $sql): string
    {
        while (true) {
            $handle = id::gen_handle('request_handle_length');
            if (empty(request::get_by_handle($sql, $handle)))
                return $handle;
        }

        return '';
    }

    static function get(SQL $sql, int $id, string $fields = ''): array
    {
        $get = request::select($fields) . 'WHERE ' . request::id . '=?';
        return base::first($sql->exec($get, $id));
    }

    static function get_by_handle(SQL $sql, string $handle, string $fields = ''): array
    {
        $get = request::select($fields) . 'WHERE ' . request::handle . '=?';
        return base::first($sql->exec($get, $handle));
    }

    static function get_list_by_email(SQL $sql, string $email, string $fields = ''): array
    {
        $get = request::select($fields) . 'WHERE ' . request::email . '=?';
        return $sql->exec($get, $email);
    }

    static function get_list_by_email_state(SQL $sql, string $email, int $state, string $fields = ''): array
    {
        $get = request::select($fields) . 'WHERE ' . request::email . '=? AND ' . request::state . '=?';
        return $sql->exec($get, array(
            1 => $email,
            2 => $state,
        ));
    }

    static function get_list_by_user_state(SQL $sql, int $user, int $state, string $fields = ''): array
    {
        $get = request::select($fields) . 'WHERE ' . request::user . '=? AND ' . request::state . '=?';
        return $sql->exec($get, array(
            1 => $user,
            2 => $state,
        ));
    }

    static function get_list_by_user_state_after_date(SQL $sql, int $user, int $state, string $date, string $fields = ''): array
    {
        $get = request::select($fields) . 'WHERE '
            . request::user . '=? AND '
            . request::state . '=? AND ('
            . request::updated . '>?)';

        return $sql->exec($get, array(
            1 => $user,
            2 => $state,
            3 => $date,
        ));
    }

    // --- event

    const event_revision = 0;

    const event_join_requested         = 1;
    const event_retry_join_requested   = 2;
    const event_joined                 = 3;

    const event_verify_requested       = 4;
    const event_retry_verify_requested = 5;
    const event_verifed                = 6;

    static function event_insert(\Base $fw, int $id, int $command, int $event, string $fields = ''): void
    {
        \domain\event::insert(
            $fw,
            \event::request,
            $id,
            $command,
            $event,
            request::get($fw->db, $id, $fields),
            request::event_revision
        );
    }

    // --- action

    static function action_insert(
        \Base $fw,
        int $command,
        string $handle,
        string $email,
        int $user,
        int $state,
        int $event
    ): void {
        $insert = 'INSERT INTO ' . db::prefix() . request::table . ' ('
            . request::handle . ','
            . request::email . ','
            . request::user . ','
            . request::state . ','
            . request::ip . ','
            . request::re . ','
            . request::ag . ','
            . request::version . ') VALUES (?,?,?,?,?,?,?,?)';

        $fw->db->exec($insert, array(
            1 => $handle,
            2 => $email,
            3 => $user,
            4 => $state,
            5 => $fw->get('ip'),
            6 => $fw->get('re'),
            7 => $fw->get('ag'),
            8 => request::latest,
        ));

        $user_request_id = \frame\db::get_last_inserted_id($fw->db);

        request::event_insert($fw, $user_request_id, $command, $event);
    }

    static function action_update(\Base $fw, int $command, string $handle, int $request_id, int $event): void
    {
        $update = 'UPDATE ' . db::prefix() . request::table . ' SET '
            . request::handle . '=?,'
            . request::ip . '=?,'
            . request::re . '=?,'
            . request::ag . '=? WHERE '
            . request::id . '=?';

        $fw->db->exec($update, array(
            1 => $handle,
            2 => $fw->get('ip'),
            3 => $fw->get('re'),
            4 => $fw->get('ag'),
            5 => $request_id,
        ));

        request::event_insert(
            $fw,
            $request_id,
            $command,
            $event,
            request::handle . ',' . request::ip . ',' . request::re . ',' . request::ag . ',' . request::version
        );
    }

    static function action_delete(\base $fw, int $command, int $request_id, int $user_id, int $event): void
    {
        $update = 'UPDATE ' . db::prefix() . request::table . ' SET '
            . request::user . '=?,'
            . request::ip . '=?,'
            . request::re . '=?,'
            . request::ag . '=? WHERE '
            . request::id . '=?';

        $fw->db->exec($update, array(
            1 => $user_id,
            2 => $fw->get('ip'),
            3 => $fw->get('re'),
            4 => $fw->get('ag'),
            5 => $request_id,
        ));

        request::event_insert($fw, $request_id, $command, $event);

        $delete = 'DELETE FROM ' . db::prefix() . request::table . ' WHERE ' . request::id . '=?';
        $fw->db->exec($delete, $request_id);
    }

    // --- command

    static function command_join(\Base $fw, string $email): void
    {
        $command = \cmd::begin($fw, \cmd::user_request_join);

        if (user::exists($fw->db, $email)) {
            log::info($fw)->write('account\request::join - email exists: ' . $email);
            return;
        }

        $requests = request::get_list_by_email_state($fw->db, $email, request::join_verify);

        if (time::request_time_active($fw, $requests)) {
            log::info($fw)->write('account\request::join - still active: ' . $email);
            return;
        }

        $handle = request::new_handle($fw->db);

        $request = time::get_request_time_expired($fw, $requests);
        if (!empty($expired_request))
            request::action_update($fw, $command, $handle, (int)$request['id'], request::event_retry_join_requested);
        else
            request::action_insert($fw, $command, $handle, $email, 0, request::join_verify, request::event_join_requested);

        mail::send_verify($fw, $email, $handle, $fw->get('DICT.join_email_subject'), $fw->get('DICT.join_email_msg'));

        \cmd::end($fw, $command);
    }

    static function command_verify(\Base $fw, int $user_id, int $command, string $email, string $command_name): bool
    {
        $requests = request::get_list_by_user_state($fw->db, $user_id, request::user_verify);

        if (time::request_time_active($fw, $requests)) {
            log::info($fw)->write($command_name . ' - still active: ' . $user_id);
            return false;
        }

        $handle = request::new_handle($fw->db);

        $request = time::get_request_time_expired($fw, $requests);
        if (!empty($expired_request))
            request::action_update($fw, $command, $handle, (int)$request['id'], request::event_retry_verify_requested);
        else
            request::action_insert($fw, $command, $handle, $email, $user_id, request::user_verify, request::event_verify_requested);

        mail::send_verify($fw, $email, $handle, $fw->get('DICT.verify_email_subject'), $fw->get('DICT.verify_email_msg'));

        return true;
    }
}
