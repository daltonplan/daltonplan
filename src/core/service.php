<?php

declare(strict_types=1);

namespace core;

use account\user;

use frame\base;

class service
{
    static function setup_db(\Base $fw): void
    {
        lab_subject::create($fw);
        lab_team::create($fw);
        lab::create($fw);

        plan_team::create($fw);
        plan::create($fw);

        subject::create($fw);

        team_subject::create($fw);
        team_user::create($fw);
        team::create($fw);
    }

    static function get_user_list(\Base $fw, int $plan_id): array
    {
        $team_list = array();

        $plan_team_list = plan_team::get_list_by_plan($fw->db, $plan_id, plan_team::team);
        foreach ($plan_team_list as $plan_team) {
            $team_id = (int)$plan_team[plan_team::team];

            $team = service::get_team($fw, $team_id);
            if (empty($team))
                continue;

            if ($team[team::active] !== team::active_on)
                continue;

            $team_list[] = $team_id;
        }

        $user_list = array();

        foreach ($team_list as $team_id) {
            $team_user_list = team_user::get_list_by_team($fw->db, (int)$team_id, team_user::user);
            foreach ($team_user_list as $team_user) {
                $user_id = (int)$team_user[team_user::user];

                if (in_array($user_id, $user_list))
                    continue;

                $user = \account\service::get_user($fw, $user_id);
                if (empty($user))
                    continue;

                if ((int)$user[user::active] !== user::active_on)
                    continue;

                $user_list[] = $user_id;
            }
        }

        return $user_list;
    }

    static function get_subject(\Base $fw, int $subject_id): array
    {
        $repo_item = base::repo_item(subject::table, $subject_id);

        if (!$fw->exists($repo_item)) {
            $subject = subject::get($fw->db, $subject_id);
            if (empty($subject))
                return array();

            if ((int)$subject[subject::parent] !== subject::parent_none) {
                $parent_id = (int)$subject[subject::parent];
                $subject[subject::parent] = service::get_subject($fw, $parent_id);
            } else {
                $subject[subject::parent] = array(); // empty
            }

            $fw->set($repo_item, $subject);
            return $subject;
        }

        return (array)$fw->get($repo_item);
    }

    static function get_team(\Base $fw, int $team_id): array
    {
        $repo_item = base::repo_item(team::table, $team_id);

        if (!$fw->exists($repo_item)) {
            $team = team::get($fw->db, $team_id);
            if (empty($team))
                return array();

            $fw->set($repo_item, $team);
            return $team;
        }

        return (array)$fw->get($repo_item);
    }

    static function get_lab(\Base $fw, int $lab_id): array
    {
        $repo_item = base::repo_item(lab::table, $lab_id);

        if (!$fw->exists($repo_item)) {
            $lab = lab::get($fw->db, $lab_id);
            if (empty($lab))
                return array();

            $fw->set($repo_item, $lab);
            return $lab;
        }

        return (array)$fw->get($repo_item);
    }
}
