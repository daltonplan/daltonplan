<?php

declare(strict_types=1);

namespace core;

use frame\app;
use frame\base;
use frame\session;

class web extends app
{
    function labs(\Base $fw): void
    {
        if (!\account\web::logged_user($fw))
            return;

        $user_only = $fw->exists('PARAMS.my');
        if (!\account\service::student($fw) && $user_only)
            team::set_list_only_user($fw, \account\service::get_id($fw));

        if (\account\service::student($fw) || $user_only) {
            subject::set_list_only_team_list($fw, $fw->get(team::list));
            lab::set_list_only_user($fw);
        } else {
            lab::set_list_detail($fw);
        }

        base::render('core/labs.htm');
    }

    function labs_archive(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator_coach($fw))
            return;

        if (!\account\web::set_user($fw))
            return;

        lab::set_list_detail_archive($fw);

        base::render('core/labs_archive.htm');
    }

    function subjects(\Base $fw): void
    {
        if (!\account\web::logged_user($fw))
            return;

        $user_only = $fw->exists('PARAMS.my');
        if (!\account\service::student($fw) && $user_only)
            team::set_list_only_user($fw, \account\service::get_id($fw));

        if (\account\service::student($fw) || $user_only)
            subject::set_list_only_team_list($fw, $fw->get(team::list));
        else
            subject::set_list($fw);

        base::render('core/subjects.htm');
    }

    function subjects_archive(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator_coach($fw))
            return;

        if (!\account\web::set_user($fw))
            return;

        subject::set_list_archive($fw);

        base::render('core/subjects_archive.htm');
    }

    function teams(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator_coach($fw))
            return;

        if (!\account\web::set_user($fw))
            return;

        team::set_list_detail($fw);

        base::render('core/teams.htm');
    }

    function fetch_add_lab(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin($fw))
            return;

        subject::set_list($fw);
        team::set_list($fw);

        session::new_csrf($fw);
        base::render('core/lab/fetch/add.htm');
    }

    function add_lab(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin($fw))
            return;

        if (!session::check_csrf_redirect($fw, lab::table, \account\service::get_handle($fw)))
            return;

        if (!lab::add($fw)) {
            base::redirect($fw);
            return;
        }

        base::return($fw);
    }

    function fetch_add_subject(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin_moderator($fw))
            return;

        subject::set_list_top($fw);

        session::new_csrf($fw);
        base::render('core/subject/fetch/add.htm');
    }

    function add_subject(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator($fw))
            return;

        if (!session::check_csrf_redirect($fw, subject::table, \account\service::get_handle($fw)))
            return;

        $subject_data = subject_data::create($fw);
        if ($subject_data === null) {
            base::redirect($fw);
            return;
        }

        subject::command_add($fw, $subject_data);

        base::return($fw);
    }

    function fetch_add_team(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin_moderator($fw))
            return;

        subject::set_list($fw);

        session::new_csrf($fw);
        base::render('core/team/fetch/add.htm');
    }

    function add_team(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator($fw))
            return;

        if (!session::check_csrf_redirect($fw, team::table, \account\service::get_handle($fw)))
            return;

        $team_data = team_data::create($fw);
        if ($team_data === null) {
            base::redirect($fw);
            return;
        }

        team::command_add($fw, $team_data);

        base::return($fw);
    }

    function fetch_assign_team_user(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin_moderator($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        $team = team::get_by_handle($fw->db, $handle, team::id);
        if (empty($team)) {
            base::fetch_no_permission();
            return;
        }

        $team_id = (int)$team[team::id];

        team_user::set_user_list($fw, $team_id);

        session::new_csrf($fw);
        base::render('core/team/fetch/assign_user.htm');
    }

    function assign_team_user(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator($fw))
            return;

        if (!session::check_csrf_redirect($fw, team_user::table, \account\service::get_handle($fw)))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $users = (array)$fw->get('POST.users');

        team_user::command_assign($fw, $handle, $users);

        base::return($fw);
    }

    function fetch_assign_team_subject(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin_moderator($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        $team = team::get_by_handle($fw->db, $handle, team::id);
        if (empty($team)) {
            base::fetch_no_permission();
            return;
        }

        $team_id = (int)$team[team::id];

        subject::set_list_by_team($fw, $team_id);

        session::new_csrf($fw);
        base::render('core/team/fetch/assign_subject.htm');
    }

    function assign_team_subject(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator($fw))
            return;

        if (!session::check_csrf_redirect($fw, team_subject::table, \account\service::get_handle($fw)))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $subjects = (array)$fw->get('POST.subjects');

        team_subject::command_assign($fw, $handle, $subjects);

        base::return($fw);
    }

    function fetch_restrict_lab(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin_moderator($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        $lab = lab::get_by_handle($fw->db, $handle, lab::id);
        if (empty($lab)) {
            base::fetch_no_permission();
            return;
        }

        $lab_id = (int)$lab[lab::id];

        team::set_list_by_lab($fw, $lab_id);
        subject::set_list_by_lab($fw, $lab_id);

        session::new_csrf($fw);
        base::render('core/lab/fetch/restrict.htm');
    }

    function restrict_lab(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator($fw))
            return;

        if (!session::check_csrf_redirect($fw, 'restrict_lab', \account\service::get_handle($fw)))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $teams = (array)$fw->get('POST.teams');
        $subjects = (array)$fw->get('POST.subjects');

        lab::command_restrict($fw, $handle, $teams, $subjects);

        base::return($fw);
    }

    function fetch_edit_lab(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        if ((strlen($handle)) === 0) {
            base::fetch_no_permission();
            return;
        }

        $lab = lab::get_by_handle($fw->db, $handle);
        if (empty($lab)) {
            base::fetch_no_permission();
            return;
        }

        $fw->set('lab', $lab);

        $lab_id = (int)$lab[lab::id];

        team::set_list_by_lab($fw, $lab_id);
        subject::set_list_by_lab($fw, $lab_id);

        session::new_csrf($fw);
        base::render('core/lab/fetch/edit.htm');
    }

    function edit_lab(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin($fw))
            return;

        if (!session::check_csrf_redirect($fw, lab::table, \account\service::get_handle($fw)))
            return;

        $lab_data = lab_data::create($fw);
        if ($lab_data === null) {
            base::redirect($fw);
            return;
        }

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        lab::command_update($fw, $handle, $lab_data);

        base::return($fw);
    }

    function fetch_edit_subject(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin_moderator($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        if ((strlen($handle)) === 0) {
            base::fetch_no_permission();
            return;
        }

        $subject = subject::get_by_handle($fw->db, $handle);
        if (empty($subject)) {
            base::fetch_no_permission();
            return;
        }

        $subject_id = (int)$subject[subject::id];
        $subject[subject::children] = subject::count_children($fw->db, $subject_id);

        $fw->set(subject::table, $subject);

        $subject_parent = (int)$subject[subject::parent];

        if ((int)$subject[subject::active] === subject::active_on)
            subject::set_list_top_by_parent($fw, $subject_parent);
        else
            subject::set_list_top_all_by_parent($fw, $subject_parent);

        session::new_csrf($fw);
        base::render('core/subject/fetch/edit.htm');
    }

    function edit_subject(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator($fw))
            return;

        if (!session::check_csrf_redirect($fw, subject::table, \account\service::get_handle($fw)))
            return;

        $subject_data = subject_data::create($fw);
        if ($subject_data === null) {
            base::redirect($fw);
            return;
        }

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        subject::command_update($fw, $handle, $subject_data);

        base::return($fw);
    }

    function fetch_edit_team(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin_moderator($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        if ((strlen($handle)) === 0) {
            base::fetch_no_permission();
            return;
        }

        $team = team::get_by_handle($fw->db, $handle);
        if (empty($team)) {
            base::fetch_no_permission();
            return;
        }

        $fw->set('team', $team);

        $team_id = (int)$team[team::id];

        subject::set_list_by_team($fw, $team_id);

        session::new_csrf($fw);
        base::render('core/team/fetch/edit.htm');
    }

    function edit_team(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator($fw))
            return;

        if (!session::check_csrf_redirect($fw, team::table, \account\service::get_handle($fw)))
            return;

        $team_data = team_data::create($fw);
        if ($team_data === null) {
            base::redirect($fw);
            return;
        }

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        team::command_update($fw, $handle, $team_data);

        base::return($fw);
    }

    function remove_lab(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        lab::command_remove($fw, $handle);

        base::return($fw);
    }

    function remove_subject(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        subject::command_remove($fw, $handle);

        base::return($fw);
    }

    function remove_team(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        team::command_remove($fw, $handle);

        base::return($fw);
    }

    function lab(\Base $fw): void
    {
        if (!\account\web::logged_user($fw))
            return;

        base::render('core/lab/detail.htm');
    }

    function subject(\Base $fw): void
    {
        if (!\account\web::logged_user($fw))
            return;

        base::render('core/subject/detail.htm');
    }

    function team(\Base $fw): void
    {
        if (!\account\web::logged_user($fw))
            return;

        base::render('core/team/detail.htm');
    }

    function change_lab_code(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        lab::command_change_code($fw, $handle);

        base::return($fw);
    }

    function archive_subject(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        subject::command_archive($fw, $handle);

        base::return($fw);
    }

    function activate_subject(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        subject::command_activate($fw, $handle);

        base::return($fw);
    }

    function archive_lab(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        lab::command_archive($fw, $handle);

        base::return($fw);
    }

    function activate_lab(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', base::max_handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        lab::command_activate($fw, $handle);

        base::return($fw);
    }
}
