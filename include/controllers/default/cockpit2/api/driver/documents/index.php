<?php

class Controller_api_driver_documents extends Crunchbutton_Controller_RestAccount {

	public function init() {

		switch ( c::getPagePiece( 3 ) ) {

			case 'upload':

				if( $_FILES ){
					$ext = pathinfo( $_FILES['file']['name'], PATHINFO_EXTENSION );
					if( Util::allowedExtensionUpload( $ext ) ){
						$name = pathinfo( $_FILES['file']['name'], PATHINFO_FILENAME );
						$name = str_replace( $ext, '', $name );
						$random = substr( str_replace( '.' , '', uniqid( rand(), true ) ), 0, 8 );
						$name = Util::slugify( $random . '-' . $name );
						$name = substr( $name, 0, 40 ) . '.'. $ext;
						$file = Cockpit_Driver_Document_Status::path() . $name;

						if( !file_exists( Util::uploadPath() ) ){
							Log::debug( [ 'action' => 'upload file error', 'error' => '"www/upload" folder doesn`t exist!', 'type' => 'drivers-onboarding'] );
							$this->_error( '"www/upload" folder doesn`t exist!' );
						}

						if( !file_exists( Cockpit_Driver_Document_Status::path() ) ){
							Log::debug( [ 'action' => 'upload file error', 'error' => '"www/upload/drivers-doc/" folder doens`t exist!', 'type' => 'drivers-onboarding'] );
							$this->_error( '"www/upload/drivers-doc/" folder doens`t exist!' );
						}

						if ( copy( $_FILES[ 'file' ][ 'tmp_name' ], $file ) ) {
							chmod( $file, 0777 );
						} else {
							Log::debug( [ 'action' => 'upload file error', 'error' => 'copy file error', 'type' => 'drivers-onboarding'] );
						}

						Log::debug( [ 'action' => 'upload file success', 'file name' => $name, 'type' => 'drivers-onboarding'] );

						echo json_encode( ['success' => $name ] );
						exit;
					} else {
						$this->_error( 'invalid extension' );
					}
				} else {
					$this->_error();
				}
				break;

			case 'save':

				// check the permission
				$id_admin = $this->request()[ 'id_admin' ];
				$user = c::user();
				$hasPermission = ( c::admin()->permission()->check( ['global', 'drivers-all'] ) || ( $id_admin == $user->id_admin ) );
				if( !$hasPermission ){
					$this->_error();
					exit;
				}

				$id_driver_document = $this->request()[ 'id_driver_document' ];
				if( $id_admin && $id_driver_document ){
					$docStatus = Cockpit_Driver_Document_Status::document( $id_admin, $id_driver_document );
					if( !$docStatus->id_driver_document_status ){
						$docStatus->id_admin = $id_admin;
						$docStatus->id_driver_document = $id_driver_document;
					} else {
						$oldFile = Cockpit_Driver_Document_Status::path() . $docStatus->file;
						if( file_exists( $oldFile ) ){
							@unlink( $oldFile );
						}
					}
					$docStatus->datetime = date('Y-m-d H:i:s');
					$docStatus->file = $this->request()[ 'file' ];
					$docStatus->save();

					// save driver's log
					$log = new Cockpit_Driver_Log();
					$log->id_admin = $id_admin;
					$log->action = Cockpit_Driver_Log::ACTION_DOCUMENT_SENT;
					$log->info = $docStatus->id_driver_document . ': ' . $docStatus->file;
					$log->datetime = date('Y-m-d H:i:s');
					$log->save();

					Log::debug( [ 'action' => 'file saved success', 'id_driver_document' => $id_driver_document, 'type' => 'drivers-onboarding'] );

					echo json_encode( ['success' => 'success'] );
				} else {
					$this->_error();
				}
				break;

			case 'pendency':
					$admin = Crunchbutton_Admin::o( c::getPagePiece( 4 ) );
					if( !$admin->id_admin ){
						echo $this->_error();
					}

					$user = c::user();
					$hasPermission = ( c::admin()->permission()->check( ['global', 'drivers-all'] ) || ( $admin->id_admin == $user->id_admin ) );
					if( !$hasPermission ){
						echo $this->_error();
					}

					$needToSendDocs = false;
					$docs = Cockpit_Driver_Document::all();
					foreach( $docs as $doc ){
						if( $doc->required ){
							$docStatus = Cockpit_Driver_Document_Status::document( $admin->id_admin, $doc->id_driver_document );
							if( $docStatus->id_driver_document_status ){
								$needToSendDocs = true;
							}
						}
					}
					echo json_encode( [ 'needToSendDocs' => $needToSendDocs ] );
				break;

			default:

				$id_admin = false;
				if( c::getPagePiece( 3 ) ){
					$admin = Crunchbutton_Admin::o( c::getPagePiece( 3 ) );
					if( $admin->id_admin ){
						$id_admin = $admin->id_admin;
					}
				}

				// Check if the logged user has permission to see the admin's docs
				$user = c::user();
				$hasPermission = ( c::admin()->permission()->check( ['global', 'drivers-all'] ) || ( $id_admin == $user->id_admin ) );

				// get driver's vehicle
				$vehicle = $admin->vehicle();

				// shows the regular list
				$list = [];
				$docs = Cockpit_Driver_Document::all();
				foreach( $docs as $doc ){
					if( !$doc->showDocument( $vehicle ) ){
						continue;
					}
					$out = $doc->exports();;
					if( $id_admin && $hasPermission ){
						$docStatus = Cockpit_Driver_Document_Status::document( $id_admin, $doc->id_driver_document );
						if( $docStatus->id_driver_document_status ){
							$out[ 'status' ] = $docStatus->exports();
						}
					}

					$list[] = $out;
				}
				echo json_encode( $list );
				break;
		}
	}

	private function _error( $error = 'invalid request' ){
		echo json_encode( [ 'error' => $error ] );
		exit();
	}
}