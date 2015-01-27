<?php

class Crunchbutton_Community extends Cana_Table_Trackchange {

	const CUSTOMER_SERVICE_ID_COMMUNITY = 92;
	const CUSTOMER_SERVICE_COMMUNITY_GROUP = 'support';

	public static function all($force = null) {
		$ip = preg_replace('/[^0-9\.]+/','',$_SERVER['REMOTE_ADDR']);
		$force = preg_replace('/[^a-z\-]+/','',$force);
		if ($force) {
			$forceq = ' OR (community.permalink="'.c::db()->escape($force).'") ';
		}

		$q = '
			select community.* from community
			left join community_ip on community_ip.id_community=community.id_community
			where
				community.active=1
				AND (
					( community.private=0 )
					OR
					(community.private=1 AND community_ip.ip="'.c::db()->escape($ip).'")
					'.$forceq.'
				)
			group by community.id_community
			order by name
		';

		return self::q($q);
	}

	public function restaurantByLoc() {
		if (!isset($this->_restaurantsByLoc)) {
			$this->_restaurantsByLoc = Restaurant::byRange([
				'lat' => $this->loc_lat,
				'lon' => $this->loc_lon,
				'range' => $this->range,
			]);
		}
		return $this->_restaurantsByLoc;
	}

	/**
	 * Returns all the restaurants that belong to this Community
	 *
	 * @return Cana_Iterator
	 *
	 * @todo probably not required to sort them as the front end sorts them
	 */
	public function restaurants() {
		if (!isset($this->_restaurants)) {
			$this->_restaurants = Restaurant::q('
				SELECT
					restaurant.*
					, restaurant_community.sort
				FROM restaurant
					left join restaurant_community using(id_restaurant)
				WHERE
						id_community="'.$this->id_community.'"
					and restaurant.active=1
				ORDER by
					restaurant_community.sort,
					restaurant.delivery DESC
			');
/*
			$this->_restaurants->sort([
				'function' => 'open'
			]);
*/
		}
		return $this->_restaurants;
	}

	/**
	 * Returns all data related to this Community
	 *
	 * @return array
	 *
	 * @see Cana_Table::exports()
	 */
	public function exports() {
		$out = $this->properties();
		$out[ 'name_alt' ] = $this->name_alt();
		$out[ 'prep' ] = $this->prep();
		$out[ 'active' ] = intval( $out[ 'active' ] );
		$out[ 'private' ] = intval( $out[ 'private' ] );
		$out[ 'image' ] = intval( $out[ 'image' ] );
		$out[ 'close_all_restaurants' ] = intval( $out[ 'close_all_restaurants' ] );
		$out[ 'close_3rd_party_delivery_restaurants' ] = intval( $out[ 'close_3rd_party_delivery_restaurants' ] );

		if( $out[ 'close_all_restaurants_id_admin' ] ){
			$admin = Admin::o( $out[ 'close_all_restaurants_id_admin' ] );
			$out[ 'close_all_restaurants_admin' ] = $admin->name;
			$date = new DateTime( $out[ 'close_all_restaurants_date' ], new DateTimeZone( c::config()->timezone ) );
			$out[ 'close_all_restaurants_date' ] = $date->format( 'M jS Y g:i:s A T' );
		}

		if( $out[ 'close_3rd_party_delivery_restaurants_id_admin' ] ){
			$admin = Admin::o( $out[ 'close_3rd_party_delivery_restaurants_id_admin' ] );
			$out[ 'close_3rd_party_delivery_restaurants_admin' ] = $admin->name;
			$date = new DateTime( $out[ 'close_3rd_party_delivery_restaurants_date' ], new DateTimeZone( c::config()->timezone ) );
			$out[ 'close_3rd_party_delivery_restaurants_date' ] = $date->format( 'M jS Y g:i:s A T' );
		}

		$next_sort = Crunchbutton_Community_Alias::q( 'SELECT MAX(sort) AS sort FROM community_alias WHERE id_community = ' . $this->id_community );
		if( $next_sort->sort ){
			$sort = $next_sort->sort + 1;
		} else {
			$sort = 1;
		}
		$out['next_sort'] = $sort;

		foreach ($this->restaurants() as $restaurant) {
			$out['_restaurants'][$restaurant->id_restaurant.' '] = $restaurant->exports();
		}
		return $out;
	}

	public function allRestaurantsClosed(){
		if( $this->close_all_restaurants > 0 ){
			return $this->close_all_restaurants_note;
		}
		return false;
	}

	public function allThirdPartyDeliveryRestaurantsClosed(){
		if( $this->close_3rd_party_delivery_restaurants > 0 ){
			return $this->close_3rd_party_delivery_restaurants_note;
		}
		return false;
	}


	public static function permalink($permalink) {
		return self::q('select * from community where permalink="'.$permalink.'"')->get(0);
	}

	public static function all_locations(){
		$res = Cana::db()->query( 'SELECT c.id_community, c.loc_lat, c.loc_lon, c.range FROM community c' );
		$locations = array();
		while ( $row = $res->fetch() ) {
			$locations[ $row->id_community ] = array( 'loc_lat' => $row->loc_lat, 'loc_lon' => $row->loc_lon, 'range' => $row->range );
		}
		return $locations;
	}

	public function name_alt(){
		$alias = Community_Alias::alias( $this->permalink );
		if( !$alias ){
			$alias = Community_Alias::community( $this->id_community );
		}
		if( $alias ){
			return $alias[ 'name_alt' ];
		}
		return $this->name_alt;
	}

	public function aliases(){
		if( !$this->_aliases ){
			$this->_aliases = Crunchbutton_Community_Alias::q( 'SELECT * FROM community_alias WHERE id_community = ' . $this->id_community . ' ORDER BY alias ASC' );
		}
		return $this->_aliases;
	}

	public function prep(){
		$alias = Community_Alias::alias( $this->permalink );
		if( !$alias ){
			$alias = Community_Alias::community( $this->id_community );
		}
		if( $alias ){
			return $alias[ 'prep' ];
		}
		return $this->prep;
	}

	public function __construct($id = null) {
		parent::__construct();
		$this
			->table('community')
			->idVar('id_community')
			->load($id);
	}

	function groupOfDrivers(){
		if (!isset($this->_groupOfDrivers)) {
			$group = Crunchbutton_Group::byName($this->driverGroup());
			if (!$group->id_group) {
				$group = Crunchbutton_Group::createDriverGroup($this->driverGroup(), $this->name);
			}
			$this->_groupOfDrivers = $group;
		}
		return $this->_groupOfDrivers;
	}

	function groupOfMarketingReps(){
		if (!isset($this->_groupOfMarketingReps)) {
			$group = Crunchbutton_Group::q( 'SELECT * FROM `group` WHERE id_community = "' . $this->id_community . '" AND type = "' . Crunchbutton_Group::TYPE_MARKETING_REP . '" ORDER BY id_group DESC LIMIT 1 ' );
			if (!$group->id_group) {
				$group = Crunchbutton_Group::createMarketingRepGroup( $this->id_community );
			}
			$this->_groupOfMarketingReps = $group;
		}
		return $this->_groupOfMarketingReps;
	}

	public function communityByDriverGroup( $group ){
		return Crunchbutton_Community::q( 'SELECT * FROM community WHERE driver_group = "' . $group . '"' );
	}

	public function active(){
		return Crunchbutton_Community::q( 'SELECT * FROM community WHERE active = 1 ORDER BY name ASC' );
	}

	public function getDriversOfCommunity(){
		$group = $this->driverGroup();

		$query = 'SELECT a.* FROM admin a
												INNER JOIN (
													SELECT DISTINCT(id_admin) FROM (
													SELECT DISTINCT(a.id_admin)
														FROM admin a
														INNER JOIN notification n ON n.id_admin = a.id_admin AND n.active = 1
														LEFT JOIN admin_notification an ON a.id_admin = an.id_admin AND an.active = 1
														INNER JOIN restaurant r ON r.id_restaurant = n.id_restaurant
														INNER JOIN restaurant_community c ON c.id_restaurant = r.id_restaurant AND c.id_community = ' . $this->id_community . '
													UNION
													SELECT DISTINCT(a.id_admin) FROM admin a
														INNER JOIN admin_group ag ON ag.id_admin = a.id_admin
														INNER JOIN `group` g ON g.id_group = ag.id_group AND g.name = "' . $group . '"
														) drivers
													)
											drivers ON drivers.id_admin = a.id_admin AND a.active = 1 ORDER BY name ASC';
		return Admin::q( $query );
	}

	public function slug(){
		return str_replace( ' ' , '-', strtolower( $this->name ) );
	}

	public function totalUsersByCommunity(){
		$chart = new Crunchbutton_Chart_User();
		$total = $chart->totalUsersByCommunity( $this->id_community );
		$all = $chart->totalUsersAll();

		$percent = intval( $total * 100 / $all );

		return [ 'community' => $total, 'all' => $all, 'percent' => $percent ];
	}

	public function totalOrdersByCommunity(){
		$chart = new Crunchbutton_Chart_Order();
		$total = $chart->totalOrdersByCommunity( $this->id_community );
		$all = $chart->totalOrdersAll();

		$percent = intval( $total * 100 / $all );

		return [ 'community' => $total, 'all' => $all, 'percent' => $percent ];
	}

	public function newUsersLastWeek(){

		$chart = new Crunchbutton_Chart_User();

		$now = new DateTime( 'now', new DateTimeZone( c::config()->timezone ) );
		$now->modify( '-1 day' );
		$chart->dayTo = $now->format( 'Y-m-d' );
		$now->modify( '-6 days' );
		$chart->dayFrom = $now->format( 'Y-m-d' );
		$chart->justGetTheData = true;
		$orders = $chart->newByDayByCommunity( false, $this->slug() );

		$now->modify( '+6 day' );

		$_orders = [];

		// fill empty spaces
		for( $i = 0; $i <= 6 ; $i++ ){
			$_orders[ $now->format( 'Y-m-d' ) ] = ( $orders[ $now->format( 'Y-m-d' ) ] ? $orders[ $now->format( 'Y-m-d' ) ] : '0' );
			$now->modify( '-1 day' );
		}

		$total = 0;
		$week = [];

		foreach( $_orders as $day => $value ){
			$total += $value;
			$week[] = $value;
		}
		return [ 'total' => $total, 'week' => join( ',', $week ) ];
	}

	public function getOrdersFromLastDaysByCommunity( $days = 14 ){
		$query = "SELECT SUM(1) orders, DATE_FORMAT( o.date, '%m/%d/%Y' ) day FROM `order` o
					INNER JOIN restaurant r ON r.id_restaurant = o.id_restaurant
					INNER JOIN restaurant_community rc ON r.id_restaurant = rc.id_restaurant AND rc.id_community = '{$this->id_community}'
					WHERE o.date > DATE_SUB(CURDATE(), INTERVAL $days DAY) AND o.name NOT LIKE '%test%' GROUP BY day ORDER BY o.date ASC";
		return c::db()->get( $query );
	}

	public function ordersLastWeek(){

		$chart = new Crunchbutton_Chart_Order();

		$now = new DateTime( 'now', new DateTimeZone( c::config()->timezone ) );
		$now->modify( '-1 day' );
		$chart->dayTo = $now->format( 'Y-m-d' );
		$now->modify( '-6 days' );
		$chart->dayFrom = $now->format( 'Y-m-d' );
		$chart->justGetTheData = true;
		$orders = $chart->byDayPerCommunity( false, $this->slug() );

		$now->modify( '+6 day' );

		$_orders = [];

		// fill empty spaces
		for( $i = 0; $i <= 6 ; $i++ ){
			$_orders[ $now->format( 'Y-m-d' ) ] = ( $orders[ $now->format( 'Y-m-d' ) ] ? $orders[ $now->format( 'Y-m-d' ) ] : '0' );
			$now->modify( '-1 day' );
		}

		$total = 0;
		$week = [];

		foreach( $_orders as $day => $value ){
			$total += $value;
			$week[] = $value;
		}
		return [ 'total' => $total, 'week' => join( ',', $week ) ];
	}

	public function getRestaurants(){
		return Restaurant::q( 'SELECT * FROM restaurant r INNER JOIN restaurant_community rc ON rc.id_restaurant = r.id_restaurant AND rc.id_community = ' . $this->id_community . ' ORDER BY r.name' );
	}

	public function driverDeliveryHere( $id_admin ){
		$group = $this->groupOfDrivers();

		if( $group->id_group ){
			$admin_group = Crunchbutton_Admin_Group::q( "SELECT * FROM admin_group ag WHERE ag.id_group = {$group->id_group} AND ag.id_admin = {$id_admin} LIMIT 1" );
			if( $admin_group->id_admin_group ){
				return true;
			}
			return false;
		} else {
			return false;
		}
		return false;
	}

	public function driverGroup(){
		if( !$this->driver_group ){
			$this->driver_group = Crunchbutton_Group::driverGroupOfCommunity( $this->name );
			$this->save();
		}
		return $this->driver_group;
	}

	public function marketingRepGroup(){
		if( !$this->_marketing_rep_group ){
			$this->_marketing_rep_group = Crunchbutton_Group::marketingRepGroupOfCommunity( $this->id_community );
		}
		return $this->_marketing_rep_group;
	}

	/**
	 * Returns the Testing community
	 *
	 * @return Crunchbutton_Community
	 */
	public function getTest(){
		$row = $this->q('SELECT * FROM community WHERE name="Testing" ')->current();
		return $row;
	}

	public function totalDriversByCommunity(){

		$drivers = $this->getDriversOfCommunity();
		$total = $drivers->count();

		$drivers = Admin::drivers();
		$all = $drivers->count();

		$percent = intval( $total * 100 / $all );

		return [ 'community' => $total, 'all' => $all, 'percent' => $percent ];
	}

	public function hasShiftThisWeek(){
		$now = new DateTime( 'now', new DateTimeZone( c::config()->timezone  ) );
		if( $now->format( 'l' ) == 'Sunday' ){
			$day = $now;
		} else {
			$day = new DateTime( 'last sunday', new DateTimeZone( c::config()->timezone  ) );
		}
		$from = $day->format( 'Y-m-d' );
		$day->modify( '+6 days' );
		$to = $day->format( 'Y-m-d' );
		$shifts = Crunchbutton_Community_Shift::q( 'SELECT COUNT(*) AS shifts FROM community_shift cs WHERE DATE_FORMAT( cs.date_start, "%Y-%m-%d" ) >= "' . $from . '" AND DATE_FORMAT( cs.date_end, "%Y-%m-%d" ) <= "' . $to . '" AND id_community = "' . $this->id_community . '" ORDER BY cs.date_start ASC' );
		return ( $shifts->shifts > 0 );
	}

	public function hasShiftByPeriod( $from = false, $to = false ){
		return Crunchbutton_Community_Shift::shiftsByCommunityPeriod( $this->id_community, $from, $to );
	}

	public function totalRestaurantsByCommunity(){

		$query = "SELECT COUNT(*) AS Total FROM restaurant r INNER JOIN restaurant_community rc ON rc.id_restaurant = r.id_restaurant AND rc.id_community = {$this->id_community}";

		$result = c::db()->get( $query );
		$total = $result->_items[0]->Total;

		$query = "SELECT COUNT(*) AS Total FROM restaurant WHERE active = 1 AND name NOT LIKE '%test%'";
		$result = c::db()->get( $query );
		$all = $result->_items[0]->Total;

		$percent = intval( $total * 100 / $all );

		return [ 'community' => $total, 'all' => $all, 'percent' => $percent ];
	}

	public function closedSince(){

		$force_closed_times = Crunchbutton_Community_Changeset::q( 'SELECT ccs.*, cc.field FROM community_change cc
																																	INNER JOIN community_change_set ccs ON ccs.id_community_change_set = cc.id_community_change_set AND id_community = "' . $this->id_community . '"
																																	AND ( cc.field = "close_all_restaurants" OR cc.field = "close_3rd_party_delivery_restaurants" )
																																	AND cc.new_value = 1
																																	ORDER BY cc.id_community_change DESC' );
		$out = [];
		if( $force_closed_times->count() ){
			foreach( $force_closed_times as $force_close ){
				$output = [];
				$closed_at = $force_close->date();
				$output[ 'closed_at' ] = $closed_at->format( 'M jS Y g:i:s A T' );
				$output[ 'closed_by' ] = $force_close->admin()->name;
				if( $force_close->field == 'close_all_restaurants' ){
					$output[ 'type' ] = 'Close All Restaurants';
				} else if ( $force_close->field == 'close_3rd_party_delivery_restaurants' ){
					$output[ 'type' ] = 'Close 3rd Party Delivery Restaurants';
				}
				$output[ 'note' ] = $this->_closedNote( $force_close->id_community_change_set, $force_close->field );
				$open = $this->_openedAt( $force_close->id_community_change_set, $force_close->field );
				if( !$open ){
					$now = new DateTime( 'now', new DateTimeZone( c::config()->timezone ) );
					$interval = $now->diff( $closed_at );
					$output[ 'how_long' ] = Crunchbutton_Util::format_interval( $interval );
					$out[] = $output;
				}
			}
		}
		// if the community was closed before we start logging it
		else {
			if( $this->close_all_restaurants ){
				$output = [];
				$closed_at = new DateTime( $this->close_all_restaurants_date, new DateTimeZone( c::config()->timezone ) );
				$output[ 'type' ] = 'Close All Restaurants';
				$output[ 'closed_at' ] = $closed_at->format( 'M jS Y g:i:s A T' );
				$output[ 'closed_by' ] = Admin::o( $this->close_all_restaurants_id_admin )->name;
				$output[ 'note' ] = $this->close_all_restaurants_note;
				$now = new DateTime( 'now', new DateTimeZone( c::config()->timezone ) );
				$interval = $now->diff( $closed_at );
				$output[ 'how_long' ] = Crunchbutton_Util::format_interval( $interval );
				$out[] = $output;
			}
			if( $this->close_3rd_party_delivery_restaurants ){
				$output = [];
				if( $this->close_3rd_party_delivery_restaurants_date ){
					$closed_at = new DateTime( $this->close_3rd_party_delivery_restaurants_date, new DateTimeZone( c::config()->timezone ) );
					$output[ 'closed_at' ] = $closed_at->format( 'M jS Y g:i:s A T' );
					$now = new DateTime( 'now', new DateTimeZone( c::config()->timezone ) );
					$interval = $now->diff( $closed_at );
					$output[ 'how_long' ] = Crunchbutton_Util::format_interval( $interval );
				} else {
					$output[ 'closed_at' ] = '-';
				}
				$output[ 'type' ] = 'Close 3rd Party Delivery Restaurants';
				$output[ 'closed_by' ] = Admin::o( $this->close_3rd_party_delivery_restaurants_id_admin )->name;
				$output[ 'note' ] = $this->close_3rd_party_delivery_restaurants_note;
				$out[] = $output;
			}
		}

		return $out;
	}

	public function forceCloseLog(){
		$force_closed_times = Crunchbutton_Community_Changeset::q( 'SELECT ccs.*, cc.field FROM community_change cc
																																	INNER JOIN community_change_set ccs ON ccs.id_community_change_set = cc.id_community_change_set AND id_community = "' . $this->id_community . '"
																																	AND ( cc.field = "close_all_restaurants" OR cc.field = "close_3rd_party_delivery_restaurants" )
																																	AND cc.new_value = 1
																																	ORDER BY cc.id_community_change DESC' );
		$out = [];
		foreach( $force_closed_times as $force_close ){
			$output = [];
			$closed_at = $force_close->date();
			$output[ 'closed_at' ] = $closed_at->format( 'M jS Y g:i:s A T' );
			$output[ 'closed_by' ] = $force_close->admin()->name;

			if( $force_close->field == 'close_all_restaurants' ){
				$output[ 'type' ] = 'Close All Restaurants';
			} else if ( $force_close->field == 'close_3rd_party_delivery_restaurants' ){
				$output[ 'type' ] = 'Close 3rd Party Delivery Restaurants';
			}

			$output[ 'note' ] = $this->_closedNote( $force_close->id_community_change_set, $force_close->field );

			$open = $this->_openedAt( $force_close->id_community_change_set, $force_close->field );
			if( $open ){
				$opened_at = $open->date();
				$output[ 'opened_at' ] = $opened_at->format( 'M jS Y g:i:s A T' );
				$output[ 'opened_by' ] = $open->admin()->name;
				$interval = $opened_at->diff( $closed_at );
				$output[ 'how_long' ] = Crunchbutton_Util::format_interval( $interval );
			} else {
				$output[ 'how_long' ] = 'It is still closed!';
			}
			$out[] = $output;
		}
		echo json_encode( $out );exit;
	}

	private function _closedNote( $id_community_change_set, $field ){
		$field = $field . '_note';
		$note = Crunchbutton_Community_Changeset::q( 'SELECT
																											ccs.*, cc.field, cc.new_value FROM community_change cc
																											INNER JOIN community_change_set ccs ON ccs.id_community_change_set = cc.id_community_change_set AND id_community = "' . $this->id_community . '"
																											AND cc.field = "' . $field . '"
																											AND ccs.id_community_change_set = ' . $id_community_change_set . '
																											ORDER BY cc.id_community_change DESC LIMIT 1' )->get( 0 );
		if( $note->new_value ){
			return $note->new_value;
		}
		return false;
	}

	private function _openedAt( $id_community_change_set, $field ){
		$opened = Crunchbutton_Community_Changeset::q( 'SELECT
																											ccs.*, cc.field FROM community_change cc
																											INNER JOIN community_change_set ccs ON ccs.id_community_change_set = cc.id_community_change_set AND id_community = "' . $this->id_community . '"
																											AND cc.field = "' . $field . '"
																											AND ( cc.new_value = 0 OR cc.new_value IS NULL ) AND ccs.id_community_change_set > ' . $id_community_change_set . '
																											ORDER BY cc.id_community_change DESC LIMIT 1' )->get( 0 );
		if( $opened->id_community_change_set ){
			return $opened;
		}
		return false;
	}

	public function currentShift(){
		return Crunchbutton_Community_Shift::currentShiftByCommunity( $this->id_community )->get( 0 );
	}

}