<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CKP_Customers_Table extends WP_List_Table {

	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox">',
			'email'        => 'Email',
			'display_name' => 'Name',
			'company_name' => 'Company',
			'status'       => 'Status',
			'licences'     => 'Licences',
			'created_at'   => 'Created',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'email'      => array( 'email', false ),
			'status'     => array( 'status', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	protected function column_cb( $item ) {
		return '<input type="checkbox" name="account_ids[]" value="' . esc_attr( $item->id ) . '">';
	}

	protected function column_email( $item ) {
		$edit_url = admin_url( 'admin.php?page=ckp-customers&action=edit&id=' . $item->id );
		$new_licence_url = admin_url( 'admin.php?page=ckp-licences&action=new&account_id=' . $item->id );

		$toggle_action = $item->status === 'active' ? 'disable' : 'enable';
		$toggle_label  = $item->status === 'active' ? 'Disable' : 'Enable';
		$toggle_url    = wp_nonce_url(
			admin_url( 'admin-post.php?action=ckp_customer_action&customer_action=' . $toggle_action . '&id=' . $item->id ),
			'ckp_customer_action_' . $item->id
		);

		$actions = array(
			'edit'       => '<a href="' . esc_url( $edit_url ) . '">Edit</a>',
			'new_licence' => '<a href="' . esc_url( $new_licence_url ) . '">Add Licence</a>',
			$toggle_action => '<a href="' . esc_url( $toggle_url ) . '">' . $toggle_label . '</a>',
		);

		return '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $item->email ) . '</a></strong>'
			. $this->row_actions( $actions );
	}

	protected function column_status( $item ) {
		$class = $item->status === 'active' ? 'ckp-badge-active' : 'ckp-badge-inactive';
		return '<span class="ckp-badge ' . $class . '">' . esc_html( $item->status ) . '</span>';
	}

	protected function column_licences( $item ) {
		$url = admin_url( 'admin.php?page=ckp-licences&account_id=' . $item->id );
		return '<a href="' . esc_url( $url ) . '">' . (int) $item->licence_count . '</a>';
	}

	protected function column_created_at( $item ) {
		return esc_html( date_i18n( 'd M Y', strtotime( $item->created_at ) ) );
	}

	protected function column_default( $item, $column_name ) {
		return esc_html( $item->$column_name ?? '' );
	}

	public function prepare_items() {
		global $wpdb;

		$table   = CKP_DB::table( 'accounts' );
		$ltable  = CKP_DB::table( 'licences' );
		$per_page = 20;
		$current  = $this->get_pagenum();
		$offset   = ( $current - 1 ) * $per_page;

		$search  = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$allowed_order = array( 'email', 'status', 'created_at' );
		$orderby = in_array( $_REQUEST['orderby'] ?? '', $allowed_order, true ) ? $_REQUEST['orderby'] : 'created_at';
		$order   = strtoupper( $_REQUEST['order'] ?? '' ) === 'ASC' ? 'ASC' : 'DESC';

		if ( $search ) {
			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$where = $wpdb->prepare( 'WHERE a.email LIKE %s OR a.display_name LIKE %s', $like, $like );
		} else {
			$where = '';
		}

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table` a $where" );

		// phpcs:ignore WordPress.DB.PreparedSQL
		$this->items = $wpdb->get_results( $wpdb->prepare(
			"SELECT a.*, (SELECT COUNT(*) FROM `$ltable` l WHERE l.account_id = a.id) AS licence_count
			FROM `$table` a $where ORDER BY $orderby $order LIMIT %d OFFSET %d",
			$per_page, $offset
		) );

		$this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per_page ) );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}
}
