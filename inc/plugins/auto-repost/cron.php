<?php
namespace Plugins\AutoRepost;

// Disable direct access
if (!defined('APP_VERSION')) 
    die("Yo, what's up?"); 



/**
 * All functions related to the cron task
 */



/**
 * Add cron task to repost new posts
 */
function addCronTask()
{
    require_once __DIR__."/models/SchedulesModel.php";
    require_once __DIR__."/models/LogModel.php";

    // Emojione client
    $Emojione = new \Emojione\Client(new \Emojione\Ruleset());


    // Get auto repost schedules
    $Schedules = new SchedulesModel;
    $Schedules->where("is_active", "=", 1)
              ->where("schedule_date", "<=", date("Y-m-d H:i:s"))
              ->where("end_date", ">=", date("Y-m-d H:i:s"))
              ->orderBy("last_action_date", "ASC")
              ->setPageSize(5) // required to prevent server overload
              ->setPage(1)
              ->fetchData();

    if ($Schedules->getTotalCount() < 1) {
        // There is not any active schedule
        return false;
    }

    // Settings
    $settings = namespace\settings();

    // Random delays between actions
    $random_delay = 0;
    if ($settings->get("data.random_delay")) {
        $random_delay = rand(0, 3600); // up to an hour
    }

    // Speeds (action count per day)
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

        // Check the account
        if (!$Account->isAvailable() || $Account->get("login_required")) {
            // Account is either removed (unexected, external factors)
            // Or login reqiured for this account
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
            $delta = round(86400/$speed) + $random_delay;
        } else {
            $delta = rand(1200, 21600); // 20 min - 6 hours
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


        // Parse targets
        $targets = @json_decode($sc->get("target"));
        if (is_null($targets)) {
            // Unexpected, data modified by external factors or empty targets
            // Deactivate schedule
            $sc->set("is_active", 0)->save();
            continue;
        }

        if (count($targets) < 1) {
            // Couldn't find any target for the feed
            // Log data
            $Log->set("data.error.msg", "Couldn't find any target to search for the feed.")
                ->save();
            return false;
        }

        // Select random target from the defined target collection
        $i = rand(0, count($targets) - 1);
        $target = $targets[$i];

        if (empty($target->type) || empty($target->id) ||
            !in_array($target->type, ["hashtag", "location", "people"])) 
        {
            // Unexpected invalid target, 
            // data modified by external factors
            $sc->set("is_active", 0)->save();
            continue;   
        }

        $Log->set("data.trigger", $target);


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


        // Logged in successfully
        $permissions = $User->get("settings.post_types");
        $video_processing = isVideoExtenstionsLoaded() ? true : false;

        $acceptable_media_types = [];
        if (!empty($permissions->timeline_photo)) {
            $acceptable_media_types[] = "1"; // Photo
        }

        if (!empty($permissions->timeline_video)) {
            $acceptable_media_types[] = "2"; // Video
        }

        if (!empty($permissions->album_photo) || !empty($permissions->album_video)) {
            $acceptable_media_types[] = "8"; // Album
        }


        // Generate a random rank token.
        $rank_token = \InstagramAPI\Signatures::generateUUID();

        if ($target->type == "hashtag") {
            $hashtag = str_replace("#", "", trim($target->id));
            if (!$hashtag) {
                continue;
            }

            try {
                $feed = $Instagram->hashtag->getFeed(
                    $hashtag,
                    $rank_token);
            } catch (\Exception $e) {
                // Couldn't get instagram feed related to the hashtag
                // Log data
                $msg = $e->getMessage();
                $msg = explode(":", $msg, 2);
                $msg = isset($msg[1]) ? $msg[1] : $msg[0];

                $Log->set("data.error.msg", "Couldn't get the feed")
                    ->set("data.error.details", $msg)
                    ->save();
                continue;
            }

            $items = array_merge($feed->getRankedItems(), $feed->getItems());
        } else if ($target->type == "location") {
            try {
                $feed = $Instagram->location->getFeed(
                    $target->id, 
                    $rank_token);
            } catch (\Exception $e) {
                // Couldn't get instagram feed related to the location id
                // Log data
                $msg = $e->getMessage();
                $msg = explode(":", $msg, 2);
                $msg = isset($msg[1]) ? $msg[1] : $msg[0];

                $Log->set("data.error.msg", "Couldn't get the feed")
                    ->set("data.error.details", $msg)
                    ->save();
                continue;
            }

            $items = $feed->getItems();
        } else if ($target->type == "people") {
            try {
                $feed = $Instagram->timeline->getUserFeed($target->id);
            } catch (\Exception $e) {
                // Couldn't get instagram feed related to the user id
                // Log data
                $msg = $e->getMessage();
                $msg = explode(":", $msg, 2);
                $msg = isset($msg[1]) ? $msg[1] : $msg[0];

                $Log->set("data.error.msg", "Couldn't get the feed")
                    ->set("data.error.details", $msg)
                    ->save();
                continue;
            }

            $items = $feed->getItems();
        }


        // Found feed item to repost
        $feed_item = null;

        // Shuffe items
        shuffle($items);

        // Iterate through the items to find a proper item to repost
        foreach ($items as $item) {
            if (!$item->getId()) {
                // Item is not valid
                continue;
            }

            if (!in_array($item->getMediaType(), $acceptable_media_types)) {
                // User has not got a permission to post this kind of the item
                continue;
            }

            if ($item->getMediaType() == 2 && !$video_processing) {
                // Video processing is not possible now,
                // FFMPEG is not configured
                continue;
            }

            if ($item->getMediaType() == 8) {
                $medias = $item->getCarouselMedia();
                $is_valid = true;
                foreach ($medias as $media) {
                    if ($media->getMediaType() == 1 && empty($permissions->album_photo)) {
                        // User has not got a permission for photo albums
                        $is_valid = false;
                        break;       
                    }

                    if ($media->getMediaType() == 2 && empty($permissions->album_video)) {
                        // User has not got a permission for video albums
                        $is_valid = false;
                        break;       
                    }

                    if ($media->getMediaType() == 2 && !$video_processing) {
                        // Video processing is not possible now,
                        // FFMPEG is not configured
                        $is_valid = false;
                        break;       
                    }
                }

                if (!$is_valid) {
                    // User can not re-post this album post because of the permission 
                    // (or absence of the ffmpeg video processing)
                    continue;
                }
            }


            $_log = new LogModel([
                "user_id" => $User->get("id"),
                "account_id" => $Account->get("id"),
                "original_media_code" => $item->getCode(),
                "status" => "success"
            ]);

            if ($_log->isAvailable()) {
                // Already reposted this feed
                continue;
            }

            // Found the feed item to repost
            $feed_item = $item;
            break;
        }


        if (empty($feed_item)) {
            $Log->set("data.error.msg", "Couldn't find the new feed item to repost")
                ->save();
            continue;
        }


        // Download the media
        $media = [];
        if ($feed_item->getMediaType() == 1 && $feed_item->getImageVersions2()->getCandidates()[0]->getUrl()) {
            $media[] = $feed_item->getImageVersions2()->getCandidates()[0]->getUrl();
        } else if ($feed_item->getMediaType() == 2 && $feed_item->getVideoVersions()[0]->getUrl()) {
            $media[] = $feed_item->getVideoVersions()[0]->getUrl();
        } else if ($feed_item->getMediaType() == 8) {
            foreach ($feed_item->getCarouselMedia() as $m) {
                if ($m->getMediaType() == 1 && $m->getImageVersions2()->getCandidates()[0]->getUrl()) {
                    $media[] = $m->getImageVersions2()->getCandidates()[0]->getUrl();

                } else if ($m->getMediaType() == 2 && $m->getVideoVersions()[0]->getUrl()) {
                    $media[] = $m->getVideoVersions()[0]->getUrl();
                }
            }
        }


        $downloaded_media = [];
        foreach ($media as $m) {
            $url_parts = parse_url($m);
            if (empty($url_parts['path'])) {
                continue;
            }

            $ext = strtolower(pathinfo($url_parts['path'], PATHINFO_EXTENSION));
            $filename = uniqid(readableRandomString(8)."-").".".$ext;
            $downres = file_put_contents(TEMP_PATH . "/". $filename, file_get_contents($m));
            if ($downres) {
                $downloaded_media[] = $filename;
            }
        }

        if (empty($downloaded_media)) {
            $Log->set("data.error.msg", "Couldn't download the media of the selected post")
                ->save();
            continue;
        }

        $original_caption = "";
        if ($feed_item->getCaption()->getText()) {
            $original_caption = $feed_item->getCaption()->getText();
        }

        $caption = $sc->get("caption");
        $variables = [
            "{{caption}}" => $original_caption,
            "{{username}}" => "@".$feed_item->getUser()->getUsername(),
            "{{full_name}}" => $feed_item->getUser()->getFullName() ?
                                   $feed_item->getUser()->getFullName() :
                                   "@".$feed_item->getUser()->getUsername()
        ];
        $caption = str_replace(
            array_keys($variables), 
            array_values($variables), 
            $caption);

        $caption = $Emojione->shortnameToUnicode($caption);
        if ($User->get("settings.spintax")) {
            $caption = \Spintax::process($caption);
        }

        $caption = mb_substr($caption, 0, 2200);



        // Try to repost
        try {
            if (count($downloaded_media) > 1) {
                $album_media = [];

                foreach ($downloaded_media as $m) {
                    $ext = strtolower(pathinfo($m, PATHINFO_EXTENSION));

                    $album_media[] = [
                        "type" => in_array($ext, ["mp4"]) ? "video" : "photo",
                        "file" => TEMP_PATH."/".$m
                    ];
                }

                $res = $Instagram->timeline->uploadAlbum($album_media, ['caption' => $caption]);
            } else {
                $m = $downloaded_media[0];
                $ext = strtolower(pathinfo($m, PATHINFO_EXTENSION));
                if (in_array($ext, ["mp4"])) {
                    $res = $Instagram->timeline->uploadVideo(TEMP_PATH."/".$m, ["caption" => $caption]);
                } else {
                    $res = $Instagram->timeline->uploadPhoto(TEMP_PATH."/".$m, ["caption" => $caption]);
                }
            }
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $msg = explode(":", $msg, 2);
            $msg = isset($msg[1]) ? $msg[1] : $msg[0];

            $Log->set("data.error.msg", __("An error occured during reposting the media."))
                ->set("data.error.details", $msg)
                ->save();
            continue;
        }

        if (!$res->isOk()) {
            $Log->set("data.error.msg", __("An error occured during reposting the media."))
                ->set("data.error.details", __("Instagram didn't return the expected result."))
                ->save();
            continue;
        }


        // Reposted media succesfully
        // Save log
        $thumb = null;
        if (null !== $feed_item->getImageVersions2()) {
            $thumb = $feed_item->getImageVersions2()->getCandidates()[0]->getUrl();
        } else if (null !== $feed_item->getCarouselMedia()) {
            $thumb = $feed_item->getCarouselMedia()[0]->getImageVersions2()->getCandidates()[0]->getUrl();
        }


        $Log->set("data.grabbed", [
            "media_id" => $feed_item->getId(),
            "media_code" => $feed_item->getCode(),
            "media_type" => $feed_item->getMediaType(),
            "media_thumb" => $thumb,
            "user" => [
                "pk" => $feed_item->getUser()->getPk(),
                "username" => $feed_item->getUser()->getUsername(),
                "full_name" => $feed_item->getUser()->getFullName()
            ]
        ]);

        $Log->set("data.reposted", [
            "upload_id" => $res->getUploadId(),
            "media_pk" => $res->getMedia()->getPk(),
            "media_id" => $res->getMedia()->getId(),
            "media_code" => $res->getMedia()->getCode()
        ]);
        
        $Log->set("status", "success")
            ->set("original_media_code", $feed_item->getCode());
            

        if ($sc->get("remove_delay") > 0) {
            $Log->set("is_removable", 1)
                ->set("remove_scheduled", date("Y-m-d H:i:s", time() + $sc->get("remove_delay")));
        }

        $Log->save();

        // Remove downloaded media files
        foreach ($downloaded_media as $m) {
            @unlink(TEMP_PATH."/".$m);
        }
    }
}
\Event::bind("cron.add", __NAMESPACE__."\addCronTask");



/**
 * Add cron task to remove the reposted post
 */
function addCronTaskRemove()
{
    // Get auto like schedules
    require_once __DIR__."/models/LogsModel.php";
    $Logs = new LogsModel;
    $Logs->where("is_removable", 1)
         ->where("status", "success")
         ->where("remove_scheduled", "<=", date("Y-m-d H:i:s"))
         ->setPageSize(5) // required to prevent server overload
         ->setPage(1)
         ->orderBy("id", "ASC")
         ->fetchData();

    if ($Logs->getTotalCount() < 1) {
        // There is not any active schedule
        return false;
    }

    $as = [__DIR__."/models/LogModel.php", __NAMESPACE__."\LogModel"];
    foreach ($Logs->getDataAs($as) as $Log) {
        $Account = \Controller::model("Account", $Log->get("account_id"));
        $User = \Controller::model("User", $Log->get("user_id"));

        if (!$Account->isAvailable() || !$User->isAvailable()) {
            // Account is either removed (unexected, external factors)
            // Remove the log
            $Log->remove();
            continue;
        } 

        if ($User->get("id") != $Account->get("user_id")) {
            // Unexpected, data modified by external factors
            // Remove remove-schedule
            $Log->remove();
            continue;
        }

        // Defautls
        $Log->set("remove_date", date("Y-m-d H:i:s")); // Last action date for the remove action (success or fail)

        if ($Account->get("login_required")) {
            // Login required for this account
            $Log->set("is_deleted", 0)
                ->set("is_removable", 0)
                ->set("data.remove.status", "error")
                ->set("data.remove.error.msg", "Re-login required for the account!")
                ->save();
            continue;
        }

        if (!$User->get("is_active")) {
            $Log->set("is_deleted", 0)
                ->set("is_removable", 0)
                ->set("data.remove.status", "error")
                ->set("data.remove.error.msg", __("User account was not active."))
                ->save();
            continue;
        }

        if ($User->isExpired()) {
            $Log->set("is_deleted", 0)
                ->set("is_removable", 0)
                ->set("data.remove.status", "error")
                ->set("data.remove.error.msg", __("User account was expired."))
                ->save();
            continue;
        }


        // Login into the account
        try {
            $Instagram = \InstagramController::login($Account);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $msg = explode(":", $msg, 2);
            $msg = isset($msg[1]) ? $msg[1] : $msg[0];

            $Log->set("is_deleted", 0)
                ->set("is_removable", 0)
                ->set("data.remove.status", "error")
                ->set("data.remove.error.msg", __("Couldn't login into the account."))
                ->set("data.remove.error.details", $msg)
                ->save();

            continue;
        }


        // Get media info
        try {
            $mediainfo = $Instagram->media->getInfo($Log->get("data.reposted.media_id"));
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $msg = explode(":", $msg, 2);
            $msg = isset($msg[1]) ? $msg[1] : $msg[0];

            $Log->set("is_deleted", 0)
                ->set("is_removable", 0)
                ->set("data.remove.status", "error")
                ->set("data.remove.error.msg", __("Couldn't get media info."))
                ->set("data.remove.error.details", $msg)
                ->save();

            continue;
        }

        if (!$mediainfo->isOk()) {
            $Log->set("is_deleted", 0)
                ->set("is_removable", 0)
                ->set("data.remove.status", "error")
                ->set("data.remove.error.msg", __("Couldn't get media info."))
                ->save();

            continue;
        }


        $media_type_id = $mediainfo->getItems()[0]->getMediaType();
        if ($media_type_id == "2") {
            $media_type = "VIDEO";
        } else if ($media_type_id == "8") {
            $media_type = "ALBUM";
        } else {
            $media_type = "PHOTO";
        }


        try {
            $res = $Instagram->media->delete($Log->get("data.reposted.media_id"), $media_type);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $msg = explode(":", $msg, 2);
            $msg = isset($msg[1]) ? $msg[1] : $msg[0];

            $Log->set("is_deleted", 0)
                ->set("is_removable", 0)
                ->set("data.remove.status", "error")
                ->set("data.remove.error.msg", __("Couldn't remove the post."))
                ->set("data.remove.error.details", $msg)
                ->save();

            continue;
        }

        if (!$res->isOk() || !$res->getDidDelete()) {
            $Log->set("is_deleted", 0)
                ->set("is_removable", 0)
                ->set("data.remove.status", "error")
                ->set("data.remove.error.msg", __("Couldn't remove the post."))
                ->save();

            continue;
        } 


        // Post removed successfully, log data
        $Log->set("is_deleted", 1)
            ->set("is_removable", 0)
            ->set("data.remove.status", "success")
            ->save();
    }
}
\Event::bind("cron.add", __NAMESPACE__."\addCronTaskRemove");
