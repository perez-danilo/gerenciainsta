<?php if (!defined('APP_VERSION')) die("Yo, what's up?"); ?>

<div class="skeleton skeleton--full">
    <div class="clearfix">
        <aside class="skeleton-aside hide-on-medium-and-down">
            <div class="aside-list js-loadmore-content" data-loadmore-id="1"></div>

            <div class="loadmore pt-20 none">
                <a class="fluid button button--light-outline js-loadmore-btn js-autoloadmore-btn" data-loadmore-id="1" href="<?= APPURL."/e/".$idname."?aid=".$Account->get("id")."&ref=messages" ?>">
                    <span class="icon sli sli-refresh"></span>
                    <?= __("Load More") ?>
                </a>
            </div>
        </aside>

        <section class="skeleton-content">
            <form class="js-welcomedm-messages-form"
                  action="<?= APPURL."/e/".$idname."/".$Account->get("id")."/messages" ?>"
                  method="POST">

                <input type="hidden" name="action" value="save">

                <div class="section-header clearfix">
                    <h2 class="section-title"><?= htmlchars($Account->get("username")) ?></h2>
                </div>

                <div class="wdm-tab-heads clearfix">
                    <a href="<?= APPURL."/e/".$idname."/".$Account->get("id") ?>"><?= __("Settings") ?></a>
                    <a href="<?= APPURL."/e/".$idname."/".$Account->get("id")."/messages" ?>" class="active"><?= __("Messages") ?></a>
                    <a href="<?= APPURL."/e/".$idname."/".$Account->get("id")."/log" ?>"><?= __("Activity Log") ?></a>
                </div>

                <div class="section-content">
                    <div class="form-result"></div>

                    <div class="clearfix">
                        <div class="col s12 m10 l8">
                            <div class="mb-20">
                                <label class="form-label"><?= __("Message") ?></label>
                                
                                <div class="clearfix">
                                    <div class="col s12 m12 l8">
                                        <div class="new-message-input input" 
                                             data-placeholder="<?= __("Add your message") ?>"
                                             contenteditable="true"></div>
                                    </div>

                                    <div class="col s12 m12 l4 l-last">
                                        <a href="javascript:void(0)" class="fluid button button--light-outline mb-15 js-add-new-message-btn">
                                            <span class="mdi mdi-plus-circle"></span>
                                            <?= __("Add Message") ?>    
                                        </a>
                                        <input class="fluid button" type="submit" value="<?= __("Save") ?>">
                                    </div>
                                </div>
                            </div>

                            <ul class="field-tips">
                                <li>
                                    <?= __("You can use following variables in the comments:") ?>

                                    <div class="mt-5">
                                        <strong>{{username}}</strong>
                                        <?= __("Media owner's username") ?>
                                    </div>

                                    <div class="mt-5">
                                        <strong>{{full_name}}</strong>
                                        <?= __("Media owner's full name. If user's full name is not set, username will be used.") ?>
                                    </div>
                                </li>
                            </ul>

                            <div class="wdm-message-list clearfix">
                                <?php 
                                    $messages = $Schedule->isAvailable()
                                              ? json_decode($Schedule->get("messages"))
                                              : [];
                                    $Emojione = new \Emojione\Client(new \Emojione\Ruleset());
                                ?>
                                <?php if ($messages): ?>
                                    <?php foreach ($messages as $m): ?>
                                        <div class="wdm-message-list-item">
                                            <a href="javascript:void(0)" class="remove-message-btn mdi mdi-close-circle"></a>
                                            <span class="message">
                                                <?= nl2br(htmlchars($Emojione->shortnameToUnicode($m))) ?>
                                            </span>
                                        </div>
                                    <?php endforeach ?>
                                <?php endif ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </section>
    </div>
</div>