<?php
namespace Plugins\WelcomeDM;

// Disable direct access
if (!defined('APP_VERSION')) 
    die("Yo, what's up?"); 



/**
 * All functions related to the cron task
 */



/**
 * Add cron task to like new posts
 */
function addCronTask()
{
    require_once __DIR__."/models/SchedulesModel.php";
    require_once __DIR__."/models/LogModel.php";

    // Get schedules
    $Schedules = new SchedulesModel;
    $Schedules->where("is_active", "=", 1)
              ->where("schedule_date", "<=", date("Y-m-d H:i:s"))
              ->where("end_date", ">=", date("Y-m-d H:i:s"))
              ->orderBy("last_action_date", "ASC")
              ->setPageSize(10) // required to prevent server overload
              ->setPage(1)
              ->fetchData();

    if ($Schedules->getTotalCount() < 1) {
        // There is not any active schedule
        return false;
    }

    // Get settings 
    $settings = namespace\settings();

    // Random delays between actions
    $random_delay = 0;
    if ($settings->get("data.random_delay")) {
        $random_delay = rand(0, 300);
    }

    // Speeds
    $default_speeds = [
        "very_slow" => 1,
        "slow" => 2,
        "medium" => 3,
        "fast" => 4,
        "very_fast" => 5,
    ];
    $speeds = $settings->get("data.speeds");
    if (empty($speeds)) {
        $speeds = [];
    } else {
        $speeds = json_decode(json_encode($speeds), true);
    }
    $speeds = array_merge($default_speeds, $speeds);

    $as = [__DIR__."/models/ScheduleModel.php", __NAMESPACE__."\ScheduleModel"];
    foreach ($Schedules->getDataAs($as) as $sc) {
        $Log = new LogModel;
        $Account = \Controller::model("Account", $sc->get("account_id"));
        $User = \Controller::model("User", $sc->get("user_id"));


        // Set default values for the log (not save yet)...
        $Log->set("user_id", $User->get("id"))
            ->set("account_id", $Account->get("id"))
            ->set("status", "error");


        // Check account
        if (!$Account->isAvailable() || $Account->get("login_required")) {
            // Account is either removed (unexected, external factors)
            // Or login required for this account
            // Deactivate schedule
            $sc->set("is_active", 0)->save();

            // Log data
            $Log->set("data.error.msg", "Activity has been stopped")
                ->set("data.error.details", "Re-login is required for the account.")
                ->save();
            continue;
        }

        // Check the user
        if (!$User->isAvailable() || !$User->get("is_active") || $User->isExpired()) {
            // User is not valid
            // Deactivate schedule
            $sc->set("is_active", 0)->save();

            // Log data
            $Log->set("data.error.msg", "Activity has been stopped")
                ->set("data.error.details", "User account is either disabled or expired.")
                ->save();
            continue;
        }

        if ($User->get("id") != $Account->get("user_id")) {
            // Unexpected, data modified by external factors
            // Deactivate schedule
            $sc->set("is_active", 0)->save();
            continue;
        }

        // Check user access to the module
        $user_modules = $User->get("settings.modules");
        if (!is_array($user_modules) || !in_array(IDNAME, $user_modules)) {
            // Module is not accessible to this user
            // Deactivate schedule
            $sc->set("is_active", 0)->save();

            // Log data
            $Log->set("data.error.msg", "Activity has been stopped")
                ->set("data.error.details", "Module is not accessible to your account.")
                ->save();
            continue;
        }


        // Calculate next schedule datetime...
        if (isset($speeds[$sc->get("speed")]) && (int)$speeds[$sc->get("speed")] > 0) {
            $speed = (int)$speeds[$sc->get("speed")];
            $delta = round(3600/$speed) + $random_delay;
        } else {
            $delta = rand(720, 7200);
        }

        $next_schedule = date("Y-m-d H:i:s", time() + $delta);
        if ($sc->get("daily_pause")) {
            $pause_from = date("Y-m-d")." ".$sc->get("daily_pause_from");
            $pause_to = date("Y-m-d")." ".$sc->get("daily_pause_to");
            if ($pause_to <= $pause_from) {
                // next day
                $pause_to = date("Y-m-d", time() + 86400)." ".$sc->get("daily_pause_to");
            }

            if ($next_schedule > $pause_to) {
                // Today's pause interval is over
                $pause_from = date("Y-m-d H:i:s", strtotime($pause_from) + 86400);
                $pause_to = date("Y-m-d H:i:s", strtotime($pause_to) + 86400);
            }

            if ($next_schedule >= $pause_from && $next_schedule <= $pause_to) {
                $next_schedule = $pause_to;
            }
        }
        $sc->set("schedule_date", $next_schedule)
           ->set("last_action_date", date("Y-m-d H:i:s"))
           ->save();

        // Check messages
        $messages = @json_decode($sc->get("messages"));
        if (is_null($messages)) {
            // Unexpected, data modified by external factors or empty messages
            // Deactivate schedule
            $sc->set("is_active", 0)->save();
            continue;
        }

        if (count($messages) < 1) {
            // Comment list is empty
            // Deactivate schedule
            $sc->set("is_active", 0)->save();

            // Log data
            $Log->set("data.error.msg", "Message list is empty.")
                ->save();
            continue;
        }

        // Login into the account
        try {
            $Instagram = \InstagramController::login($Account);
        } catch (\Exception $e) {
            // Couldn't login into the account
            $Account->refresh();

            // Log data
            if ($Account->get("login_required")) {
                $sc->set("is_active", 0)->save();
                $Log->set("data.error.msg", "Activity has been stopped");
            } else {
                $Log->set("data.error.msg", "Action re-scheduled");
            }

            $Log->set("data.error.details", $e->getMessage())
                ->save();

            continue;
        }


        // Find user to send welcome DM.
        $follower_id = null;
        $follower_username = null;
        $follower_fullname = null;
        $follower_profile_pic = null;

        try {
            $ai = $Instagram->people->getRecentActivityInbox();
        } catch (\Exception $e) {
            // Couldn't get activity inbox
            // Log data
            $msg = $e->getMessage();
            $msg = explode(":", $msg, 2);
            $msg = isset($msg[1]) ? $msg[1] : $msg[0];

            $Log->set("data.error.msg", "Couldn't get the activity feed")
                ->set("data.error.details", $msg)
                ->save();
            continue;   
        }
        
        $stories = array_merge($ai->getNewStories(), $ai->getOldStories());
        $stories = array_reverse($stories);
        foreach ($stories as $s) {
            if ($s->getType() != 3 || !$s->getArgs()->getProfileId()) {
                continue;
            }

            $_log = new LogModel([
                "user_id" => $User->get("id"),
                "account_id" => $Account->get("id"),
                "follower_id" => $s->getArgs()->getProfileId(),
                "status" => "success"
            ]);

            if (!$_log->isAvailable()) {
                // Found the follower to DM
                $follower_id = $s->getArgs()->getProfileId();

                if ($s->getArgs()->getInlineFollow()) {
                    $follower_username = $s->getArgs()->getInlineFollow()->getUserInfo()->getUsername();
                    $follower_fullname = $s->getArgs()->getInlineFollow()->getUserInfo()->getFullName();
                    $follower_profile_pic = $s->getArgs()->getInlineFollow()->getUserInfo()->getProfilePicUrl();
                } else {
                    $follower_username = $s->getArgs()->getProfileName();
                    $follower_profile_pic = $s->getArgs()->getProfileImage();
                }

                break;
            }

            // Still not found, check if 'second_profile_id' is exists. 
            if ($s->getArgs()->getSecondProfileId()) {
                $_log = new LogModel([
                    "user_id" => $User->get("id"),
                    "account_id" => $Account->get("id"),
                    "follower_id" => $s->getArgs()->getSecondProfileId(),
                    "status" => "success"
                ]);
                
                if (!$_log->isAvailable()) {
                    // Get user info for the second profile
                    try {
                        $second_profile_info = $Instagram->people->getInfoById($s->getArgs()->getSecondProfileId());
                    } catch (\Exception $e) {
                        // Don't anything here, accept the issue as not found
                    }

                    $follower_id = $s->getArgs()->getSecondProfileId();
                    $follower_username = $second_profile_info->getUser()->getUsername();
                    $follower_fullname = $second_profile_info->getUser()->getFullName();
                    $follower_profile_pic = $s->getArgs()->getSecondProfileImage();
                    break;
                }
            }

        }

        if (empty($follower_id)) {
            $Log->set("data.error.msg", "Couldn't find any new follower to send a DM")
                ->save();
            continue;
        }


        // Emojione client
        $Emojione = new \Emojione\Client(new \Emojione\Ruleset());

        // Select random message from the defined message collection
        $i = rand(0, count($messages) - 1);
        $message = $messages[$i];
        
        $search = ["{{username}}", "{{full_name}}"];
        $replace = ["@".$follower_username, 
                    $follower_fullname ? $follower_fullname : "@".$follower_username];
        $message = str_replace($search, $replace, $message);

        $message = $Emojione->shortnameToUnicode($message);

        // Check spintax permission
        if ($User->get("settings.spintax")) {
            $message = \Spintax::process($message);
        }


        // New folloer found
        // Send DM
        try {
            $res = $Instagram->direct->sendText(
                ["users" => [$follower_id]], 
                $message);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $msg = explode(":", $msg, 2);
            $msg = isset($msg[1]) ? $msg[1] : $msg[0];

            $Log->set("data.error.msg", "Couldn't send a message")
                ->set("data.error.details", $msg)
                ->save();
            continue;
        }

        if (!$res->isOk()) {
            $Log->set("data.error.msg", __("An error while sending a message."))
                ->set("data.error.details", __("Instagram didn't return the expected result."))
                ->save();
            continue;
        }


        // Send DM successfully
        // Save log
        $Log->set("status", "success")
            ->set("data.message", $Emojione->toShort($message))
            ->set("data.to", [
                "id" => $follower_id,
                "username" => $follower_username,
                "fullname" => $follower_fullname,
                "profile_pic" => $follower_profile_pic
            ])
            ->set("follower_id", $follower_id)
            ->save();
    }
}
\Event::bind("cron.add", __NAMESPACE__."\addCronTask");
