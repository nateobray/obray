<?php

	/*****************************************************************************

	The MIT License (MIT)
	
	Copyright (c) 2014 Nathan A Obray
	
	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the 'Software'), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:
	
	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.
	
	THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.
	
	*****************************************************************************/

	if (!class_exists( 'OObject' )) { die(); }

	/********************************************************************************************************************

		OUsers:	User/Permission Manager

	********************************************************************************************************************/
	
	Class oUserRoles extends ODBO{

		public function __construct(){
			
			parent::__construct();

			$this->table = 'oUserRoles';
			$this->table_definition = array(
				'ouser_role_id' => 	array( 'primary_key' => TRUE ),
				'orole_id' => 		array( 'data_type'=>'integer',		'required'=>TRUE    ),
				'ouser_id' => 		array( 'data_type'=>'integer',		'required'=>TRUE    )
			);
			
			$this->permissions = array(
                'object' => 'any',
				'getArray' => 'any'
			);

        }

        public function getArray(){

            $sql = "SELECT oPermissions.opermission_code, oRoles.orole_code 
                        FROM oUserRoles
                        JOIN oRoles ON oRoles.orole_id = oUserRoles.orole_id
                LEFT JOIN oRolePermissions ON oRolePermissions.orole_id = oUserRoles.orole_id
                        JOIN oPermissions ON oPermissions.opermission_id = oRolePermissions.opermission_id
                    WHERE oUserRoles.ouser_id = :ouser_id";

            try {
                $statement = $this->dbh->prepare($sql);
                $statement->bindValue(':ouser_id', $_SESSION["ouser"]->ouser_id);
                $result = $statement->execute();
                $this->data = [];
                $statement->setFetchMode(PDO::FETCH_OBJ);
                while ($row = $statement->fetch()) {
                    $this->data[] = $row;
                }
            } catch (Exception $e) {
                $this->throwError($e);
                $this->logError(oCoreProjectEnum::ODBO, $e);
            }

            $roles = array(); $permissions = array();
            forEach( $this->data as $codes ){
                if( !in_array($codes->orole_code,$roles) ){
                    $roles[] = $codes->orole_code;
                }
                if( !in_array($codes->opermission_code,$permissions) ){
                    $permissions[] = $codes->opermission_code;
                }
            }

            $this->data = array(
                "permissions" => $permissions,
                "roles" => $roles
            );

        }

	}
?>