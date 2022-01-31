<?php

declare(strict_types=1);

namespace domain;

use frame\app;
use frame\base;
use frame\db;
use frame\session;

class web extends app
{
    function setup(\Base $fw): void
    {
        if (!db::ready($fw)) {
            echo 'error: no database - check configuration';
            return;
        }

        session::reset($fw);

        service::setup_db($fw);

        if (\account\service::create_owner($fw)) {
            \account\web::login_unchecked($fw);
            return;
        }

        base::redirect($fw);
    }

    function terms(\Base $fw): void
    {
        $lang = $fw->get('lang');

        $tos_file = $lang . '.md';
        $tos_folder = $fw->get('TOS');

        $file = $fw->read($tos_folder . $tos_file);
        $content = \Markdown::instance()->convert($file);

        $fw->set('tos_content', $content);

        base::render('terms.htm');
    }

    function fetch_feedback(\Base $fw): void
    {
        if (!\account\web::fetch_logged($fw))
            return;

        session::new_csrf($fw);
        base::render('fetch/feedback.htm');
    }

    function feedback(\Base $fw): void
    {
        if (!\account\web::logged($fw))
            return;

        if (!session::check_csrf_redirect($fw, 'feedback', \account\service::get_handle($fw)))
            return;

        $description = base::trim_fw($fw, 'POST.description', 'max_description_length');
        if (strlen($description) !== 0)
            \frame\mail::send_feedback($fw, $description);

        base::return($fw);
    }
}
