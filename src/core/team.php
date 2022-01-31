<?php

declare(strict_types=1);

namespace core;

use account\user;

use DB\SQL;

use frame\base;
use frame\db;
use frame\id;
use frame\session;

abstract class team
{
    const latest = 0;
    const table = 'team';
    const list = 'team_list';

    const id = db::id;
    const handle = db::handle;
    const name = 'name';
    const description = 'description';
    const link = 'link';
    const icon = 'icon';
    const parent = 'parent';
    const active = 'active';
    const version = db::version;
    const created = db::created;
    const updated = db::updated;

    const all = team::id . ','
        . team::handle . ','
        . team::name . ','
        . team::description . ','
        . team::link . ','
        . team::icon . ','
        . team::parent . ','
        . team::active . ','
        . team::version . ','
        . team::created . ','
        . team::updated;

    // active
    const active_on  = 1;
    const active_off = 0;

    static function create(\Base $fw): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . db::prefix() . team::table . '` (
                `' . team::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . team::handle . '` varchar(' . $fw->get(base::max_handle_length) . ') NOT NULL,
                `' . team::name . '` varchar(' . $fw->get('max_name_length') . ') NOT NULL,
                `' . team::description . '` varchar(' . $fw->get('max_description_length') . ') NOT NULL,
                `' . team::link . '` varchar(' . $fw->get('max_link_length') . ') NOT NULL,
                `' . team::icon . '` varchar(' . $fw->get('max_icon_length') . ') NOT NULL,
                `' . team::parent . '` int(11) NOT NULL DEFAULT 0,
                `' . team::active . '` int(11) NOT NULL DEFAULT 1,
                `' . team::version . '` int(11) NOT NULL DEFAULT ' . team::latest . ',
                `' . team::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                `' . team::updated . '` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                 PRIMARY KEY (`' . team::id . '`) USING BTREE,
                 UNIQUE KEY `' . team::handle . '` (`' . team::handle . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    // ---

    static function set_list(\Base $fw): void
    {
        $team_list = team::get_list($fw->db);

        $fw->set(team::list, $team_list);
    }

    static function set_list_detail(\Base $fw): void
    {
        $team_list = team::get_list($fw->db);

        foreach ($team_list as $t_key => $t_value) {
            $team_id = (int)$t_value[team::id];

            $students = 0;
            $coaches = 0;

            $team_user_list = team_user::get_list_by_team($fw->db, $team_id, team_user::user);
            foreach ($team_user_list as $tu_value) {
                $user_id = (int)$tu_value[team_user::user];

                $user = \account\service::get_user($fw, $user_id);
                if (empty($user))
                    continue;

                if ((int)$user[user::active] !== user::active_on)
                    continue;

                $user_role = (int)$user[user::role];
                if ($user_role === user::role_student)
                    $students++;
                else
                    $coaches++;
            }

            $team_list[$t_key][user::students] = $students;
            $team_list[$t_key][user::coaches] = $coaches;

            $subject_list = array();

            $team_subject_list = team_subject::get_list_by_team($fw->db, $team_id, team_subject::subject);
            foreach ($team_subject_list as $ts_value) {
                $subject_id = (int)$ts_value[team_subject::subject];

                $subject = service::get_subject($fw, $subject_id);
                if (empty($subject))
                    continue;

                if ((int)$subject[subject::active] !== subject::active_on)
                    continue;

                subject::check_parent($subject);
                $subject_list[] = $subject;
            }

            $team_list[$t_key][subject::list] = $subject_list;
        }

        $fw->set(team::list, $team_list);
    }

    static function set_list_by_lab(\Base $fw, int $lab_id): void
    {
        $lab_team_list = lab_team::get_list_by_lab($fw->db, $lab_id, lab_team::team);

        $team_list = team::get_list($fw->db);

        foreach ($team_list as $t_key => $t_value) {
            $team_list[$t_key][db::selected] = false;

            foreach ($lab_team_list as $lt_value) {
                if ((int)$lt_value[lab_team::team] === (int)$t_value[team::id]) {
                    $team_list[$t_key][db::selected] = true;
                    break;
                }
            }
        }

        $fw->set(team::list, $team_list);
    }

    static function set_list_by_user(\Base $fw, int $user_id): void
    {
        $team_user_list = team_user::get_list_by_user($fw->db, $user_id, team_user::team);

        $team_list = team::get_list($fw->db);

        foreach ($team_list as $t_key => $t_value) {
            $team_list[$t_key][db::selected] = false;

            foreach ($team_user_list as $tu_value) {
                if ((int)$tu_value[team_user::team] === (int)$t_value[team::id]) {
                    $team_list[$t_key][db::selected] = true;
                    break;
                }
            }
        }

        $fw->set(team::list, $team_list);
    }

    static function set_list_only_user(\base $fw, int $user_id): void
    {
        $team_list = array();

        $team_user_list = team_user::get_list_by_user($fw->db, $user_id, team_user::team);
        foreach ($team_user_list as $tu_value) {
            $team = \core\service::get_team($fw, (int)$tu_value[team_user::team]);
            if (empty($team))
                continue;

            if ((int)$team[team::active] !== team::active_on)
                continue;

            $team_list[] = $team;
        }

        $fw->set(team::list, $team_list);
    }

    static function get_id_by_handle(\Base $fw, string $handle): int
    {
        $team = team::get_by_handle($fw->db, $handle, team::id);
        if (empty($team))
            return 0;

        return $team[team::id];
    }

    // --- query

    static function select(string $fields): string
    {
        return 'SELECT ' . (($fields === '') ? team::all : $fields) . ' FROM ' . db::prefix() . team::table . ' ';
    }

    static function new_handle(SQL $sql): string
    {
        while (true) {
            $handle = id::gen_handle();
            if (empty(team::get_by_handle($sql, $handle)))
                return $handle;
        }

        return '';
    }

    static function count(SQL $sql): int
    {
        $get = 'SELECT count(*) as total FROM ' . db::prefix() . team::table . ' WHERE ' . team::active . ' ="1"';
        $result = $sql->exec($get);
        return empty($result) ? 0 : (int)$result[0]['total'];
    }

    static function get(SQL $sql, int $id, string $fields = ''): array
    {
        $get = team::select($fields) . 'WHERE ' . team::id . '=?';
        return base::first($sql->exec($get, $id));
    }

    static function get_by_handle(SQL $sql, string $handle, string $fields = ''): array
    {
        $get = team::select($fields) . 'WHERE ' . team::handle . '=?';
        return base::first($sql->exec($get, $handle));
    }

    static function get_list(SQL $sql, string $fields = ''): array
    {
        $get = team::select($fields) . 'WHERE ' . team::active . '=1';

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . team::name;
        else
            $get .= ' ORDER BY ' . team::updated . ' DESC';

        return $sql->exec($get);
    }

    // --- event

    const event_revision = 0;

    const event_created     = 1;
    const event_updated     = 2;
    const event_deleted     = 3;
    const event_deactivated = 4;
    const event_activated   = 5;

    static function event_insert(\Base $fw, int $id, int $command, int $event, string $fields = ''): void
    {
        \domain\event::insert(
            $fw,
            \event::team,
            $id,
            $command,
            $event,
            team::get($fw->db, $id, $fields),
            team::event_revision
        );
    }

    // --- action

    static function action_insert(
        \Base $fw,
        int $command,
        string $handle,
        string $name,
        string $description,
        string $link,
        string $icon
    ): int {
        $insert = 'INSERT INTO ' . db::prefix() . team::table . ' ('
            . team::handle . ','
            . team::name . ','
            . team::description . ','
            . team::link . ','
            . team::icon . ','
            . team::version . ') VALUES (?,?,?,?,?,?)';

        $fw->db->exec($insert, array(
            1 => $handle,
            2 => $name,
            3 => $description,
            4 => $link,
            5 => $icon,
            6 => team::latest,
        ));

        $id = db::get_last_inserted_id($fw->db);
        team::event_insert($fw, $id, $command, team::event_created);

        return $id;
    }

    static function action_update(
        \Base $fw,
        int $command,
        int $team,
        string $name,
        string $description,
        string $link,
        string $icon
    ): void {
        $update = 'UPDATE ' . db::prefix() . team::table . ' SET '
            . team::name . '=?,'
            . team::description . '=?,'
            . team::link . '=?,'
            . team::icon . '=? WHERE '
            . team::id . '=?';

        $fw->db->exec($update, array(
            1 => $name,
            2 => $description,
            3 => $link,
            4 => $icon,
            5 => $team,
        ));

        team::event_insert($fw, $team, $command, team::event_updated);
    }

    static function action_delete(\Base $fw, int $command, int $id): void
    {
        team::event_insert($fw, $id, $command, team::event_deleted);

        $delete = 'DELETE FROM ' . db::prefix() . team::table . ' WHERE ' . team::id . '=?';

        $fw->db->exec($delete, $id);
    }

    static function action_deactivate(\Base $fw, int $command, int $id): void
    {
        team::event_insert($fw, $id, $command, team::event_deactivated);

        $update = 'UPDATE ' . db::prefix() . team::table . ' SET ' . team::active . '=0 WHERE ' . team::id . '=?';

        $fw->db->exec($update, $id);
    }

    // --- command

    static function command_add(\Base $fw, team_data $data): void
    {
        $command = \cmd::begin($fw, \cmd::team_add);

        $handle = team::new_handle($fw->db);

        $team_id = team::action_insert($fw, $command, $handle, $data->name, $data->description, $data->link, $data->icon);

        $result = 1;

        foreach ($data->subjects as $subject_handle) {
            $subject = subject::get_by_handle($fw->db, $subject_handle, subject::id);
            if (empty($subject))
                continue;

            team_subject::action_insert($fw, $command, $team_id, (int)$subject[subject::id]);

            $result++;
        }

        \cmd::end_user($fw, $command, $result);
    }

    static function command_update(\Base $fw, string $handle, team_data $data): void
    {
        $command = \cmd::begin($fw, \cmd::team_update);

        $team = team::get_by_handle($fw->db, $handle, team::id . ',' . team::active);
        if (empty($team))
            return;

        if ((int)$team[team::active] !== team::active_on)
            return;

        $team_id = (int)$team[team::id];

        team::action_update(
            $fw,
            $command,
            $team_id,
            $data->name,
            $data->description,
            $data->link,
            $data->icon
        );

        $result = 1;

        $result += team::command_assign_subjects($fw, $command, $team_id, $data->subjects);

        \cmd::end($fw, $command, $result);
    }

    static function command_assign_subjects(\Base $fw, int $command, int $team_id, array $subject_handles): int
    {
        $result = 0;

        // TODO: need to use facade (everywhere)
        $team_subject_list = team_subject::get_list_by_team($fw->db, $team_id);

        if (!empty($subject_handles)) {
            $updated = array();

            foreach ($subject_handles as $subject_handle) {
                $subject = subject::get_by_handle($fw->db, $subject_handle, subject::id);
                if (empty($subject))
                    continue;

                $subject_id = (int)$subject[subject::id];

                $found = false;
                foreach ($team_subject_list as $team_subject) {
                    if ($team_subject[team_subject::subject] === $subject_id) {
                        $found = true;

                        $updated[] = $subject_id;
                        break;
                    }
                }

                if (!$found) {
                    team_subject::action_insert($fw, $command, $team_id, $subject_id);

                    $updated[] = $subject_id;
                    $result++;
                }
            }

            foreach ($team_subject_list as $team_subject) {
                if (!in_array((int)$team_subject[team_subject::subject], $updated)) {
                    $team_subject_id = (int)$team_subject[team_subject::id];
                    team_subject::action_delete($fw, $command, $team_subject_id);

                    $result++;
                }
            }
        } else {
            foreach ($team_subject_list as $team_subject) {
                $team_subject_id = (int)$team_subject[team_subject::id];
                team_subject::action_delete($fw, $command, $team_subject_id);

                $result++;
            }
        }

        return $result;
    }

    static function command_remove(\Base $fw, string $handle): void
    {
        $command = \cmd::begin($fw, \cmd::team_remove);

        $team = team::get_by_handle($fw->db, $handle, team::id . ',' . team::active);
        if (empty($team))
            return;

        if ((int)$team[team::active] !== team::active_on)
            return;

        $team_id = (int)$team[team::id];

        team::action_deactivate($fw, $command, $team_id);

        \cmd::end($fw, $command);
    }
}

class team_data
{
    public string $name;
    public string $description;
    public string $link;
    public string $icon;

    public array $subjects;

    static function create(\Base $fw): team_data
    {
        $data = new team_data();

        if (!$fw->exists('POST.name'))
            return null;

        $data->name = base::trim_fw($fw, 'POST.name', 'max_name_length');
        if (strlen($data->name) === 0)
            return null;

        $data->description = base::trim_fw($fw, 'POST.description', 'max_description_length');
        $data->link = base::trim_fw($fw, 'POST.link', 'max_link_length');
        $data->icon = base::trim_fw($fw, 'POST.icon', 'max_icon_length');

        $data->subjects = (array)$fw->get('POST.subjects');

        return $data;
    }
}
