<?php 

namespace NestedPages\Entities\PostType;

class PostTypeRepository 
{

	/**
	* Get Available Post Types
	* @since 1.2.1
	* @return array
	*/
	public function getPostTypes($return = 'names')
	{
		return get_post_types(array('show_ui'=>true), $return);
	}

	/**
	* Get an object of non-page post types
	* @since 1.2.1
	* @return object
	*/
	public function getPostTypesObject($pages = false)
	{
		$all_types = $this->getPostTypes('objects');
		$post_types = array();
		$enabled_types = $this->enabledPostTypes();
		$invalid_types = array(
			'acf-field-group'
		);
		foreach($all_types as $key => $type){
			if ( (!$pages) && ($key == 'attachment') ) continue;
			if ( in_array($type->name, $invalid_types) ) continue;
			$post_types[$type->name] = new \stdClass();
			$post_types[$type->name]->name = $type->name;
			$post_types[$type->name]->label = $type->labels->name;
			$post_types[$type->name]->hierarchical = $type->hierarchical;
			$post_types[$type->name]->np_enabled = ( array_key_exists($type->name, $this->enabledPostTypes()) ) ? true : false;
			$post_types[$type->name]->replace_menu = $this->overrideMenu($type->name);
			$post_types[$type->name]->hide_default = $this->hideDefault($type->name);
			$post_types[$type->name]->disable_nesting = $this->disableNesting($type->name);
			$post_types[$type->name]->custom_fields_enabled = $this->customFieldsEnabled($type->name);
		}
		return $post_types;
	}

	/**
	* Is the specified post type set to override the default menu?
	* @param string post type name
	* @return boolean
	*/
	private function overrideMenu($post_type)
	{
		foreach($this->enabledPostTypes() as $key => $type){
			if ( $key == $post_type ){
				return ( isset($type['replace_menu']) && $type['replace_menu'] == 'true' )
					? true
					: false;
			}
		}
	}

	/**
	* Is the specified post type set to hide the default link?
	* @param string post type name
	* @return boolean
	*/
	public function hideDefault($post_type)
	{
		foreach($this->enabledPostTypes() as $key => $type){
			if ( $key == $post_type ){
				return ( isset($type['hide_default']) && $type['hide_default'] == 'true' )
					? true
					: false;
			}
		}
	}

	/**
	* Is nesting disabled on the specified post type
	* @param string post type name
	* @return boolean
	*/
	public function disableNesting($post_type)
	{
		foreach($this->enabledPostTypes() as $key => $type){
			if ( $key == $post_type ){
				return ( isset($type['disable_nesting']) && $type['disable_nesting'] == 'true' )
					? true
					: false;
			}
		}
	}

	/**
	* Is custom field configuration enabled on the specified post type
	* @param string post type name
	* @return boolean
	*/
	public function customFieldsEnabled($post_type)
	{
		foreach($this->enabledPostTypes() as $key => $type){
			if ( $key == $post_type ){
				return ( isset($type['custom_fields_enabled']) && $type['custom_fields_enabled'] == 'true' )
					? true
					: false;
			}
		}
	}

	/**
	* Get an array of NP enabled Post Types
	* @since 1.2.1
	* @return array
	*/
	public function enabledPostTypes()
	{
		$types = get_option('nestedpages_posttypes');
		return ( !$types ) ? array() : $types;
	}

	/**
	* Add New Post Link
	* @since 1.2.1
	* @param string post_type
	* @return string
	*/
	public function addNewPostLink($post_type)
	{
		return esc_url( admin_url('post-new.php?post_type=' . $post_type) );
	}

	/**
	* Trash Link
	* @since 1.2.1
	* @param string post_type
	* @return string
	*/
	public function trashLink($post_type)
	{
		return esc_url( admin_url('edit.php?post_status=trash&post_type=' . $post_type) );
	}

	/**
	* Edit Post Link
	* @since 1.2.1
	* @param string post_type
	* @return string
	*/
	public function editSlug($post_type)
	{
		return ( $post_type->name == 'post' ) ? 'edit.php' : 'edit.php?post_type=' . $post_type->name;
	}

	/**
	* Get Taxonomies enabled for post type
	* @since 1.2.1
	* @return array of taxonomy objects
	* @param string post_type name
	* @param boolean hierarchical
	*/
	public function getTaxonomies($post_type, $hierarchical = true)
	{
		$taxonomy_names = get_object_taxonomies( $post_type );
		$hierarchical_taxonomies = array();
		$flat_taxonomies = array();
		foreach ( $taxonomy_names as $taxonomy_name ) {
			$taxonomy = get_taxonomy( $taxonomy_name );
			if ( !$taxonomy->show_ui )continue;

			if ( $taxonomy->hierarchical )
				$hierarchical_taxonomies[] = $taxonomy;
			else
				$flat_taxonomies[] = $taxonomy;
		}
		return ($hierarchical) ? $hierarchical_taxonomies : $flat_taxonomies;
	}

	/**
	* Are Categories Enabled for a post type?
	* @since 1.5.0
	* @return boolean
	*/
	public function categoriesEnabled($post_type)
	{
		$taxonomies = $this->getTaxonomies($post_type, true);
		$enabled = false;
		foreach($taxonomies as $taxonomy){
			if ( $taxonomy->name == 'category' ) $enabled = true;
		}
		return $enabled;
	}

	/**
	* Get the NP menu slug for a post type
	* @param object WP Post Type Object
	*/
	public function getMenuSlug($post_type)
	{
		return ( $post_type->name == 'page' ) ? 'nestedpages' : 'nestedpages-' . $post_type->name;
	}

	/**
	* Set the Submenu Text
	* "Nested View" for Hierarchical Post Types
	* "Sort View" for Non-Hierarchical Post Types
	*/
	public function getSubmenuText($post_type)
	{
		return ( $post_type->hierarchical ) ? __('Nested View', 'nestedpages') : __('Sort View', 'nestedpages');
	}

	/**
	* Get all custom fields associated with a post type
	* @param string post_type
	* @param boolean show_hidden
	* @return array
	*/
	public function getCustomFields($post_type, $show_hidden = false)
	{
		global $wpdb;
		$post_table = $wpdb->prefix . 'posts';
		$meta_table = $wpdb->prefix . 'postmeta';
		if ( $show_hidden ){
			$sql = "SELECT DISTINCT meta_key FROM $post_table AS p LEFT JOIN $meta_table AS m ON m.post_id = p.id WHERE p.post_type = '$post_type' AND meta_key NOT LIKE ''";
		} else {
			$sql = "SELECT DISTINCT meta_key FROM $post_table AS p LEFT JOIN $meta_table AS m ON m.post_id = p.id WHERE p.post_type = '$post_type' AND meta_key NOT LIKE '\_%'";
		}
		$results = $wpdb->get_results($sql);
		$fields = ( $results ) ? $this->fieldsArray($results) : array();
		return $fields;
	}

	/**
	* Format DB results into an array
	*/
	private function fieldsArray($results)
	{
		$fields = array();
		$exclude = array('_wp_page_template', '_edit_lock', '_edit_last', '_wp_trash_meta_status', '_wp_trash_meta_time', 'layout', 'position', 'rule', 'hide_on_screen', 'np_link_target', 'np_nav_title', 'np_title_attribute', 'np_nav_status', 'nested_pages_status', 'np_nav_css_classes');
		foreach ( $results as $field ){
			if ( !in_array($field->meta_key, $exclude) ) 
				array_push($fields, $field->meta_key);
		}
		return $fields;
	}

}