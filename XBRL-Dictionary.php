<?php

/**
 * Extend the class TupleDictionary to make a dictionary that can be persisted
 *
 * @author Bill Seddon
 * @version 0.9
 * @copyright Lyquidity Solutions Limited 2016
 * @license Apache 2
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
 */

/**
 * Load the dictionary class
 */
$utiltiesPath = isset( $_ENV['UTILITY_LIBRARY_PATH'] )
	? $_ENV['UTILITY_LIBRARY_PATH']
	: ( defined( 'UTILITY_LIBRARY_PATH' ) ? UTILITY_LIBRARY_PATH : __DIR__ . "/../utilities/" );
require '$utiltiesPath/tuple-dictionary.php';

/**
 * Load the Log class
 */
require 'XBRL-Log.php';

/**
 * Class implementation
 */
class XBRL_Dictionary extends TupleDictionary
{
	/**
	 * Return the dictionary contents as an array
	 * @return array
	 */
	public function toArray()
	{
		return array(
			'data' => &$this->data,
			'hash_algorithm' => $this->hash_algorithm,
		);
	}

	/**
	 * Alternative constructor
	 * @param array $dictionary	An array containing the tuple dictionary
	 * 							data and optionally a hash algorithm
	 */
	public function __construct( $dictionary = null )
	{
		parent::__construct();

		if ( ! $dictionary || ! isset( $dictionary['data'] ) ) return;
		$this->data = $dictionary['data'];

		if ( ! isset( $dictionary['hash_algorithm']) ) return;
		$this->hash_algorithm = $dictionary['hash_algorithm'];
	}
}
