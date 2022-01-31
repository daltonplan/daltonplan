<?php

declare(strict_types=1);

namespace core;

use account\user;

use DB\SQL;

use frame\base;
use frame\db;
use frame\session;

abstract class team_user
{
    const latest = 0;
    const table = 'team_user';

    const id = db::id;
    const team = 'team';
    const user = 'user';
    const version = db::version;
    const created = db::created;
    const updated = db::updated;

    const all = team_user::id . ','
        . team_user::team  . ','
        . team_user::user . ','
        . team_user::version . ','
        . team_user::created . ','
        . team_user::updated;

    static function create(\Base $fw): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . db::prefix() . team_user::table . '` (
                `' . team_user::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . team_user::team . '` int(11) NOT NULL,
                `' . team_user::user . '` int(11) NOT NULL,
                `' . team_user::version . '` int(11) NOT NULL DEFAULT ' . team_user::latest . ',
                `' . team_user::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                `' . team_user::updated . '` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`' . team_user::id . '`) USING BTREE,
                KEY `' . team_user::team . '` (`' . team_user::team . '`) USING BTREE,
                KEY `' . team_user::user . '` (`' . team_user::user . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    static function set_user_list(\Base $fw, int $team_id): void
    {
        $team_user_list = team_user::get_list_by_team($fw->db, $team_id);

        $users = user::get_list($fw->db);

        foreach ($users as $key => $user) {
            $users[$key][db::selected] = false;

            foreach ($team_user_list as $team_user) {
                if ((int)$user[user::id] === (int)$team_user[team_user::user]) {
                    $users[$key][db::selected] = true;
                    break;
                }
            }
        }

        $fw->set('user_list', $users);
    }

    // ---

    // --- query

    static function select(string $fields): string
    {
        return 'SELECT ' . (($fields === '') ? team_user::all : $fields) . ' FROM ' . db::prefix() . team_user::table . ' ';
    }

    static function get(SQL $sql, int $id, string $fields = ''): array
    {
        $get = team_user::select($fields) . 'WHERE ' . team_user::id . '=?';
        return base::first($sql->exec($get, $id));
    }

    static function get_list(SQL $sql, string $fields = ''): array
    {
        $get = team_user::select($$fields);

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . team_user::created;
        else
            $get .= ' ORDER BY ' . team_user::updated . ' DESC';

        return $sql->exec($get);
    }

    static function get_list_by_team(SQL $sql, int $team, string $fields = ''): array
    {
        $get = team_user::select($fields) . 'WHERE ' . team_user::team . '=?';

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . team_user::created;
        else
            $get .= ' ORDER BY ' . team_user::updated . ' DESC';

        return $sql->exec($get, $team);
    }

    static function get_list_by_user(SQL $sql, int $user, string $fields = ''): array
    {
        $get = team_user::select($fields) . 'WHERE ' . team_user::user . '=?';

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . team_user::created;
        else
            $get .= ' ORDER BY ' . team_user::updated . ' DESC';

        return $sql->exec($get, $user);
    }

    // --- event

    const event_revision = 0;

    const event_created = 1;
    const event_deleted = 2;

    static function event_insert(\Base $fw, int $id, int $command, int $event, string $fields = ''): void
    {
        \domain\event::insert(
            $fw,
            \event::team_user,
            $id,
            $command,
            $event,
            team_user::get($fw->db, $id, $fields),
            team_user::event_revision
        );
    }

    // --- action

    static function action_insert(\Base $fw, int $command, int $team_id, int $user_id): int
    {
        $insert = 'INSERT INTO ' . db::prefix() . team_user::table . ' ('
            . team_user::team . ','
            . team_user::user . ','
            . team_user::version . ') VALUES (?,?,?)';

        $fw->db->exec($insert, array(
            1 => $team_id,
            2 => $user_id,
            3 => team_user::latest,
        ));

        $id = db::get_last_inserted_id($fw->db);
        team_user::event_insert($fw, $id, $command, team_user::event_created);

        return $id;
    }

    static function action_delete(\Base $fw, int $command, int $id): void
    {
        team_user::event_insert($fw, $id, $command, team_user::event_deleted);

        $delete = 'DELETE FROM ' . db::prefix() . team_user::table . ' WHERE ' . team_user::id . '=?';

        $fw->db->exec($delete, $id);
    }

    // --- command

    static function command_assign(\Base $fw, string $handle, array $user_handles): void
    {
        $command = \cmd::begin($fw, \cmd::team_assign_user);

        $team = team::get_by_handle($fw->db, $handle, team::id);
        if (empty($team))
            return;

        $team_id = (int)$team[team::id];

        $result = 0;

        $team_user_list = team_user::get_list_by_team($fw->db, $team_id);

        if (!empty($user_handles)) {
            $updated = array();

            foreach ($user_handles as $user_handle) {
                $user = user::get_by_handle($fw->db, $user_handle, user::id);
                if (empty($user))
                    continue;

                $user_id = (int)$user[user::id];

                $found = false;
                foreach ($team_user_list as $team_user) {
                    if ($team_user[team_user::user] === $user_id) {
                        $found = true;

                        $updated[] = $user_id;
                        break;
                    }
                }

                if (!$found) {
                    team_user::action_insert($fw, $command, $team_id, $user_id);

                    $updated[] = $user_id;
                    $result++;
                }
            }

            foreach ($team_user_list as $team_user) {
                if (!in_array((int)$team_user[team_user::user], $updated)) {
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

        if ($result !== 0)
            \cmd::end($fw, $command, $result);
    }
}
