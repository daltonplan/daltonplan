<?php

declare(strict_types=1);

namespace core;

use DB\SQL;

use frame\base;
use frame\db;
use frame\id;
use frame\session;

abstract class lab
{
    const latest = 0;
    const table = 'lab';
    const list = 'lab_list';

    const id = db::id;
    const handle = db::handle;
    const participation = 'participation';
    const name = 'name';
    const description = 'description';
    const link = 'link';
    const icon = 'icon';
    const room = 'room';
    const capacity = 'capacity';
    const locked = 'locked';
    const managed = 'managed';
    const parent = 'parent';
    const active = 'active';
    const version = db::version;
    const created = db::created;
    const updated = db::updated;

    const all = lab::id . ','
        . lab::handle  . ','
        . lab::participation  . ','
        . lab::name . ','
        . lab::description . ','
        . lab::link . ','
        . lab::icon . ','
        . lab::room . ','
        . lab::capacity . ','
        . lab::locked . ','
        . lab::managed . ','
        . lab::parent . ','
        . lab::active . ','
        . lab::version . ','
        . lab::created . ','
        . lab::updated;

    // locked
    const locked_off    = 0;
    const locked_on     = 1;

    // managed
    const managed_off   = 0;
    const managed_on    = 1;

    // active
    const active_on  = 1;
    const active_off = 0;

    static function create(\Base $fw): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . db::prefix() . lab::table . '` (
                `' . lab::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . lab::handle . '` varchar(' . $fw->get(base::max_handle_length) . ') NOT NULL,
                `' . lab::participation . '` varchar(' . $fw->get(base::max_handle_length) . ') NOT NULL,
                `' . lab::name . '` varchar(' . $fw->get('max_name_length') . ') NOT NULL,
                `' . lab::description . '` varchar(' . $fw->get('max_description_length') . ') NOT NULL,
                `' . lab::link . '` varchar(' . $fw->get('max_link_length') . ') NOT NULL,
                `' . lab::icon . '` varchar(' . $fw->get('max_icon_length') . ') NOT NULL,
                `' . lab::room . '` varchar(' . $fw->get('max_room_length') . ') NOT NULL,
                `' . lab::capacity . '` int(11) NOT NULL,
                `' . lab::locked . '` int(11) NOT NULL DEFAULT 0,
                `' . lab::managed . '` int(11) NOT NULL DEFAULT 0,
                `' . lab::parent . '` int(11) NOT NULL DEFAULT 0,
                `' . lab::active . '` int(11) NOT NULL DEFAULT 1,
                `' . lab::version . '` int(11) NOT NULL DEFAULT ' . lab::latest . ',
                `' . lab::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                `' . lab::updated . '` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`' . lab::id . '`) USING BTREE,
                UNIQUE KEY `' . lab::handle . '` (`' . lab::handle . '`) USING BTREE,
                UNIQUE KEY `' . lab::participation . '` (`' . lab::participation . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    // ---

    static function set_list(\Base $fw): void
    {
        $lab_list = lab::get_list($fw->db);

        $fw->set(lab::list, $lab_list);
    }

    static function set_list_detail_info(\Base $fw, array $lab_list): array
    {
        $result = array();

        foreach ($lab_list as $l_key => $l_value) {
            $lab_id = (int)$l_value[lab::id];

            $team_list = array();

            $lab_team_list = lab_team::get_list_by_lab($fw->db, $lab_id, lab_team::team);
            foreach ($lab_team_list as $lt_value) {
                $team_id = (int)$lt_value[lab_team::team];

                $team = \core\service::get_team($fw, $team_id);
                if (empty($team))
                    continue;

                if ((int)$team[team::active] !== team::active_on)
                    continue;

                $team_list[] = $team;
            }

            $subject_list = array();
            $lab_subject_list = lab_subject::get_list_by_lab($fw->db, $lab_id, lab_subject::subject);
            foreach ($lab_subject_list as $ls_value) {
                $subject_id = (int)$ls_value[lab_subject::subject];

                $subject = service::get_subject($fw, $subject_id);
                if (empty($subject))
                    continue;

                if ((int)$subject[subject::active] !== subject::active_on)
                    continue;

                subject::check_parent($subject);
                $subject_list[] = $subject;
            }

            $result[$l_key] = $l_value;
            $result[$l_key][team::list] = $team_list;
            $result[$l_key][subject::list] = $subject_list;
        }

        return $result;
    }

    static function set_list_detail(\Base $fw): void
    {
        $lab_list = lab::get_list($fw->db);

        $result = lab::set_list_detail_info($fw, $lab_list);

        $fw->set(lab::list, $result);
    }

    static function set_list_detail_archive(\Base $fw): void
    {
        $lab_list = lab::get_list_inactive($fw->db);

        $result = lab::set_list_detail_info($fw, $lab_list);

        $fw->set(lab::list, $result);
    }

    static function set_list_only_user(\Base $fw): void
    {
        $user_subject_list = $fw->get(subject::list);

        if (empty($user_subject_list)) {
            $fw->set(lab::list, array());
            return;
        }

        $result = array();

        $user_team_list = $fw->get(team::list);

        $lab_list = lab::get_list($fw->db);

        foreach ($lab_list as $l_key => $l_value) {
            $lab_id = (int)$l_value[lab::id];

            if ((int)$l_value[lab::locked] === lab::locked_on)
                continue;

            $team_list = array();

            $lab_team_list = lab_team::get_list_by_lab($fw->db, $lab_id, lab_team::team);
            foreach ($lab_team_list as $lt_value) {
                $team_id = (int)$lt_value[lab_team::team];

                $team = \core\service::get_team($fw, $team_id);
                if (empty($team))
                    continue;

                if ((int)$team[team::active] !== team::active_on)
                    continue;

                $team_list[] = $team;
            }

            if (sizeof($team_list) > 0) {
                $found = false;

                foreach ($team_list as $t_value) {

                    foreach ($user_team_list as $ut_value) {
                        if ((int)$t_value[team::id] === (int)$ut_value[team::id]) {
                            $found = true;
                            break;
                        }
                    }

                    if ($found)
                        break;
                }

                if (!$found)
                    continue;
            }

            $subject_list = array();

            $lab_subject_list = lab_subject::get_list_by_lab($fw->db, $lab_id, lab_subject::subject);
            foreach ($lab_subject_list as $ls_value) {
                $subject_id = (int)$ls_value[lab_subject::subject];

                $found = false;

                foreach ($user_subject_list as $us_value) {
                    if ((int)$us_value[subject::id] === $subject_id) {
                        $found = true;
                        break;
                    }

                    if (isset($us_value[subject::children])) {
                        foreach ($us_value[subject::children] as $child) {
                            if ((int)$child[subject::id] === $subject_id) {
                                $found = true;
                                break;
                            }
                        }
                    }

                    if ($found)
                        break;
                }

                if (!$found)
                    continue;

                $subject = service::get_subject($fw, $subject_id);
                if (empty($subject))
                    continue;

                subject::check_parent($subject);

                if ((int)$subject[subject::active] !== subject::active_on)
                    continue;

                if ((int)$subject[subject::locked] === subject::locked_on)
                    continue;

                $subject_list[] = $subject;
            }

            $result[$l_key] = $l_value;
            $result[$l_key][team::list] = $team_list;
            $result[$l_key][subject::list] = $subject_list;
        }

        $fw->set(lab::list, $result);
    }

    static function add(\Base $fw): bool
    {
        $lab_data = lab_data::create($fw);
        if ($lab_data === null)
            return false;

        lab::command_add($fw, $lab_data);

        return true;
    }

    // --- query

    static function select(string $fields): string
    {
        return 'SELECT ' . (($fields === '') ? lab::all : $fields) . ' FROM ' . db::prefix() . lab::table . ' ';
    }

    static function new_handle(SQL $sql): string
    {
        while (true) {
            $handle = id::gen_handle();
            if (empty(lab::get_by_handle($sql, $handle)))
                return $handle;
        }

        return '';
    }

    static function new_participation(SQL $sql): string
    {
        while (true) {
            $handle = id::gen_handle();
            if (empty(lab::get_by_participation($sql, $handle)))
                return $handle;
        }

        return '';
    }

    static function count(SQL $sql): int
    {
        $get = 'SELECT count(*) as total FROM ' . db::prefix() . lab::table . ' WHERE ' . lab::active . ' =' . lab::active_on;
        $result = $sql->exec($get);
        return empty($result) ? 0 : (int)$result[0]['total'];
    }

    static function get(SQL $sql, int $id, string $fields = ''): array
    {
        $get = lab::select($fields) . 'WHERE ' . lab::id . '=?';
        return base::first($sql->exec($get, $id));
    }

    static function get_by_handle(SQL $sql, string $handle, string $fields = ''): array
    {
        $get = lab::select($fields) . 'WHERE ' . lab::handle . '=?';
        return base::first($sql->exec($get, $handle));
    }

    static function get_by_participation(SQL $sql, string $participation, string $fields = ''): array
    {
        $get = lab::select($fields) . 'WHERE ' . lab::participation . '=?';
        return base::first($sql->exec($get, $participation));
    }

    static function get_list(SQL $sql, string $fields = ''): array
    {
        $get = lab::select($fields) . 'WHERE ' . lab::active . '=' . lab::active_on;

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . lab::name;
        else
            $get .= ' ORDER BY ' . lab::updated . ' DESC';

        return $sql->exec($get);
    }

    static function get_list_all(SQL $sql, string $fields = ''): array
    {
        $get = lab::select($fields);

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . lab::name;
        else
            $get .= ' ORDER BY ' . lab::updated . ' DESC';

        return $sql->exec($get);
    }

    static function get_list_inactive(SQL $sql, string $fields = ''): array
    {
        $get = lab::select($fields) . 'WHERE ' . lab::active . '=' . lab::active_off;

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . lab::name;
        else
            $get .= ' ORDER BY ' . lab::updated . ' DESC';

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
            \event::lab,
            $id,
            $command,
            $event,
            lab::get($fw->db, $id, $fields),
            lab::event_revision
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
        string $icon,
        string $room,
        int $capacity,
        bool $managed,
        bool $locked
    ): int {

        $participation = lab::new_participation($fw->db);

        $insert = 'INSERT INTO ' . db::prefix() . lab::table . ' ('
            . lab::handle . ','
            . lab::participation . ','
            . lab::name . ','
            . lab::description . ','
            . lab::link . ','
            . lab::icon . ','
            . lab::room . ','
            . lab::capacity . ','
            . lab::managed . ','
            . lab::locked . ','
            . lab::version . ') VALUES (?,?,?,?,?,?,?,?,?,?,?)';

        $fw->db->exec($insert, array(
            1 => $handle,
            2 => $participation,
            3 => $name,
            4 => $description,
            5 => $link,
            6 => $icon,
            7 => $room,
            8 => $capacity,
            9 => $managed,
            10 => $locked,
            11 => lab::latest,
        ));

        $id = db::get_last_inserted_id($fw->db);
        lab::event_insert($fw, $id, $command, lab::event_created);

        return $id;
    }

    static function action_update(
        \Base $fw,
        int $command,
        int $lab,
        string $name,
        string $description,
        string $link,
        string $icon,
        string $room,
        int $capacity,
        bool $managed,
        bool $locked
    ): void {
        $update = 'UPDATE ' . db::prefix() . lab::table . ' SET '
            . lab::name . '=?,'
            . lab::description . '=?,'
            . lab::link . '=?,'
            . lab::icon . '=?,'
            . lab::room . '=?,'
            . lab::capacity . '=?,'
            . lab::managed . '=?,'
            . lab::locked . '=? WHERE '
            . lab::id . '=?';

        $fw->db->exec($update, array(
            1 => $name,
            2 => $description,
            3 => $link,
            4 => $icon,
            5 => $room,
            6 => $capacity,
            7 => $managed,
            8 => $locked,
            9 => $lab,
        ));

        lab::event_insert($fw, $lab, $command, lab::event_updated);
    }

    static function action_update_participation(\base $fw, int $command, int $id, string $participation): void
    {
        $update = 'UPDATE ' . db::prefix() . lab::table . ' SET '
            . lab::participation . '=? WHERE '
            . lab::id . '=?';

        $fw->db->exec($update, array(
            1 => $participation,
            2 => $id,
        ));

        lab::event_insert(
            $fw,
            $id,
            $command,
            lab::event_updated,
            lab::participation . ',' . lab::version
        );
    }

    static function action_delete(\Base $fw, int $command, int $id): void
    {
        lab::event_insert($fw, $id, $command, lab::event_deleted);

        $delete = 'DELETE FROM ' . db::prefix() . lab::table . ' WHERE ' . lab::id . '=?';

        $fw->db->exec($delete, $id);
    }

    static function action_activate(\Base $fw, int $command, int $id): void
    {
        lab::event_insert($fw, $id, $command, lab::event_activated);

        $update = 'UPDATE ' . db::prefix() . lab::table . ' SET ' . lab::active . '=' . lab::active_on . ' WHERE ' . lab::id . '=?';

        $fw->db->exec($update, $id);
    }

    static function action_deactivate(\Base $fw, int $command, int $id): void
    {
        lab::event_insert($fw, $id, $command, lab::event_deactivated);

        $update = 'UPDATE ' . db::prefix() . lab::table . ' SET ' . lab::active . '=' . lab::active_off . ' WHERE ' . lab::id . '=?';

        $fw->db->exec($update, $id);
    }

    // --- command

    static function command_add(\Base $fw, lab_data $data): void
    {
        $command = \cmd::begin($fw, \cmd::lab_add);

        $handle = lab::new_handle($fw->db);

        $lab_id = lab::action_insert(
            $fw,
            $command,
            $handle,
            $data->name,
            $data->description,
            $data->link,
            $data->icon,
            $data->room,
            $data->capacity,
            $data->managed,
            $data->locked
        );

        $result = 1;

        foreach ($data->teams as $team_handle) {
            $team = team::get_by_handle($fw->db, $team_handle, team::id);
            if (empty($team))
                continue;

            lab_team::action_insert($fw, $command, $lab_id, (int)$team[team::id]);

            $result++;
        }

        foreach ($data->subjects as $subject_handle) {
            $subject = subject::get_by_handle($fw->db, $subject_handle, subject::id);
            if (empty($subject))
                continue;

            lab_subject::action_insert($fw, $command, $lab_id, (int)$subject[subject::id]);

            $result++;
        }

        \cmd::end($fw, $command, $result);
    }

    static function command_update(\Base $fw, string $handle, lab_data $data): void
    {
        $command = \cmd::begin($fw, \cmd::lab_update);

        $lab = lab::get_by_handle($fw->db, $handle, lab::id . ',' . lab::active);
        if (empty($lab))
            return;

        $lab_id = (int)$lab[lab::id];

        // TODO: check if a field changed, then only update and add result = 1

        lab::action_update(
            $fw,
            $command,
            $lab_id,
            $data->name,
            $data->description,
            $data->link,
            $data->icon,
            $data->room,
            $data->capacity,
            $data->managed,
            $data->locked
        );

        $result = 1;

        $result += lab::command_assign_teams($fw, $command, $lab_id, $data->teams);
        $result += lab::command_assign_subjects($fw, $command, $lab_id, $data->subjects);

        \cmd::end($fw, $command, $result);
    }

    static function command_assign_teams(\Base $fw, int $command, int $lab_id, array $team_handles): int
    {
        $result = 0;

        $lab_team_list = lab_team::get_list_by_lab($fw->db, $lab_id);

        if (!empty($team_handles)) {
            $updated = array();

            foreach ($team_handles as $team_handle) {
                $team = team::get_by_handle($fw->db, $team_handle, team::id);
                if (empty($team))
                    continue;

                $team_id = (int)$team[team::id];

                $found = false;
                foreach ($lab_team_list as $lab_team) {
                    if ($lab_team[lab_team::team] === $team_id) {
                        $found = true;

                        $updated[] = $team_id;
                        break;
                    }
                }

                if (!$found) {
                    lab_team::action_insert($fw, $command, $lab_id, $team_id);

                    $updated[] = $team_id;
                    $result++;
                }
            }

            foreach ($lab_team_list as $lab_team) {
                if (!in_array((int)$lab_team[lab_team::team], $updated)) {
                    $lab_team_id = (int)$lab_team[lab_team::id];
                    lab_team::action_delete($fw, $command, $lab_team_id);

                    $result++;
                }
            }
        } else {
            foreach ($lab_team_list as $lab_team) {
                $lab_team_id = (int)$lab_team[lab_team::id];
                lab_team::action_delete($fw, $command, $lab_team_id);

                $result++;
            }
        }

        return $result;
    }

    static function command_assign_subjects(\Base $fw, int $command, int $lab_id, array $subject_handles): int
    {
        $result = 0;

        $lab_subject_list = lab_subject::get_list_by_lab($fw->db, $lab_id);

        if (!empty($subject_handles)) {
            $updated = array();

            foreach ($subject_handles as $subject_handle) {
                $subject = subject::get_by_handle($fw->db, $subject_handle, subject::id);
                if (empty($subject))
                    continue;

                $subject_id = (int)$subject[subject::id];

                $found = false;
                foreach ($lab_subject_list as $lab_subject) {
                    if ($lab_subject[lab_subject::subject] === $subject_id) {
                        $found = true;

                        $updated[] = $subject_id;
                        break;
                    }
                }

                if (!$found) {
                    lab_subject::action_insert($fw, $command, $lab_id, $subject_id);

                    $updated[] = $subject_id;
                    $result++;
                }
            }

            foreach ($lab_subject_list as $lab_subject) {
                if (!in_array((int)$lab_subject[lab_subject::subject], $updated)) {
                    $lab_subject_id = (int)$lab_subject[lab_subject::id];
                    lab_subject::action_delete($fw, $command, $lab_subject_id);

                    $result++;
                }
            }
        } else {
            foreach ($lab_subject_list as $lab_subject) {
                $lab_subject_id = (int)$lab_subject[lab_subject::id];
                lab_subject::action_delete($fw, $command, $lab_subject_id);

                $result++;
            }
        }

        return $result;
    }

    static function command_restrict(\Base $fw, string $handle, array $teams, array $subjects): void
    {
        $command = \cmd::begin($fw, \cmd::lab_restrict);

        $lab = lab::get_by_handle($fw->db, $handle, lab::id . ',' . lab::active);
        if (empty($lab))
            return;

        $lab_id = (int)$lab[lab::id];

        $result = 0;

        $result += lab::command_assign_teams($fw, $command, $lab_id, $teams);
        $result += lab::command_assign_subjects($fw, $command, $lab_id, $subjects);

        if ($result > 0)
            \cmd::end($fw, $command, $result);
    }

    static function command_remove(\Base $fw, string $handle): void
    {
        $command = \cmd::begin($fw, \cmd::lab_remove);

        $lab = lab::get_by_handle($fw->db, $handle, lab::id . ',' . lab::active);
        if (empty($lab))
            return;

        if ((int)$lab[lab::active] !== lab::active_off)
            return;

        $lab_id = (int)$lab[lab::id];

        lab::action_delete($fw, $command, $lab_id);

        \cmd::end($fw, $command);
    }

    static function command_change_code(\Base $fw, string $handle): void
    {
        $command = \cmd::begin($fw, \cmd::lab_change_code);

        $lab = lab::get_by_handle($fw->db, $handle, lab::id . ',' . lab::active);
        if (empty($lab))
            return;

        $lab_id = (int)$lab[lab::id];

        $participation = lab::new_participation($fw->db);

        lab::action_update_participation($fw, $command, $lab_id, $participation);

        \cmd::end($fw, $command);
    }

    static function command_archive(\Base $fw, string $handle): void
    {
        $command = \cmd::begin($fw, \cmd::lab_archive);

        $lab = lab::get_by_handle($fw->db, $handle, lab::id . ',' . lab::active);
        if (empty($lab))
            return;

        if ((int)$lab[lab::active] !== lab::active_on)
            return;

        $lab_id = (int)$lab[lab::id];

        lab::action_deactivate($fw, $command, $lab_id);

        \cmd::end($fw, $command);
    }

    static function command_activate(\Base $fw, string $handle): void
    {
        $command = \cmd::begin($fw, \cmd::lab_activate);

        $lab = lab::get_by_handle($fw->db, $handle, lab::id . ',' . lab::active);
        if (empty($lab))
            return;

        if ((int)$lab[lab::active] === lab::active_on)
            return;

        $lab_id = (int)$lab[lab::id];

        lab::action_activate($fw, $command, $lab_id);

        \cmd::end($fw, $command);
    }
}

class lab_data
{
    public string $name;
    public string $description;
    public string $link;
    public string $icon;

    public string $room;
    public int $capacity;

    public bool $managed;
    public bool $locked;

    public array $teams;
    public array $subjects;

    static function create(\Base $fw): lab_data
    {
        $data = new lab_data();

        if (!$fw->exists('POST.name'))
            return null;

        $data->name = base::trim_fw($fw, 'POST.name', 'max_name_length');
        if (strlen($data->name) === 0)
            return null;

        $data->description = base::trim_fw($fw, 'POST.description', 'max_description_length');
        $data->link = base::trim_fw($fw, 'POST.link', 'max_link_length');
        $data->icon = base::trim_fw($fw, 'POST.icon', 'max_icon_length');
        $data->room = base::trim_fw($fw, 'POST.room', 'max_room_length');

        $data->capacity = (int)$fw->get('POST.capacity');
        $max_users = (int)$fw->get('max_users');
        if ($data->capacity < 0)
            $data->capacity = 0;
        else if ($data->capacity > $max_users)
            $data->capacity = $max_users;

        $data->managed = $fw->exists('POST.managed');
        $data->locked = $fw->exists('POST.locked');

        $data->teams = (array)$fw->get('POST.teams');
        $data->subjects = (array)$fw->get('POST.subjects');

        return $data;
    }
}
