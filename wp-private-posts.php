<?php
/*
Plugin Name: WP Private Posts
Description: Plugin that allows to define permisions for all post types based of rols and users.
Author: Ovi García - ovimedia.es
Author URI: http://www.ovimedia.es/
Text Domain: wp-private-posts
Version: 0.2
*/

if ( ! defined( 'ABSPATH' ) ) exit; 

if ( ! class_exists( 'private_posts' ) ) 
{
	class private_posts 
    {        
        function __construct() 
        {   
            add_action( 'init', array( $this, 'wpp_load_languages') );
            add_action( 'admin_init', array( $this, 'wpp_register_options') );
            add_action( 'admin_menu', array( $this, 'wpp_admin_menu') );
            add_action( 'admin_print_scripts', array( $this, 'wpp_admin_js_css') );
            add_action( 'add_meta_boxes', array( $this, 'wpp_init_metabox') ); 
            add_action( 'save_post', array( $this, 'wpp_save_data_post_permissions') );
            add_action( 'wp_head', array( $this, 'wpp_load_head'), 1 ); 
            add_filter( 'the_content', array( $this, 'wpp_load_body') );
        }

        public function wpp_load_languages() 
        {
            load_plugin_textdomain( 'wp-private-posts', false, '/'.basename( dirname( __FILE__ ) ) . '/languages/' ); 
        }
                
        public function wpp_admin_menu() 
        {	
            add_submenu_page('options-general.php', 'Private Posts', 'Private Posts', 'manage_options',  
                'wp_private_posts', array( $this,'wpp_options_form'));
        }  

        public function wpp_admin_js_css() 
        {
            wp_register_style( 'custom_private_post_admin_css', WP_PLUGIN_URL. '/'.basename( dirname( __FILE__ ) ).'/css/style.css', false, '1.0.0' );
            wp_enqueue_style( 'custom_private_post_admin_css' );

            wp_register_style( 'private_post_select2_css', WP_PLUGIN_URL. '/'.basename( dirname( __FILE__ ) ).'/css/select2.min.css', false, '1.0.0' );
            wp_enqueue_style( 'private_post_select2_css' );

            wp_enqueue_script( 'private_post_script', WP_PLUGIN_URL. '/'.basename( dirname( __FILE__ ) ).'/js/scripts.js', array('jquery') );
            wp_enqueue_script( 'private_post_select2', WP_PLUGIN_URL. '/'.basename( dirname( __FILE__ ) ).'/js/select2.min.js', array('jquery') );
        }

        public function wpp_init_metabox()
        {
            add_meta_box( 'zone-private-post', translate( 'Post Permissions', 'wp-private-posts' ), 
                         array( $this, 'wpp_meta_options'), array("post", "page", "product"), 'side', 'default' );
        }

        public function wpp_register_options() 
        {
            register_setting( 'wpp_data_options', 'wpp_message_posts' );
        }

        public function wpp_options_form()
        {
            ?>

            <div class="wpp_options_form">

            <form method="post" action="options.php">

                <h3><?php echo translate( 'Message for not allowed users in a post.', 'wp-private-posts' ); ?></h3>

                <?php

                    settings_fields( 'wpp_data_options' ); 
                    do_settings_sections( 'wpp_data_options' ); 

                    $editor_id = 'wpp_message_posts';
                    $settings = array( 'media_buttons' => false, 'editor_height' => '250' );

                    wp_editor(get_option( "wpp_message_posts") , $editor_id, $settings );

                    submit_button();
                ?>
            </form>

            </div>

            <?php
        }
        
        public function wpp_meta_options( $post )
        {
            global $wpdb;
            
            $allow_roles = get_post_meta( get_the_ID(), 'wpp_post_allow_roles', true);
            $allow_users = get_post_meta( get_the_ID(), 'wpp_post_allow_users', true);

            ?>
            <div class="meta_div_post_permisions">         
                <p>
                    <label for="wpp_post_allow_roles">
                        <?php echo translate( 'Allow User Rols:', 'wp-private-posts' ) ?>
                    </label>
                </p>
                <p>
                    <select multiple="multiple"  id="wpp_post_allow_roles" name="wpp_post_allow_roles[]">
                        <option value="all" <?php if(in_array("all", $allow_roles) || !isset($allow_roles)) echo ' selected="selected" '; ?> >
                            <?php echo translate( 'All', 'wp-private-posts' ) ?>
                        </option>
                        <?php

                            $roles = get_editable_roles(); 
                            
                            foreach ( $roles as $rol )
                            {
                                echo '<option ';

                                if( in_array($rol["name"], $allow_roles) )
                                    echo ' selected="selected" ';

                                echo ' value="'.$rol["name"].'">'.$rol["name"].'</option>';
                            } 

                        ?>
                    </select>
                </p>

                <p>
                    <label for="wpp_post_allow_users">
                        <?php echo translate( 'Allow Users:', 'wp-private-posts' ) ?>
                    </label>
                </p>
                <p>
                    <select multiple="multiple"  id="wpp_post_allow_users" name="wpp_post_allow_users[]">
                        <option value="all" <?php if(in_array("all", $allow_users) || !isset($allow_users)) echo ' selected="selected" '; ?> >
                            <?php echo translate( 'All', 'wp-private-posts' ) ?>
                        </option>
                        <?php

                            $users = get_users(); 
                            
                            foreach ( $users as $user )
                            {
                                echo '<option ';

                                if( in_array($user->display_name, $allow_users) )
                                    echo ' selected="selected" ';

                                echo ' value="'.$user->display_name .'">'.ucfirst ($user->display_name ).'</option>';
                            } 

                        ?>
                    </select>
                </p>

                <input type="hidden" value="ok" name="wpp_validate_data" id="wpp_validate_data" />

            </div>
        <?php 
        }

        public function wpp_save_data_post_permissions( $post_id )
        {
            if (current_user_can("administrator") != 1  || !isset($_REQUEST['wpp_validate_data'])) return;

            $post_allow_roles = $post_allow_uses  = array();

            $validate_wpp_post_allow_roles = $validate_wpp_post_allow_users =  true;

            foreach( $_REQUEST['wpp_post_allow_roles'] as $rol)
            {
                if(wp_check_invalid_utf8( $rol, true ) != "")
                    $post_allow_roles[] = sanitize_text_field($rol);
                else
                    $validate_wpp_post_allow_roles = false;
            }

            foreach( $_REQUEST['wpp_post_allow_users'] as $user)
            {
                if(wp_check_invalid_utf8( $user, true ) != "")
                    $post_allow_uses[] = sanitize_text_field($user);
                else
                    $validate_wpp_post_allow_users = false;
            }

            if($validate_wpp_post_allow_roles )
                update_post_meta( $post_id, 'wpp_post_allow_roles', $post_allow_roles);

            if($validate_wpp_post_allow_users )
                update_post_meta( $post_id, 'wpp_post_allow_users', $post_allow_uses);
        }

        public function wpp_load_body($content) 
        {
            global $post;

            $message = get_option( "wpp_message_posts");

            if($message == "") $message = '<a href="'.get_admin_url().'options-general.php?page=wp_private_posts">'.translate( 'Define the private posts message in the settings menu.', 'wp-private-posts' ).'</a>';

            $user = wp_get_current_user();  

            $allow_roles = get_post_meta( $post->ID, 'wpp_post_allow_roles', true);
            $allow_users = get_post_meta( $post->ID, 'wpp_post_allow_users', true);

            if(is_array($user->roles))
                $rol = $user->roles[0];
            else
                $rol = $user->roles;

            if(in_array("all", $allow_roles) || in_array("all", $allow_users))
                return $content;

            if( in_array(ucfirst($rol), $allow_roles) || in_array($user->display_name, $allow_users))
                return $content;   

            if( $allow_roles == "" && $allow_users == "")
                return $content; 

            if( count($allow_roles) == 0 && count($allow_users) == 0)
                return $content; 

            return $message; 
        } 

        public function wpp_load_head()
        {
            global $post;

            $user = wp_get_current_user();  

            $allow_roles = get_post_meta( $post->ID, 'wpp_post_allow_roles', true);
            $allow_users = get_post_meta( $post->ID, 'wpp_post_allow_users', true);

            if(!in_array("all", $allow_roles) && !in_array("all", $allow_users)
            && (count($allow_roles) > 0  || count($allow_users) > 0))
                echo '<meta name="robots" content="noindex,nofollow">';
        }
    }
}

$GLOBALS['private_posts'] = new private_posts();   
    
?>
