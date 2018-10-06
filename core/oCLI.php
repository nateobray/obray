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

	Class oCLIPanel {
		public $window;
		public $x;
		public $y;
		public $rows;
		public $cols;

		public function __construct( $window, $x, $y, $cols, $rows ){
			$this->window = $window; $this->x = $x; $this->y = $y; $this->cols = $cols; $this->rows = $rows;
		}
	}

	/********************************************************************************************************************

		OUsers:	User/Permission Manager

	********************************************************************************************************************/
	
	Class oCLI extends ODBO{

		protected $windows = array();
		protected $rows = 0;
		protected $cols = 0;
		protected $tableData = array();

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

			// get screen size
			ncurses_getmaxyx($this->window, $this->rows, $this->cols);

			// draw a border around the whole thing.
			ncurses_border(0,0, 0,0, 0,0, 0,0);

			// set the application title
			if( !is_null($title) ){  ncurses_mvaddstr(0,2," ".$title." ");  }

			// hide cursor & initiate color
			ncurses_curs_set(0);
			ncurses_start_color();
			ncurses_init_pair ( 1 , NCURSES_COLOR_YELLOW , NCURSES_COLOR_BLACK );

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
				$bar_str .= "-";
			}

			ncurses_mvwaddstr($windows[$name]->bar, 0, 1, " ".$percent."% ");
			ncurses_wrefresh($windows[$name]->bar);
			
			ncurses_mvwaddstr($windows[$name]->progress, 0, 1, $bar_str);
			ncurses_wrefresh($windows[$name]->progress);
			
		}

		public function closeCLI(){
			ncurses_end();
		}

		public function createCLIPanel( $window, $cols=NULL, $rows=NULL, $x=NULL, $y=NULL, $border=TRUE, $text=FALSE ){
			
			$panel = ncurses_new_panel( $window );
			$window = ncurses_newwin($rows, $cols, $y, $x);

			ncurses_replace_panel($panel,$window);
			
			if( $border ){
				ncurses_wborder( $window, 0,0, 0,0, 0,0, 0,0 );
			}

			if( $text !== FALSE ){
				ncurses_waddstr($window,$text);
			}
			
			ncurses_wrefresh( $window );
			
			return new oCLIPanel($window,$x,$y,$cols,$rows);
			
		}

		public function createCLITable( $name, $window, $data, $cols, $rows, $x, $y, $border=FALSE ){

			$this->tableData[$name] = array();

			$table = $this->createCLIPanel( $window, $cols, $rows, $x, $y, $border );

			
			$keys = array_keys((array)$data[0]);
			$header = $this->createCLIRow( $table, $keys, 0, 1 );
			

			forEach( $data as $row => $dati ){
				$this->tableData[ $name ][ $row ] = $this->createCLIRow( $table, $dati, $row+1 );
			}

		}

		public function createCLIRow( $table, $data, $row, $color=0 ){

			$row = $this->createCLIPanel( $table->window, $table->cols, 1, $table->x, $table->y+$row, FALSE );

			
			ncurses_wcolor_set( $row->window, $color );
			ncurses_wclear( $row->window );
			ncurses_waddstr($row->cells[$key]->window,' ');

			ncurses_wrefresh( $row->cells[$key]->window );
			
			$row->cells = array();
			$tableColumns = 0;
			$columnCols = 10;
			forEach( $data as $key => $dati ){
				$row->cells[$key] = $this->createCLIPanel( $row->window, $columnCols, 1, $row->x+($columnCols*$tableColumns), $row->y, FALSE );

				
				ncurses_wcolor_set( $row->cells[$key]->window, $color );
				ncurses_wclear( $row->cells[$key]->window );
				ncurses_waddstr($row->cells[$key]->window,$dati);

				ncurses_wrefresh( $row->cells[$key]->window );

				$row->cells[$key]->value = $dati;
				++$tableColumns;
			}
			return $row;

		}

		public function updateCLITableCells( $name, $data ){

			forEach( $data as $row => $dati ){
				forEach( $dati as $key => $value ){

					if( $value != $this->tableData[ $name ][ $row ]->cells[ $key ]->value ){

						ncurses_init_pair ( 1 , NCURSES_COLOR_RED , NCURSES_COLOR_WHITE );
						ncurses_wcolor_set( $this->tableData[ $name ][ $row ]->cells[ $key ]->window, 1 );

						ncurses_wclear( $this->tableData[ $name ][ $row ]->cells[ $key ]->window );
						ncurses_waddstr($this->tableData[ $name ][ $row ]->cells[ $key ]->window,$value);
						$this->tableData[ $name ][ $row ]->cells[ $key ]->value = $value;

						ncurses_wrefresh( $this->tableData[ $name ][ $row ]->cells[ $key ]->window );
						
					}
					
				}
			}

		}
		
	}
?>