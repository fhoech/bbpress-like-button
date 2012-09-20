jQuery(function() {

    jQuery(document).ready(function($){

        $('.bbpl_button').click(function(e){
            e.preventDefault();
            if($(this).hasClass('liked')) return false;
            
            $(this).addClass('like-loading');
            var data = {
                action: 'like_this',
                user_id: $(this).data('user'),
                post_id: $(this).data('post')
            }
            
            var button = $(this);
            
            $.post(ajaxurl, data, function(response){
                /*console.debug(response);*/
                button.removeClass('like-loading').addClass('liked');
            });
            
        });
        
        $("span.who_liked[title]").tooltip({
            position: 'center right'
        });
        
    });


});