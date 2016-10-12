// Adds paragraph tag to Comment content as specified by WordPress
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

/**
 * Strips HTML tags other than the allowed tags from the given string
 *
 * @see http://locutus.io/php/strings/strip_tags/
 */
function strip_tags (input, allowed) {
    // eslint-disable-line camelcase
    //  discuss at: http://locutus.io/php/strip_tags/
    // original by: Kevin van Zonneveld (http://kvz.io)
    // improved by: Luke Godfrey
    // improved by: Kevin van Zonneveld (http://kvz.io)
    //    input by: Pul
    //    input by: Alex
    //    input by: Marc Palau
    //    input by: Brett Zamir (http://brett-zamir.me)
    //    input by: Bobby Drake
    //    input by: Evertjan Garretsen
    // bugfixed by: Kevin van Zonneveld (http://kvz.io)
    // bugfixed by: Onno Marsman (https://twitter.com/onnomarsman)
    // bugfixed by: Kevin van Zonneveld (http://kvz.io)
    // bugfixed by: Kevin van Zonneveld (http://kvz.io)
    // bugfixed by: Eric Nagel
    // bugfixed by: Kevin van Zonneveld (http://kvz.io)
    // bugfixed by: Tomasz Wesolowski
    //  revised by: Rafał Kukawski (http://blog.kukawski.pl)
    //   example 1: strip_tags('<p>Kevin</p> <br /><b>van</b> <i>Zonneveld</i>', '<i><b>')
    //   returns 1: 'Kevin <b>van</b> <i>Zonneveld</i>'
    //   example 2: strip_tags('<p>Kevin <img src="someimage.png" onmouseover="someFunction()">van <i>Zonneveld</i></p>', '<p>')
    //   returns 2: '<p>Kevin van Zonneveld</p>'
    //   example 3: strip_tags("<a href='http://kvz.io'>Kevin van Zonneveld</a>", "<a>")
    //   returns 3: "<a href='http://kvz.io'>Kevin van Zonneveld</a>"
    //   example 4: strip_tags('1 < 5 5 > 1')
    //   returns 4: '1 < 5 5 > 1'
    //   example 5: strip_tags('1 <br/> 1')
    //   returns 5: '1  1'
    //   example 6: strip_tags('1 <br/> 1', '<br>')
    //   returns 6: '1 <br/> 1'
    //   example 7: strip_tags('1 <br/> 1', '<br><br/>')
    //   returns 7: '1 <br/> 1'

    // making sure the allowed arg is a string containing only tags in lowercase (<a><b><c>)
    allowed = (((allowed || '') + '').toLowerCase().match(/<[a-z][a-z0-9]*>/g) || []).join('')

    var tags = /<\/?([a-z][a-z0-9]*)\b[^>]*>/gi
    var commentsAndPhpTags = /<!--[\s\S]*?-->|<\?(?:php)?[\s\S]*?\?>/gi

    return input.replace(commentsAndPhpTags, '').replace(tags, function ($0, $1) {
        return allowed.indexOf('<' + $1.toLowerCase() + '>') > -1 ? $0 : ''
    })
}

// Handle live comment preview when DOM is ready
jQuery( document ).ready(function () {

    /**
     * Allowed HTML tags to use in Comments
     * Initialize with <p> tag since wpautop will render <p> tags
     */
    var wpAllowedTags = '<p>',
        // List of allowed HTML tags to use in Comments
        htmlTagObj = ya_live_cp.allowed_tags;

    // Convert the allowed HTML tags object into a string
    jQuery.each( htmlTagObj, function( key, value ) {
        wpAllowedTags = wpAllowedTags + '<' + key + '>';
    });

    // Add Event listener to comment, name, email and website
    // to update the Live comment preview when any of these values change
    jQuery( '#' + ya_live_cp.commentID + ',' +
            '#' + ya_live_cp.authorID + ',' +
            '#' + ya_live_cp.emailID + ',' +
            '#' + ya_live_cp.urlID ).on('keyup', function() {
        updateLivePreview();
    });

    /**
     * Updates the comment preview in real time
     * Preview is automatically updated when the comment fields are modified
     */
    function updateLivePreview() {

        // Get Comment field values
        var comment             = jQuery( '#' + ya_live_cp.commentID ).val(),
            author              = jQuery( '#' + ya_live_cp.authorID ).val(),
            url                 = jQuery( '#' + ya_live_cp.urlID ).val(),
            email               = jQuery( '#' + ya_live_cp.emailID ).val(),

            // Identifies if & is to be added for query string params
            isAppendQueryParams = false,

            // Get user & avatar data from server side
            userGravatar        = ya_live_cp.user_gravatar,
            displayName         = ya_live_cp.display_name,
            defaultName         = ya_live_cp.default_name,
            userURL             = ya_live_cp.user_url,
            avatarDefault       = ya_live_cp.avatar_default,
            avatarSize          = ya_live_cp.avatar_size,
            avatarRating        = ya_live_cp.avatar_rating,
            gravatar;

        // Get gravatar information based on user logged in status
        if ( 'undefined' === jQuery.type( userGravatar ) &&
                'undefined' === jQuery.type( displayName ) &&
                'undefined' === jQuery.type( userURL ) ) {

            if ( '' === email ) {
                gravatar = 'https://www.gravatar.com/avatar/00000000000000000000000000000000';
            } else {
                gravatar = 'https://www.gravatar.com/avatar/' + hex_md5(email);
            }

            if ( avatarSize || avatarDefault || avatarRating ) {
                gravatar = gravatar + '?';
            }

            if ( avatarSize ) {
                gravatar += ( isAppendQueryParams ) ?
                '&s=' + avatarSize :
                's=' + avatarSize;
                isAppendQueryParams = true;
            }

            if ( avatarDefault ) {
                gravatar += ( isAppendQueryParams ) ?
                '&d=' + encodeURIComponent( avatarDefault ) :
                'd=' + encodeURIComponent( avatarDefault );
                isAppendQueryParams = true;
            }

            if ( avatarRating ) {
                gravatar += ( isAppendQueryParams ) ?
                '&r=' + encodeURIComponent( avatarRating ) :
                'r=' + encodeURIComponent( avatarRating );
                isAppendQueryParams = true;
            }
        } else {
            gravatar = userGravatar;
            author   = displayName;
            url      = userURL;
        }

        // Set default name when name is specified
        if ( '' === author ) {
            author = defaultName;
        }

        // Strip tags other than allowed tags
        comment = strip_tags( comment, wpAllowedTags );

        // Add paragraph tag to Comment content
        comment = wpautop( comment );

        // Replace comment fields with live values as user types
        template = ya_live_cp.template;
        template = template.replace(/\bCOMMENT_AUTHOR\b/, author);
        template = template.replace( /\bCOMMENT_AUTHOR_URL\b/, url );
        template = template.replace( /COMMENT_CONTENT/, comment );
        template = template.replace( /\bAVATAR_URL\b/, gravatar );

        jQuery( '#comment-preview' ).html( template );

        // If no URL is specified deactivate hyperlink
        if ( '' === url ) {
            jQuery('#comment-preview a.url').replaceWith(author);
        }
    }
});
