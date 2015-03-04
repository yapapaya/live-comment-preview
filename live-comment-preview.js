function updateLivePreview() {

    var $comment = jQuery(ya_live_cp.commentID).val();
    var $author = jQuery(ya_live_cp.authorID).val();
    var $url = jQuery(ya_live_cp.urlID).val();
    var $email = jQuery(ya_live_cp.emailID).val();

    var $name;

    if ($author && $url) {
        $name = '<a href="' + $url + '">' + $author + '</a>';
    } else if (!$author && $url) {
        $name = '<a href="' + $url + '">' + ya_live_cp.default_name + '</a>';
    } else if (!$author && $url) {
        $name = $author;
    } else {
        $name = ya_live_cp.default_name;
    }

    var user_gravatar = addslashes(ya_live_cp.user_gravatar);
    var $gravatar = addslashes(ya_live_cp.avatar_default) + '?';
    var avatar_default = encodeURIComponent(ya_live_cp.avatar_default);
    if ($email !== '') {
        $gravatar = 'http://www.gravatar.com/avatar/' + hex_md5($email) + '?d=' + encodeURIComponent(ya_live_cp.avatar_default) + '&';
    }
    else if (user_gravatar !== '') {
        $gravatar = user_gravatar + '?d=' + avatar_default + '&';
    }

    $gravatar += 's=' + ya_lc_preview.avatar_size;

    if (ya_lc_preview.avatar_rating) {
        $gravatar += '&r=' + ya_lc_preview.avatar_rating;

    }

    template = ya_live_cp.template;
    template = template.replace("COMMENT_AUTHOR", $name);
    template = template.replace("COMMENT_CONTENT", $comment);
    template = template.replace("AVATAR_URL", $gravatar);
    template = "'" + template + "';";

    jQuery('#commentPreview').html(template);
}

function addslashes(str) {
    //  discuss at: http://phpjs.org/functions/addslashes/
    // original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // improved by: Ates Goral (http://magnetiq.com)
    // improved by: marrtins
    // improved by: Nate
    // improved by: Onno Marsman
    // improved by: Brett Zamir (http://brett-zamir.me)
    // improved by: Oskar Larsson Högfeldt (http://oskar-lh.name/)
    //    input by: Denny Wardhana
    //   example 1: addslashes("kevin's birthday");
    //   returns 1: "kevin\\'s birthday"

    return (str + '')
            .replace(/[\\"']/g, '\\$&')
            .replace(/\u0000/g, '\\0');
}


function wptexturize(text) {
    text = ' ' + text + ' ';
    var next = true;
    var output = '';
    var prev = 0;
    var length = text.length;
    var tagsre = new RegExp('^/?(' + ya_live_cp.allowedtags.join('|') + ')\\b', 'i');
    while (prev < length) {
        var index = text.indexOf('<', prev);
        if (index > -1) {
            if (index == prev) {
                index = text.indexOf('>', prev);
            }
            index++;
        } else {
            index = length;
        }
        var s = text.substring(prev, index);
        prev = index;
        if (output.match(/<$/) && !s.match(tagsre)) {
            // jwz: omit illegal tags
            output = output.replace(/<$/, ' ');
            s = s.replace(/^[^>]*(>|$)/, '');
        } else if (s.substr(0, 1) != '<' && next == true) {
            s = s.replace(/---/g, '&#8212;');
            s = s.replace(/--/g, '&#8211;');
            s = s.replace(/\.{3}/g, '&#8230;');
            s = s.replace(/``/g, '&#8220;');
            s = s.replace(/'s/g, '&#8217;s');
            s = s.replace(/'(\d\d(?:&#8217;|')?s)/g, '&#8217;$1');
            s = s.replace(/([\s"])'/g, '$1&#8216;');
            s = s.replace(/([^\s])'([^'\s])/g, '$1&#8217;$2');
            s = s.replace(/(\s)"([^\s])/g, '$1&#8220;$2');
            s = s.replace(/"(\s)/g, '&#8221;$1');
            s = s.replace(/'(\s|.)/g, '&#8217;$1');
            s = s.replace(/\(tm\)/ig, '&#8482;');
            s = s.replace(/\(c\)/ig, '&#169;');
            s = s.replace(/\(r\)/ig, '&#174;');
            s = s.replace(/''/g, '&#8221;');
            s = s.replace(/(\d+)x(\d+)/g, '$1&#215;$2');
        } else if (s.substr(0, 5) == '<code') {
            next = false;
        } else {
            next = true;
        }
        output += s;
    }
    return output.substr(1, output.length - 2);
}

function wpautop(p) {
    p = p + '\n\n';
    p = p.replace(/(<blockquote[^>]*>)/g, '\n$1');
    p = p.replace(/(<\/blockquote[^>]*>)/g, '$1\n');
    p = p.replace(/\r\n/g, '\n');
    p = p.replace(/\r/g, '\n');
    p = p.replace(/\n\n+/g, '\n\n');
    p = p.replace(/\n?(.+?)(?:\n\s*\n)/g, '<p>$1</p>');
    p = p.replace(/<p>\s*?<\/p>/g, '');
    p = p.replace(/<p>\s*(<\/?blockquote[^>]*>)\s*<\/p>/g, '$1');
    p = p.replace(/<p><blockquote([^>]*)>/ig, '<blockquote$1><p>');
    p = p.replace(/<\/blockquote><\/p>/ig, '<p></blockquote>');
    p = p.replace(/<p>\s*<blockquote([^>]*)>/ig, '<blockquote$1>');
    p = p.replace(/<\/blockquote>\s*<\/p>/ig, '</blockquote>');
    p = p.replace(/\s*\n\s*/g, '<br />');
    return p;
}

jQuery(document).ready(function () {

    jQuery(ya_live_cp.commentID + ',' + ya_live_cp.authorID + ',' + ya_live_cp.urlID).on('keyup', function () {
        updateLivePreview();
    });

});
