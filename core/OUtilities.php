<?php

	/*****************************************************************************

	The MIT License (MIT)
	
	Copyright (c) 2013 Nathan A Obray <nathanobray@gmail.com>
	
	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:
	
	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.
	
	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.
	
	*****************************************************************************/

	if (!class_exists( 'OObject' )) { die(); }

	/********************************************************************************************************************

		WDictionary:	This is our controller object for our Dictionary application.

	********************************************************************************************************************/

	Class OUtilities extends OObject{

		private $permissions = array(
			"object"=>"any",
			"output"=>"any",
			"generateToken"=>"any"
		);


		public function generateToken($params){
			
			$this->safe = FALSE;
			$this->token = hash('sha512',base64_encode(openssl_random_pseudo_bytes(128,$this->safe)));

		}

		public function hasPermission($object){ if( isSet($this->permissions[$object]) && $this->permissions[$object] === 'any'){ return TRUE; } else { return FALSE; }	}

	}
?>