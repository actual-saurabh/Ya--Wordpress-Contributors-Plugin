<?php
    /*
    Plugin Name: Ya! Wordpress Contributors
    Description: Ya! Wordpress Contributors lets you add multiple users as contributors to any post type.
    Version: 0.1
    Author: Saurabh Shukla
    Author URI: http://www.yapapayalabs.com/
    */

class ya_wp_contributors{
    
    // Constructor for sparkly new PHP 5.x
    function __construct() {
        
        // Add necessary css
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_ya_scripts' ) );
        
        // Action to set contributors when a post is saved
        add_action( 'save_post', array( $this, 'contributors_update_post' ), 10, 2 );
        
        // Add the meta box
        add_action( 'add_meta_boxes', array( $this, 'add_contributors_box' ) );
        
        // Filter content to display contributors
        add_filter( 'the_content', array( $this, 'show_contributors' ) );
        
        // Filter author page to display list of contributions (?)
        // Add a way to display contribution count at different views (?)
        // What views? Author pages, short author bios on posts, etc (?)
    }
    
    // Constructor for dull, old PHP 4.x like
    function ya_wp_contributors(){
        $this->__construct();
    }
    
    // Checks whether the admin scripts are needed
    function should_load_scripts() {
        global $pagenow;
        
        return in_array( $pagenow, array( 'post.php', 'post-new.php' ) );
    } 
    
    
    function get_current_post_type() {
        
        // Initialise some global variables
        global $post, $typenow, $current_screen;
        
        // Why go through all the trouble, if it is already set
        if( isset( $this->_current_post_type ) )
            return $this->_current_post_type;
        
        // Assign the current post's post type
        if( $post && $post->post_type )
            $post_type = $post->post_type;
        // That failed, see if a global post type is set
        elseif( $typenow )
            $post_type = $typenow;
        // Not that, check if the current admin screen can give us a clue
        elseif( $current_screen && isset( $current_screen->post_type ) )
            $post_type = $current_screen->post_type;
        //If that fails, get it from the query
        elseif( isset( $_REQUEST['post_type'] ) )
            $post_type = sanitize_key( $_REQUEST['post_type'] );
        else
            $post_type = '';
        
        // Return a post type, if found
        if( $post_type )
            $this->_current_post_type = $post_type;
            return $post_type;
    }

    function contributors_update_post( $post_id, $post ) {
        global $current_user;
        // Get the post type
        $post_type = $post->post_type;
        
        // Ignore if this is for an autosave
        if ( defined( 'DOING_AUTOSAVE' ) && !DOING_AUTOSAVE )
            return;
        
        // Validate nonce and the submission    
        if( isset( $_POST['ya-wp-contributors-nonce'] ) && isset( $_POST['contributors'] ) ) {
            check_admin_referer( 'contributors-edit', 'ya-wp-contributors-nonce' );
            
            if( current_user_can('edit_posts') ){
                $contributors = (array) $_POST['contributors'];
                $contributors = array_map( 'sanitize_user', $contributors );
                return $this->add_contributors( $post_id, $contributors );
            }
        }
    }
    
    // Function to get a list of Contributor Ids or full user objects
    function get_contributors($post_id=false, $asid=false){
        global $current_user,$post;
        $post_id = (int) $post_id;
        if(!$post_id){
            $post_id=$post->ID;
        }
        
        // Initialise as empty array
        $contributors = array();
        $contributormeta    = get_post_meta($post_id, 'ya-contributors',true);
        
        // If there are contributors set for this post  
        if(is_array($contributormeta)&&!empty($contributormeta)){    
            if(!$asid){ //We want full user object
                $uargs              = array('include'=>$contributormeta);
                $contributors       = get_users($uargs);  
            }else{      //We only want Ids
                $contributors       = $contributormeta;
            }
        }   
        
        return $contributors;
    }
    
    function add_contributors( $post_id=false, $contributors) {
        global $current_user,$post;
        if(!$post_id){
            global $post;
            $post_id=$post->ID;
        }
        $post_id = (int) $post_id;
        
        // At least set the default author as contributor
        if ( !is_array( $contributors ) || 0 == count( $contributors ) || empty( $contributors ) ) {
            $contributors = array( $current_user->ID );
        }
                
        // Add contributors to post meta
        update_post_meta($post_id, 'ya-contributors', $contributors);
        
        //Vice versa step to add an array of post ids to user meta: contributions and another: contribution_count (?)
    }
    
    function add_contributors_box() {
        global $current_user;
        $post_type = $this->get_current_post_type();
        
        if( current_user_can( 'edit_posts' ) )
            add_meta_box('ya-wp-contributors-div', __('Post Contributors', 'ya-wp-contributors'), array( &$this, 'contributors_meta_box' ), $post_type, 'normal', 'high');
    }
    
    function contributors_meta_box( $post ) {
        global $post, $current_user;
        
        $post_id = $post->ID;
        
        if( !$post_id || $post_id == 0 || !$post->post_author )
            $contributors = array( $current_user->ID );
        else 
            $contributors = $this->get_contributors($post_id,true);
        
        if (empty($contributors)){
            $contributors = array( $current_user->ID );
        }
        $users = get_users();
        
        $count = 0;
        if( !empty( $users ) ) :
            ?>
            <div id="contributorswrap">
                <?php
                foreach( $users as $auser ){
                    $count++;
                    ?>
                    <div class="contributor" id="contributor-<?php echo $count; ?>">
                        <label for="contributors">
                            <input type="checkbox" name="contributors[]" value="<?php echo esc_attr( $auser->ID ); ?>" <?php echo(in_array($auser->ID, $contributors)?'checked="checked"':''); ?> />
                            <div class="cont-profile">
                                <?php echo get_avatar( $auser->user_email, 25 ); ?>
                                <strong class="name"><?php echo esc_attr( $auser->display_name ); ?></strong>
                                <strong class="login"><?php echo esc_attr( $auser->user_login ); ?></strong>
                                <em class="login"><?php echo esc_attr( $auser->user_email); ?></em> 
                            </div>
                        </label>
                    </div>
                    <?php
                }
                ?>             
                <div class="clear"></div>
            </div>
            <?php
        endif;
        ?>
        
        <?php wp_nonce_field( 'contributors-edit', 'ya-wp-contributors-nonce' ); //nonce for security ?>
        
        <?php
    }
    function show_contributors($content){
        global $post;
        
        $post_id = $post->ID;
        
        $contributors   = $this->get_contributors($post_id);
        
        // If there are contributors, else just leave stuff, as it is
        if(!empty($contributors)){        
            $contributorbox = '<div class="contributors"><h3>Contributors</h3><ul>';
            
            foreach($contributors as $contributor){
                $contributorbox .=  '<li class="description"><a href="'.get_author_posts_url( $contributor->ID, $contributor->user_nicename ).'
                                        " title="'.esc_attr( $contributor->display_name ).'">'
                                        .get_avatar( $contributor->user_email, 25 ).'
                                        <span class="name">'.esc_attr( $contributor->display_name ).'</span>
                                    </a></li>';
            }
            
            $contributorbox .= '</ul></div>';
        }
        
        $content = $content.$contributorbox; // append contributor box to content
        
        return $content;
                            
    }
    
    function enqueue_scripts(){
        global $pagenow, $post,$current_user;
        
        $post_type = $this->get_current_post_type();
        
        // Load only when needed
        if ( !$this->should_load_scripts() || !current_user_can('edit_posts') )
            return;
        
        wp_enqueue_style( 'ya-wp-contributors-css', plugin_dir_url( __FILE__ ) . 'css/style.css', false, 1, 'all' );
        
    }

    function enqueue_ya_scripts(){
        // this can be overridden by theme style or via an options panel (?)
        wp_register_style( 'ya-wp-contributors', plugin_dir_url( __FILE__ ) . 'css/ya-wp-contributors.css' );
        wp_enqueue_style( 'ya-wp-contributors' );
    }
    
}

global $ya_wp_contributors;
$ya_wp_contributors = new ya_wp_contributors();
?>