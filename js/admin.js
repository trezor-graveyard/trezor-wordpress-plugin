/**
 * Created by Merlin on 9.5.2015.
 */

function setMessage(messageClass, messageText) {
    var e = document.getElementById('trezor_connect_result');
    e.className = messageClass;
    e.innerHTML = messageText;
}

function setFormItemValue(itemName, value) {
    var elements = document.getElementsByName(itemName);
    var e = elements[0];
    if( e != undefined ) {
        e.setAttribute('value', value);
    }
}

function trezorConnect(response) {
    if (response.success) {
        setFormItemValue('trezor_publickey', response.public_key);
        setFormItemValue('trezor_signature', response.signature);
        setFormItemValue('trezor_version', response.version);
        setFormItemValue('trezor_connect_changed', 1);
        showLinkPasswordField();
    } else {
        alert('Wrong signature');
    }
}

function showLinkPasswordField() {
    jQuery('#link_sign').hide();
    jQuery('#link_password').show();
    jQuery('#link_password input[name=link_password]').focus();
}

function showUnlinkPasswordField() {
    jQuery('#unlink_button').attr('class', 'button button-primary');
    jQuery('#unlink_button').attr("onclick", "javascript:trezorUnlink()");

    jQuery('#unlink_password').attr('style', 'display: inline-block;');
    jQuery('#unlink_password input[name=unlink_password]').focus();
}

function hideUnlinkPasswordField() {
    jQuery('#unlink_button').attr('class', 'button button-default');
    jQuery('#unlink_button').attr("onclick", "javascript:showUnlinkPasswordField()");

    jQuery('#unlink_password').hide();
}

function trezorLink() {
    var data = {
        "psw": jQuery('input[name=link_password]').val(),
        "trezor_publickey": jQuery('input[name=trezor_publickey]').val(),
        "trezor_signature": jQuery('input[name=trezor_signature]').val(),
        "trezor_version": jQuery('input[name=trezor_version]').val(),
        "trezor_connect_changed": jQuery('input[name=trezor_connect_changed]').val()
    };

    jQuery.ajax({
        type: "POST",
        dataType: "json",
        url: "?trezor_action=link",
        data: data,
        success: function(data) {
            if (data['result'] == 'success') {
                setFormItemValue('trezor_connected', 1);
                setFormItemValue('trezor_connect_changed', 1);
                switchState();
            } else {
                alert(data['message']);
            }
        }
    });
}

function trezorUnlink() {
    var data = {
        "psw": jQuery('input[name=unlink_password]').val()
    };

    jQuery.ajax({
        type: "POST",
        dataType: "json",
        url: "?trezor_action=unlink",
        data: data,
        success: function(data) {
            if (data['result'] == 'success') {
                setFormItemValue('trezor_connected', 0);
                setFormItemValue('trezor_connect_changed', 1);
                switchState();
                hideUnlinkPasswordField();
            } else {
                alert(data['message']);
            }
        }
    });
}

function switchState() {
    switch (jQuery('#trezor_connected').val()) {
        case '1':
            jQuery('.trezor_linked').show();
            jQuery('.trezor_unlinked').hide();
            break;
        default:
            jQuery('.trezor_linked').hide();
            jQuery('.trezor_unlinked').show();
            break;
    }
}

jQuery(document).ready(function() {
    //jQuery('#unlink_button').on("click", showUnlinkPasswordField());
    switchState();
});
