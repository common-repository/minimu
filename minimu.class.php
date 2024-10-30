<?php
/*
Plugin Name: MiniMU
Plugin URI: http://www.smashlab.com/incubator/minimu
Description: Manage multiple blogs with a single standard WordPress installation. Each may have its own theme and domain while sharing users and administration. <a href="options-general.php?page=wp-minimu-admin-main">Configure options</a> 
Version: 0.6.9
Author: Eric Shelkie
Author URI: http://www.smashlab.com
License: GPL2
*/

/*  Copyright 2010 Eric Shelkie (email : shelkie@smashlab.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

 //error_reporting(E_ALL);
 //ini_set('display_errors',true);


class MiniMU{
	const VERSION="0.6.9";
	const WP_OPTION_KEY='minimu-options';
	const DEFAULT_CATEGORY_BASE="category";
	const MAX_CUSTOM_VAR_NAME_LENGTH=16;
	protected $arr_plugin_options=array();
	private static $instance;
	static $in_admin=false;
	protected $plugin_directory;
	public $arr_domains;
	protected $arr_categories;
	protected $arr_themes;
	protected $bln_settings_saved=false;
	public $current_domain_id=null;
	protected $obj_current_domain_options;
	protected $base_domain;
	protected $obj_base_domain;
	protected $arr_values=array(
		array('input_name'=>'minimu-domain-name', 'property_name'=>'domain_name'),
		array('input_name'=>'minimu-catselect', 'property_name'=>'category_id'),
		array('input_name'=>'minimu-themeselect', 'property_name'=>'theme_name'),
		array('input_name'=>'minimu-blog-title', 'property_name'=>'blog_title'),
		array('input_name'=>'minimu-tagline', 'property_name'=>'tagline'),
		array('input_name'=>'minimu-custom-vars', 'property_name'=>'custom_vars')
	);

	/**
	 * Get instance of this class
	 *
	 * @return MiniMU
	 */
	static function get_instance(){
		if (!self::$instance instanceof MiniMU ){
			self::$instance=new MiniMU();
		}
		return self::$instance;
	}

	/**
	 * Initialize plugin
	 *
	 * @author Eric Shelkie
	 * @since November 22, 2009
	 */
	static public function init(){
		global $minimu_obj_base_domain;
		$dc_instance=MiniMU::get_instance();
		$dc_instance->base_domain=$minimu_obj_base_domain->domain_name;
		
		// Get plugin options
		$dc_instance->arr_domains=array();
		$dc_instance->arr_plugin_options=$dc_instance->get_plugin_options();
		
		if (is_array($dc_instance->arr_plugin_options) && isset($dc_instance->arr_plugin_options['arr_domains'])) $dc_instance->arr_domains=$dc_instance->arr_plugin_options['arr_domains'];
		if (!isset($dc_instance->arr_domains['0'])) $dc_instance->arr_domains['0']=$minimu_obj_base_domain;
		
		// The wordpress base/default domain
		$dc_instance->obj_base_domain=$minimu_obj_base_domain;
		$arr_merge=(array)$minimu_obj_base_domain;

		unset($arr_merge['category_id']);
		$dc_instance->arr_domains['0']=(object)array_merge((array)$dc_instance->arr_domains['0'], $arr_merge);

		// Prime this before setting filters
		$dc_instance->get_current_domain_id();
		
		
		// Add filters
		//add_filter('option_url', 'MiniMU::filter_url', 0);  /************ this s/b removed? ****************/
		add_filter('site_url', 'MiniMU::filter_site_url', 0,3);
		add_filter('option_siteurl', 'MiniMU::filter_site_url', 0,1);
		add_filter('content_url', 'MiniMU::filter_content_url', 0,2);
		add_filter('option_blogname', 'MiniMU::filter_blogname', 0);
		add_filter('option_home', 'MiniMU::filter_home', 0);
		add_filter('option_blogdescription', 'MiniMU::filter_blogdescription', 0);
		add_filter('option_template', 'MiniMU::filter_theme', 0);
		add_filter('option_stylesheet', 'MiniMU::filter_stylesheet', 0);
		add_filter('template', 'MiniMU::filter_theme', 0);
        add_filter('allowed_http_origins', 'MiniMU::filter_allowed_http_origins');

        //add_filter('bloginfo_url', 'MiniMU::filter_bloginfo', 15,2);
		//add_filter('bloginfo', 'MiniMU::filter_bloginfo', 15,2); 
		//add_filter('the_posts', 'MiniMU::the_posts');
		add_filter('post_link', 'MiniMU::filter_post_link', 0, 3);
		add_filter('getarchives_where', 'MiniMU::filter_getarchives_where');
		add_filter('getarchives_join', 'MiniMU::filter_getarchives_join' );
		add_filter('posts_where', 'MiniMU::filter_posts_where');
		add_filter('posts_join', 'MiniMU::filter_posts_join' );
		add_filter('posts_join', 'MiniMU::filter_posts_join' );
		add_filter('posts_groupby', 'MiniMU::filter_posts_groupby' );
		
		add_filter('get_previous_post_where', 'MiniMU::filter_previous_post_where', 0, 3);
		add_filter('get_previous_post_join', 'MiniMU::filter_previous_post_join', 0, 3);
		add_filter('get_next_post_where', 'MiniMU::filter_previous_post_where', 0, 3);
		add_filter('get_next_post_join', 'MiniMU::filter_previous_post_join', 0, 3);
		
		
		//add_filter('body_class','MiniMU::filter_body_class'); /** Disabled this to remove reference to other domains*/
		add_filter('category_link','MiniMU::filter_category_link', 0, 2);
		add_filter('the_content', 'MiniMU::filter_replace_tokens');
		add_filter('the_excerpt', 'MiniMU::filter_replace_tokens');
		
		add_filter('comments_clauses', 'MiniMU::filter_comments');

		add_filter( 'list_terms_exclusions', 'MiniMU::filter_list_terms_exclusions', 0 ,2); 
		//add_filter('get_terms', 'MiniMU::filter_get_terms');

		// Set up admin menus
		add_action( 'admin_init', 'MiniMU::admin_init' );
		add_action('admin_menu', 'MiniMU::add_admin_menu');		//$this->plugin_directory=WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__));

		// Initialize widgets
		add_action('widgets_init', create_function('', 'return register_widget("MiniMU_blog_list_widget");'));
		
		// Customize category creation/editing forms
		// 06/02/2011 - Disabled editing on Category page - should only have one way to select categories for domain
		//add_action( 'category_add_form_fields', 'MiniMU::add_category_form_fields', 15, 1);
		//add_action( 'category_edit_form_fields', 'MiniMU::edit_category_form_fields', 15, 1 );
		//add_action( 'created_category', 'MiniMU::category_edited', 15, 2 );
		//add_action( 'edited_category', 'MiniMU::category_edited', 15, 2 );
		add_filter( 'manage_edit-category_columns', 'MiniMU::category_column_headers');
		add_action( 'manage_category_custom_column', 'MiniMU::category_column_data', 15, 3);
		
		add_filter( 'get_pages', 'MiniMU::filter_get_pages', 12, 2);
		add_action( 'init', 'MiniMU::add_meta_boxes', 12, 0);	
		
		
	}
	
	/**
	 * Add category taxonomy to page objects
	 * 
	 *  @author Eric Shelkie
	 *  @since May 24, 2013
	 */
	static function add_meta_boxes(){
		if (!is_object_in_taxonomy('page', 'category')){
			register_taxonomy_for_object_type('category', 'page');
		}
	}
	
	static function filter_get_pages($arr_pages){
		// don't filter if we're in admin area
		if (!is_admin()){
			$dc_instance=MiniMU::get_instance();
			$obj_options=$dc_instance->get_domain_options();
			$arr_domain_categories=(array) $obj_options->category_id;
			
			foreach ($arr_pages as $key=>$page){
				$arr_term_ids = array();
				$arr_terms = get_the_terms($page, 'category');
				//echo "Terms: " . print_r($arr_terms, true);
				if (is_array($arr_terms)){
					foreach ($arr_terms as $obj_term) $arr_term_ids[] = $obj_term->term_id;
					
					// if page has categories, but none that are part of this domain, drop it
					// if page has no categories, don't drop it
					if (count($arr_terms)>0 && !array_intersect($arr_term_ids, $arr_domain_categories)){
						unset($arr_pages[$key]);
						
						// Also remove sub-pages
						foreach($arr_pages as $key=>$sub_page){
							if ($sub_page->post_parent == $page->ID) unset($arr_pages[$key]);
						}
					}
				}
			}
		}
		return $arr_pages;
	}

    /**
     * Filter allowed origins to include MiniMU domains
     */
    static function filter_allowed_http_origins($allowed) {
        $dc_instance=MiniMU::get_instance();
        foreach($dc_instance->arr_domains as $domain)
            if ( !$domain->is_base ) {
                $allowed[] = "http://" . $domain->domain_name;
                $allowed[] = "https://" . $domain->domain_name;
            }
        return $allowed;
    }


    /**
	 * Customize Category Add form
	 *
	 * @author Eric Shelkie
	 * @since April 11, 2011
	 */
	/*
	static function add_category_form_fields(){
		?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="parent"><?php _ex('MiniMU Domain(s)', 'MiniMU Domain'); ?></label></th>
			<td>
				<?php $arr_domains=MiniMU::get_blog_list();
				foreach ($arr_domains as $obj_domain){
					echo "<label><input type='checkbox' name='minimu_domains[{$obj_domain->id}]' value='1' style='width: auto; margin-right: 5px;' />{$obj_domain->domain_name}</label>\n";	
				}
				?>
				<span class="description"><?php _e('This category will appear within these blogs/domains'); ?></span>
				<br />
			</td>
		</tr>
		<?php 
	}8/
	
	/**
	 * Customize Category Edit form
	 *
	 * @author Eric Shelkie
	 * @since April 11, 2011
	 */
	/*
	static function edit_category_form_fields($obj_category){
		$dc_instance=MiniMU::get_instance();
		$arr_domains=$dc_instance->get_domains_from_category_ids((array)$obj_category->term_id);
		?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="parent"><?php _ex('MiniMU Domain(s)', 'MiniMU Domain'); ?></label></th>
			<td>
				<?php $arr_all_domains=MiniMU::get_blog_list();
				foreach ($arr_all_domains as $obj_domain){
					echo "<label><input type='checkbox' name='minimu_domains[]' value='d{$obj_domain->id}' style='width: auto; margin-right: 5px;'";
					if (array_key_exists($obj_domain->id, $arr_domains)) echo " checked='checked'";
					echo " />{$obj_domain->domain_name}</label><br />\n";	
				}
				?>
				<span class="description"><?php _e('This category will appear within these blogs/domains'); ?></span>
				<br />
			</td>
		</tr>
		<?php 
	}*/
	
	/*
	static function category_created($term_id, $tt_id, $taxonomy){
		error_log( "created category: $term_id : " . $tt_id . " : " . print_R($_POST, true));
	}*/
	
	/**
	 * Update categories when Category is edited
	 *
	 * @author Eric Shelkie
	 * @since April 12, 2011
	 */
	/*
	static function category_edited($term_id, $tt_id){
		$dc_instance=MiniMU::get_instance();
		// Build an array of domain that should have this category
		$arr_checked_domains=array();
		if (isset($_POST['minimu_domains']) && is_array($_POST['minimu_domains'])){
			foreach ($_POST['minimu_domains'] as $int_domain_id){
				$arr_checked_domains[]=(int)ltrim($int_domain_id,'d');
			}
		}
		$arr_checked_domains=array_unique($arr_checked_domains);
		foreach ($dc_instance->arr_domains as $obj_domain){
			$domain_id=$obj_domain->id;
			$arr_domain_categories=(array)$obj_domain->category_id;
			// add or remove the category from the domain
			// if domain checkbox was selected...
			if (in_array($domain_id, $arr_checked_domains)){
				// add the category to the domain, unless it it's already there
				if (!in_array($term_id, $arr_domain_categories,true)){
					$arr_domain_categories[]=$term_id;
				}
			}else{
				// otherwise, remove the category from the domain
				$arr_domain_categories=array_diff($arr_domain_categories, (array)$term_id);
			}
			$dc_instance->arr_domains[$domain_id]->category_id=$arr_domain_categories;
		}
		
		// Save the options
		$dc_instance->arr_plugin_options['arr_domains']=$dc_instance->arr_domains;
		update_option(MiniMU::WP_OPTION_KEY, $dc_instance->arr_plugin_options);
	}*/
	
	/**
	 * Display custom Category List column header
	 *
	 * @author Eric Shelkie
	 * @since April 12, 2011
	 */
	static function category_column_headers($arr_columns){
		$arr_columns['minimu_domain']="MiniMU Domain";
		return $arr_columns;	
	}
	
	/**
	 * Display custom Category List column
	 *
	 * @author Eric Shelkie
	 * @since April 12, 2011
	 */
	static function category_column_data($null, $column_name, $term_id){
		if ($column_name=='minimu_domain'){
			$dc_instance=MiniMU::get_instance();
			$arr_domains=$dc_instance->get_domains_from_category_ids((array)$term_id);
			foreach($arr_domains as $key=>$obj_domain){
				echo $obj_domain->domain_name . "<br />\n";
			}
		}
	}
	
	/**
	 * Get plugin options
	 *
	 * @return array $plugin_options
	 * @author Eric Shelkie
	 * @since December 7, 2009
	 */
	protected function get_plugin_options(){
		// Check if we already have the options
		if (is_array($this->arr_plugin_options) && isset($this->arr_plugin_options['arr_domains'])) return $this->arr_plugin_options;
		
		// Get options from database
		$arr_options=get_option(MiniMU::WP_OPTION_KEY);
		if (is_array($arr_options) && isset($arr_options['version'])){
				
			// Check if the options match the plugin, and if not, perform any necessary updates
			if ($arr_options['version']<MiniMU::VERSION){
				// Additional version checks will go here, as required
				
				// Save updated options array
				$arr_options['version']=MiniMU::VERSION;	
				update_option(MiniMU::WP_OPTION_KEY, $arr_options);
			}
		}else{
			// Didn't find any saved options
			$arr_options=array();
			$arr_options['version']=MiniMU::VERSION;	
			$arr_options['arr_domains']=array();

			// See if there are other options in the database
			if ($old_options=get_option('minimu_arr_domains')){
				$arr_options['arr_domains']=$old_options;
			}
			update_option(MiniMU::WP_OPTION_KEY, $arr_options);
		}
		$this->arr_plugin_options=$arr_options;
		return $this->arr_plugin_options;
	}


	/**
	 * Get options for domain
	 *
	 * @param int $domain_id
	 * @return stdClass Options
	 * @author Eric Shelkie
	 * @since November 26, 2009
	 */
	static function get_domain_options($domain_id=null){
		$return_value=false;
		
		$dc_instance=MiniMU::get_instance();

		if (is_null($domain_id)) $domain_id=$dc_instance->get_current_domain_id();

		if (!$domain_id) $domain_id=0;
		if ($domain_id!==false){
			if (isset($dc_instance->arr_domains[$domain_id])){
				$return_value=$dc_instance->arr_domains[$domain_id];
			}
		}	
		return $return_value;
	}


	/**
	 * Get the domain id of the current URI
	 *
	 * @return int Domain id
	 * @author Eric Shelkie
	 * @since November 26, 2009
	 */
	private function get_current_domain_id(){
		// Check if we've already done this
		if (!is_null($this->current_domain_id)) return $this->current_domain_id;
        $key=0;

		$arr_domains=$this->get_domain_array_by_domain_name();
		$protocol=(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!='off')?'https://':'http://';
		
		$server_name=(($_SERVER['SERVER_NAME'])?$_SERVER['SERVER_NAME']:$_SERVER['HTTP_HOST']);
		
		// Try to match the page to the list of configured domains
		$arr_server_path_options=array(
			$server_name,
			trim($server_name . $_SERVER['REQUEST_URI'], ' /')
		);
		

		// Look through possible domain matches, and use the one with the most "/" characters
		$arr_match=array_intersect($arr_domains, $arr_server_path_options);
		//echo "<pre>" . print_R($arr_match, true) . "</pre>";
		if (is_array($arr_match) && count($arr_match)>0){
			$max=0;
			$best_match=null;
			foreach (array_reverse($arr_match, true) as $key=>$value){
				$count=substr_count($value, '/');
				if ($count>=$max){
					// Use the base domain if its domain name is used by additional minimu domains
					if ($count==$max && !is_null($best_match) && $this->arr_domains[$best_match]->is_base) continue;
					
					$max=$count;
					$best_match=$key;
				}
			}
			$key=$best_match;
		}	
		//echo "Best match: $best_match " . print_r($this->arr_domains[$best_match],true);				
		
		// See if the URI contains category info - this would override the domain match
		$arr_page_categories=array();
			
		// Try to get category from current uri
		$arr_temp=explode('?', trim($_SERVER['REQUEST_URI']));
		
		$path=trim($arr_temp[0], '/');
		
		// clean up path
		$category_base=trim(get_option('category_base'),' /');
		if ($category_base=='') $category_base=MiniMU::DEFAULT_CATEGORY_BASE;
		$path='/' . str_replace($category_base, '', $path);
		
		// use WP function to find category in path
		$obj_category=get_category_by_path($path, true);

		if ($obj_category instanceof stdClass ){
			$arr_page_categories[]=$obj_category->cat_ID;
		}else{
		
			// try to get category from REQUEST
			if (isset($_REQUEST['cat'])) $arr_page_categories[]=(int) $_REQUEST['cat'];
		}

		// see if we can match the current category with one in our domain list
		if (count($arr_page_categories)>0){
			
			// Okay, we have some categories to consider. If none match though, we'll default to the base domain
			$key=0;
			// Try to match the category ID to a domain
			$arr_possible_domains=$this->get_domains_from_category_ids($arr_page_categories);

			if (!in_array($key, $arr_possible_domains, true)){
				$best_match=array_shift($arr_possible_domains);
				if ($best_match) $key=$best_match->id;
			}

		}

		// Compare current url to detected domain and redirect if necessary
		if (isset($this->arr_domains[$key])){
			$obj_domain=$this->arr_domains[$key];
			$domain_match=array_intersect($arr_server_path_options, array($obj_domain->domain_name));
			if (count($domain_match)==0){
				global $query;
				$current_url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
				
				if (!stristr($current_url, $obj_domain->domain_name)){
					$new_url=str_replace($_SERVER['HTTP_HOST'] , $obj_domain->domain_name, $current_url);
					//wp_redirect($new_url, 301);
				}
			}
		}

		$this->current_domain_id=$key;
		return $key;
	}
	
	

	/**
	 * Try to find a configured domain that is linked to a category
	 *
	 * @param array $arr_category_ids
	 * @return array domain objects
	 * @author Eric Shelkie
	 * @since December 16, 2009
	 */
	private function get_domains_from_category_ids(Array $arr_category_ids){
		if (!is_array($arr_category_ids)) $arr_category_ids=array($arr_category_ids);
		$arr_domains=$this->get_domain_array_by_domain_name();
		$arr_return=array();
		
		// Loop through the configured domains and use the first one who's category matches
		foreach($this->arr_domains as $obj_domain){
			$matches=array_intersect((array)$arr_category_ids, (array)$obj_domain->category_id);
			//error_log("matches for " . $obj_domain->domain_name . "(" . implode(", ", (array)$obj_domain->category_id) . ") and " . implode(', ', $arr_category_ids) . " are " . implode(', ', $matches));
			if (count($matches)>0){
				$arr_return[$obj_domain->id]=$obj_domain;				
			}
		}
		return $arr_return;
	}


	/**
	 * Get an array of configured domains with id as key and domain as value
	 *
	 * @author Eric Shelkie
	 * @since November 26, 2009
	 */	
	protected function get_domain_array_by_domain_name(){
		$arr_return=array();
		foreach ($this->arr_domains as $key=>$obj_domain){
			if ($obj_domain instanceof stdClass && isset($obj_domain->domain_name)) {
				$arr_return[$key]=MiniMU::get_token('domain_name', $obj_domain->id);
			}
		}
		return $arr_return;
	}


	/**
	 * Filter to replace url
	 *
	 * @author Eric Shelkie
	 * @since November 26, 2009
	 */
	static function filter_site_url($str_input, $path=null, $scheme=null){
		$dc_instance=MiniMU::get_instance();
		$str_output=$str_input;
		$domain_name=$str_input;
		$obj_options=$dc_instance->get_domain_options();
		//echo "$str_input " . print_r($obj_options,true);
		if (!$obj_options || $obj_options->is_base) return $str_input;
		if ($obj_options instanceof stdClass ){
			//$protocol=(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!='off')?'https://':'http://';
			$old_domain=trim(str_replace($path, '', $str_input),'/ ');
			$old_domain=preg_replace("/http[s]?:\/\//i", '', $old_domain);
			$arr_temp=explode('/', $old_domain);
			$old_domain=$arr_temp[0];
			$new_domain=trim(MiniMU::get_token('domain_name'), '/ ');
			
			$new_url=str_replace($old_domain, $new_domain, $str_input);
			$str_output=$new_url;
		}
		return $str_output;
	}
	
	/**
	 * Filter to content url
	 *
	 * @author Eric Shelkie
	 * @since April 26, 2011
	 */
	static function filter_content_url($str_input, $path){
		$str_output = MiniMu::filter_site_url($str_input, $path, null);
		return $str_output;	
	}


	/**
	 * Filter to replace siteurl
	 *
	 * @author Eric Shelkie
	 * @since November 26, 2009
	 */
	static function filter_home($str_input){
		$dc_instance=MiniMU::get_instance();
		$str_output=$str_input;
		$obj_options=$dc_instance->get_domain_options();
		if ($obj_options->is_base) return $str_input;
		if ($obj_options instanceof stdClass ){
			$protocol=(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!='off')?'https://':'http://';
			$domain_name=MiniMU::get_token('domain_name');
			if ($domain_name) $str_output=$protocol . $domain_name;
		}
		return $str_output;
	}

	/**
	 * Filter to replace Blog title
	 *
	 * @author Eric Shelkie
	 * @since November 26, 2009
	 */
	static function filter_blogname($str_input){
		$dc_instance=MiniMU::get_instance();
		$str_output=$str_input;
		$obj_options=$dc_instance->get_domain_options();
		if ($obj_options->is_base) return $str_input;
		if ($obj_options instanceof stdClass ){
			if (isset($obj_options->blog_title)) $str_output=$obj_options->blog_title;
		}
		return $str_output;
	}
	
	/**
	 * Filter bloginfo calls
	 *
	 * @author Eric Shelkie
	 * @since April 26, 2009
	 */
	/*
	static function filter_bloginfo($value, $key){
		//echo "Filter: $key: $value";
		switch ($key){
			case 'wpurl':
				$value=MiniMU::filter_site_url($value);
				break;
		}
		return $value;	
	}*/
	
	/**
	 * Custom SQL join for the_posts
	 * 
	 * @param string $str_join
	 * @return string @str_join modified
	 * @author Eric Shelkie
	 * @since March 14, 2010
	 */
	static function filter_posts_join( $str_join ) {
		global $wpdb;

		return $str_join . " LEFT JOIN $wpdb->term_relationships as minimu_relationships ON ($wpdb->posts.ID = minimu_relationships.object_id) LEFT JOIN $wpdb->term_taxonomy as minimu_term_taxonomy ON (minimu_relationships.term_taxonomy_id = minimu_term_taxonomy.term_taxonomy_id AND minimu_term_taxonomy.taxonomy='category')";
	}
	
	/**
	 * Custom SQL Group By for the_posts
	 * 
	 * @param string $str_group_by
	 * @return string @$str_group_by modified
	 * @author Eric Shelkie
	 * @since March 14, 2010
	 */
	static function filter_posts_groupby( $str_groupby ) {
		global $wpdb;
		if (!$str_groupby){
			$str_groupby="{$wpdb->posts}.ID";
		}else{
			if (!stristr($wpdb->posts . '.ID', $str_groupby)) $str_groupby="{$str_groupby}, {$wpdb->posts}.ID";
		}
		return $str_groupby;
	}
	
	
	/**
	 * Add post archive filter - should not show posts that are in a different domain/category
	 *
	 * @param string $where clause
	 * @return string $where clause
	 * @author Eric Shelkie
	 * @since March 15, 2010
	 */
	static function filter_posts_where($str_where){
		global $wpdb;
		//echo "filter where: $str_where";
		$str_where_modified=$str_where;
		
		// Get the post type
		preg_match("/{$wpdb->posts}.post_type = '(.*?)'/im", $str_where, $arr_matches);
		if (isset($arr_matches[1])) $post_type = $arr_matches[1];
		
		// Check if this post type uses the 'category' taxonomy
		
		if(MiniMU::post_type_has_categories($post_type)){
			// Don't filter posts if we're in the admin area
			if(!MiniMU::$in_admin){
				$dc_instance=MiniMU::get_instance();
				$obj_options=$dc_instance->get_domain_options();
				$arr_domain_categories=(array) $obj_options->category_id;
	
				//if (count($arr_domain_categories)>0){ // don't show anything if no categories have been specified
					$str_categories=implode(',', $arr_domain_categories);
					if ($str_categories) $str_where_modified.=" AND ((minimu_term_taxonomy.taxonomy = 'category' AND minimu_term_taxonomy.term_id IN ($str_categories))";
					if ($post_type == 'page') $str_where_modified.= " OR (minimu_term_taxonomy.taxonomy IS NULL AND minimu_term_taxonomy.term_id IS NULL)";  // remove this <<<<<
                    if ($str_categories) $str_where_modified.=  ')';
				//}
			}
		}
		return $str_where_modified;
	}
	
	/**
	 * Check if post_type uses the 'category' taxonmy
	 * @param string $post_type
	 * @return boolean
	 * @author Eric Shelkie
	 * @since May 23, 2013
	 */
	static function post_type_has_categories($post_type){
		global $wpdb;
		if (is_string($post_type)){
			$has_posts = $wpdb->get_results("
					SELECT *
					FROM
					$wpdb->posts
					LEFT JOIN
					$wpdb->term_relationships
					ON
					$wpdb->posts.ID=$wpdb->term_relationships.object_id
					LEFT JOIN
					$wpdb->term_taxonomy
					ON
					$wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
					WHERE
					$wpdb->posts.post_type='$post_type' AND $wpdb->term_taxonomy.taxonomy='category'
					LIMIT 1;");
			
			if(count($has_posts)>0) return true;
		}
	}
	
	
	/**
	 * Add prevous_post link join filter - should not show posts that are in a different domain/category
	 *
	 * @param string $join clause
	 * @return string $join clause
	 * @author Eric Shelkie
	 * @since May 5, 2011
	 */
	static function filter_previous_post_join($join, $in_same_cat, $excluded_categories){
		//echo "<br />join: $join";
		if(!MiniMU::$in_admin){
			$arr_domain_categories=(array) MiniMU::get_domain_categories();
			if (!$join && count($arr_domain_categories)>0){
				global $wpdb;
				$join = " INNER JOIN $wpdb->term_relationships AS tr ON p.ID = tr.object_id INNER JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";	
			}
		}
		//echo "<br />after: $join";
		return $join;
	}
	
	/**
	 * Add prevous_post link where filter - should not show posts that are in a different domain/category
	 *
	 * @param string $where clause
	 * @return string $where clause
	 * @author Eric Shelkie
	 * @since May 5, 2011
	 */
	static function filter_previous_post_where($str_where, $in_same_cat, $excluded_categories){
		//echo "<br />before: $str_where";
		if(!MiniMU::$in_admin){
			$arr_domain_categories=(array) MiniMU::get_domain_categories();
			if ( count($arr_domain_categories)>0){
				$str_where .= " AND tt.taxonomy = 'category' AND tt.term_id IN (" . implode($arr_domain_categories, ',') . ')';
			}
		}
		//echo "<br />after: $str_where";
		return $str_where;
	}
	
	
	/**
	 * Filter categories
	 *
	 * @author Eric Shelkie
	 * @since April 10, 2011
	 */
	static function filter_get_terms($arr_categories){
		
		// filter categories if we're not in the admin area
		if (!is_admin()){
			$arr_categories_modified=$arr_categories;
			$dc_instance=MiniMU::get_instance();
	
			if(!is_null($dc_instance->current_domain_id)){
				$obj_options=$dc_instance->get_domain_options();
				foreach($arr_categories as $key=>$obj_category){
					if (!in_array($obj_category->term_id,(array) $obj_options->category_id)){
						unset($arr_categories[$key]);
					}
				}
			}
		}
		
		return $arr_categories;	
	}
	
	
/**
	 * Filter term (category) list
	 *
	 * @param string $where clause
	 * @return string $where clause
	 * @author Eric Shelkie
	 * @since April 27, 2011
	 */
	static function filter_list_terms_exclusions($exclusions, $args){
		global $wpdb;
		
		$str_exclusions=$exclusions;
		//return $str_exclusions;
		// Don't filter categories if we're in the admin area
		if(!MiniMU::$in_admin){
			$dc_instance=MiniMU::get_instance();
			$obj_options=$dc_instance->get_domain_options();
			$arr_domain_categories=(array) $obj_options->category_id;
			
			switch($args['taxonomy']){
				case 'category':
					$str_categories=implode(',', $arr_domain_categories);
					//if (count($arr_domain_categories)>0){ // don't show anyting if no categories have been specified
					$str_exclusions.=" AND t.term_id IN ($str_categories)";
					//}
					break;
					
				case 'post_tag':
					$arr_include_tags=MiniMu::get_category_tag_ids($arr_domain_categories);
					$str_include_tags=implode(',', $arr_include_tags);
					$str_exclusions.=" AND t.term_id IN ($str_include_tags)";
				
				//
				case 'page_tag':
					//$str_exclusions.=" AND t.term_id IN ($str_categories)";
			}
		}
		
		return $str_exclusions;
	}
	
	
	/**
	 * Filter to only show posts for the associated category
	 *
	 * @author Eric Shelkie
	 * @since November 26, 2009
	 */
	/*
	static function the_posts($arr_posts){
		$arr_return=array();
		$dc_instance=MiniMU::get_instance();
		
		// Get the options for the current domain
		$obj_options=$dc_instance->get_domain_options();
		
		// If no category has been assiged to this domain, return all posts
		if (!$obj_options->category_id || MiniMU::$in_admin) return $arr_posts;

		// Check each post to see if it should be included
		foreach ($arr_posts as $post){
			$arr_post_categories=wp_get_post_categories($post->ID);
			if (in_array($obj_options->category_id, $arr_post_categories)) $arr_return[]=$post;
		}
		return $arr_return;
	}
	*/
	
	
	/**
	 * Filter category links
	 * 
	 * @param string $str_link
	 * @param stdClass $int_category
	 * @return string $permalink
	 * 
	 * @author Eric Shelkie
	 * @since December 17, 2009
	 */
	static function filter_category_link($str_link, $int_category=null){
		//$arr_domain=MiniMU::
		$dc_instance=MiniMU::get_instance();
        $new_domain = false;
		//$new_domain=$dc_instance->obj_base_domain->domain_name;
		
		$obj_options=$dc_instance->get_domain_options();
		$arr_domain_categories=(array) $obj_options->category_id;
		//echo "ic: $int_category : " . print_r($arr_domain_categories,true) . "<br />";
		// If this category has not been assigned to this domain, link to the other domain instead
		if ($int_category && (!in_array($int_category, $arr_domain_categories))){
			$arr_domains=$dc_instance->get_domains_from_category_ids((array)$int_category);
			if (count($arr_domains)>0){
				$obj_new_domain=array_shift($arr_domains);
				$new_domain=$obj_new_domain->domain_name;
			}
		}
		//echo "filter: " . htmlspecialchars($str_link);
		//echo "$str_link $int_category - new: $new_domain<br />";
		if ($new_domain && !strstr($str_link, $new_domain)){
			//echo "siteURL: " . site_url() . "<br />";
			$old_domain=preg_replace("/http[s]?:\/\//",'',site_url());
			$new_link=str_replace($old_domain, $new_domain, $str_link);
			//echo "rewrite link: $str_link to $new_link -- $old_domain > " . $obj_domain->domain_name .  "<br />";
			$str_link=$new_link;
		}
		return $str_link;
	}
	
	
	/**
	 * Filter post links
	 * 
	 * @param string $permalink
	 * @param stdClass $post
	 * @param string $leavename
	 * @return string $permalink
	 * 
	 * @author Eric Shelkie
	 * @since December 19, 2009
	 */
	static function filter_post_link($permalink, $post=null, $leavename=null){

		return $permalink;
		// 05/05/2011 - disabled this. Don't think it's necessary, as we're now only showing posts in current domain
		/*
		if (is_admin()){
			$arr_category_ids=array();
			$dc_instance=MiniMU::get_instance();
			if ($post instanceof stdClass){
				$arr_post_categories=get_the_category($post->ID);
				$match=array_shift($arr_post_categories);
				//print_r($match);
				echo "match:" . $match->cat_ID . "<br />";
				$permalink=$dc_instance->filter_category_link($permalink, $match->cat_ID);
				/*foreach($arr_post_categories as $obj_category) $arr_category_ids[]=$obj_category->cat_ID;
	
				// If the post belongs to the current category, then don't change anything
				//if (!in_array(the_category_ID(false), $arr_category_ids)){
				echo "<br />link before: $permalink";
				if (count(array_intersect($arr_category_ids, MiniMU::get_domain_categories()))){
					
					// otherwise, see if the post belongs to a configured category
					$match=null;
					foreach ($dc_instance->arr_domains as $obj_domain){
						if (($match=array_search($obj_domain->category_id, $arr_category_ids))!==false){
							$match=$arr_category_ids[$match];
							break;
						}
					}
					echo "match:" . $match;
					// Transform the link to reference to correct category
					$permalink=$dc_instance->filter_category_link($permalink, $match);
				}
			}
		}
		return $permalink;*/
	}
	
	/**
	 * Filter comments
	 * 
	 * @author Eric Shelkie
	 * @since January 26, 2012
	 */
	static function filter_comments($arr_args){
		global $wpdb;
		$str_where_modified=(isset($arr_args['where'])?$arr_args['where']:'');
		$str_join_modified=(isset($arr_args['join'])?$arr_args['join']:'');
		// Don't filter if we're in the admin area
		if(!MiniMU::$in_admin){
			
			$str_join_modified.= " INNER JOIN $wpdb->term_relationships as minimu_relationships ON ($wpdb->comments.comment_post_ID = minimu_relationships.object_id) INNER JOIN $wpdb->term_taxonomy as minimu_term_taxonomy ON (minimu_relationships.term_taxonomy_id = minimu_term_taxonomy.term_taxonomy_id)";
			
			$dc_instance=MiniMU::get_instance();
			$obj_options=$dc_instance->get_domain_options();
			$arr_domain_categories=(array) $obj_options->category_id;
			
			if (count($arr_domain_categories)>0){
				$str_categories=implode(',', $arr_domain_categories);
				$str_where_modified.=" AND minimu_term_taxonomy.taxonomy = 'category' AND minimu_term_taxonomy.term_id IN ($str_categories)";
			}
		}

		$arr_args['join']=$str_join_modified;
		$arr_args['where']=$str_where_modified;
		return $arr_args;
	}

	/**
	 * Filter to replace Tagline
	 *
	 * @author Eric Shelkie
	 * @since November 26, 2009
	 */
	static function filter_blogdescription($str_input){
		$dc_instance=MiniMU::get_instance();
		$str_output=$str_input;
		$obj_options=$dc_instance->get_domain_options();
		if ($obj_options->is_base) return $str_input;
		if ($obj_options instanceof stdClass ){
			if (isset($obj_options->tagline)) $str_output=$obj_options->tagline;
		}
		return $str_output;
	}

	/**
	 * Filter to change template
	 *
	 * @author Eric Shelkie
	 * @since November 26, 2009
	 */
	static function filter_theme($str_input){
		$dc_instance=MiniMU::get_instance();
		$str_output=$str_input;
		$obj_options=$dc_instance->get_domain_options();
		if ($obj_options->is_base) return $str_input;
		if ($obj_options instanceof stdClass ){
			if (isset($obj_options->theme_name)){
				$arr_themes=$dc_instance->get_themes_array();
				if (isset($arr_themes[$obj_options->theme_name])){
					$str_output=$arr_themes[$obj_options->theme_name]->Template;
				}
			}
		}
		return $str_output;
	}
	
/**
	 * Filter to change template
	 *
	 * @author Eric Shelkie
	 * @since November 26, 2009
	 */
	static function filter_stylesheet($str_input){
		$dc_instance=MiniMU::get_instance();
		$str_output=$str_input;
		$obj_options=$dc_instance->get_domain_options();
		if ($obj_options->is_base) return $str_input;
		if ($obj_options instanceof stdClass ){
			if (isset($obj_options->theme_name)){
				$arr_themes=$dc_instance->get_themes_array();
				if (isset($arr_themes[$obj_options->theme_name])){
					$str_output=$arr_themes[$obj_options->theme_name]->Stylesheet;
				}
			}
		}
		return $str_output;
	}
	
	
	/**
	 * Add the category slug to the body class so we can style categories more easily
	 *
	 * @param array body classes
	 * @return array modified body class
	 * @author Eric Shelkie
	 * @since November 30, 2009
	 */
	static function filter_body_class($arr_body_classes){
		$dc_instance=MiniMU::get_instance();
		
		$str_slug=MiniMU::get_domain_slug();
		if ($str_slug ){
			if (!in_array($str_slug, $arr_body_classes)) $arr_body_classes[]=$str_slug;
		}
		return $arr_body_classes;
	}
	
	
	/**
	 * Custom SQL join for getarchives
	 * 
	 * @param string $str_join
	 * @return string @str_join modified
	 * @author Eric Shelkie
	 * @since March 14, 2010
	 */
	static function filter_getarchives_join( $str_join ) {
		global $wpdb;
		return $str_join . " INNER JOIN $wpdb->term_relationships as minimu_relationships ON ($wpdb->posts.ID = minimu_relationships.object_id) INNER JOIN $wpdb->term_taxonomy as minimu_term_taxonomy ON (minimu_relationships.term_taxonomy_id = minimu_term_taxonomy.term_taxonomy_id)";
	}
	
	/**
	 * Add post archive filter - should not show posts that are in a different domain/category
	 *
	 * @param string $where clause
	 * @return string $where clause
	 * @author Eric Shelkie
	 * @since March 15, 2010
	 */
	static function filter_getarchives_where($str_where){
		global $wpdb;
		
		//echo "filter where: $str_where";
		$str_where_modified=$str_where;
		
		// don't modify where clause if for page query
		if (stristr($str_where, "{$wpdb->posts}.post_type = 'page'")) return $str_where;
		
		// Don't filter posts if we're in the admin area
		if(!MiniMU::$in_admin){
			$dc_instance=MiniMU::get_instance();
			$obj_options=$dc_instance->get_domain_options();
			$arr_domain_categories=(array) $obj_options->category_id;
			
			if (count($arr_domain_categories)>0){
				$str_categories=implode(',', $arr_domain_categories);
				$str_where_modified.=" AND minimu_term_taxonomy.taxonomy = 'category' AND minimu_term_taxonomy.term_id IN ($str_categories)";
			}
		}
		
		return $str_where_modified;
	}
	
	/**
	 * Get an array of tag ids that appear in specified categories
	 * @param array $arr_categories
	 * @return array $arr_tag_ids
	 * @author john.andrews
	 * @link http://wordpress.org/support/topic/get-tags-specific-to-category?replies=38
	 */
	static function get_category_tag_ids($arr_categories){
		global $wpdb;
		$arr_tag_ids=array();
		if (is_array($arr_categories) && count($arr_categories)>0){
			$tags = $wpdb->get_results("
				SELECT DISTINCT terms2.term_id as tag_id, terms2.name as tag_name, null as tag_link
				FROM
					$wpdb->posts as p1
					LEFT JOIN $wpdb->term_relationships as r1 ON p1.ID = r1.object_ID
					LEFT JOIN $wpdb->term_taxonomy as t1 ON r1.term_taxonomy_id = t1.term_taxonomy_id
					LEFT JOIN $wpdb->terms as terms1 ON t1.term_id = terms1.term_id,
		
					$wpdb->posts as p2
					LEFT JOIN $wpdb->term_relationships as r2 ON p2.ID = r2.object_ID
					LEFT JOIN $wpdb->term_taxonomy as t2 ON r2.term_taxonomy_id = t2.term_taxonomy_id
					LEFT JOIN $wpdb->terms as terms2 ON t2.term_id = terms2.term_id
				WHERE
					t1.taxonomy = 'category' AND p1.post_status = 'publish' AND terms1.term_id IN (".implode(',',$arr_categories).") AND
					t2.taxonomy = 'post_tag' AND p2.post_status = 'publish'
					AND p1.ID = p2.ID
			");
			foreach ($tags as $tag) {
				$arr_tag_ids[]=$tag->tag_id;
			}
		}
		return $arr_tag_ids;
	}



	/**
	 * Add the admin menu items
	 *
	 * @author Eric Shelkie
	 * @since November 22, 2009
	 */
	static function add_admin_menu(){
		if ( is_admin() ){ // admin actions
			add_options_page('MiniMU options', 'MiniMU', 'administrator', 'wp-minimu-admin-main', 'MiniMU::options_page');
		} else {
		  // non-admin enqueues, actions, and filters
		}
	}


	/**
	 * Initialize Admin area
	 *
	 * @author Eric Shelkie
	 * @since November 22, 2009
	 */
	static function admin_init(){
		$dc_instance=MiniMU::get_instance();

		
		MiniMu::$in_admin=true;
		// Check if we're in the admin area and redirect to the base domain if necessary
		$site_url=preg_replace("/http[s]?\:\/\//i", '', $dc_instance->base_domain);
		$protocol=(is_ssl())?"https://":"http://";
		//echo "redirect to: " . $site_url . $_SERVER['REQUEST_URI'];
		if (!stristr($site_url, $_SERVER['SERVER_NAME']) &&
            (isset($_SERVER['HTTP_HOST']) && !stristr( $site_url, $_SERVER['HTTP_HOST'])) &&
            !stristr($_SERVER['REQUEST_URI'], 'admin-ajax.php')
        ){
            wp_redirect($protocol . $site_url . $_SERVER['REQUEST_URI']);
            exit();
		}

		$dc_instance=MiniMU::get_instance();
		$arr_categories=$dc_instance->get_categories_array();
		$arr_themes=$dc_instance->get_themes_array();

		add_action('admin_head', 'MiniMU::minimu_head');
		add_action('wp_ajax_minimu_add_domain_click', 'MiniMU::add_domain_click');
		wp_enqueue_script( array("jquery", "jquery-ui-core", "interface", "jquery-ui-sortable", "wp-lists", "jquery-ui-sortable", "jquery-ui-dialog") );
		wp_register_style( 'jquery-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.1/themes/smoothness/jquery-ui.css', true);
		wp_enqueue_style( 'jquery-style' );
		
		if (isset($_POST['minimu-action'])){

			switch($_POST['minimu-action']){

				case 'update':
					$dc_instance->save_options();
					break;
			}
		}
	}


	/**
	 * Add header JavaScript
	 *
	 * @author Eric Shelkie
	 * @since November 24, 2009
	 */
	static function minimu_head() {
		$arrCustomVarNames=MiniMU::get_custom_variable_names();
		?>
		<script type="text/javascript">
			var minimu_custom_var_names=Array(<?php if(count($arrCustomVarNames)>0) echo '"' . implode('","', $arrCustomVarNames) . '"';?>);
		
			function minimu_post_ajax(data){
				var ajax_url = '<?php echo admin_url("admin-ajax.php"); ?>';

				jQuery.post(ajax_url, data, function(str_response) {
					eval("response=" + str_response);
					if (response.command){
						eval(response.command + "(response.args)");
					}
				});
			}

			function minimu_add_empty_domain(args){
				jQuery(".minimu-domain-container:last").after(args);
				smpSortableInit();
				minimu_attach_events();
			}
			
			function minimu_add_var(obj_domain){
				var new_varname=prompt("New token name:");
				if (new_varname){
					var invalid=new_varname.match(/[^a-zA-Z\-\_]/);
					new_varname=new_varname.substr(0, <?php echo MiniMU::MAX_CUSTOM_VAR_NAME_LENGTH;?>);
					if (new_varname.length<1) invalid=true;
				 	if (invalid){
				 		alert("Invalid name! Please use a-z, A-Z, underscore, dash");
				 		return minimu_add_var(obj_domain);
				 		
				 	}else if(jQuery.inArray(new_varname.toLowerCase(),  jQuery.map(minimu_custom_var_names, function(item){return item.toLowerCase();}))>=0){
						alert("That token name is already used!");
						return minimu_add_var(obj_domain);	
				 	}else{
				 		minimu_insert_var(obj_domain, new_varname);
				 		minimu_custom_var_names.push(new_varname);
				 		minimu_attach_events();
					}
				 }
			}
			
			
			function minimu_insert_var(obj_domain, var_name){
				id_match=obj_domain.attr('id').match(/\-(\d*)$/);
				domain_id=id_match[1];
				if (domain_id){
					obj_domain.find(".minimu-add-var-row").before('<tr class="minimu-var-row"><td class="c0"><a class="remove" href="Javascript:{}">X</a></td><td class="c1">' + var_name + '</td><td class="c2"><textarea name="minimu-custom-vars[' + domain_id + '][' + var_name + ']" class="minimu-var-textarea"></textarea></td></tr>');
					minimu_attach_events();
				}
			}
			
		 
			var smpSortable;
			function smpSortableInit() {
				  try { // a hack to make sortables work in jQuery 1.2+ and IE7
				   jQuery('#sortcontainer').sortable('destroy');
				  } catch(e) {}
				  smpSortable = jQuery('#sortcontainer').sortable( {
				  accept: 'sortable',
				  onStop: smpSortableInit,
				  handle: '.sortable-handle',
				  axis: 'vertical'
				  } );
			 }

			 // Add delete domain button event
			function minimu_delete_domain(domain_id){
				var confirmed=confirm("Are you sure you want to remove this domain?");
				if (confirmed){
					jQuery("#minimu-tr-" + domain_id).fadeOut(function(){
						jQuery(this).remove();
					});
					jQuery("#minimu-delete").attr("value", jQuery("#minimu-delete").attr("value") + "," + domain_id);
				}
			 }
		 

			 function minimu_attach_events(){
				 // Show delete variable button on hover
				 jQuery(".minimu-domain-container .minimu-var-row").unbind("hover");
				 jQuery(".minimu-domain-container .minimu-var-row").hover(function(){
					jQuery(this).find(".remove").show();
				 },function(){
					 jQuery(this).find(".remove").hide();
				 });

				 // Add delete variable button event
				 jQuery(".minimu-domain-container .minimu-var-row .c0 .remove").unbind("click");
				 jQuery(".minimu-domain-container .minimu-var-row .c0 .remove").click(function(){
					var confirmed=confirm("Are you sure you want to remove this value?");
					if (confirmed){
						jQuery(this).parents(".minimu-var-row").fadeOut(function(){
							jQuery(this).remove();
						});
					}
				 });


				// Custom event that updates select options
				 jQuery('.minimu-add-var-select').bind('updateOptions', function(){
					var obj_select=jQuery(this);
					obj_select.children().remove();
				 	var output = Array('<option value="" >Select...</option>');
				 	jQuery.each(minimu_custom_var_names, function(key, value){
						if (obj_select.parents(".minimu-domain-container").find("[name$='][" + value + "]']").length==0){
				 	  		output.push('<option value="'+ value +'">'+ value +'</option>');
						}
				 	});
				 	output.push('<option value="new" >Add new...</option>');
				 	obj_select.get(0).innerHTML = output.join('');
				 });

				 jQuery(".minimu-add-var-link").unbind("click");
				 jQuery(".minimu-add-var-link").click(function(e){
					 	var obj_select=jQuery(this).siblings('.minimu-add-var-select');
					 	var obj_domain=jQuery(this).parents(".minimu-domain-container");
					 	var val;
					 	if (obj_select.children().length==1){ // not using obj_select.length because it seems to be broken in jQuery 1.6.1
					 		// Add new variable
					 		minimu_add_var(obj_domain);
					 	}else{
						 	jQuery(this).hide();
						 	obj_select.trigger('updateOptions');
						 	obj_select.show();
						}
					 });
					 
					 /**
					  * Select a custom variable, or add a new one
					  *
					  */
					 jQuery(".minimu-add-var-select").unbind("change");
					 jQuery(".minimu-add-var-select").change(function(e){
					 	var valid=false;
					 	var new_varname;
					 	var select=jQuery(this);
					 	var obj_domain=jQuery(this).parents(".minimu-domain-container");
					 	var val=select.val();
					 	
					 	select.hide();
					 	select.attr("selectedIndex", 0);
					 	select.siblings('.minimu-add-var-link').show();
					 	if (val){
					 		if (val=='new'){
					 			minimu_add_var(obj_domain);
					 		}else{
					 			minimu_insert_var(obj_domain, val);
					 		}
					 	}

					 });

					 jQuery(".minimu-add-var-select").unbind("blur");
					 jQuery(".minimu-add-var-select").blur(function(e){
					 	jQuery(this).hide();
					 	jQuery(this).siblings('.minimu-add-var-link').show();
					 });

					 jQuery(".minimu-show-categories").unbind("click");
					 jQuery(".minimu-show-categories").click(function(){
						 var domain_id=jQuery(this).closest('.minimu-domain-container').attr('id').replace('minimu\-tr\-','');
						 var categories_input=jQuery('#minimu-catselect-' + domain_id);
						 if (categories_input.length>0){
							 categories_input=categories_input[0];
							 var categories_array=categories_input.value.split(',');

							 // clear category checkboxes
							 jQuery("#minimu-categories-modal input:checkbox").attr('checked', false);

							// Mark the currently selected categories
							for(x=0;x<categories_array.length;x++){
								 jQuery("#minimu-category-select-" + categories_array[x]).attr('checked',true);
							 }
							jQuery("#minimu-categories-modal").dialog({
								modal:true,
								title: 'Select categories to include',
								buttons:{
									'Done':function(){
										var arr_checked=[];
										jQuery("#minimu-categories-modal input:checkbox").each(function(){
											if(jQuery(this).attr('checked')){
												var category_id=jQuery(this).attr('id').replace("minimu-category-select-",'');
												arr_checked.push(category_id);
											}
										});
										categories_input.value=arr_checked.join(',');
										jQuery(this).dialog('close');
									}
								}
							});
						 }else{
							alert("Error: category data not found");
									
						 }
					 });
					 
			 }
		 
		
			jQuery(document).ready(function(){
				 // initialize sortable
				 smpSortableInit();

				 minimu_attach_events();
				 
			});
			
		</script>
		<style type="text/css">
		<!--
			.minimu-domain-container{
				width: 500px;
			}
			.minimu-domain-container input, .minimu-domain-container select{
				width: 340px;
			}
			
			.minimu-domain-container select {
				font-size:12px !important;
				height:2em;
				padding:2px;
			}
			
			.minimu-domain-container td.c0{
				width: 25px;
				vertical-align: top;
				text-align: left;
			}
			
			.minimu-domain-container td.c0 a.remove{
				float: left;
				width: 1em;
				height: 1em;
				margin: 5px 3px 0 3px;
				padding: 2px 0px 2px 2px;
				line-height: 1em;
				border: 1px solid #999;
				background-color: #999;
				text-decoration: none;
				color: #fff;
				font-weight: bold;
				font-size: 0.6em;
			}
			
			.minimu-domain-container td.c0 a.remove:hover{
				text-decoration: none;
			}
			
			.minimu-domain-container .minimu-var-row a.remove{
				display: none;
			}
			
			
			.minimu-domain-container td.c1{
				width: 100px;
				vertical-align: top;
				padding: 0.4em 2px 0 10px;
				text-align: right;
			}
			
			.minimu-domain-container td.c2{
				width: 340px;
			}
			
			.minimu-domain-container td.c3{
				width: 10px;
			}
			
			.minimu-domain-container td.category{
				font-size: 0.9em;
				font-style: italic;
				padding-top: 0.4em;
				padding-bottom: 3px;
			}
			
			.minimu-domain-container td.error{
				background-color: #FFEBE8;
			}
			
			.minimu-var-textarea{
				width: 340px;
			}
			
			select.minimu-add-var-select{
				width: 100px;
				display: none;
			}
			
			.sortable-handle{
				width: 15px;
				background-color: #ccc;
				cursor: move;
			}
			
			.wp-list-table .column-minimu_domain{
				white-space: nowrap;
				width: 25%;
			}
			
			#minimu-categories-modal{
				display: none;
			}
			
			#minimu-categories-modal ul ul{
				margin-left: 20px;
			}
			
			#minimu-categories-modal li input{
				margin-right: 4px;
			}
 

		-->
		</style>
		<?php

	}


	/**
	 * Save options
	 *
	 * @return void
	 * @author Eric Shelkie
	 * @since November 22, 2009
	 */
	protected function save_options(){
		$has_errors=false;
		if (is_admin()){
			if (isset($_POST['minimu-domain-name']) && is_array($_POST['minimu-domain-name'])){
				$arr_raw_domains=MiniMU::get_domain_objects_from_post();
				//$this->arr_domains=array();

				foreach ($arr_raw_domains as $key=>$obj_domain){
					$obj_validated=null;
					$is_base=false;
					if (isset($obj_domain->is_base) && $obj_domain->is_base) $is_base=true;
					//print_r($obj_domain);
					// use the category ids from the existing domain object
					//if (isset($this->arr_domains[$key])) $obj_domain->category_id=$this->arr_domains[$key]->category_id;
					
					$obj_validated=MiniMU::validate_domain($obj_domain);
					$obj_domain_safe=$obj_validated->sanitized;

					$this->arr_domains[$key]=$obj_domain_safe;
					if ($obj_validated instanceof stdClass && count((array)$obj_validated->errors)>0) $has_errors=true;

					//echo "save: $key - " . $obj_domain_safe->domain_name;
				}
				
				// See if domains should be removed
				if (isset($_POST['minimu-delete'])){
					$arr_delete=explode(',',trim($_POST['minimu-delete'], ', '));
					foreach($arr_delete as $delete_id){
						if ($delete_id) unset($this->arr_domains[$delete_id]);
					}
				}

				// If there are no errors, go ahead and save
				if (!$has_errors){
					$this->arr_plugin_options['arr_domains']=$this->arr_domains;
					$this->arr_plugin_options['version']=MiniMU::VERSION;
					update_option(MiniMU::WP_OPTION_KEY, $this->arr_plugin_options);
					$this->bln_settings_saved=true;
				}
			}
		}
		// Clear domain array
		//update_option('minimu_arr_domains', array());
	}


	/**
	 * Extract domain objects from $_POST
	 *
	 * @return array Domain objects
	 * @author Eric Shelkie
	 * @since November 22, 2009
	 */
	protected function get_domain_objects_from_post(){
		$dc_instance=MiniMU::get_instance();
		$arr_raw_domains=false;
		if (is_admin()){
			
			$arr_raw_domains=array();
			if (isset($_POST['minimu-domain-name']) && is_array($_POST['minimu-domain-name'])){
				foreach ($_POST['minimu-domain-default'] as $key=>$is_default){
					$obj_domain=new stdClass();
					
					if ($is_default=='true'){
						//echo "Add default: " . print_r($dc_instance->obj_base_domain);
						$domain_id=0;
						$obj_domain=clone $dc_instance->obj_base_domain;
					}else{
						// if key==new, we're adding a new domain, so get the next free domain_id
						if (stristr($key, "new-")){
							if (count($dc_instance->arr_domains)>0){
								$domain_id=max(array_keys($dc_instance->arr_domains))+1;
								// Add an empty placeholder
								$dc_instance->arr_domains[$domain_id]=new stdClass();
							}else{
								$domain_id=1;
							}
						}else{
							$domain_id=(int)$key;
							if (isset($dc_instance->arr_domains[$domain_id])) $obj_domain=clone $dc_instance->arr_domains[$domain_id];
						}
					}
					
					// Add domain to the list
					$obj_domain->id=$domain_id;

					// Get form values and assign to object properties
					foreach($dc_instance->arr_values as $arr_value){
						$value=null;
						if (isset($_POST[$arr_value['input_name']][$key])){
							$value=stripslashes_deep($_POST[$arr_value['input_name']][$key]);
							
							// Get custom variables
							if ($arr_value['property_name']=='custom_vars'){
								if (is_array($value)){
									foreach($value as $custom_var_name=>$custom_var_value){
										$key=trim(substr(MiniMU::sanitize_varname($custom_var_name), 0, MiniMU::MAX_CUSTOM_VAR_NAME_LENGTH));
										if ($key){
											$obj_value=new stdClass();
											$obj_value->value=$custom_var_value;
											$obj_value->type='textarea'; // might add more types
											$value[$key]=$obj_value;
										}
									}
								}
							}
						}
						
						if ($arr_value['property_name']=='category_id') $value=explode(',',$value);

						$obj_domain->$arr_value['property_name']=$value;
						//error_log("set " . $arr_value['property_name'] . " to: " . $value);
					}
	
					// If this is the default domain, merge in the correct settings
					if ($is_default=='true'){
						$arr_base_domain=(array)$dc_instance->obj_base_domain;
						unset($arr_base_domain['category_id']);
						$obj_domain=(object)array_merge((array)$obj_domain, $arr_base_domain);
						$arr_raw_domains[$domain_id]=$obj_domain;
					}
					$arr_raw_domains[$domain_id]=$obj_domain;
				}
			}
		}
		return $arr_raw_domains;
	}


	/**
	 * Validate a domain object
	 *
	 * @param stdClass Domain object
	 * @return stdClass Two objects - sanitized values, errors
	 * @author Eric Shelkie
	 * @since November 24, 2009
	 */
	static function validate_domain($obj_domain){
		global $allowedposttags;
		//print_r($obj_domain);
		$obj_existing=null;
		$dc_instance=MiniMU::get_instance();
		$obj_errors=new stdClass();
		$obj_sanitized=new stdClass();
		$obj_return=new stdClass();
		$obj_return->sanitized=$obj_sanitized;
		$obj_return->errors=$obj_errors;

		//error_log("original{$obj_domain->id}: " . print_r($dc_instance->arr_domains[$obj_domain->id],true));
		// Check if we're updating a pre-exsting domain
		if (isset($dc_instance->arr_domains[$obj_domain->id])){
			$obj_domain=(object)array_merge((array)$dc_instance->arr_domains[$obj_domain->id],(array)$obj_domain);
		}
		
		// ID
		$obj_sanitized->id=intval($obj_domain->id);
		unset($obj_domain->id);
		
		// Is this the base domain?
		$obj_sanitized->is_base=(isset($obj_domain->is_base) && $obj_domain->is_base===true)?true:false;

		// Domain name
		if (trim($obj_domain->domain_name)!=''){
			// limit length
			$obj_domain->domain_name=substr($obj_domain->domain_name, 0, 100);
			if (preg_match("/^localhost/i", $obj_domain->domain_name)){
				
				$obj_sanitized->domain_name=$obj_domain->domain_name;
			}else{
				preg_match("/^([a-z0-9]([a-z0-9\-]*\.)+([a-z]{2}|aero|arpa|biz|com|coop|edu|gov|info|int|jobs|mil|museum|name|nato|net|org|pro|travel)|(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]))(\/[a-z0-9_\-\.~]+)*(\/([a-z0-9_\-\.]*)(\?)?)?$/", $obj_domain->domain_name, $arr_matches);
				//echo "check: " . $obj_domain->domain_name . print_r($arr_matches, true);
				if (isset($arr_matches[1])){
					$obj_sanitized->domain_name=trim($arr_matches[1]);
				}else{
					$clean_url=clean_url($obj_domain->domain_name);
					$clean_url=preg_replace('/^http(s)?\:\/\//','',$clean_url);
					$obj_sanitized->domain_name=$clean_url;
					$obj_errors->domain_name=array('message'=>'Please enter a valid domain name (eg. www.mydomain.com)');
	
				}
			}
		}else{
			$obj_sanitized->domain_name='';
			//$obj_errors->domain_name=array('message'=>'Domain name is required (eg. www.mydomain.com)');
		}
		unset($obj_domain->domain_name);

		$obj_sanitized->category_id=$obj_domain->category_id;
		unset($obj_domain->category_id);


		// Theme
		if (in_array($obj_domain->theme_name, array_keys($dc_instance->arr_themes))){
			$obj_sanitized->theme_name=$obj_domain->theme_name;
		}else{
			$obj_sanitized->theme_name='wordpress-default';
		}
		unset($obj_domain->theme_name);


		// Blog Title
		$obj_sanitized->blog_title=substr(wp_kses($obj_domain->blog_title, null), 0, 256);
		if ($obj_sanitized->blog_title=='') $obj_sanitized->blog_title=get_bloginfo( 'name' );
		unset($obj_domain->blog_title);


		// Tagline
		$obj_sanitized->tagline=substr(esc_html($obj_domain->tagline), 0, 512);
		unset($obj_domain->tagline);

		// Custom variables
		if (isset($obj_domain->custom_vars) && is_array($obj_domain->custom_vars) && count($obj_domain->custom_vars)>0){
			$obj_sanitized->custom_vars=array();
			foreach($obj_domain->custom_vars as $key=>$obj_value){
				// check custom variable name
				$key=trim(substr(MiniMU::sanitize_varname($key), 0, MiniMU::MAX_CUSTOM_VAR_NAME_LENGTH));
				if ($key){
					// sanitize custom variable value
					$obj_value_clean=new stdClass();
					$value=$obj_value->value;
					$value=wp_kses($value, $allowedposttags);
					if ($value){
						$obj_value_clean->value=$value;
						$obj_value_clean->type='textarea';
						$obj_sanitized->custom_vars[$key]=$obj_value_clean;
					}
				}	
			}
		}
		//echo "clean: " . print_r($obj_return,true);
		return $obj_return;
	}


	/**
	 * Add domain button handler
	 *
	 * @author Eric Shelkie
	 * @since November 24, 2009
	 */
	static function add_domain_click(){
		$dc_instance=MiniMU::get_instance();

		$new_domain_id=0;
		if (count($dc_instance->arr_domains)>0){
			$new_domain_id=max(array_keys($dc_instance->arr_domains))+1;
		}
		$new_domain=new stdClass();
		$new_domain->id=$new_domain_id;
		$new_domain->domain_name='';
		$new_domain->category_id=array();
		$new_domain->theme_name='wordpress-default';
		$new_domain->is_base=false;

		$dc_instance->arr_domains[$new_domain_id]=$new_domain;

		$obj_temp=new stdClass();
		$obj_temp->sanitized=MiniMU::get_empty_domain_object();
		$obj_temp->errors=new stdClass();
		$str_empty_domain=MiniMU::get_domain_row_html($obj_temp);
		echo json_encode(array("command"=>'minimu_add_empty_domain', 'args'=>$str_empty_domain));
		exit();
	}



	/**
	 * Get a default domain object;
	 *
	 * @return stdObject
	 * @author Eric Shelkie
	 * @since November 24, 2009
	 */
	static function	get_empty_domain_object(){
		$obj_empty_domain=new stdClass();
		$obj_empty_domain->is_base=false;
		$obj_empty_domain->id='new-' . time();
		$obj_empty_domain->domain_name='';
		$obj_empty_domain->category_id=array();
		$obj_empty_domain->theme_name='wordpress-default';
		$obj_empty_domain->blog_title=get_bloginfo( 'name' );
		$obj_empty_domain->tagline=get_bloginfo( 'description' );
		return $obj_empty_domain;
	}
	
	
	/**
	 * Validate variable name.
	 *
	 * @param string
	 * @return string
	 * @author Eric Shelkie
	 * @since December 1, 2009
	 */
	static function sanitize_varname($str_input){
		$str_output=preg_replace("/[^a-zA-Z0-9\-\_]/", '', $str_input);
		return $str_output;	
	}


	/**
	 * Generate the admin Menus
	 *
	 * @author Eric Shelkie
	 * @since November 22, 2009
	 */
	static function options_page(){
		$dc_instance=MiniMU::get_instance();

		?>
		<div class="wrap">
		<h2>MiniMU</h2>
		<?php if ($dc_instance->bln_settings_saved){ ?>
		<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>Settings saved.</strong></p></div>
		<?php } ?>
		<form id="minimu-options-form" method="post" action="">


		<?php

		// Populate existing domain fields
		echo '<div id="sortcontainer">' . "\n";
		if (is_array($dc_instance->arr_domains)){
			foreach ($dc_instance->arr_domains as $obj_domain){
				//echo "check: " . print_R($obj_domain);
				$enabled=true;
				if ($obj_domain instanceof stdClass ){
					$obj_validated=MiniMU::validate_domain(clone $obj_domain);
					if (isset($obj_domain->is_base) && $obj_domain->is_base) $enabled=false;
					echo MiniMU::get_domain_row_html($obj_validated, array('disabled'=>!$enabled));
				}
			}
		}
		echo "</div>";




		$obj_temp=new stdClass();
		$obj_temp->sanitized=MiniMU::get_empty_domain_object();
		$obj_temp->errors=new stdClass();
		if (count($dc_instance->arr_domains)==0) echo MiniMU::get_domain_row_html($obj_temp);
		?>

		<input type="button" class="secondary-button" value="Add another domain" onclick="minimu_post_ajax({action:'minimu_add_domain_click'})"/>


		<?php /* <input type="hidden" name="action" value="update" />
		<input type="hidden" name="page_options" value="new_option_name,some_other_option,option_etc" />*/?>
		<input type="hidden" name="minimu-delete" id="minimu-delete" value="" />

		<p class="submit">
		<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
		</p>
		<?php //settings_fields( 'minimu-option-group' ); ?>
		<input type="hidden" id="minimu-action" name="minimu-action" value="update" />
		<input type="hidden" id="minimu-option-value" name="minimu-value" value="update" />
		<div id="minimu-categories-modal">
		<?php echo MiniMU::get_category_html();?>
		</div>
		</form>
		</div>
		<?php
	}
	
	static function get_category_html($parent=0, $depth=0){
		$str_html="";
		//$arr_categories=MiniMU::get_categories_array($parent);
		$arr_categories=get_categories(array(
			'type'                     => 'post',
			'orderby'                  => 'name',
			'order'                    => 'ASC',
			'hide_empty'			   => false,
			'hierarchical'				=> false,
			'parent'					=> $parent
			)
		);
		if (count($arr_categories)>0){
			$str_html.="<ul>";
			//print_r($arr_categories);
			foreach($arr_categories as $obj_category){
				$str_html.="<li><input type='checkbox' id='minimu-category-select-{$obj_category->term_id}' name='minimu-category-select-{$obj_category->term_id}' />" . esc_html($obj_category->name);
				$str_children=MiniMU::get_category_html($obj_category->term_id, $depth+1);
				$str_html.=$str_children;
				$str_html.="</li>\n";
			}
			$str_html.="</ul>";
		}
		return $str_html;
	}


	/**
	 * Get HTML for a single domain
	 *
	 * @param stdClass Domain data
	 * @param array $args
	 * @return string HTML
	 * @author Eric Shelkie
	 * @since November 23, 2009
	 */
	static function get_domain_row_html($obj_data, $args=array()){
		global $allowedposttags;
		$dc_instance=MiniMU::get_instance();
		$obj_domain=$obj_data->sanitized;
		$obj_error=$obj_data->errors;
		$disabled=false;
		$is_base=false;
		if (isset($args['disabled'])) $disabled=$args['disabled'];
		if (isset($obj_domain->is_base) ) $is_base=$obj_domain->is_base;


		
		ob_start();

		?>
		<input type="hidden" id="minimu-catselect-<?php echo $obj_domain->id?>" name="minimu-catselect[<?php echo $obj_domain->id?>]" value="<?php echo implode(',',(array)$obj_domain->category_id)?>" />
		<table id="minimu-tr-<?php echo $obj_domain->id;?>" class="minimu-domain-container sortable widget">
			<tr>
				<td class="c0"><?php if (!$is_base){?><a class="remove" href="Javascript:{}" onclick="minimu_delete_domain('<?php echo $obj_domain->id;?>')">X</a><?php } ?></td>
				<td class="c1<?php if (isset($obj_error->domain_name)) echo ' error' ?>">Domain</td>
				<td class="c2<?php if (isset($obj_error->domain_name)) echo ' error' ?>">
					<?php if ($disabled){ ?>
						<input type="text" value="<?php echo esc_attr($obj_domain->domain_name);?>" disabled="disabled" />
						<input type="hidden" name="minimu-domain-name[<?php echo $obj_domain->id ?>]" value="<?php echo esc_attr($obj_domain->domain_name);?>" />
					<?php }else{ ?>
						<input type="text" name="minimu-domain-name[<?php echo $obj_domain->id ?>]" value="<?php echo esc_attr($obj_domain->domain_name);?>" />
					<?php } ?>
					<input type="hidden" name="minimu-domain-default[<?php echo $obj_domain->id ?>]" value="<?php echo ($is_base)?"true":"false" ?>" />
				</td>
				<td rowspan="50" class="sortable-handle c3">&nbsp;</td>
			</tr>

			<tr>
				<td class="c0"></td>
				<td class="c1">Categories</td>
				<td class="c2 category"><?php
				if (count((array)$obj_domain->category_id)>0){
					$str_categories='';
					foreach((array)$obj_domain->category_id as $category_id){
						if ((int)$category_id>0){
							$str_categories .= $dc_instance->arr_categories[$category_id]->name . ', ';
						}
					}
					//echo trim($str_categories, ', ');
					//echo '&nbsp;&nbsp;<a href="edit-tags.php?taxonomy=category">edit...</a>';
					echo '<a class="minimu-show-categories" href="Javascript:{}">Choose...</a>';
					
				}else{?>
					Remember to <a class="minimu-show-categories" href="Javascript:{}">add some categories...</a>
				<?php 
				} 
				?></td>
			</tr>

			<tr>
				<td class="c0"></td>
				<td class="c1">Theme</td>
				<td><?php
					if ($is_base){
						echo get_option('current_theme') . " <em><a href='themes.php' onclick='confirm(\"Leave page without saving changes?\");'>Change theme</a></em>";
					}else{
						MiniMU::build_categories_select('minimu-themeselect[' . $obj_domain->id . ']', $dc_instance->arr_themes, $obj_domain->theme_name, $args);
					}?>
				</td>
			</tr>
			<tr>
				<td class="c0"></td>
				<td class="c1">Blog Title</td>
				<td><input type="text" name="minimu-blog-title[<?php echo $obj_domain->id ?>]" value="<?php echo esc_attr($obj_domain->blog_title);?>" <?php if ($disabled) echo 'disabled="disabled"' ?> /></td>
			</tr>
			<tr>
				<td class="c0"></td>
				<td class="c1">Tagline</td>
				<td><input type="text" name="minimu-tagline[<?php echo $obj_domain->id ?>]" value="<?php echo esc_attr($obj_domain->tagline);?>" <?php if ($disabled) echo 'disabled="disabled"' ?> /></td>
			</tr>
			<?php

			 if (isset($obj_domain->custom_vars) && is_array($obj_domain->custom_vars) && count($obj_domain->custom_vars)>0){
				 foreach ($obj_domain->custom_vars as $var_name=>$obj_var){
				 	$var_name=MiniMU::sanitize_varname($var_name);
				 	if ($var_name){
				 	?>
				<tr class="minimu-var-row">
					<td class="c0"><a class="remove" href="Javascript:{}">X</a></td>
					<td class="c1">
						<?php echo esc_html($var_name); ?>
					</td>
					<td>
						<?php
						switch($obj_var->type){
							/*default:
								echo '<input type="text" class="minimu-var-text" name="minimu-custom-vars[' . $obj_domain->id . '][' . $var_name . ']" value="' . wp_kses($obj_var->value, $allowedtags) . '" />';
								break;*/
								
							case 'textarea':
							default:
								echo '<textarea name="minimu-custom-vars[' . $obj_domain->id . '][' . $var_name . ']" class="minimu-var-textarea">' . $obj_var->value . '</textarea>';
								break;
								

						}
						?>
					</td>
				</tr>
			<?php
				 }
			  }
			}
			?>
			
			<tr class="minimu-add-var-row">
				<td colspan="2" style="padding: 3px 2px 10px 15px; text-align: right;">
					<a class="minimu-add-var-link" href="Javascript:{};" >More tokens</a>
					<select id="minimu-add-var[<?php echo $obj_domain->id ?>]" class="minimu-add-var-select">
						<option value="" >Select...</option>
						<?php
						foreach (MiniMU::get_custom_variable_names() as $var_name){
							$var_name=MiniMU::sanitize_varname($var_name);
							if (!isset($obj_domain->custom_vars) || ( isset($obj_domain->custom_vars) &&!array_key_exists($var_name, $obj_domain->custom_vars))){
								echo '<option value="' . $var_name . '">' . $var_name . "</option>\n";
							}
						}
						?>
						<option value="new" >Add new...</option>
					</select>
				</td>
				<td></td>
			</tr>
			
			<?php
			if (count((array)$obj_error)>0){?>
			<tr>
				<td colspan="3" class="error"><div class="error"><?php
				foreach ($obj_error as $arr_error){
					echo $arr_error['message'] . "<br />\n";
				}?>
				</div>
			</tr>
				<?php
			}
			?>
		</table>
		<?php

		return ob_get_clean();
	}


	/**
	 * Get array of Wordpress categories
	 *
	 * @todo Check "child_of" - we should be able to point to sub-categories as well.
	 * @author Eric Shelkie
	 * @since November 22, 2009
	 */
	static function get_categories_array($parent=null){
		$dc_instance=MiniMU::get_instance();
		if(is_array($dc_instance->arr_categories)) return $dc_instance->arr_categories;
		
		$arr_options=array(
			'type'                     => 'post',
			'orderby'                  => 'name',
			'order'                    => 'ASC',
			'hide_empty'			   => false
			);
			
		if ($parent)$arr_options['parent']=$parent;
		
		$arr_temp = get_categories($arr_options);

		$dc_instance->arr_categories=array();
		
		// Let the user choose Blog Home as the category
		/*$obj_blank=new stdClass();
		$obj_blank->cat_ID=1;
		$obj_blank->name='Blog home';
		$dc_instance->arr_categories['1']=$obj_blank;*/
		
		// Put category objects into an associative array
		foreach ($arr_temp as $cat){
			//if ($cat->cat_ID==1) continue;
			$dc_instance->arr_categories[$cat->cat_ID]=$cat;
		}


		return $dc_instance->arr_categories;
	}


	protected function get_themes_array(){
		if(is_array($this->arr_themes)) return $this->arr_themes;
		$arr_temp=get_themes();
		foreach ($arr_temp as $theme){
			if ($theme['Status']=='publish'){
				$obj_theme=(object)$theme;
				$this->arr_themes[sanitize_title_with_dashes($obj_theme->Name)]=$obj_theme;
			}
		}
	}
	
	
	/**
	 * Get an array of custom variable names in use
	 *
	 * @return array
	 * @author Eric Shelkie
	 * @since December 2, 2009
	 */
	static function get_custom_variable_names(){
		$dc_instance=MiniMU::get_instance();
		$arr_var_names=array();
		
		foreach ($dc_instance->arr_domains as $obj_domain){
			if (isset($obj_domain->custom_vars) && is_array($obj_domain->custom_vars)){
				foreach ($obj_domain->custom_vars as $name=>$value){
					$name=MiniMU::sanitize_varname($name);
					if (!in_array($name, $arr_var_names)) $arr_var_names[]=$name;
				}
			}
		}
		sort($arr_var_names);
		return $arr_var_names;
	}
	
	
	/**
	 * Get the slug for the current domain. Taken from linked category slug
	 *
	 * @return string
	 * @author Eric Shelkie
	 * @since December 1, 2009
	 */
	static function get_domain_slug(){
		$dc_instance=MiniMU::get_instance();
		$str_slug=null;
		
		$obj_options=$dc_instance->get_domain_options();
		if ($obj_options instanceof stdClass ){
			if ($obj_options->category_id){
				$obj_category=get_category($obj_options->category_id);
				if ($obj_category instanceof stdClass ){
					$str_slug=$obj_category->category_nicename;
				}
			}
		}
		return $str_slug;
	}
	
	
	/**
	 * Get array of categories selected for a domain
	 *
	 * @return array
	 * @author Eric Shelkie
	 * @since april 11, 2011
	 */
	static function get_domain_categories($obj_domain=null){
		$arr_categories=array();
		if (!$obj_domain){
			$dc_instance=MiniMU::get_instance();
			$obj_domain=$dc_instance->get_domain_options();
		}
		
		if (!is_array($obj_domain->category_id)){
			$arr_categories=array($obj_domain->category_id);
		}else{
			$arr_categories=$obj_domain->category_id;
		}
        $arr_temp = array();
        foreach($arr_categories as $category){
            if (is_int($category)) $arr_temp[]=$category;
        }
        $arr_categories = $arr_temp;

		return $arr_categories;
	}
	

	static function build_categories_select($control_name, $arr_options=array(), $selected_value=null, $args){
		$str_options='';
		//$pad=str_pad('',$depth*3,' ');
		$pad='';
		$disabled=false;
		if (isset($args['disabled'])) $disabled=$args['disabled'];
		
		foreach ($arr_options as $value=>$obj_category){
			if (isset($obj_category->cat_ID)) $value=$obj_category->cat_ID;
			$str_name=(isset($obj_category->name))?$obj_category->name:$obj_category->Name;
			$str_options.='<option value="' . esc_attr($value) . '"';
			if ($value==$selected_value) $str_options.=' selected="selected"';
			$str_options.='>' . esc_html($pad . $str_name) . "</option>\n";
		}

		echo "<select name='$control_name' ";
		if ($disabled) echo 'disabled="disabled"';
		echo ">\n";
		echo $str_options;
		echo "</select>";

	}


	/**
	 * Get a list of domain/blogs configured
	 *
	 * @author Eric Shelkie
	 * @since November 22, 2009
	 */
	static function get_blog_list(){
		$dc_instance=MiniMU::get_instance();
		return $dc_instance->arr_domains;
	}


	/**
	 * Get a list of domain/blogs configured
	 *
	 * @param string Heading text
	 * @author Eric Shelkie
	 * @since November 22, 2009
	 */
	static function show_blog_list($args){
		$dc_instance=MiniMU::get_instance();
		$str_html='';
		$arr_domains=MiniMU::get_blog_list();
		if (is_array($arr_domains)){
			if (isset($args['heading'])) $str_html.= "<h2>" . esc_html($args['heading']) . "</h2>\n<ul>";
			foreach ($arr_domains as $domain){
				if ($domain->is_base) $domain=$dc_instance->obj_base_domain;
				$str_html.= "<li";
				if (in_array(the_category_ID(false), (array)$domain->category_id)) $str_html.= " class='active'";
				$str_html.= "><a href='http://" . MiniMU::get_token('domain_name', $domain->id) . "'>";
				
				// if set, use "link_text" for link text, otherwise, use blog title

				$link_text=$domain->blog_title;
				if (isset($args['link_text'])){
					if ($arg_text=MiniMU::get_token($args['link_text'], $domain->id)){
						$link_text=$arg_text;
					}
				}
				$str_html.=esc_html($link_text) . "</a></li>\n";
			}
			$str_html.= "</ul>\n";
		}
		echo $str_html;
	}

	
	/**
	 * Retrieve custom variable value
	 *
	 * @param string $var_name
	 * @param string $domain_id
	 * @return string value
	 * @author Eric Shelkie
	 * @since December 3, 2009
	 */
	static function get_token($var_name, $domain_id=null){
		$str_return='';
		$dc_instance=MiniMU::get_instance();
		
		$obj_options=MiniMU::get_domain_options($domain_id);
		
		if ($obj_options instanceof stdClass ){
			if ($var_name=='domain_name'){
				$domain_name=$obj_options->domain_name;
				$str_return=$domain_name;

				if (!$domain_name){
					$arr_categories=MiniMU::get_categories_array();
					$domain_name=$dc_instance->obj_base_domain->domain_name;

					// If a category has been selected, build a url for it, but only if blog uses structured permalinks
					if (isset($obj_options->category_id) && get_option('permalink_structure')){
						$category_ids=(array) $obj_options->category_id;
						$category_id=array_pop($category_ids);
						if (isset($arr_categories[$category_id]) ){
							$obj_category=$arr_categories[$category_id];
	
							if ($obj_category instanceof stdClass && isset($obj_category->slug) &&  $obj_category->slug!=""){
								$category_base=rtrim(get_option('category_base'),' /');
								if ($category_base=='') $category_base=MiniMU::DEFAULT_CATEGORY_BASE;
								if ($category_base) $domain_name=$domain_name . '/' . $category_base;
								$domain_name.= '/' . $obj_category->slug;
							}
						}
					}
				}

				return $domain_name;
			}
			if (isset($obj_options->$var_name)) return $str_return=$obj_options->$var_name;
			if (isset($obj_options->custom_vars)){
				if (isset($obj_options->custom_vars[$var_name])){
					$obj_value=$obj_options->custom_vars[$var_name];
					$str_return=$obj_value->value;
				}
			}
		}
		//echo "<br /><br />";
		return $str_return;
	}
	
	
	/**
	 * Replace tokens with variable values
	 *
	 * @param string $strContent
	 * @return string
	 * @author Eric Shelkie
	 * @since March 9, 2010
	 */
	static function filter_replace_tokens($strContent){
		$arrCustomVars=MiniMU::get_custom_variable_names();
		foreach ($arrCustomVars as $varName){
			$strValue=MiniMU::get_token($varName);
			$strContent=str_replace("{*" . $varName . "*}", $strValue, $strContent);
		}
		return $strContent;
	}
	
	
	
}

class MiniMU_blog_list_widget extends WP_Widget {
	
	function MiniMU_blog_list_widget(){
  		$widget_ops = array('description' => 'A list of blogs configured with the miniMU plugin.' );
		$this->WP_Widget('minimu_blog_list', 'MiniMU blogs list', $widget_ops);
	}

	function widget($args, $instance) {
		MiniMU::show_blog_list(array('heading'=>'Our blogs', 'link_text'=>'ShortName'));
	}
	
	function update($new_instance, $old_instance) {
		
	}
}
//register_sidebar_widget(__('MiniMU blogs list'), 'MiniMU::widget_minimu_blogs');



// Get info for the base/default domain
// Need to do this before the plugin starts
global $minimu_obj_base_domain;
$minimu_obj_base_domain=new stdClass();
$minimu_obj_base_domain->is_base=true;
$minimu_obj_base_domain->id=0;
$minimu_obj_base_domain->domain_name=preg_replace("/http[s]?\:\/\//i", '', get_option('siteurl'));
$minimu_obj_base_domain->category_id=array_keys(MiniMU::get_categories_array());
$minimu_obj_base_domain->theme_name=get_option('current_theme');
$minimu_obj_base_domain->blog_title=get_bloginfo('name');
$minimu_obj_base_domain->tagline=get_bloginfo('description');

// Initialize the plugin
$obj_MiniMU=MiniMU::init();

?>