<?php

/**
 * Single report implementation
 *
 * @author Bill Seddon
 * @version 0.9
 * @Copyright (C) 2018 Lyquidity Solutions Limited
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * Load the XBRL implementation
 */
require_once( 'XBRL-Report-Base.php' );

/**
 * Class to support the creation of single company reports
 * @author Bill Seddon
 *
 */
class XBRL_Report extends XBRL_Report_Base
{
	/**
	 * A private variable to record whether the constuctor is being called.
	 * @var boolean $inConstructor
	 */
	private $inConstructor = false;

	/**
	 * Default constructor
	 *
	 * @param string $instance_file (optional)
	 * @param string $taxonomy_file (optional)
	 */
	public function __construct( $instance_file = null, $taxonomy_file = null )
	{
		$this->inConstructor = true;

		parent::__construct();
		$this->addInstanceDocument( $instance_file, $taxonomy_file );

		$this->inConstructor = false;
	}

	/**
	 * Add an instance document to the report
	 * @param string $instance_file The file name of the instance document to add the report
	 * @param string $taxonomy_file If provided the name of the .json or .zip file containing the taxonomy information
	 * @return void
	 */
	public function addInstanceDocument( $instance_file = null, $taxonomy_file = null )
	{
		if ( ! $this->inConstructor )
			throw new Exception( "Reports created using XBRL_Report do not support multiple instances" );

		parent::addInstanceDocument( $instance_file, $taxonomy_file );
	}

}

?>