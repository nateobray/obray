<?php
	
    /***********************************************************************
	
    Obray - Super lightweight framework.  Write a little, do a lot, fast.
    Copyright (C) 2013  Nathan A Obray

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
    
    ***********************************************************************/
	
	if (!class_exists( 'OObject' )) { die(); }
	
	/********************************************************************************************************************
		
		AUsers:	Application scoper user manager
		
	********************************************************************************************************************/
	include __PATH_TO_CORE__ . "OUsers.php";
	
	Class AUsers extends OUsers{
		
		public function __construct(){
		
			parent::__construct();
			
			$this->permissions = array(
				"object"=>"user",
				"add"=>10,
				"login"=>"any",
				"get"=>"user",
				"logout"=>"any"
			);
			
		}
		
		
		
	}
	
	
