<?php
/**
* Plugin Name: Listing Company Details Plugin
* Plugin URI: ''
* Description: Listing Company profiles details.
* Version: 1.0
* Author: Sethulakshmi
* Author URI: https://github.com/sethulakshmimm/
**/



if ( !defined( 'ABSPATH' ) ) exit;

register_activation_hook( __FILE__, "activate_myplugin" );
register_deactivation_hook( __FILE__, "deactivate_myplugin" );


function activate_myplugin() {
	init_db_myplugin();
}


function init_db_myplugin() {

	global $wpdb;

	$companyTable = 'ncp_table';

	if( $wpdb->get_var( "show tables like '$companyTable'" ) != $companyTable ) {

		// Query - Create Table
		$sql = "CREATE TABLE `$companyTable` (";
		$sql .= " `ID` int(11) NOT NULL auto_increment, ";
		$sql .= " `company_name` varchar(500) NOT NULL, ";
		$sql .= " `description` text NOT NULL, ";
		$sql .= " `Zone` varchar(500) NOT NULL, ";
		$sql .= " `street` varchar(500) NOT NULL, ";
		$sql .= " `building_number` varchar(500) NOT NULL, ";
		$sql .= " PRIMARY KEY `table_id` (`id`) ";
		$sql .= ") ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;";

		require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
	
		dbDelta( $sql );
	}

}



if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Company_List extends WP_List_Table {


	public function __construct() {

		parent::__construct( [
			'singular' => __( 'Company', 'sp' ), //singular name of the listed records
			'plural'   => __( 'Companies', 'sp' ), //plural name of the listed records
			'ajax'     => false 
		] );

	}



	public static function get_companies( $per_page = 5, $page_number = 1 ) {

		global $wpdb;

		if(!empty($_REQUEST['search'])){
		$sql = "SELECT * FROM ncp_table WHERE `company_name` like '%".$_REQUEST['search']."%' or `description` like '%".$_REQUEST['search']."%' or `Zone` like '%".$_REQUEST['search']."%' or `street` like '%".$_REQUEST['search']."%' or `building_number` like '%".$_REQUEST['search']."%'";	

		}else{
			$sql = "SELECT * FROM ncp_table";
		}

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
			$sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
		}

		$sql .= " LIMIT $per_page";
		$sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;


		$result = $wpdb->get_results( $sql, 'ARRAY_A' );

		return $result;
	}


	/**
	 * Delete a company record.
	 */
	public static function delete_company( $id ) {
		global $wpdb;

		$wpdb->delete(
			"ncp_table",
			[ 'ID' => $id ],
			[ '%d' ]
		);
	}



	/**
	 * Returns the count of records in the database.
	 *
	 * @return null|string
	 */
	public static function record_count() {
		global $wpdb;
		if(!empty($_REQUEST['search'])){
		$sql = "SELECT COUNT(*) FROM ncp_table WHERE `company_name` like '%".$_REQUEST['search']."%' or `description` like '%".$_REQUEST['search']."%' or `Zone` like '%".$_REQUEST['search']."%' or `street` like '%".$_REQUEST['search']."%' or `building_number` like '%".$_REQUEST['search']."%'";	
		
		}else{
			$sql = "SELECT COUNT(*) FROM ncp_table";
		}
		

		return $wpdb->get_var( $sql );
	}


	/** Text displayed when no company data is available */
	public function no_items() {
		_e( 'No Company list avaliable.', 'sp' );
	}


	/**
	 * Render a column when no column specific method exist.
	 *
	 * @param array $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'company_name':
			case 'description':
			case 'Zone':
			case 'street':
			case 'building_number':
				return $item[ $column_name ];
			default:
				return print_r( $item, true ); //Show the whole array for troubleshooting purposes
		}
	}

	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['ID']
		);
	}

	/**
	 * Method for name column
	 *
	 * @param array $item an array of DB data
	 *
	 * @return string
	 */
	function column_name( $item ) {

		$delete_nonce = wp_create_nonce( 'sp_delete_company' );

		$title = '<strong>' . $item['company_name'] . '</strong>';

		$actions = [
			'delete' => sprintf( '<a href="?page=%s&action=%s&company=%s">Delete</a>', esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['ID'] ) )
		];

		return $title . $this->row_actions( $actions );
	}

	/**
	 *  Associative array of columns
	 *
	 * @return array
	 */
	function get_columns() {
		$columns = [
			'cb'      => '<input type="checkbox" />',
			'company_name'    => __( 'Company Name', 'sp' ),
			'description' => __( 'Description', 'sp' ),
			'Zone'    => __( 'Zone', 'sp' ),
			'street'    => __( 'Street', 'sp' ),
			'building_number'    => __( 'Building Number', 'sp' )
		];

		return $columns;
	}


	/**
	 * Columns to make sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'company_name' => array( 'company_name', true ),
		);

		return $sortable_columns;
	}

	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = [
			'bulk-delete' => 'Delete'
		];

		return $actions;
	}


	/**
	 * Handles data query and filter, sorting, and pagination.
	 */
	public function prepare_items() {

		$this->_column_headers = $this->get_column_info();

		/** Process bulk action */
		$this->process_bulk_action();

		$per_page     = $this->get_items_per_page( 'company_per_page', 5 );
		$current_page = $this->get_pagenum();
		$total_items  = self::record_count();

		$this->set_pagination_args( [
			'total_items' => $total_items, //WE have to calculate the total number of items
			'per_page'    => $per_page //WE have to determine how many items to show on a page
		] );

		$this->items = self::get_companies( $per_page, $current_page );
	}

	public function process_bulk_action() {


		// If the delete bulk action is triggered
		if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )
		     || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' )
		) {

			$delete_ids = esc_sql( $_POST['bulk-delete'] );

			// loop over the array of record IDs and delete them
			foreach ( $delete_ids as $id ) {
				self::delete_company( $id );

			}

			// esc_url_raw() is used to prevent converting ampersand in url to "#038;"
		        // add_query_arg() return the current url
		        wp_redirect( esc_url_raw(add_query_arg()) );
			exit;
		}
	}

}


class SP_Plugin {

	static $instance;

	public $company_obj;

	// class constructor
	public function __construct() {
		add_filter( 'set-screen-option', [ __CLASS__, 'set_screen' ], 10, 3 );
		add_action( 'admin_menu', [ $this, 'plugin_menu' ] );
	}


	public static function set_screen( $status, $option, $value ) {
		return $value;
	}

	public function plugin_menu() {

		$hook = add_menu_page(
			'Companies List',
			'Companies List',
			'manage_options',
			'wp_list_table_company',
			[ $this, 'plugin_settings_page' ]
		);

		add_action( "load-$hook", [ $this, 'screen_option' ] );

	}


	/**
	 * Plugin settings page
	 */
	public function plugin_settings_page() { ?>
		<h2>Company List</h2>
		<form id="nds-user-list-form" method="get">
			<input type="text" name="search" value="<?php echo $_REQUEST['search']; ?>" />
			<input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>">
			<input type="submit" value="search" class="button action">					
		</form> <?php
							echo '<form method="post">';
								$this->company_obj->prepare_items();
								$this->company_obj->display(); 
							echo '</form>';

	}

	/**
	 * Screen options
	 */
	public function screen_option() {

		$option = 'per_page';
		$args   = [
			'label'   => 'Companies',
			'default' => 5,
			'option'  => 'company_per_page'
		];

		add_screen_option( $option, $args );

		$this->company_obj = new Company_List();
	}


	/** Singleton instance */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}


add_action( 'plugins_loaded', function () {
	SP_Plugin::get_instance();
} );
