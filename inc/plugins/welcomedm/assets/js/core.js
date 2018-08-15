/**
 * WelcomeDM Namespane
 */
var WelcomeDM = {};

/**
 * Lnky users and tags
 */
WelcomeDM.Linky = function()
{
    $(".wdm-message-list-item .message").not(".js-linky-done")
        .addClass("js-linky-done")
        .linky({
            mentions: true,
            hashtags: true,
            urls: false,
            linkTo:"instagram"
        });
}


/**
 * WelcomeDM Schedule Form
 */
WelcomeDM.ScheduleForm = function()
{
    var $form = $(".js-welcomedm-schedule-form");

    // Daily pause
    $form.find(":input[name='daily-pause']").on("change", function() {
        if ($(this).is(":checked")) {
            $form.find(".js-daily-pause-range").css("opacity", "1");
            $form.find(".js-daily-pause-range").find(":input").prop("disabled", false);
        } else {
            $form.find(".js-daily-pause-range").css("opacity", "0.25");
            $form.find(".js-daily-pause-range").find(":input").prop("disabled", true);
        }
    }).trigger("change");

    // Submit the form
    $form.on("submit", function() {
        $("body").addClass("onprogress");

        $.ajax({
            url: $form.attr("action"),
            type: $form.attr("method"),
            dataType: 'jsonp',
            data: {
                action: "save",
                speed: $form.find(":input[name='speed']").val(),
                is_active: $form.find(":input[name='is_active']").val(),
                daily_pause: $form.find(":input[name='daily-pause']").is(":checked") ? 1 : 0,
                daily_pause_from: $form.find(":input[name='daily-pause-from']").val(),
                daily_pause_to: $form.find(":input[name='daily-pause-to']").val()
            },
            error: function() {
                $("body").removeClass("onprogress");
                NextPost.DisplayFormResult($form, "error", __("Oops! An error occured. Please try again later!"));
            },

            success: function(resp) {
                if (resp.result == 1) {
                    NextPost.DisplayFormResult($form, "success", resp.msg);
                } else {
                    NextPost.DisplayFormResult($form, "error", resp.msg);
                }

                $("body").removeClass("onprogress");
            }
        });

        return false;
    });
}


WelcomeDM.MessagesForm = function()
{
    var $form = $(".js-welcomedm-messages-form");

    // Linky
    WelcomeDM.Linky();

    // Emoji
    var emoji = $(".new-message-input").emojioneArea({
        saveEmojisAs      : "unicode", // unicode | shortname | image
        imageType         : "svg", // Default image type used by internal CDN
        pickerPosition: 'bottom',
        buttonTitle: __("Use the TAB key to insert emoji faster")
    });

    // Emoji area input filter
    emoji[0].emojioneArea.on("drop", function(obj, event) {
        event.preventDefault();
    });

    emoji[0].emojioneArea.on("paste keyup input emojibtn.click", function() {
        $form.find(":input[name='new-message-input']").val(emoji[0].emojioneArea.getText());
    });

    // Add message
    $(".js-add-new-message-btn").on("click", function() {
        var message = $.trim(emoji[0].emojioneArea.getText());

        if (message) {
            $message = $("<div class='wdm-message-list-item'></div>");
            $message.append('<a href="javascript:void(0)" class="remove-message-btn mdi mdi-close-circle"></a>');
            $message.append("<span class='message'></span>");
            $message.find(".message").html(message.replace(/(?:\r\n|\r|\n)/g, '<br>\n'));

            $message.prependTo(".wdm-message-list");

            WelcomeDM.Linky();

            emoji[0].emojioneArea.setText("");
        }
    });

    // Submit the form
    $form.on("submit", function() {
        $("body").addClass("onprogress");

        var messages = [];
        $form.find(".wdm-message-list-item .message").each(function() {
            messages.push($(this).text());
        })

        $.ajax({
            url: $form.attr("action"),
            type: $form.attr("method"),
            dataType: 'jsonp',
            data: {
                action: "save",
                messages: JSON.stringify(messages)
            },
            error: function() {
                $("body").removeClass("onprogress");
                NextPost.DisplayFormResult($form, "error", __("Oops! An error occured. Please try again later!"));
            },

            success: function(resp) {
                if (resp.result == 1) {
                    NextPost.DisplayFormResult($form, "success", resp.msg);
                } else {
                    NextPost.DisplayFormResult($form, "error", resp.msg);
                }

                $("body").removeClass("onprogress");
            }
        });

        return false;
    });
}


/**
 * Auto Comment Index
 */
WelcomeDM.Index = function()
{
    $(document).ajaxComplete(function(event, xhr, settings) {
        var rx = new RegExp("(welcomedm\/[0-9]+(\/)?)$");
        if (rx.test(settings.url)) {
            WelcomeDM.ScheduleForm();
            WelcomeDM.MessagesForm();
        }
    });

    // Remove message
    $("body").on("click", ".remove-message-btn", function() {
        $(this).parents(".wdm-message-list-item").remove();
    })
}