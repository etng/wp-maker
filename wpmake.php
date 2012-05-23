<?php
array_shift($argv);
$command ='all';
if($argv)
{
    $command = array_shift($argv);
}
$wp_maker = new Wp_Maker();


switch($command)
{
    case 'install':
        $wp_maker->install();
        echo 'installed'.PHP_EOL;
        break;
    case 'theme':
        break;
    case 'plugin':
        $wp_maker->activatePlugins();
        echo 'actived'.PHP_EOL;
        break;
    case 'all':
    default:
        $wp_maker->prepareFiles();
        echo 'files ready' . PHP_EOL;
        $wp_maker->updateConfig();
        echo 'config file ready'.PHP_EOL;
        echo 'done' . PHP_EOL;
        $wp_maker->install();
        echo 'installed'.PHP_EOL;
        $wp_maker->activatePlugins();
        echo 'actived'.PHP_EOL;
        break;
}


class Wp_Maker
{
    function __construct()
    {
        $this->cwd = getcwd();
        if(!file_exists($config_file = $this->cwd . '/wpmake.ini'))
        {
            die('need wpmake.ini');
        }
        $this->config = parse_ini_file($config_file, true);
        if(!$this->config)
        {
            die('malformed wpmake.ini');
        }
        define('ABSPATH', $this->cwd .'/');
        define('WPINC', 'wp-includes');
        define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
        define('WP_DEBUG', false);
        if(!defined('WP_SITEURL'))
        {
            define('WP_SITEURL', $this->config['General']['url']);
        }
    }
    function prepareFiles()
    {
        if($this->isInstalled())
        {
            echo 'installed, no need to create files';
            return false;
        }
        $core_config = $this->config['core'];

        if(!isset($core_config['filename']))
        {
            $core_config['filename'] = "wordpress-{$core_config['version']}.zip";
        }

        if(!file_exists($filename = $this->cwd . '/' . $core_config['filename']))
        {
            if(!isset($core_config['url']))
            {
                $core_config['url'] = "http://wordpress.org/wordpress-{$core_config['version']}.zip";
            }
            file_put_contents($filename, file_get_contents($core_config['url']));
        }
        unzip($filename, $this->cwd, 'wordpress/');
        foreach($this->config['plugins'] as $plugin_name=>$plugin_config)
        {
            if(!isset($plugin_config['filename']))
            {
                $plugin_config['filename'] = "{$plugin_name}.{$plugin_config['version']}.zip";
            }
            if(!isset($plugin_config['url']))
            {
                $plugin_config['url'] = "http://downloads.wordpress.org/plugin/{$plugin_name}.{$plugin_config['version']}.zip";
            }
            if(!file_exists($filename = $this->cwd . '/' . $plugin_config['filename']))
            {
                file_put_contents($filename, file_get_contents($plugin_config['url']));
            }
            echo 'unzip plugin ' . $plugin_name . PHP_EOL;
            unzip($filename, $this->cwd . '/wp-content/plugins/');
         }
        foreach($this->config['themes'] as $theme_name=>$theme_config)
        {
            if(!isset($theme_config['filename']))
            {
                $theme_config['filename'] = "{$theme_name}.{$theme_config['version']}.zip";
            }
            if(!file_exists($filename = $this->cwd . '/' . $theme_config['filename']))
            {
                if(!isset($theme_config['url']))
                {
                    $theme_config['url'] = "http://wordpress.org/extend/themes/download/{$theme_name}.{$theme_config['version']}.zip";
                }
                file_put_contents($filename, file_get_contents($theme_config['url']));
            }
            echo 'unzip theme ' . $theme_name . PHP_EOL;
            unzip($filename, $this->cwd . '/wp-content/themes/');
        }
    }
    function updateConfig()
    {
        if($this->isInstalled())
        {
            echo 'installed, no need to update config';
            return false;
        }
            define( 'WP_INSTALLING', true );
            define('WP_SETUP_CONFIG', true);
            require_once(ABSPATH . WPINC . '/load.php');
            require_once(ABSPATH . WPINC . '/version.php');
            wp_check_php_mysql_versions();
            wp_unregister_GLOBALS();

            require_once(ABSPATH . WPINC . '/compat.php');
            require_once(ABSPATH . WPINC . '/functions.php');
            require_once(ABSPATH . WPINC . '/class-wp-error.php');
            require_once(ABSPATH . WPINC . '/formatting.php');
            wp_magic_quotes();
            require_once( ABSPATH . '/wp-includes/wp-db.php');
            global $wpdb;
            $wpdb = new wpdb( $this->config['Db']['user'], $this->config['Db']['pass'], $this->config['Db']['name'], $this->config['Db']['host'] );
            if ( ! empty( $wpdb->error ) ) {
                die( $wpdb->error->get_error_message());
            }
            require_once( ABSPATH . WPINC . '/plugin.php' );
            require_once( ABSPATH . WPINC . '/l10n.php' );
            require_once( ABSPATH . WPINC . '/pomo/translations.php' );
            $secret_keys = array();
            require_once( ABSPATH . WPINC . '/pluggable.php' );
            for ( $i = 0; $i < 8; $i++ ) {
                $secret_keys[] = wp_generate_password( 64, true, true );
            }
            $key = 0;
            $configFile = file(ABSPATH . 'wp-config-sample.php');

            foreach ($configFile as $line_num => $line) {
                switch (substr($line,0,16)) {
                    case "define('DB_NAME'":
                        $configFile[$line_num] = str_replace("database_name_here", $this->config['Db']['name'], $line);
                        break;
                    case "define('DB_USER'":
                        $configFile[$line_num] = str_replace("'username_here'", "'{$this->config['Db']['user']}'", $line);
                        break;
                    case "define('DB_PASSW":
                        $configFile[$line_num] = str_replace("'password_here'", "'{$this->config['Db']['pass']}'", $line);
                        break;
                    case "define('DB_HOST'":
                        $configFile[$line_num] = str_replace("localhost", $this->config['Db']['host'], $line);
                        break;
                    case '$table_prefix  =':
                        $configFile[$line_num] = str_replace('wp_', $this->config['Db']['prefix'], $line);
                        break;
                    case "define('AUTH_KEY":
                    case "define('SECURE_A":
                    case "define('LOGGED_I":
                    case "define('NONCE_KE":
                    case "define('AUTH_SAL":
                    case "define('SECURE_A":
                    case "define('LOGGED_I":
                    case "define('NONCE_SA":
                        $configFile[$line_num] = str_replace('put your unique phrase here', $secret_keys[$key++], $line );
                        break;
                }
            }
            $handle = fopen(ABSPATH . 'wp-config.php', 'w');
            foreach( $configFile as $line ) {
                fwrite($handle, $line);
            }
            fclose($handle);
            chmod(ABSPATH . 'wp-config.php', 0666);

    }
    function install()
    {
        if(defined('WP_SETUP_CONFIG'))
        {
            die('rerun this command later, you are setting config now');
        }
        define( 'WP_INSTALLING', true );
        // in function,something we must declare to global,fuck wordpress sb!!!
        global $wp_the_query,$wp_rewrite;
        require_once(ABSPATH . 'wp-load.php');
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        require_once(ABSPATH . 'wp-includes/wp-db.php');
        if(is_blog_installed())
        {
            die('uninstall first');
        }
        $site = $this->config['General'];
        wp_install($site['title'], $site['admin_username'], $site['admin_email'], $site['public'], '', $site['admin_password']);
        foreach($this->config['options'] as $k=>$v)
        {
            update_option($k, $v);
        }
    }
    function activatePlugins()
    {
        if(!$this->isInstalled())
        {
            die('you should config and install it first');
        }
        global $wp_the_query,$wp_rewrite;
        require_once(ABSPATH . 'wp-load.php');
        require_once(ABSPATH . 'wp-admin/includes/admin.php');
        if(!is_blog_installed())
        {
            die('you should install it first');
        }
        $plugins_to_active = array();
        foreach($this->config['plugins'] as $plugin_name=>$plugin_config)
        {
            if(empty($plugin_config['active']) || $plugin_config['active']==1)
            {
                $plugins_to_active []= $plugin_name;
            }
        }
        $plugins = apply_filters( 'all_plugins', get_plugins() );
        foreach((array)$plugins as $plugin_file=>$plugin_meta)
        {
            if(!is_plugin_active( $plugin_file ))
            {
                $plugin_name = current(explode('/', $plugin_file, 2));
                if(in_array($plugin_name, $plugins_to_active))
                {
                    echo "activating plugin " . $plugin_meta['Name'] . PHP_EOL;
                    activate_plugin($plugin_file);
                }
            }
        }
    }
    function isInstalled()
    {
        return file_exists(ABSPATH . 'wp-config.php');
    }
}

function unzip($zip_file, $dest_dir, $prefix=null)
{
    $zip = new ZipArchive;
    if ($zip->open($zip_file) === TRUE)
    {
        if($prefix)
        {
            for($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if(substr($entry, -1)=='/')
                {

                }
                else
                {
//                echo 'Extracting ' . $entry . '...' . PHP_EOL;
                $dest_file = $dest_dir . '/' . substr($entry, strlen($prefix));
                if(!is_dir($d=dirname($dest_file)))
                {
                    mkdir($d, 0777, true);
                }
                copy('zip://'.$zip_file.'#'.$entry, $dest_file);
                }
            }
        }
        else
        {
            $zip->extractTo($dest_dir);
         }
        $zip->close();
       return true;
    }
    return false;
}



function wp_install_defaults($user_id) {
    global $wpdb, $wp_rewrite, $current_site, $table_prefix;
    global $wp_maker;
    $wpdb->show_errors();
    // Default category
    $cat_name = __('Uncategorized');
    /* translators: Default category slug */
    $cat_slug = sanitize_title(_x('Uncategorized', 'Default category slug'));

    if ( global_terms_enabled() ) {
        $cat_id = $wpdb->get_var( $wpdb->prepare( "SELECT cat_ID FROM {$wpdb->sitecategories} WHERE category_nicename = %s", $cat_slug ) );
        if ( $cat_id == null ) {
            $wpdb->insert( $wpdb->sitecategories, array('cat_ID' => 0, 'cat_name' => $cat_name, 'category_nicename' => $cat_slug, 'last_updated' => current_time('mysql', true)) );
            $cat_id = $wpdb->insert_id;
        }
        update_option('default_category', $cat_id);
    } else {
        $cat_id = 1;
    }

    $wpdb->insert( $wpdb->terms, array('term_id' => $cat_id, 'name' => $cat_name, 'slug' => $cat_slug, 'term_group' => 0) );
    $wpdb->insert( $wpdb->term_taxonomy, array('term_id' => $cat_id, 'taxonomy' => 'category', 'description' => '', 'parent' => 0, 'count' => 1));
    $cat_tt_id = $wpdb->insert_id;

    // Default link category
    $cat_name = __('Blogroll');
    /* translators: Default link category slug */
    $cat_slug = sanitize_title(_x('Blogroll', 'Default link category slug'));

    if ( global_terms_enabled() ) {
        $blogroll_id = $wpdb->get_var( $wpdb->prepare( "SELECT cat_ID FROM {$wpdb->sitecategories} WHERE category_nicename = %s", $cat_slug ) );
        if ( $blogroll_id == null ) {
            $wpdb->insert( $wpdb->sitecategories, array('cat_ID' => 0, 'cat_name' => $cat_name, 'category_nicename' => $cat_slug, 'last_updated' => current_time('mysql', true)) );
            $blogroll_id = $wpdb->insert_id;
        }
        update_option('default_link_category', $blogroll_id);
    } else {
        $blogroll_id = 2;
    }

    $wpdb->insert( $wpdb->terms, array('term_id' => $blogroll_id, 'name' => $cat_name, 'slug' => $cat_slug, 'term_group' => 0) );
    $wpdb->insert( $wpdb->term_taxonomy, array('term_id' => $blogroll_id, 'taxonomy' => 'link_category', 'description' => '', 'parent' => 0, 'count' => count($wp_maker->config['links'])));
    $blogroll_tt_id = $wpdb->insert_id;
    // Now drop in some default links
     foreach($wp_maker->config['links'] as $slug=>$link)
    {
        $wpdb->insert( $wpdb->links, $link);
        $wpdb->insert( $wpdb->term_relationships, array('term_taxonomy_id' => $blogroll_tt_id, 'object_id' => $wpdb->insert_id) );
    }
    $now = date('Y-m-d H:i:s');
    $now_gmt = gmdate('Y-m-d H:i:s');
    $post_id = 1;

    foreach($wp_maker->config['pages'] as $slug=>$page)
    {

        $page['slug'] = $slug;
        $page['guid'] = get_option('home') . '/?page_id=' . $post_id;

        $wpdb->insert( $wpdb->posts, array(
                                'post_author' => $user_id,
                                'post_date' => $now,
                                'post_date_gmt' => $now_gmt,
                                'post_content' => $page['content'],
                                'post_excerpt' => '',
                                'post_title' => $page['title'],
                                /* translators: Default page slug */
                                'post_name' => $page['slug'],
                                'post_modified' => $now,
                                'post_modified_gmt' => $now_gmt,
                                'guid' => $page['guid'],
                                'post_type' => 'page',
                                'to_ping' => '',
                                'pinged' => '',
                                'post_content_filtered' => ''
                                ));
        $wpdb->insert( $wpdb->postmeta, array( 'post_id' => $wpdb->insert_id, 'meta_key' => '_wp_page_template', 'meta_value' => empty($page['template'])?'default':$page['template'] ) );
        $post_id++;
    }
    foreach($wp_maker->config['post-cates'] as $slug=>$cate)
    {
        //@doto insert cate data
        $cate_tt_ids[]=$wpdb->insert_id;
    }
    foreach($wp_maker->config['posts'] as $slug=>$post)
    {
        $post['slug'] = $slug;
        $post['guid'] = get_option('home') . '/?p=' . $post_id;
        $wpdb->insert( $wpdb->posts, array(
                                    'post_author' => $user_id,
                                    'post_date' => $now,
                                    'post_date_gmt' => $now_gmt,
                                    'post_content' => $post['content'],
                                    'post_excerpt' => '',
                                    'post_title' => $post['title'],
                                    /* translators: Default post slug */
                                    'post_name' => $post['slug'],
                                    'post_modified' => $now,
                                    'post_modified_gmt' => $now_gmt,
                                    'guid' => $post['guid'],
                                    'comment_count' => 1,
                                    'to_ping' => '',
                                    'pinged' => '',
                                    'post_content_filtered' => ''
                                    ));
        $cat_tt_id = $cate_tt_ids[$post['cate']];
        $wpdb->insert( $wpdb->term_relationships, array('term_taxonomy_id' => $cat_tt_id, 'object_id' => $wpdb->insert_id) );
        if(!empty($wp_maker->config['dev']['dummy_comment']))
        {
            foreach(range(1, 100) as $i)
            {
                // Default comment
                $dummy_comment_author = 'Dummy';
                $dummy_comment_url = 'http://dummy.org/';
                $dummy_comment = 'This is a dummy comment for this post with number ' . $i;

                $wpdb->insert( $wpdb->comments, array(
                                            'comment_post_ID' => $post_id,
                                            'comment_author' => $dummy_comment_author,
                                            'comment_author_email' => '',
                                            'comment_author_url' => $dummy_comment_url,
                                            'comment_date' => $now,
                                            'comment_date_gmt' => $now_gmt,
                                            'comment_content' => $dummy_comment
                                            ));
            }
        }
        $post_id++;
    }
    // Set up default widgets for default theme.
    update_option( 'widget_search', array ( 2 => array ( 'title' => '' ), '_multiwidget' => 1 ) );
    update_option( 'widget_recent-posts', array ( 2 => array ( 'title' => '', 'number' => 5 ), '_multiwidget' => 1 ) );
    update_option( 'widget_recent-comments', array ( 2 => array ( 'title' => '', 'number' => 5 ), '_multiwidget' => 1 ) );
    update_option( 'widget_archives', array ( 2 => array ( 'title' => '', 'count' => 0, 'dropdown' => 0 ), '_multiwidget' => 1 ) );
    update_option( 'widget_categories', array ( 2 => array ( 'title' => '', 'count' => 0, 'hierarchical' => 0, 'dropdown' => 0 ), '_multiwidget' => 1 ) );
    update_option( 'widget_meta', array ( 2 => array ( 'title' => '' ), '_multiwidget' => 1 ) );
    update_option( 'sidebars_widgets', array ( 'wp_inactive_widgets' => array ( ), 'sidebar-1' => array ( 0 => 'search-2', 1 => 'recent-posts-2', 2 => 'recent-comments-2', 3 => 'archives-2', 4 => 'categories-2', 5 => 'meta-2', ), 'sidebar-2' => array ( ), 'sidebar-3' => array ( ), 'sidebar-4' => array ( ), 'sidebar-5' => array ( ), 'array_version' => 3 ) );

    if ( ! is_multisite() )
        update_user_meta( $user_id, 'show_welcome_panel', 1 );
    elseif ( ! is_super_admin( $user_id ) && ! metadata_exists( 'user', $user_id, 'show_welcome_panel' ) )
        update_user_meta( $user_id, 'show_welcome_panel', 2 );

    if ( is_multisite() ) {
        // Flush rules to pick up the new page.
        $wp_rewrite->init();
        $wp_rewrite->flush_rules();

        $user = new WP_User($user_id);
        $wpdb->update( $wpdb->options, array('option_value' => $user->user_email), array('option_name' => 'admin_email') );

        // Remove all perms except for the login user.
        $wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix.'user_level') );
        $wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix.'capabilities') );

        // Delete any caps that snuck into the previously active blog. (Hardcoded to blog 1 for now.) TODO: Get previous_blog_id.
        if ( !is_super_admin( $user_id ) && $user_id != 1 )
            $wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE user_id = %d AND meta_key = %s", $user_id, $wpdb->base_prefix.'1_capabilities') );
    }
}