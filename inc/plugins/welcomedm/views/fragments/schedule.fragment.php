<?php if (!defined('APP_VERSION')) die("Yo, what's up?"); ?>

<div class="skeleton skeleton--full" id="welcomedm-schedule">
    <div class="clearfix">
        <aside class="skeleton-aside hide-on-medium-and-down">
            <div class="aside-list js-loadmore-content" data-loadmore-id="1"></div>

            <div class="loadmore pt-20 none">
                <a class="fluid button button--light-outline js-loadmore-btn js-autoloadmore-btn" data-loadmore-id="1" href="<?= APPURL."/e/".$idname."?aid=".$Account->get("id")."&ref=schedule" ?>">
                    <span class="icon sli sli-refresh"></span>
                    <?= __("Load More") ?>
                </a>
            </div>
        </aside>

        <section class="skeleton-content">
            <form class="js-welcomedm-schedule-form"
                  action="<?= APPURL."/e/".$idname."/".$Account->get("id") ?>"
                  method="POST">

                <input type="hidden" name="action" value="save">

                <div class="section-header clearfix">
                    <h2 class="section-title"><?= htmlchars($Account->get("username")) ?></h2>
                </div>

                <div class="wdm-tab-heads clearfix">
                    <a href="<?= APPURL."/e/".$idname."/".$Account->get("id") ?>" class="active"><?= __("Settings") ?></a>
                    <a href="<?= APPURL."/e/".$idname."/".$Account->get("id")."/messages" ?>"><?= __("Messages") ?></a>
                    <a href="<?= APPURL."/e/".$idname."/".$Account->get("id")."/log" ?>"><?= __("Activity Log") ?></a>
                </div>

                <div class="section-content">
                    <div class="form-result mb-25"></div>

                    <div class="clearfix">
                        <div class="col s12 m10 l8">
                            <div class="clearfix mb-40">
                                <div class="col s6 m6 l6">
                                    <label class="form-label"><?= __("Speed") ?></label>

                                    <select class="input" name="speed">
                                        <option value="0" <?= $Schedule->get("speed") == 0 ? "selected" : "" ?>><?= __("Auto"). " (".__("Recommended").")" ?></option>
                                        <option value="1" <?= $Schedule->get("speed") == 1 ? "selected" : "" ?>><?= __("Very slow") ?></option>
                                        <option value="2" <?= $Schedule->get("speed") == 2 ? "selected" : "" ?>><?= __("Slow") ?></option>
                                        <option value="3" <?= $Schedule->get("speed") == 3 ? "selected" : "" ?>><?= __("Medium") ?></option>
                                        <option value="4" <?= $Schedule->get("speed") == 4 ? "selected" : "" ?>><?= __("Fast") ?></option>
                                        <option value="5" <?= $Schedule->get("speed") == 5 ? "selected" : "" ?>><?= __("Very Fast") ?></option>
                                    </select>
                                </div>

                                <div class="col s6 s-last m6 m-last l6 l-last">
                                    <label class="form-label"><?= __("Status") ?></label>

                                    <select class="input" name="is_active">
                                        <option value="0" <?= $Schedule->get("is_active") == 0 ? "selected" : "" ?>><?= __("Deactive") ?></option>
                                        <option value="1" <?= $Schedule->get("is_active") == 1 ? "selected" : "" ?>><?= __("Active") ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="clearfix">
                                <div class="col s12 m6 l6">
                                    <div class="mb-20">
                                        <label>
                                            <input type="checkbox" 
                                                   class="checkbox" 
                                                   name="daily-pause" 
                                                   value="1"
                                                   <?= $Schedule->get("daily_pause") ? "checked" : "" ?>>
                                            <span>
                                                <span class="icon unchecked">
                                                    <span class="mdi mdi-check"></span>
                                                </span>
                                                <?= __('Pause actions everyday') ?> ...
                                            </span>
                                        </label>
                                    </div>

                                    <div class="clearfix mb-20 js-daily-pause-range">
                                        <?php $timeformat = $AuthUser->get("preferences.timeformat") == "12" ? 12 : 24; ?>

                                        <div class="col s6 m6 l6">
                                            <label class="form-label"><?= __("From") ?></label>

                                            <?php 
                                                $from = new \DateTime(date("Y-m-d")." ".$Schedule->get("daily_pause_from"));
                                                $from->setTimezone(new \DateTimeZone($AuthUser->get("preferences.timezone")));
                                                $from = $from->format("H:i");
                                            ?>

                                            <select class="input" name="daily-pause-from">
                                                <?php for ($i=0; $i<=23; $i++): ?>
                                                    <?php $time = str_pad($i, 2, "0", STR_PAD_LEFT).":00"; ?>
                                                    <option value="<?= $time ?>" <?= $from == $time ? "selected" : "" ?>>
                                                        <?= $timeformat == 24 ? $time : date("h:iA", strtotime(date("Y-m-d")." ".$time)) ?>    
                                                    </option>
                                                    
                                                    <?php $time = str_pad($i, 2, "0", STR_PAD_LEFT).":30"; ?>
                                                    <option value="<?= $time ?>" <?= $from == $time ? "selected" : "" ?>>
                                                        <?= $timeformat == 24 ? $time : date("h:iA", strtotime(date("Y-m-d")." ".$time)) ?>    
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>

                                        <div class="col s6 s-last m6 m-last l6 l-last">
                                            <label class="form-label"><?= __("To") ?></label>

                                            <?php 
                                                $to = new \DateTime(date("Y-m-d")." ".$Schedule->get("daily_pause_to"));
                                                $to->setTimezone(new \DateTimeZone($AuthUser->get("preferences.timezone")));
                                                $to = $to->format("H:i");
                                            ?>

                                            <select class="input" name="daily-pause-to">
                                                <?php for ($i=0; $i<=23; $i++): ?>
                                                    <?php $time = str_pad($i, 2, "0", STR_PAD_LEFT).":00"; ?>
                                                    <option value="<?= $time ?>" <?= $to == $time ? "selected" : "" ?>>
                                                        <?= $timeformat == 24 ? $time : date("h:iA", strtotime(date("Y-m-d")." ".$time)) ?>    
                                                    </option>
                                                    
                                                    <?php $time = str_pad($i, 2, "0", STR_PAD_LEFT).":30"; ?>
                                                    <option value="<?= $time ?>" <?= $to == $time ? "selected" : "" ?>>
                                                        <?= $timeformat == 24 ? $time : date("h:iA", strtotime(date("Y-m-d")." ".$time)) ?>    
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="clearfix">
                                <div class="col s12 m6 l6">
                                    <input class="fluid button" type="submit" value="<?= __("Save") ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </section>
    </div>
</div>