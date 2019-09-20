<?php
namespace WeDevs\PM\Discussion_Board\Helper;

use WP_REST_Request;
// data: {
// 	with: 'discussion,users',
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

class Discussion_Board {
	private static $_instance;
	private $query_params;
	private $select;
	private $join;
	private $where;
	private $limit;
	private $with;
	private $discussions;
	private $discussion_ids;
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

    public static function get_discussion_boards( WP_REST_Request $request ) {
		$discussions = self::get_results( $request->get_params() );
		wp_send_json( $discussions );
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

		$response = $self->format_discussions( $self->discussions );

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
	public function format_discussions( $discussions ) {
		$response = [
			'data' => [],
			'meta' => []
		];

		if ( ! is_array( $discussions ) ) {
			$response['data'] = $this->fromat_discussion( $discussions );

			return $response;
		}

		foreach ( $discussions as $key => $discussion ) {
			$discussions[$key] = $this->fromat_discussion( $discussion );
		}

		$response['data']  = $discussions;
		$response ['meta'] = $this->set_discussion_meta();

		return $response;
	}

	/**
	 * Set meta data
	 */
	private function set_discussion_meta() {
		return [
			'pagination' => [
				'total'   => $this->found_rows,
				'per_page'       => ceil( $this->found_rows/$this->get_per_page() )
			]
		];
	}

	public function fromat_discussion( $discussion ) {
		$items = [
			'id'          => (int) $discussion->id,
			'title'       => (string) $discussion->title,
			'description' => pm_filter_content_url( $discussion->description ),
			'order'       => (int) $discussion->order,
			'status'      => $discussion->status,
			'created_at'  => format_date( $discussion->created_at ),
			'extra'       => true,
			'project_id'  => $discussion->project_id
        ];

		$select_items = empty( $this->query_params['select'] ) ? null : $this->query_params['select'];

		if ( ! is_array( $select_items ) && !is_null( $select_items ) ) {
			$select_items = str_replace( ' ', '', $select_items );
			$select_items = explode( ',', $select_items );
		}

		if ( empty( $select_items ) ) {
			$items = $this->item_with( $items,$discussion );
			$items = $this->item_meta( $items,$discussion );
			return $items;
		}

		foreach ( $items as $item_key => $item ) {
			if ( ! in_array( $item_key, $select_items ) ) {
				unset( $items[$item_key] );
			}
		}

		$items = $this->item_with( $items, $discussion );
		$items = $this->item_meta( $items, $discussion );

		return $items;
	}

	private function item_with( $items, $discussion ) {
		$with = empty( $this->query_params['with'] ) ? [] : $this->query_params['with'];

		if ( ! is_array( $with ) ) {
			$with = explode( ',', $with );
		}

		$discussion_with_items =  array_intersect_key( (array) $discussion, array_flip( $with ) );

		$items = array_merge($items,$discussion_with_items);

		return $items;
	}

	private function item_meta( $items, $discussion ) {
		$meta = empty( $this->query_params['discussion_meta'] ) ? [] : $this->query_params['discussion_meta'];

		if( ! $meta ) {
			return $items;
		}
		$items['meta'] = empty( $discussion->meta ) ? [ 'data' => [] ] : [ 'data' => $discussion->meta];

		return $items;
	}

	private function with() {
		$this->include_comments()->include_users();
		$this->discussions = apply_filters( 'pm_discussion_with',$this->discussions, $this->discussion_ids, $this->query_params );

		return $this;
	}

	private function include_comments() {
		global $wpdb;
		$with = empty( $this->query_params['with'] ) ? [] : $this->query_params['with'];

		if ( ! is_array( $with ) ) {
			$with = explode( ',', $with );
		}

		$comments = [];

		if ( ! in_array( 'comments', $with ) ) {
			return $this;
		}

		$tb_pm_comments    = pm_tb_prefix() . 'pm_comments';
		$tb_boards         = pm_tb_prefix() . 'pm_boards';
		$discussion_format = pm_get_prepare_format( $this->discussion_ids );

		$query ="SELECT DISTINCT $tb_pm_comments.*,
		$tb_boards.id as discussion_id FROM $tb_pm_comments
			LEFT JOIN $tb_boards  ON $tb_boards.id = $tb_pm_comments.commentable_id
			WHERE $tb_pm_comments.commentable_type = 'discussion_board'
			AND $tb_boards.id IN ($discussion_format)
		";

		$results = $wpdb->get_results( $wpdb->prepare( $query, $this->discussion_ids ) );

		foreach ( $results as $key => $result ) {
			$discussion_id = $result->discussion_id;
			unset($result->discussion_id);
			$comments[$discussion_id] = $result;
		}

		foreach ( $this->discussions as $key => $discussion ) {
			$discussion->comments['data'] = empty( $comments[$discussion->id] ) ? [] : $comments[$discussion->id];
		}

		return $this;
	}

	private function include_users() {
		global $wpdb;
		$with = empty( $this->query_params['with'] ) ? [] : $this->query_params['with'];

		if ( ! is_array( $with ) ) {
			$with = explode( ',', $with );
		}

		$users = [];

		if ( ! in_array( 'users', $with ) ) {
			return $this;
		}

		$tb_users          = pm_tb_prefix() . 'Users';
		$tb_boardable      = pm_tb_prefix() . 'pm_boardables';
		$discussion_format = pm_get_prepare_format( $this->discussion_ids );

		$query ="SELECT DISTINCT $tb_users.* ,
		$tb_boardable.board_id as discussion_id FROM $tb_users
			LEFT JOIN $tb_boardable  ON $tb_boardable.boardable_id = $tb_users.id
			WHERE $tb_boardable.board_type = 'discussion_board'
			AND $tb_boardable.boardable_type = 'user'
			AND $tb_boardable.board_id IN ($discussion_format)
			group by $tb_boardable.board_id
		";

		$results = $wpdb->get_results( $wpdb->prepare( $query, $this->discussion_ids ) );

		foreach ( $results as $key => $result ) {
			$discussion_id = $result->discussion_id;
			unset($result->discussion_id);
			$users[$discussion_id] = $result;
		}

		foreach ( $this->discussions as $key => $discussion ) {
			$discussion->users['data'] = empty( $users[$discussion->id] ) ? [] : $users[$discussion->id];
		}

		return $this;
	}

	private function meta() {
		$meta = empty( $this->query_params['discussion_meta'] ) ? [] : $this->query_params['discussion_meta'];

		if ( ! is_array( $meta ) && $meta != 'all' ) {
			$meta = explode( ',', $meta );
		}

		if ( $meta == 'all' ) {
			$this->total_comments_count();
			$this->total_users_count();
			$this->total_files_count();
		}

		if( in_array('total_comments', $meta ) ) {
			$this->total_comments_count();
		}

		if( in_array('total_users', $meta ) ) {
			$this->total_users_count();
		}

		if( in_array('total_files', $meta ) ) {
			$this->total_files_count();
		}

		$this->get_meta_tb_data();

		return $this;
	}

	private function get_meta_tb_data() {
        global $wpdb;
		$metas             = [];
		$tb_projects       = pm_tb_prefix() . 'pm_projects';
		$tb_meta           = pm_tb_prefix() . 'pm_meta';
		$discussion_format = pm_get_prepare_format( $this->discussion_ids );

        $query = "SELECT DISTINCT $tb_meta.meta_key, $tb_meta.meta_value, $tb_meta.entity_id
            FROM $tb_meta
            WHERE $tb_meta.entity_id IN ($discussion_format)
            AND $tb_meta.entity_type = 'discussion_board'";

        $results = $wpdb->get_results( $wpdb->prepare( $query, $this->discussion_ids ) );

        foreach ( $results as $key => $result ) {
            $discussion_id = $result->entity_id;
            unset( $result->entity_id );
            $metas[$discussion_id][] = $result;
        }

        foreach ( $this->discussions as $key => $discussion ) {
            $filter_metas = empty( $metas[$discussion->id] ) ? [] : $metas[$discussion->id];

            foreach ( $filter_metas as $key => $filter_meta ) {
                $discussion->meta[$filter_meta->meta_key] = $filter_meta->meta_value;
            }
        }

        return $this;
    }

	private function total_comments_count() {
		global $wpdb;
		$metas = [];
		$tb_pm_comments = pm_tb_prefix() . 'pm_comments';
		$tb_boards  = pm_tb_prefix() . 'pm_boards';
		$discussion_format = pm_get_prepare_format( $this->discussion_ids );

		$query ="SELECT DISTINCT count($tb_pm_comments.id) as comment_count,
		$tb_pm_comments.commentable_id as discussion_id FROM $tb_pm_comments
			WHERE $tb_pm_comments.commentable_type = 'discussion_board'
			AND $tb_pm_comments.commentable_id IN ( $discussion_format )
			group by $tb_pm_comments.commentable_id
		";

		$results = $wpdb->get_results( $wpdb->prepare( $query, $this->discussion_ids ) );

		foreach ( $results as $key => $result ) {
			$discussion_id = $result->discussion_id;
			unset($result->discussion_id);
			$metas[$discussion_id] = $result->comment_count;
		}

		foreach ( $this->discussions as $key => $discussion ) {
			$discussion->meta['total_comments'] = empty( $metas[$discussion->id] ) ? 0 :
			$metas[$discussion->id];
		}

		return $this;
	}

	private function total_users_count() {
		global $wpdb;
		$metas = [];
		$tb_users = pm_tb_prefix() . 'Users';
		$tb_boardable  = pm_tb_prefix() . 'pm_boardables';
		$discussion_format = pm_get_prepare_format( $this->discussion_ids );

		$query ="SELECT DISTINCT count($tb_users.id) as user_count,
		$tb_boardable.board_id as discussion_id FROM $tb_users
			LEFT JOIN $tb_boardable  ON $tb_boardable.boardable_id = $tb_users.id
			WHERE $tb_boardable.board_type = 'discussion_board'
			AND $tb_boardable.boardable_type = 'user'
			AND $tb_boardable.board_id IN ($discussion_format)
			group by $tb_boardable.board_id
		";

		$results = $wpdb->get_results( $wpdb->prepare( $query, $this->discussion_ids ) );

		foreach ( $results as $key => $result ) {
			$discussion_id = $result->discussion_id;
			unset($result->discussion_id);
			$metas[$discussion_id] = $result->user_count;
		}

		foreach ( $this->discussions as $key => $discussion ) {
			$discussion->meta['total_users'] = empty( $metas[$discussion->id] ) ? 0 : $metas[$discussion->id];
		}

		return $this;
	}

	private function total_files_count() {
		global $wpdb;
		$metas = [];
		$tb_files  = pm_tb_prefix() . 'pm_files';
		$discussion_format = pm_get_prepare_format( $this->discussion_ids );

		$query ="SELECT DISTINCT count($tb_files.id) as files_count,
		$tb_files.fileable_id as discussion_id FROM $tb_files
			WHERE $tb_files.fileable_type = 'discussion_board'
			AND $tb_files.fileable_id IN ( $discussion_format )
			group by $tb_files.fileable_id
		";

		$results = $wpdb->get_results( $wpdb->prepare( $query, $this->discussion_ids ) );

		foreach ( $results as $key => $result ) {
			$discussion_id = $result->discussion_id;
			unset($result->discussion_id);
			$metas[$discussion_id] = $result->files_count;
		}

		foreach ( $this->discussions as $key => $discussion ) {
			$discussion->meta['total_files'] = empty( $metas[$discussion->id] ) ? 0 : $metas[$discussion->id];
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
			$this->select = $this->tb_discussion . '.*';

			return $this;
		}

		$select_items = $this->query_params['select'];

		if ( ! is_array( $select_items ) ) {
			$select_items = str_replace( ' ', '', $select_items );
			$select_items = explode( ',', $select_items );
		}

		foreach ( $select_items as $key => $item ) {
			$item = str_replace( ' ', '', $item );
			$select .= $this->tb_discussion . '.' . $item . ',';
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
			$this->where .= " AND {$this->tb_discussion}.id IN ($query_id)";
		}

		if ( !is_array( $id ) ) {
			$this->where .= " AND {$this->tb_discussion}.id IN ($id)";

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

		$this->where .= " AND {$this->tb_discussion}.title LIKE '%$title%'";

		return $this;
	}

	private function where_project_id() {
		$id = isset( $this->query_params['project_id'] ) ? $this->query_params['project_id'] : false;

		if ( empty( $id ) ) {
			return $this;
		}

		if ( is_array( $id ) ) {
			$query_id = implode( ',', $id );
			$this->where .= " AND {$this->tb_discussion}.project_id IN ($query_id)";
		}

		if ( !is_array( $id ) ) {
			$this->where .= " AND {$this->tb_discussion}.project_id = $id";
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
			FROM {$this->tb_discussion}
			{$this->join}
			WHERE 1=1 {$this->where} AND $this->tb_discussion.type='discussion_board'
			{$this->orderby} {$this->limit} ";

		$results = $wpdb->get_results( $query );

		$this->found_rows = $wpdb->get_var( "SELECT FOUND_ROWS()" );
		$this->discussions = $results;

		if ( ! empty( $results ) && is_array( $results ) ) {
			$this->discussion_ids = wp_list_pluck( $results, 'id' );
		}

		if ( ! empty( $results ) && !is_array( $results ) ) {
			$this->discussion_ids = [$results->id];
		}

		return $this;
	}


	private function set_table_name() {
		$this->tb_project          = pm_tb_prefix() . 'pm_projects';
		$this->tb_discussion       = pm_tb_prefix() . 'pm_boards';
		$this->tb_task             = pm_tb_prefix() . 'pm_tasks';
		$this->tb_project_user     = pm_tb_prefix() . 'pm_role_user';
		$this->tb_task_user        = pm_tb_prefix() . 'pm_assignees';
		$this->tb_categories       = pm_tb_prefix() . 'pm_categories';
		$this->tb_category_project = pm_tb_prefix() . 'pm_category_project';
	}
}
