jQuery(document).ready(function ($) {
    $( ".bbp-reply-author" ).on( 'click', '.bbpress-user-notes-toggle', function (e) {
        e.preventDefault();
        $( $(this).data('bbp-user-note-toggle') ).slideToggle();
    } );
});