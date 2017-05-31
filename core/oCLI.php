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
	
	Class oCLI extends ODBO{

		private $windows = array();
		private $rows = 0;
		private $cols = 0;

		/****************************************************************************************************************

			Initiates a CLI application

				This will initiate a fullscreen window with a border and title then will draw it to the screen.  after
				calling openCLI call on of the other component functions as needed and when complete call closeCLI.

				1.  initiate ncurses
				2.	Create a full-screen window
				3.	Disable echoing the characters without our control
				4.	let ncurses know we wish to use the whole screen
				5.	get screen size
				6.  draw a border around the whole thing.
				7.	set the application title
				8.	paint window

		****************************************************************************************************************/

		public function openCLI( $title=NULL ){

			// 1. initiate ncurses
			ncurses_init();

			// Create a full-screen window
			$this->window = ncurses_newwin(0, 0, 0, 0);

			// Disable echoing the characters without our control
			ncurses_noecho();

			// let ncurses know we wish to use the whole screen
			$screen = ncurses_newwin ( 0, 0, 0, 0);

			// get screen size
			ncurses_getmaxyx($screen, $this->rows, $this->cols);

			// draw a border around the whole thing.
			ncurses_border(0,0, 0,0, 0,0, 0,0);

			// set the application title
			if( !is_null($title) ){  ncurses_mvaddstr(0,2," ".$title." ");  }

			// paint window
			ncurses_refresh();


		}

		public function progress( $name, $percent, $rows=NULL, $cols=NULL, $x=NULL, $y=NULL ){

			if( empty( $windows[$name] ) ){

				$windows[$name] = new stdClass();

				// set defaults to a dynamicly sized window
				if( is_null($rows) ){ $rows = 3; }
				if( is_null($cols) ){ $cols = $this->cols - 4; }
				if( is_null($x) ){ $x = $this->rows-4; }
				if( is_null($y) ){ $y = 2; }

				// draw our new window
				$windows[$name]->bar = ncurses_newwin($rows, $cols, $x, $y);
				$windows[$name]->progress = ncurses_newwin($rows-2, $cols-2, $x+1, $y+1);

				// border our progress bar.
				ncurses_wborder($windows[$name]->bar,0,0, 0,0, 0,0, 0,0);

			}
			
			$bars = ($cols-2) * ($percent/100);
			$bar_str = "";
			for( $i=0;$i<$bars;++$i ){
				$bar_str .= "█";
			}

			ncurses_mvwaddstr($windows[$name]->bar, 0, 1, " ".$percent."% ");
			ncurses_wrefresh($windows[$name]->bar);
			
			ncurses_mvwaddstr($windows[$name]->progress, 0, 1, $bar_str);
			ncurses_wrefresh($windows[$name]->progress);
			
		}

		public function checklist( $name, $items, $rows=NULL, $cols=NULL, $x=NULL, $y=NULL ){

			if( empty($windows[$name]) ){

				if( is_null($rows) ){ $rows = count($items); }
				if( is_null($cols) ){ $cols = intval($this->cols/2) - 3; }
				if( is_null($x) ){ $x = 2; }
				if( is_null($y) ){ $y = 2; }

				$checklist = new stdClass();
				$checklist->window = ncurses_newwin($rows, $cols, $x, $y);
				$checklist->items = array();

				// border our progress bar.
				ncurses_wborder($checklist->window,0,0, 0,0, 0,0, 0,0);
				
				$windows[$name] = $checklist;

			} else {
				$checklist = $windows[$name];
			}

			ncurses_mvwaddstr($checklist->window, 0, 1, " ".$name." ");
			ncurses_wrefresh($checklist->window);

		}

		public function log( $name, $rows=NULL, $cols=NULL, $x=NULL, $y=NULL ){
			
			if( empty($windows[$name]) ){
				
				if( is_null($rows) ){ $rows = $this->rows-10; }
				if( is_null($cols) ){ $cols = intval($this->cols/2) - 3; }
				if( is_null($x) ){ $x = intval($this->cols/2) + 1; }
				if( is_null($y) ){ $y = 2; }

				$log = new stdClass();
				$log->window = ncurses_newwin($rows, $cols, $x, $y);
				$log->items = array();

				ncurses_wborder($log->window,0,0, 0,0, 0,0, 0,0);
				$windows[$name] = $log;

			} else {
				$log = $windows[$name];
			}

			ncurses_mvwaddstr($log->window, 0, 1, " ".$name." ");
			ncurses_wrefresh($log->window);

		}

		public function closeCLI(){
			ncurses_end();
		}
		
	}
?>