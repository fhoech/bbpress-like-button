<?php
class bbpress_like {
    
    private $plugin_name;
    private $plugin_path;
    private $l10n;
    private $wpsf;
    private $settings;
    private $table_name;
    public $labels;
    
    function __construct() 
    {	
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

        //Common used variables
        global $wpdb;
        $this->plugin_name = 'bbpress-like-button';
        $this->plugin_path = plugin_dir_path( __FILE__ );
        $this->l10n = 'bbpl';
        $this->table_name = $wpdb->prefix.'postmeta';
        
        //This plugin depends on bbPress
        if ( !is_plugin_active( 'bbpress/bbpress.php' ) ) {
            wp_die(__("bbPress Like Button needs bbPress installed and activated in order to work.", $this->l10n));
        }
        //Buddypress compatibility
        update_option( 'bbpl_buddypress', (Integer)is_plugin_active( 'buddypress/bp-loader.php' ));

        //Include and create a new WordPressSettingsFramework
        require_once( $this->plugin_path .'wp-settings-framework.php' );
        $this->wpsf = new WordPressSettingsFramework( $this->plugin_path .'settings/settings-bbpl.php' );
        add_filter( $this->wpsf->get_option_group() .'_settings_validate', array($this, 'settings_validate') );
        //Get plugin settings
        $this->settings = wpsf_get_settings( $this->plugin_path .'settings/settings-bbpl.php' );
        if(!$this->settings){
            //Defaults
            $this->settings = array(
                'settingsbbpl_bbpl_general_autoembed' => 1,
                'settingsbbpl_bbpl_general_show_number' => 0,
                'settingsbbpl_bbpl_general_show_tooltip' => 1
            );
        }
        //Output
        if((Bool)$this->settings['settingsbbpl_bbpl_general_autoembed']){
            add_action('bbp_theme_before_reply_admin_links', array($this,'bbpl_show_button'));
        }
        
        //Inits and menus
        add_action('init', array($this,'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));

        //Scripts and Styles
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'public_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'public_styles') );

        //Ajax requests
        add_action('wp_ajax_like_this', array($this,'like_this'));
        add_action('wp_ajax_nopriv_like_this', array($this,'like_this'));
        add_action('wp_ajax_delete_like', array($this,'delete_like'));

        //Shortcodes
        add_shortcode('most_liked_users', array($this,'get_most_liked_users_shortcode'));
        add_shortcode('most_liking_users', array($this,'get_most_liking_users_shortcode'));
        add_shortcode('most_liked_posts', array($this,'get_most_liked_posts_shortcode'));

        //Load text strings
        $this->load_labels();

        //Add settings link to plugins page
        add_filter('plugin_action_links', array($this,'settings_link'), 10, 4 );
    }
    
    function init() {
        //Styles
        wp_register_style( 'bbpl_public', plugins_url('bbpress-like-button/css/bbpl_style.css' ), array(), '1.0' );
        wp_register_style( 'flexigrid', plugins_url('bbpress-like-button/css/flexigrid.css' ) );
    }
  
    function admin_menu(){
        $pages = array();
        $pages[] = add_menu_page(__('Likes',$this->l10n), __('Likes',$this->l10n), 'add_users', 'bbpress-likes' , array($this,'admin_screen'), plugins_url('bbpress-like-button/img/thumbs_up_15_15.png'), 100);
        $pages[] = add_submenu_page('bbpress-likes',__('Likes logs',$this->l10n), __('Likes logs',$this->l10n), 'add_users', 'bbpress-likes-logs', array($this,'logs_screen'));
        $pages[] = add_submenu_page('bbpress-likes',__('Likes statistics',$this->l10n), __('Likes statistics',$this->l10n), 'add_users', 'bbpress-likes-statistics', array($this,'statistics_screen'));
        
        //Add styles only for plugin pages
        foreach($pages as $page){
            add_action( 'admin_print_styles-' . $page, array($this, 'admin_styles') );
        }
    }
    
    function admin_init(){

    }
    
    function plugin_uninstall(){
        
    }

    //This function will load default labels and if the user did specify any different labels they will be used instead
    function load_labels(){
        //Defaults
        $this->labels = array(
            "action" => "Like this",
            "action_done" => "You liked this"
        );
        //User defined
        if ( $this->settings['settingsbbpl_bbpl_labels_label_like_button_action'] != "") {
            $this->labels['action'] = $this->settings['settingsbbpl_bbpl_labels_label_like_button_action'];
        }

        if ( $this->settings['settingsbbpl_bbpl_labels_label_like_button_action_done'] != "") {
            $this->labels['action_done'] = $this->settings['settingsbbpl_bbpl_labels_label_like_button_action_done'];
        }
    }

    //Add settings link on plugin page
    function settings_link($links, $file) {
        $plugin_file = $this->plugin_name.'/'.'bbpress-like.php';
        //make sure it is our plugin we are modifying
        if ( $file == $plugin_file ) {
            $settings_link = '<a href="'.admin_url('admin.php?page=bbpress-likes').'">'.__('Settings',$this->l10n).'</a>';
            array_push($links, $settings_link);
        }
        return $links;
    }

    /* STYLES AND SCRIPT START */
    function admin_styles(){
        wp_enqueue_style( 'flexigrid' );
        wp_enqueue_style( 'bbpl_public' );
    }
    
    function public_styles(){
        wp_enqueue_style('bbpl_public');
    }
    
    function admin_enqueue_scripts(){
        wp_enqueue_script( 
             'jquery.cookie'
            ,plugins_url('bbpress-like-button/js/jquery.cookie.js' )
            ,'jquery'
            ,'1.2'
            ,true 
        );
        wp_enqueue_script( 
             'flexigrid'
            ,plugins_url('bbpress-like-button/js/flexigrid.js')
            ,'jquery'
            ,'1.1'
            ,true 
        );
        wp_enqueue_script( 
             'bbpl-functions'
            ,plugins_url('bbpress-like-button/js/admin_functions.js')
            ,''
            ,'1.1'
            ,true 
        );
        wp_enqueue_script( 
             'jquery.tools.tooltip'
            ,plugins_url('bbpress-like-button/js/jquery.tools.min.tooltip.js')
            ,'jquery'
            ,'1.2.7'
            ,true 
        );
    }
    
    function public_enqueue_scripts(){
        wp_enqueue_script( 
             'bbpl-functions'
            ,plugins_url('bbpress-like-button/js/public_functions.js')
            ,''
            ,'1.1'
            ,true 
        );
        wp_enqueue_script( 
             'jquery.tools.tooltip'
            ,plugins_url('bbpress-like-button/js/jquery.tools.min.tooltip.js')
            ,'jquery'
            ,'1.2.7'
            ,true 
        );
    }
    /* STYLES AND SCRIPT END */
    
    /* HELPER FUNCTIONS START */
    function get_like($post_id,$user_id){
        global $wpdb;
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                post_id, 
                meta_value AS user_id 
            FROM 
                $this->table_name 
            WHERE 
                meta_key = 'bbpl_like'
                AND post_id = %d
                 AND meta_value = %d"
            , $post_id, $user_id));
        return $result;
    }
    
    function get_likes_number($post_id){
        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT
                COUNT(*)
            FROM
                $this->table_name
            WHERE
                meta_key = 'bbpl_like'
                AND post_id = %d"
            , $post_id));
        return $result;
    }
    
    function get_who_liked($post_id){
        global $wpdb;
        $result = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                meta_value AS user_id
            FROM
                $this->table_name
            WHERE
                meta_key = 'bbpl_like'
                AND post_id = %d
            ORDER BY
                meta_id DESC"
            ,$post_id), ARRAY_A);
        return $result;
    }
    
    function delete_like(){
        // DEPRECATED

        //global $wpdb;
        
        //extract($_POST);
        
        //if(!isset($id)) die;
        
        //return $wpdb->query("DELETE FROM $this->table_name WHERE id = $id");
        
        //die;
    }
    
    function like_this(){
        global $wpdb;
        
        extract($_REQUEST);
        
        //Check if previously liked
        $liked = self::get_like($post_id,$user_id);
        if($liked){
            _e('This post was already liked by this user', $this->l10n);
        }else{
            //Insert like
            $result = add_post_meta($post_id, 'bbpl_like', $user_id);
            if(!$result){ _e('An error ocurred: ', $this->l10n).$wpdb->print_error(); }else{ _e('Liked successfully',$this->l10n);}
            //Buddypress show on activity stream
            if (get_option('bbpl_buddypress',0) && (Bool)$this->settings['settingsbbpl_bbpl_general_buddypress_activity_stream'] && function_exists('bp_activity_add')) {
                //Get post type
                $post_type = get_post_type($post_id);
                $user_info = get_userdata($user_id);
                switch ($post_type) {
                    case 'topic':
                        //Get the data
                        $reply_info = get_post_ancestors($post_id);
                        $forum_id = reset($reply_info);
                        $topic_id = $post_id;
                        break;
                    case 'reply':
                    default:
                        //Get the data
                        $reply_info = get_post_ancestors($post_id);
                        $forum_id = end($reply_info);
                        $topic_id = reset($reply_info);
                        break;
                }

                //Placeholders replacement
                $placeholders = array(
                    'USERNAME' => '<a href="'.bbp_get_user_profile_url($user_id).'" title="'.$user_info->user_nicename.'">'.$user_info->user_nicename .'</a>',
                    'TOPIC'    => '<a href="'.trailingslashit(get_post_permalink($topic_id)).'#post-'.$post_id.'" title="'.get_the_title($topic_id).'">'.get_the_title($topic_id).'</a>',
                    'FORUM'    => '<a href="'.get_post_permalink($forum_id).'" title="'.get_the_title($forum_id).'">'.get_the_title($forum_id).'</a>'
                );
                $action = $this->settings['settingsbbpl_bbpl_labels_label_like_button_buddypress'];
                foreach($placeholders as $key => $value){
                    $action = str_replace('%%'.strtoupper($key).'%%', $value, $action);
                }

                $args = array(
                    'action' => $action,
                    'component' => 'bbpl',
                    'type' => 'like',
                    'user_id' => $user_id,
                    'recorded_time' => date( 'Y-m-d H:i:s'),
                );
                $activity_id = bp_activity_add( $args );
            }
        }
        die;
    }
    
    function who_liked_tooltip($post_id, $limit = 3){
        $who_liked = self::get_who_liked($post_id);
        $who_liked_caption = array();
        $who_liked_users = array(); //for caching purposes
        foreach ($who_liked as $who){
            if(count($who_liked_users)>=$limit){
                $who_liked_caption[] = __('and',$this->l10n).' '.(count($who_liked)-$limit).' '.__('more people', $this->l10n);
                break;
            }
            if(!key_exists($who['user_id'], $who_liked_users)){
                $who_liked_users[$who['user_id']] = get_userdata($who['user_id']);
            }
            $who_liked_caption[] = $who_liked_users[$who['user_id']]->display_name;
        }
        return implode(', ',$who_liked_caption);
    }
    
    /* HELPER FUNCTIONS END */
    
    /* SHORTCODES START */
    function get_most_liked_users_shortcode($atts){
        extract(shortcode_atts(array(
          'exclude_admins' => false
        ), $atts));
        return self::get_most_liked_users(false, $exclude_admins);
    }
    
    function get_most_liking_users_shortcode($atts){
        return self::get_most_liking_users(false);
    }
    
    function get_most_liked_posts_shortcode($atts){
        return self::get_most_liked_posts(false);
    }
    /* SHORTCODES END */
    
    /* STATISTICS START */
    function get_most_liked_posts($echo = true){
        global $wpdb;
        $result = $wpdb->get_results(
            "SELECT
                COUNT(*) liked_post_count,
                post_id 
            FROM 
                $this->table_name
            WHERE 
                meta_key = 'bbpl_like' 
            GROUP BY 
                post_id"
            , ARRAY_A);

        if(!$echo) ob_start();
        if($result){
            echo '<ol>';
            
            foreach($result as $liked_posts){

                $post = get_post($liked_posts['post_id']);
                $who_liked_caption= '';
                if((Bool)$this->settings['settingsbbpl_bbpl_general_show_tooltip']){
                    $who_liked_caption = self::who_liked_tooltip($post->ID);
                }
                $post_title = $post->post_title;
                if($post->post_type=='reply'){
                    $parent_id = $post->post_parent;
                    $extra_link = '#post-'.$post->ID;
                    if(empty($post_title)) {
                        $post_title = get_the_title($post->post_parent);
                    }
                }else{
                    $parent_id = $post->ID;
                    $extra_link = '';
                    $post_title .= ' (OP)';
                }
                $link = get_permalink($parent_id).$extra_link;
                $user = get_userdata($post->post_author);
                if(current_user_can('add_users')){
                    $admin_links = '<a href="post.php?post='.$post->ID.'&action=edit" target="_blank" >'.__('Edit',$this->l10n).'</a>';
                }
                $tooltip_class = '';
                if(!empty($who_liked_caption)){
                    $tooltip_class = ' class="who_liked"';
                }

                echo '<li><a href="'.$link.'" target="_blank" title="'.__('View',$this->l10n).'" >'.$post_title.'</a> '.__('by',$this->l10n).' '.$user->display_name.' <span'.$tooltip_class.' title="'.$who_liked_caption.' '.__('liked this', $this->l10n).'">('.$liked_posts['liked_post_count'].' '.__('likes',$this->l10n).')</span> '.$admin_links.'</li>';
            }
            echo '</ol>';
        }else{
            _e('Sorry! Nobody has liked any post or reply yet...',$this->l10n);
        }
        if(!$echo) return ob_get_clean();
    }
    function get_most_liked_users($echo = true, $exclude_admins = false, $get_only_data = false){
        global $wpdb;
        $result = $wpdb->get_results(
            "SELECT 
                COUNT(*) liked_user_count,
                meta_value as user_id 
            FROM
                $this->table_name
            WHERE
                meta_key = 'bbpl_like'
            GROUP BY
                user_id
            ORDER BY
                liked_user_count
            DESC LIMIT 10"
            , ARRAY_A);
        
        $get_only_data_arr = array();
        if(!$echo) ob_start();
        if($result){
            echo '<ol>';
            foreach($result as $liked_users){
                $user = get_userdata($liked_users['user_id']);
                if($get_only_data){
                    $get_only_data_arr[] = array('user' => $user, 'number_likes' => $liked_users['liked_user_count']);
                }
                if($exclude_admins && $user->caps['administrator']==1) continue;
                echo '<li>'.$user->user_login.' ('.$liked_users['liked_user_count'].' '.__('likes',$this->l10n).')</li>';
            }
            echo '</ol>';
        }else{
            _e('Sorry! Nobody has liked any post or reply yet...',$this->l10n);
        }
        if($get_only_data){
            ob_get_clean();
            return $get_only_data_arr;
        }
        if(!$echo) return ob_get_clean();
    }
    
    function get_most_liking_users($echo = true, $exclude_admins = false){
        global $wpdb;
        $result = $wpdb->get_results(
            "SELECT
                 COUNT(*) liking_user_count,
                 meta_value as user_id 
             FROM 
                $this->table_name 
             WHERE 
                meta_key = 'bbpl_like' 
             GROUP BY 
                user_id 
             ORDER BY 
                liking_user_count 
             DESC LIMIT 10"
        , ARRAY_A);
        
        if(!$echo) ob_start();
        if($result){
            echo '<ol>';
            foreach($result as $liking_users){
                $user = get_userdata($liking_users['user_id']);
                if($exclude_admins && $user->caps['administrator']==1) continue;
                echo '<li>'.$user->user_login.' ('.$liking_users['liking_user_count'].' '.__('likes',$this->l10n).')</li>';
            }
            echo '</ol>';
        }else{
            _e('Sorry! Nobody has liked any post or reply yet...',$this->l10n);
        }
        if(!$echo) return ob_get_clean();
    }
    /* STATISTICS END */
    
    /* OUTPUT FUNCTIONS START */
    public function bbpl_show_button($echo = true){
        if ( !is_user_logged_in() ) return; //only for logged users
        
        $post_id = bbp_get_reply_id();
        $user_id = get_current_user_id();
        
        $liked = ($this->get_like($post_id,$user_id)) == NULL ? false : true;
        
        $link_caption = ($liked == true ? __($this->labels["action_done"],$this->l10n) : __($this->labels["action"], $this->l10n));

        $label_link_caption = $link_caption;
        if(!(Bool)$this->settings['settingsbbpl_bbpl_general_show_like_button_label']){
            $label_link_caption = '&nbsp;';
        }
        
        ob_start();
        ?>
        <div class="bbpl_button_wrapper">
            <a href="#" data-user="<?php echo $user_id; ?>" data-post="<?php echo $post_id; ?>" title="<?php echo $link_caption; ?>" class="bbpl_button <?php echo $liked==true ? 'liked' : '' ?>"><span><?php echo $label_link_caption; ?></span></a>
            <?php
            $like_number = $this->get_likes_number($post_id);
            if((Bool)$this->settings['settingsbbpl_bbpl_general_show_number'] && $like_number){
                ?>
                <span class="bbpl_number"><?php echo $like_number; ?></span>
                <?php
            }
            ?>
        </div>    
        <?php
        $content = ob_get_clean();
        if($echo === false) { return $content; }else{ echo $content; }
    }
    
     function settings_validate($input){
        //NO VALIDATION NOW...
        return $input;
    }
    
    function admin_screen(){
        ?>
        <div class="wrap">
            <h2><?php _e('Likes Options',$this->l10n); ?></h2>
            <?php 
            // Output your settings form
            $this->wpsf->settings(); 
            ?>
        </div>
        <?php
    }
    
    function statistics_screen(){
        ?>
        <div class="wrap">
            <h2><?php _e('Likes statistics',$this->l10n); ?></h2>
        </div>
        <div>
            <h3><?php _e('Most liked users (admins included)', $this->l10n); ?></h3>
            <p class="howto"><?php _e('Top 10 most liked users.', $this->l10n); ?></p>
            <?php self::get_most_liked_users(); ?>
            
            <h3><?php _e('Most liked users (admins excluded)', $this->l10n); ?></h3>
            <p class="howto"><?php _e('Top 10 most liked users.', $this->l10n); ?></p>
            <?php self::get_most_liked_users(true, true); ?>
            
            <h3><?php _e('Most liking users', $this->l10n); ?></h3>
            <p class="howto"><?php _e('Top 10 users who most use Like button.', $this->l10n); ?></p>
            <?php self::get_most_liking_users(); ?>
            
            <h3><?php _e('Most liked posts', $this->l10n); ?></h3>
            <p class="howto"><?php _e('Top 10 posts liked.', $this->l10n); ?></p>
            <?php self::get_most_liked_posts(); ?>
        </div>
        <?php
    }
    
    function logs_screen(){
        ?>
        <div class="wrap">
            <h2><?php _e('Likes Logs',$this->l10n); ?></h2>
        </div>
        <div>
        <h3></h3>
        <?php
        //Get the data
        global $wpdb;
        $results = $wpdb->get_results("SELECT * FROM $this->table_name where meta_key = 'bbpl_like'",ARRAY_A);

        
        if(empty($results)){
            ?>
            <p><?php _e('Sorry! Nobody has liked any post or reply yet...',$this->l10n); ?></p>
            <?php
        }else{
            ?>
            <table class="likes_log_table"></table>
            <span id="flex_col_caption_0" class="hide"><?php _e('ID',$this->l10n); ?></span>
            <span id="flex_col_caption_1" class="hide"><?php _e('Date',$this->l10n); ?></span>
            <span id="flex_col_caption_2" class="hide"><?php _e('User',$this->l10n); ?></span>
            <span id="flex_col_caption_3" class="hide"><?php _e('Post/Reply',$this->l10n); ?></span>
            <span id="flex_col_caption_4" class="hide"><?php _e('Action',$this->l10n); ?></span>
            <span id="flex_but_caption_1" class="hide"><?php _e('Delete like',$this->l10n); ?></span>
            <?php
        }
        ?>
        </div>
        <?php
        /* OUTPUT FUNCTIONS END */
    }
}
global $bbpl;
$bbpl = new bbpress_like();
