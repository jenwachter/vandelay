<?php

namespace vandelay\commands;

use WP_CLI;

class Vandelay extends \WP_CLI_Command
{
  public function getFileLocation()
  {
    return get_template_directory() . '/config/acf.json';
  }

	/**
	 * Use an ACF function to export field groups
	 * @return null
	 */
	public function export($args)
	{
    if (is_multisite()) {
      if (empty($args)) {
        \WP_CLI::error('Please specify a blog ID');
      }
      switch_to_blog($args[0]);
    }

		// include ACF file needed for export
		acf_include("admin/settings-tools.php");
		$tools = new \acf_settings_tools();

		// create post variable for ACF function
		$_POST["acf_export_keys"] = $this->getFieldGroupNames();

		$data = $tools->get_json();
		$this->saveConfig($data, $this->getFileLocation());

    restore_current_blog();
	}

	/**
	 * Imports ACF field groups from file to database
	 * @return null
	 */
	public function import($args)
	{
    if (is_multisite()) {
      if (empty($args)) {
        \WP_CLI::error('Please specify a blog ID');
      }
      switch_to_blog($args[0]);
    }

    $fileLocation = $this->getFileLocation();

		// create files variable for ACF function
		$_FILES = array(
			"acf_import_file" => array(
				"name" => $fileLocation,
				"tmp_name" => $fileLocation,
				"error" => false
			)
		);

		$this->importFields();

    restore_current_blog();
	}

	/*
	*  getFields
	*
	*  Gets all acf-field-group posts from the database
	*
	*  @type	function
	*  @date	12/16/2015
	*
	*  @param	n/a
	*  @return	n/a
	*/
	function getFieldGroups() {
		$posts = get_posts(array(
			"posts_per_page" => -1,
			"post_type" => "acf-field-group",
			"post_status" => "publish"
		));

		$return = array();

		foreach ($posts as $post) {
			$return[$post->ID] = $post;
		}

		return $return;
	}

	/*
	*  getFields
	*
	*  Gets all acf-field posts from the database
	*
	*  @type	function
	*  @date	12/16/2015
	*
	*  @param	n/a
	*  @return	n/a
	*/
	function getFields() {
		$posts = get_posts(array(
			"posts_per_page" => -1,
			"post_type" => "acf-field",
			"post_status" => "publish"
		));

		$return = array();

		foreach ($posts as $post) {
			$return[$post->ID] = $post;
		}

		return $return;
	}

	/**
	 * An altered version of acf/admin/settings-tool.php::import()
	 *
	 * ACF's import function takes care of most things, but this
	 * function makes up for what it lacks:
	 *
	 * - Removes fields that are in the DB, but not in the import file
	 * - Removes field groups that are in the DB, but not in the import file
	 * - Updates fields that have changed
	 * - Displays command-line messages
	 *
	 * @return null
	 */
	protected function importFields() {

		// validate
		if( empty($_FILES['acf_import_file']) ) {

			acf_add_admin_notice( __("No file selected", 'acf') , 'error');
			return;

		}


		// vars
		$file = $_FILES['acf_import_file'];


		// validate error
		if( $file['error'] ) {

			acf_add_admin_notice(__('Error uploading file. Please try again', 'acf'), 'error');
			return;

		}


		// validate type
		if( pathinfo($file['name'], PATHINFO_EXTENSION) !== 'json' ) {

			acf_add_admin_notice(__('Incorrect file type', 'acf'), 'error');
			return;

		}


		// read file
		$json = file_get_contents( $file['tmp_name'] );


		// decode json
		$json = json_decode($json, true);


		// validate json
    	if( empty($json) ) {

    		acf_add_admin_notice(__('Import file empty', 'acf'), 'error');
	    	return;

    	}


    	// if importing an auto-json, wrap field group in array
    	if( isset($json['key']) ) {

	    	$json = array( $json );

    	}


    	// vars
    	$added = array();
    	$deletedgroups = array();
    	$deletedfields = array();
    	$ref = array();
    	$order = array();

    	$allgroups = $this->getFieldGroups();
    	$allfields = $this->getFields();

    	foreach( $json as $field_group ) {

				$update = false;

	    	// check if field group exists
	    	if( $post = acf_get_field_group($field_group['key'], true) ) {

					// \WP_CLI::log($field_group['title'] . " group already exists. Updating.");

					// add ID to trigger update instead of insert
					$field_group["ID"] = $post["ID"];

					$update = true;

	    	// } else {
				// 	\WP_CLI::log($field_group['title'] . " group is new. Adding.");
				}


	    	// remove fields
				$fields = acf_extract_var($field_group, 'fields');


				// format fields
				$fields = acf_prepare_fields_for_import( $fields );


				// save field group
				$field_group = acf_update_field_group( $field_group );


				// remove group from $allgroups array
				if (isset($allgroups[$field_group['ID']])) {
					unset($allgroups[$field_group['ID']]);
				}


				// add to ref
				$ref[ $field_group['key'] ] = $field_group['ID'];


				// add to order
				$order[ $field_group['ID'] ] = 0;


				// add fields
				foreach( $fields as $field ) {

					// add parent
					if( empty($field['parent']) ) {

						$field['parent'] = $field_group['ID'];

					} elseif( isset($ref[ $field['parent'] ]) ) {

						$field['parent'] = $ref[ $field['parent'] ];

					}


					// add field menu_order
					if( !isset($order[ $field['parent'] ]) ) {

						$order[ $field['parent'] ] = 0;

					}

					$field['menu_order'] = $order[ $field['parent'] ];
					$order[ $field['parent'] ]++;


					// add ID if the field already exists
					if($post = acf_get_field($field['key'], true) ) {

						// add ID to trigger update instead of insert
						$field["ID"] = $post["ID"];

						// \WP_CLI::log($field_group['title'] . "->" . $field['label'] . " field already exists. Updating.");


					// } else {
					// 	\WP_CLI::log($field_group['title'] . "->" . $field['label'] . " field is new. Adding.");
					}


					// save field
					$field = acf_update_field( $field );


					// remove field from allfields array
					if (isset($allfields[$field['ID']])) {
						unset($allfields[$field['ID']]);
					}


					// add to ref
					$ref[ $field['key'] ] = $field['ID'];

				}

				if ($update) {
					\WP_CLI::success($field_group['title'] . " field group updated.");
				} else {
					\WP_CLI::success($field_group['title'] . " field group added.");
				}

    	}

			if (!empty($allgroups)) {
				foreach ($allgroups as $post) {
					\WP_CLI::success($post->post_title . " field group deleted.");
					wp_delete_post($post->ID);
				}
			}

			if (!empty($allfields)) {
				foreach ($allfields as $post) {
					\WP_CLI::success($post->post_title . " field deleted.");
					wp_delete_post($post->ID);
				}
			}

	}

	protected function getFieldGroupNames()
	{
		$posts = get_posts(array(
			"posts_per_page" => -1,
			"post_type" => "acf-field-group",
			"post_status" => "publish"
		));

		return array_map(function ($post) {
			return $post->post_name;
		}, $posts);
	}

	/**
	 * Save configuration to a file
	 * @param  array  $data      Array of data
	 * @param  string $location  File location
	 * @return null
	 */
	protected function saveConfig($data, $location)
	{
		$put = file_put_contents($location, json_encode($data, JSON_PRETTY_PRINT));

		if ($put) {
			\WP_CLI::success("ACF settings were successfully exported.");
		} else {
			\WP_CLI::error("Something went wrong while trying to write to the config file. Please make sure " . $location . " is writable by WP CLI.");
		}
	}
}
