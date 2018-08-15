<?php if (!defined('APP_VERSION')) die("Yo, what's up?"); ?>

<div class="skeleton skeleton--full">
    <div class="clearfix">
        <aside class="skeleton-aside hide-on-medium-and-down">
            <div class="aside-list js-loadmore-content" data-loadmore-id="1"></div>

            <div class="loadmore pt-20 mb-20 none">
                <a class="fluid button button--light-outline js-loadmore-btn js-autoloadmore-btn" data-loadmore-id="1" href="<?= APPURL."/e/".$idname."?aid=".$Account->get("id")."&ref=log" ?>">
                    <span class="icon sli sli-refresh"></span>
                    <?= __("Load More") ?>
                </a>
            </div>
        </aside>

        <section class="skeleton-content">
            <div class="section-header clearfix">
                <h2 class="section-title">
                    <?= htmlchars($Account->get("username")) ?>
                    <?php if ($Account->get("login_required")): ?>
                        <small class="color-danger ml-15">
                            <span class="mdi mdi-information"></span>    
                            <?= __("Re-login required!") ?>
                        </small>
                    <?php endif ?>
                </h2>
            </div>

            <div class="arp-tab-heads clearfix">
                <a href="<?= APPURL."/e/".$idname."/".$Account->get("id") ?>"><?= __("Target & Settings") ?></a>
                <a href="<?= APPURL."/e/".$idname."/".$Account->get("id")."/log" ?>" class="active"><?= __("Activity Log") ?></a>
            </div>

            <?php if ($ActivityLog->getTotalCount() > 0): ?>
                <div class="arp-log-list js-loadmore-content" data-loadmore-id="2">
                    <?php if ($ActivityLog->getPage() == 1 && $Schedule->get("is_active")): ?>
                        <?php 
                            $nextdate = new \Moment\Moment($Schedule->get("schedule_date"), date_default_timezone_get());
                            $nextdate->setTimezone($AuthUser->get("preferences.timezone"));

                            $diff = $nextdate->fromNow(); 
                        ?>
                        <?php if ($diff->getDirection() == "future"): ?>
                            <div class="arp-next-schedule">
                                <?= __("Next request will be sent %s approximately", $diff->getRelative()) ?>
                            </div>
                        <?php elseif (abs($diff->getSeconds()) < 60*10): ?>
                            <div class="arp-next-schedule">
                                <?= __("Next request will be sent in a few moments") ?>
                            </div>
                        <?php else: ?>
                            <div class="arp-log-list-item error">
                                <div class="clearfix">
                                    <span class="circle">
                                        <span class="text">E</span>    
                                    </span>

                                    <div class="inner clearfix">
                                        <div class="action">
                                            <div class="error-msg">
                                                <?= __("Something is wrong on the system") ?>
                                            </div>
                                            <div class="error-details"><?= __("System task error") ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif ?>
                    <?php endif ?>

                    <?php foreach ($Logs as $l): ?>
                        <div class="arp-log-list-item <?= $l->get("status") ?>">
                            <div class="clearfix">
                                <span class="circle">
                                    <?php if ($l->get("status") == "success"): ?>
                                        <?php $img = $l->get("data.grabbed.media_thumb"); ?>
                                        <span class="img" style="<?= $img ? "background-image: url('".htmlchars($img)."');" : "" ?>"></span>
                                    <?php else: ?>
                                        <span class="text">E</span>    
                                    <?php endif ?>
                                </span>

                                <div class="inner clearfix">
                                    <?php 
                                        $date = new \Moment\Moment($l->get("date"), date_default_timezone_get());
                                        $date->setTimezone($AuthUser->get("preferences.timezone"));

                                        $fulldate = $date->format($AuthUser->get("preferences.dateformat")) . " " 
                                                  . $date->format($AuthUser->get("preferences.timeformat") == "12" ? "h:iA" : "H:i");
                                    ?>

                                    <div class="action">
                                        <?php if ($l->get("status") == "success"): ?>
                                            <?php
                                                $media_type = $l->get("data.grabbed.media_type");
                                                if ($media_type == 1) {
                                                    $type_label = "photo";
                                                } else if ($media_type == 2) {
                                                    $type_label = "video";
                                                } else if ($media_type == 8) {
                                                    $type_label = "album";
                                                } else {
                                                    $type_label = "post";
                                                }

                                                $username = "<a href='https://www.instagram.com/".htmlchars($l->get("data.grabbed.user.username"))."' target='_blank'>".htmlchars($l->get("data.grabbed.user.username"))."</a>";
                                                $type_label = "<a href='https://www.instagram.com/p/".htmlchars($l->get("data.grabbed.media_code"))."' target='_blank'>".$type_label."</a>";

                                                echo __("Reposted {username}'s {post}", [
                                                    "{username}" => $username,
                                                    "{post}" => $type_label 
                                                ]);
                                            ?>
                                            <span class="date" title="<?= $fulldate ?>"><?= $date->fromNow()->getRelative() ?></span>
                                        <?php else: ?>
                                            <?php if ($l->get("data.error.msg")): ?>
                                                <div class="error-msg">
                                                    <?= __($l->get("data.error.msg")) ?>
                                                    <span class="date" title="<?= $fulldate ?>"><?= $date->fromNow()->getRelative() ?></span>    
                                                </div>
                                            <?php endif ?>
                                            <?php if ($l->get("data.error.details")): ?>
                                                <div class="error-details"><?= __($l->get("data.error.details")) ?></div>
                                            <?php endif ?>
                                        <?php endif ?>
                                    </div>

                                    <?php if ($l->get("data.trigger")): ?>
                                        <?php $trigger = $l->get("data.trigger"); ?>
                                        <?php if ($trigger->type == "hashtag"): ?>
                                            <a class="meta mr-20" href="<?= "https://www.instagram.com/explore/tags/".htmlchars($trigger->value) ?>" target="_blank">
                                                <span class="icon mdi mdi-pound"></span>
                                                <?= htmlchars($trigger->value) ?>
                                            </a>
                                        <?php elseif ($trigger->type == "location"): ?>
                                            <a class="meta mr-20" href="<?= "https://www.instagram.com/explore/locations/".htmlchars($trigger->id) ?>" target="_blank">
                                                <span class="icon mdi mdi-map-marker"></span>
                                                <?= htmlchars($trigger->value) ?>
                                            </a>
                                        <?php elseif ($trigger->type == "people"): ?>
                                            <a class="meta mr-20" href="<?= "https://www.instagram.com/".htmlchars($trigger->value) ?>" target="_blank">
                                                <span class="icon mdi mdi-instagram"></span>
                                                <?= htmlchars($trigger->value) ?>
                                            </a>
                                        <?php endif ?>
                                    <?php endif ?>

                                    <?php if ($l->get("status") == "success"): ?>
                                        <?php if ($l->get("is_deleted")): ?>
                                            <span class="meta">
                                                <span class="icon mdi mdi-delete"></span>

                                                <?php 
                                                    $date = new DateTime(
                                                        $l->get("remove_date"),
                                                        new DateTimeZone(date_default_timezone_get()));
                                                    $date->setTimezone(new DateTimeZone($AuthUser->get("preferences.timezone")));
                                                    $date = $date->format($AuthUser->get("preferences.dateformat")) . " " 
                                                          . $date->format($AuthUser->get("preferences.timeformat") == "12" ? "h:iA" : "H:i");
                                                ?>
                                                <span class="color-success">
                                                    <?= __("Removed at %s", $date) ?>
                                                </span>
                                            </span>
                                        <?php elseif ($l->get("is_removable")): ?>
                                            <span class="meta">
                                                <span class="icon mdi mdi-delete"></span>

                                                <?php 
                                                    $date = new DateTime(
                                                        $l->get("remove_scheduled"),
                                                        new DateTimeZone(date_default_timezone_get()));
                                                    $date->setTimezone(new DateTimeZone($AuthUser->get("preferences.timezone")));
                                                    $date = $date->format($AuthUser->get("preferences.dateformat")) . " " 
                                                          . $date->format($AuthUser->get("preferences.timeformat") == "12" ? "h:iA" : "H:i");
                                                ?>
                                                <?= __("Scheduled to remove at %s", $date) ?>
                                            </span>
                                        <?php elseif ($l->get("data.remove.status") == "error" && $l->get("data.remove.error.msg")): ?>
                                            <span class="meta">
                                                <span class="icon mdi mdi-delete"></span>

                                                <?php 
                                                    $date = new DateTime(
                                                        $l->get("remove_date"),
                                                        new DateTimeZone(date_default_timezone_get()));
                                                    $date->setTimezone(new DateTimeZone($AuthUser->get("preferences.timezone")));
                                                    $date = $date->format($AuthUser->get("preferences.dateformat")) . " " 
                                                          . $date->format($AuthUser->get("preferences.timeformat") == "12" ? "h:iA" : "H:i");
                                                ?>
                                                <span class="color-danger"><?= __("Failed to remove at %s", $date) ?></span>
                                            </span>

                                            <?php if ($l->get("data.remove.error.msg")): ?>
                                                <div class="error-msg mt-10">
                                                    <?= __($l->get("data.remove.error.msg")) ?>
                                                </div>
                                            <?php endif ?>

                                            <?php if ($l->get("data.remove.error.details")): ?>
                                                <div class="error-details"><?= __($l->get("data.remove.error.details")) ?></div>
                                            <?php endif ?>
                                        <?php endif ?>
                                    <?php endif ?>

                                    <div class="buttons clearfix">
                                        <?php if ($l->get("data.grabbed.media_code")): ?>
                                            <a href="<?= "https://www.instagram.com/p/".htmlchars($l->get("data.grabbed.media_code")) ?>" class="button small button--light-outline" target="_blank">
                                                <?= __("View Original") ?>
                                            </a>
                                        <?php endif ?>

                                        <?php if (!$l->get("is_deleted") && $l->get("data.reposted.media_code")): ?>
                                            <a href="<?= "https://www.instagram.com/p/".htmlchars($l->get("data.reposted.media_code")) ?>" class="button small button--light-outline" target="_blank">
                                                <?= __("View Reposted") ?>
                                            </a>
                                        <?php endif ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="arp-amount-of-action">
                    <?= __("Total %s actions", $ActivityLog->getTotalCount()) ?>
                </div>

                <?php if($ActivityLog->getPage() < $ActivityLog->getPageCount()): ?>
                    <div class="loadmore mt-20 mb-20">
                        <?php 
                            $url = parse_url($_SERVER["REQUEST_URI"]);
                            $path = $url["path"];
                            if(isset($url["query"])){
                                $qs = parse_str($url["query"], $qsarray);
                                unset($qsarray["page"]);

                                $url = $path."?".(count($qsarray) > 0 ? http_build_query($qsarray)."&" : "")."page=";
                            }else{
                                $url = $path."?page=";
                            }
                        ?>
                        <a class="fluid button button--light-outline js-loadmore-btn" data-loadmore-id="2" href="<?= $url.($ActivityLog->getPage()+1) ?>">
                            <span class="icon sli sli-refresh"></span>
                            <?= __("Load More") ?>
                        </a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <p><?= __("Auto Like activity log for %s is empty", 
                    "<a href='https://www.instagram.com/".htmlchars($Account->get("username"))."' target='_blank'>".htmlchars($Account->get("username"))."</a>") ?></p>
                </div>
            <?php endif ?>
        </section>
    </div>
</div>