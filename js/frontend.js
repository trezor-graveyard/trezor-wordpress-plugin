/**
 * Created by Merlin on 9.5.2015.
 */

function trezorLogin(response) {
    if (response.success) {
        var redirect_to = gup('redirect_to', location.href);
        if(redirect_to == undefined) {
            var els = document.getElementsByName("redirect_to");
            var el = els[0];
            redirect_to = el.value;
            redirect_to = encodeURIComponent(redirect_to);
        }

        var url = "?trezor_action=login"
                    + "&address="          + encodeURIComponent(response.address)
                    + "&public_key="       + encodeURIComponent(response.public_key)
                    + "&challenge_visual=" + encodeURIComponent(response.challenge_visual)
                    + "&challenge_hidden=" + encodeURIComponent(response.challenge_hidden)
                    + "&signature="        + encodeURIComponent(response.signature)
                    + "&redirect_to="      + redirect_to;

        window.location.href = url;
    } else {
        var errorMsg = document.getElementById("login_error");
        if (errorMsg == undefined) {
            var login_div = document.getElementById("login");
            login_div.innerHTML = login_div.innerHTML.replace("</h1>", "</h1><div id='login_error'></div>");
            errorMsg = document.getElementById("login_error");
        }
        //errorMsg.innerHTML = "<strong>ERROR</strong>: TREZOR device not linked. Please login into your account and go to user profile setting to link it.<br>";
        errorMsg.innerHTML = "<strong>ERROR</strong>: TREZOR Connect cancel login process.<br>";
    }
}

function gup(name, url) {
    if (!url) url = location.href
    name = name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");
    var regexS = "[\\?&]"+name+"=([^&#]*)";
    var regex = new RegExp(regexS);
    var results = regex.exec(url);
    return results == null ? null : results[1];
}
