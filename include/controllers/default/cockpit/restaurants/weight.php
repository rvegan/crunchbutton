<?php

class Controller_Restaurants_Weight extends Crunchbutton_Controller_Account {

	public function _form(){
		$view = Cana::view();
		$view->display( 'restaurants/weight/form' );
	}

	public function _restaurants(){

		if ( $_REQUEST[ 'lat' ] && $_REQUEST[ 'lon' ] ) {

			$restaurants = Restaurant::byRange( [
				'lat' => c::db()->escape($_REQUEST['lat']),
				'lon' => c::db()->escape($_REQUEST['lon']),
				'range' => 2,
			] );

			$sort = [];
			foreach ( $restaurants as $restaurant ) {
				$sort[] = $restaurant;
			}

			usort( $sort, function($a, $b){ return $a->_weight < $b->_weight; } );

			c::view()->restaurants = $sort;
			c::view()->layout( 'layout/ajax' );
			c::view()->display('restaurants/weight/restaurants');
		} 

	}

	public function init() {

		if (!c::admin()->permission()->check(['global', 'restaurants-all', 'restaurants-crud', 'restaurants-weight-adj-page'])) {
			return;
		}

		if( c::getPagePiece( 2 ) == 'restaurants' ) {
			$this->_restaurants();
		} else {
			$this->_form();	
		}

	}

}
