<?php
namespace WeDevs\PM\Milestone\Helper;

use WP_REST_Request;
// data: {
// 	with: 'Millestone,users',
// 	per_page: '10',
// 	select: 'id, title',
// 	categories: [2, 4],
// 	assignees: [1,2],
// 	id: [1,2],
// 	title: 'Rocket', 'test'
// 	status: '0',
// 	page: 1,
//  orderby: [title=>'asc', 'id'=>desc]
// },

class Millestone_Board {
	private static $_instance;
	private $query_params;
	private $select;
	private $join;
	private $where;
	private $limit;
	private $with;
	private $millestones;
	private $millestone_ids;
	private $is_single_query = false;

	public static function getInstance() {
        if ( !self::$_instance ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    function __construct() {
    	$this->set_table_name();
    }

    public static function get_millestone_boards( WP_REST_Request $request ) {
		$millestones = self::get_results( $request->get_params() );
		wp_send_json( $millestones );
	}

	public static function get_results( $params ) {
		$self = self::getInstance();
		$self->query_params = $params;

		$self->select()
			->join()
			->where()
			->limit()
			->orderby()
			->get()
			->with()
			->meta();

		$response = $self->format_millestones( $self->millestones );

		if( $self->is_single_query && count( $response['data'] ) ) {
			return ['data' => $response['data'][0]] ;
		}

		return $response;
	}

	/**
	 * Format TaskList data
	 *
	 * @param array $tasklists
	 *
	 * @return array
	 */
	public function format_millestones( $millestones ) {
		$response = [
			'data' => [],
			'meta' => []
		];

		if ( ! is_array( $millestones ) ) {
			$response['data'] = $this->fromat_millestone( $millestones );

			return $response;
		}

		foreach ( $millestones as $key => $$millestone ) {
			$millestones[$key] = $this->fromat_millestone( $$millestone );
		}

		$response['data']  = $millestones;
		$response ['meta'] = $this->set_millestone_meta();

		return $response;
	}

	/**
	 * Set meta data
	 */
	private function set_millestone_meta() {
		return [
			'pagination' => [
				'total'   => $this->found_rows,
				'per_page'       => ceil( $this->found_rows/$this->get_per_page() )
			]
		];
	}

	public function fromat_millestone( $millestone ) {
		$items = [
			'id'          => (int) $millestone->id,
			'title'       => (string) $millestone->title,
			'description' => pm_filter_content_url( $millestone->description ),
			'order'       => (int) $millestone->order,
			'status'      => $millestone->status,
			'created_at'  => format_date( $millestone->created_at ),
			'extra'       => true,
			'project_id'  => $millestone->project_id
        ];

		$select_items = empty( $this->query_params['select'] ) ? null : $this->query_params['select'];

		if ( ! is_array( $select_items ) && !is_null( $select_items ) ) {
			$select_items = str_replace( ' ', '', $select_items );
			$select_items = explode( ',', $select_items );
		}

		if ( empty( $select_items ) ) {
			$items = $this->item_with( $items,$millestone );
			$items = $this->item_meta( $items,$millestone );
			return $items;
		}

		foreach ( $items as $item_key => $item ) {
			if ( ! in_array( $item_key, $select_items ) ) {
				unset( $items[$item_key] );
			}
		}

		$items = $this->item_with( $items, $millestone );
		$items = $this->item_meta( $items, $millestone );

		return $items;
	}

	private function item_with( $items, $millestone ) {
		$with = empty( $this->query_params['with'] ) ? [] : $this->query_params['with'];

		if ( ! is_array( $with ) ) {
			$with = explode( ',', $with );
		}

		$millestone_with_items =  array_intersect_key( (array) $millestone, array_flip( $with ) );

		$items = array_merge($items,$millestone_with_items);

		return $items;
	}

	private function item_meta( $items, $millestone ) {
		$meta = empty( $this->query_params['millestone_meta'] ) ? [] : $this->query_params['millestone_meta'];

		if( ! $meta ) {
			return $items;
		}

		$items['meta'] = empty( $millestone->meta ) ? [ 'data' => [] ] : [ 'data' => $millestone->meta];

		return $items;
	}

	private function with() {
		$this->include_discussion_boards()->include_task_lists();
		$this->millestones = apply_filters( 'pm_millestone_with',$this->millestones, $this->millestone_ids, $this->query_params );

		return $this;
	}

	private function include_discussion_boards() {
		global $wpdb;
		$with = empty( $this->query_params['with'] ) ? [] : $this->query_params['with'];

		if ( ! is_array( $with ) ) {
			$with = explode( ',', $with );
		}

		$discussions = [];

		if ( ! in_array( 'discussions', $with ) ) {
			return $this;
		}

		$tb_pm_comments = pm_tb_prefix() . 'pm_comments';
		$tb_boards      = pm_tb_prefix() . 'pm_boards';
		$tb_boardable   = pm_tb_prefix() . 'pm_boardables';

		$millestone_format = pm_get_prepare_format( $this->millestone_ids );

		$query ="SELECT DISTINCT $tb_boards.*, $tb_boardable.board_id as millestone_id
			FROM $tb_boardable
			LEFT JOIN $tb_boards on $tb_boards.id = $tb_boardable.boardable_id
			WHERE $tb_boardable.board_type = 'milestone'
			AND $tb_boardable.boardable_type = 'discussion_board'
			AND $tb_boardable.board_id IN ($millestone_format)
		";

		$results = $wpdb->get_results( $wpdb->prepare( $query, $this->millestone_ids ) );

		foreach ( $results as $key => $result ) {
			$millestone_id = $result->millestone_id;
			unset($result->millestone_id);
			$discussions[$millestone_id] = $result;
		}

		foreach ( $this->millestones as $key => $millestone ) {
			$millestone->discussions['data'] = empty( $discussions[$millestone->id] ) ? [] : $discussions[$millestone->id];
		}

		return $this;
	}

	private function include_task_lists() {
		global $wpdb;
		$with = empty( $this->query_params['with'] ) ? [] : $this->query_params['with'];

		if ( ! is_array( $with ) ) {
			$with = explode( ',', $with );
		}

		$task_lists = [];

		if ( ! in_array( 'task_lists', $with ) ) {
			return $this;
		}

		$tb_pm_comments = pm_tb_prefix() . 'pm_comments';
		$tb_boards      = pm_tb_prefix() . 'pm_boards';
		$tb_boardable   = pm_tb_prefix() . 'pm_boardables';

		$millestone_format = pm_get_prepare_format( $this->millestone_ids );

		$query ="SELECT DISTINCT $tb_boards.*, $tb_boardable.board_id as millestone_id
			FROM $tb_boardable
			LEFT JOIN $tb_boards on $tb_boards.id = $tb_boardable.boardable_id
			WHERE $tb_boardable.board_type = 'milestone'
			AND $tb_boardable.boardable_type = 'task_list'
			AND $tb_boardable.board_id IN ($millestone_format)
		";

		$results = $wpdb->get_results( $wpdb->prepare( $query, $this->millestone_ids ) );

		foreach ( $results as $key => $result ) {
			$millestone_id = $result->millestone_id;
			unset($result->millestone_id);
			$task_lists[$millestone_id] = $result;
		}

		foreach ( $this->millestones as $key => $millestone ) {
			$millestone->task_lists['data'] = empty( $task_lists[$millestone->id] ) ? [] : $task_lists[$millestone->id];
		}

		return $this;
	}

	private function meta() {
		$meta = empty( $this->query_params['millestone_meta'] ) ? [] : $this->query_params['millestone_meta'];

		if ( ! is_array( $meta ) && $meta != 'all' ) {
			$meta = explode( ',', $meta );
		}

		if ( $meta == 'all' ) {
			$this->total_task_list_count();
			$this->total_discussion_board_count();
		}

		if( in_array( 'total_task_list', $meta ) ) {

			$this->total_task_list_count();
		}

		if( in_array( 'total_discussion_board', $meta ) ) {
			$this->total_discussion_board_count();
		}

		$this->get_meta_tb_data();

		return $this;
	}

	private function get_meta_tb_data() {
        global $wpdb;
		$metas             = [];
		$tb_projects       = pm_tb_prefix() . 'pm_projects';
		$tb_meta           = pm_tb_prefix() . 'pm_meta';
		$millestone_format = pm_get_prepare_format( $this->millestone_ids );

        $query = "SELECT DISTINCT $tb_meta.meta_key, $tb_meta.meta_value, $tb_meta.entity_id
            FROM $tb_meta
            WHERE $tb_meta.entity_id IN ($millestone_format)
            AND $tb_meta.entity_type = 'milestone'";

        $results = $wpdb->get_results( $wpdb->prepare( $query, $this->millestone_ids ) );

        foreach ( $results as $key => $result ) {
            $millestone_id = $result->entity_id;
            unset( $result->entity_id );
            $metas[$millestone_id][] = $result;
        }

        foreach ( $this->millestones as $key => $millestone ) {
            $filter_metas = empty( $metas[$millestone->id] ) ? [] : $metas[$millestone->id];

            foreach ( $filter_metas as $key => $filter_meta ) {
                $millestone->meta[$filter_meta->meta_key] = $filter_meta->meta_value;
            }
        }

        return $this;
    }

	private function total_task_list_count() {
		global $wpdb;
		$metas = [];
		$tb_pm_comments    = pm_tb_prefix() . 'pm_comments';
		$tb_boards         = pm_tb_prefix() . 'pm_boards';
		$tb_boardable      = pm_tb_prefix() . 'pm_boardables';
		$millestone_format = pm_get_prepare_format( $this->millestone_ids );

		$query ="SELECT DISTINCT  count($tb_boards.id) as task_list_count, $tb_boardable.board_id as millestone_id
			FROM $tb_boardable
			LEFT JOIN $tb_boards on $tb_boards.id = $tb_boardable.boardable_id
			WHERE $tb_boardable.board_type = 'milestone'
			AND $tb_boardable.boardable_type = 'task_list'
			AND $tb_boardable.board_id IN ($millestone_format)
			group by $tb_boardable.board_id
		";


		$results = $wpdb->get_results( $wpdb->prepare( $query, $this->discussion_ids ) );

		foreach ( $results as $key => $result ) {
			$millestone_id = $result->millestone_id;
			unset($result->millestone_id);
			$metas[$millestone_id] = $result->task_list_count;
		}

		foreach ( $this->millestones as $key => $millestone ) {
			$millestone->meta['total_task_list'] = empty( $metas[$millestone->id] ) ? 0 :
			$metas[$millestone->id];
		}

		return $this;
	}

	private function total_discussion_board_count() {
		global $wpdb;
		$metas = [];
		$tb_pm_comments    = pm_tb_prefix() . 'pm_comments';
		$tb_boards         = pm_tb_prefix() . 'pm_boards';
		$tb_boardable      = pm_tb_prefix() . 'pm_boardables';
		$millestone_format = pm_get_prepare_format( $this->millestone_ids );

		$query ="SELECT DISTINCT $tb_boards.*, $tb_boardable.board_id as millestone_id
			FROM $tb_boardable
			LEFT JOIN $tb_boards on $tb_boards.id = $tb_boardable.boardable_id
			WHERE $tb_boardable.board_type = 'milestone'
			AND $tb_boardable.boardable_type = 'discussion_board'
			AND $tb_boardable.board_id IN ($millestone_format)
			group by $tb_boardable.board_id
		";


		$results = $wpdb->get_results( $wpdb->prepare( $query, $this->discussion_ids ) );

		foreach ( $results as $key => $result ) {
			$millestone_id = $result->millestone_id;
			unset($result->millestone_id);
			$metas[$millestone_id] = $result->task_list_count;
		}

		foreach ( $this->millestones as $key => $millestone ) {
			$millestone->meta['total_discussion_board'] = empty( $metas[$millestone->id] ) ? 0 :
			$metas[$millestone->id];
		}

		return $this;
	}

	private function get_selectable_items( $tb, $key ) {
		$select = '';
		$select_items = $this->query_params[$key];

		if ( empty( $select_items ) ) {
			$select = $tb . '.*';
		}

		$select_items = str_replace( ' ', '', $select_items );
		$select_items = explode( ',', $select_items );

		foreach ( $select_items as $key => $item ) {
			$select .= $tb . '.' . $item . ',';
		}

		return substr( $select, 0, -1 );
	}

	private function select() {
		$select = '';

		if ( empty( $this->query_params['select'] ) ) {
			$this->select = $this->tb_millestone . '.*';

			return $this;
		}

		$select_items = $this->query_params['select'];

		if ( ! is_array( $select_items ) ) {
			$select_items = str_replace( ' ', '', $select_items );
			$select_items = explode( ',', $select_items );
		}

		foreach ( $select_items as $key => $item ) {
			$item = str_replace( ' ', '', $item );
			$select .= $this->tb_millestone . '.' . $item . ',';
		}

		$this->select = substr( $select, 0, -1 );

		return $this;
	}

	private function join() {
		return $this;
	}

	private function where() {

		$this->where_id()->where_project_id()
			->where_title();

		return $this;
	}

	/**
	 * Filter list by ID
	 *
	 * @return class object
	 */
	private function where_id() {
		$id = isset( $this->query_params['id'] ) ? $this->query_params['id'] : false;

		if ( empty( $id ) ) {
			return $this;
		}

		if ( is_array( $id ) ) {
			$query_id = implode( ',', $id );
			$this->where .= " AND {$this->tb_millestone}.id IN ($query_id)";
		}

		if ( !is_array( $id ) ) {
			$this->where .= " AND {$this->tb_millestone}.id IN ($id)";

			$explode = explode( ',', $id );

			if ( count( $explode ) == 1 ) {
				$this->is_single_query = true;
			}
		}

		return $this;
	}

	/**
	 * Filter task by title
	 *
	 * @return class object
	 */
	private function where_title() {
		$title = isset( $this->query_params['title'] ) ? $this->query_params['title'] : false;

		if ( empty( $title ) ) {
			return $this;
		}

		$this->where .= " AND {$this->tb_millestone}.title LIKE '%$title%'";

		return $this;
	}

	private function where_project_id() {
		$id = isset( $this->query_params['project_id'] ) ? $this->query_params['project_id'] : false;

		if ( empty( $id ) ) {
			return $this;
		}

		if ( is_array( $id ) ) {
			$query_id = implode( ',', $id );
			$this->where .= " AND {$this->tb_millestone}.project_id IN ($query_id)";
		}

		if ( !is_array( $id ) ) {
			$this->where .= " AND {$this->tb_millestone}.project_id = $id";
		}

		return $this;
	}



	private function limit() {

		$per_page = isset( $this->query_params['per_page'] ) ? $this->query_params['per_page'] : false;

		if ( $per_page === false || $per_page == '-1' ) {
			return $this;
		}

		$this->limit = " LIMIT {$this->get_offset()},{$this->get_per_page()}";

		return $this;
	}

	private function orderby() {
        global $wpdb;

		$tb_pj    = $wpdb->prefix . 'pm_boards';
		$odr_prms = isset( $this->query_params['orderby'] ) ? $this->query_params['orderby'] : false;

        if ( $odr_prms === false && !is_array( $odr_prms ) ) {
            return $this;
        }

        $orders = [];

        $odr_prms = str_replace( ' ', '', $odr_prms );
        $odr_prms = explode( ',', $odr_prms );

        foreach ( $odr_prms as $key => $orderStr ) {
			$orderStr         = str_replace( ' ', '', $orderStr );
			$orderStr         = explode( ':', $orderStr );
			$orderby          = $orderStr[0];
			$order            = empty( $orderStr[1] ) ? 'asc' : $orderStr[1];
			$orders[$orderby] = $order;
        }

        $order = [];

        foreach ( $orders as $key => $value ) {
            $order[] =  $tb_pj .'.'. $key . ' ' . $value;
        }

        $this->orderby = "ORDER BY " . implode( ', ', $order);

        return $this;
    }

	private function get_offset() {
		$page = isset( $this->query_params['page'] ) ? $this->query_params['page'] : false;

		$page   = empty( $page ) ? 1 : absint( $page );
		$limit  = $this->get_per_page();
		$offset = ( $page - 1 ) * $limit;

		return $offset;
	}

	private function get_per_page() {

		$per_page = isset( $this->query_params['per_page'] ) ? $this->query_params['per_page'] : false;

		if ( ! empty( $per_page ) && intval( $per_page ) ) {
			return intval( $per_page );
		}

		return 10;
	}

	private function get() {
		global $wpdb;
		$id = isset( $this->query_params['id'] ) ? $this->query_params['id'] : false;

		$query = "SELECT SQL_CALC_FOUND_ROWS DISTINCT {$this->select}
			FROM {$this->tb_millestone}
			{$this->join}
			WHERE 1=1 {$this->where} AND $this->tb_millestone.type='milestone'
			{$this->orderby} {$this->limit} ";
		$results = $wpdb->get_results( $query );

		$this->found_rows = $wpdb->get_var( "SELECT FOUND_ROWS()" );
		$this->millestones = $results;

		if ( ! empty( $results ) && is_array( $results ) ) {
			$this->millestone_ids = wp_list_pluck( $results, 'id' );
		}

		if ( ! empty( $results ) && !is_array( $results ) ) {
			$this->millestone_ids = [$results->id];
		}

		return $this;
	}


	private function set_table_name() {
		$this->tb_project          = pm_tb_prefix() . 'pm_projects';
		$this->tb_millestone       = pm_tb_prefix() . 'pm_boards';
		$this->tb_task             = pm_tb_prefix() . 'pm_tasks';
		$this->tb_project_user     = pm_tb_prefix() . 'pm_role_user';
		$this->tb_task_user        = pm_tb_prefix() . 'pm_assignees';
		$this->tb_categories       = pm_tb_prefix() . 'pm_categories';
		$this->tb_category_project = pm_tb_prefix() . 'pm_category_project';
	}
}
