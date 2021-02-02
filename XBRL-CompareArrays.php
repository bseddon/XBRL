<?php
/**
 * XBRL CompareArrays
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
 * Class to find similarities and differences between two arrays
 * Only arrays or primitives will be considered.  Objects will not be compared.
 */
class XBRL_CompareArrays
{
	// public $arrayA = array();
	// public $arrayB = array();
	public $differences = array();
	public $onlyA = array();
	public $onlyB = array();
	// public $path = '';

	/**
	 * Static function to create an instance that compares two arrays
	 * @return XBRL_CompareArrays
	 */
	public static function createDiff()
	{
		return new XBRL_CompareArrays();
	}

	
	/**
	 * Compare two JSON files.  The companison will be doing by create assoc arrays not objects
	 * @param string $jsonA
	 * @param string $jsonB
	 * @return XBRL_CompareArrays
	 */
	function diffJSONFiles( &$fileA, &$fileB )
	{
		if ( ! file_exists( $fileA ) || ! ( $a = file_get_contents( $fileA, true ) ) ) throw new Exception("Error opening file: '$fileA'");
		if ( ! file_exists( $fileB ) || ! ( $b = file_get_contents( $fileB, true ) ) ) throw new Exception("Error opening file: '$fileB'");

		return $this->diffJSON( $a, $b );
	}

	/**
	 * Compare two JSON strings.  The companison will be doing by create assoc arrays not objects
	 * @param string $jsonA
	 * @param string $jsonB
	 * @return XBRL_CompareArrays
	 */
	function diffJSON( &$jsonA, &$jsonB )
	{
		if ( ! ( $a = json_decode( $jsonA, true ) ) ) throw new Exception( \XBRL::json_last_error_msg() );
		unset( $jsonA );
		if ( ! ( $b = json_decode( $jsonB, true ) ) ) throw new Exception( \XBRL::json_last_error_msg() );
		unset( $jsonB );

		return $this->diff( $a, $b );
	}

	/**
	 * Performs the diff. Fills info arrays.
	 * @return XBRL_CompareArrays
	 */
	function diff( &$currentA, &$currentB, $path = '', $depth = 0 )
	{
		if ( $depth === 0 ) echo "diff\n";

		if ( ! is_array( $currentA ) ) throw new \Exception('The first parameter is not an array');
		if ( ! is_array( $currentB ) ) throw new \Exception('The second parameter is not an array');

		$diffA = array_diff_key( $currentA, $currentB );
		$diffB = array_diff_key( $currentB, $currentA );

		if ( $diffA ) $this->onlyA[ $path ] = array_keys( $diffA );
		if ( $diffA ) $this->onlyB[ $path ] = array_keys( $diffB );

		$intersect = array_intersect_key( $currentA, $currentB );
		$currentDifference =& $this->differences;
		if ( $path )
		{
			$parts = explode( '/|', $path );
			foreach( $parts as $part )
			{
				if ( ! isset( $currentDifference[ $part ] ) )
				{
					$currentDifference[ $part ] = array();
				}

				$currentDifference =& $currentDifference[ $part ];
			}
		}

		foreach( $intersect as $key => &$elementA )
		{
			$elementB = $currentB[ $key ];
			if ( is_array( $elementA ) && is_array( $intersect[ $key ] ) )
			{
				$newPath = $path ? $path . "/|$key" : $key;
				$this->diff( $elementA, $elementB, $newPath, $depth + 1 );
				if ( ! count( $currentDifference[ $key ] ) ) unset( $currentDifference[ $key ] );
				continue;
			}

			$valueA = is_array( $elementA ) ? 'array' : $elementA;
			$valueB = is_array( $elementB ) ? 'array' : $elementB;

			switch( $key )
			{
				// Use case switchs to ignore keys
				default:
					if ( $valueA == $valueB )
					{
						// Should do something
					}
					else
					{
						$currentDifference[ $key ] = array( 'A' => $valueA, 'B' => $valueB );
					}
			}
		}

		return $this;
	}

	/**
	 * Saves the diff content to files
	 *
	 * @param string $dir	The folder in which files will be saved
	 * @param string $prefix This prefix will be added to the file names so the source can be identified
	 * @param bool $twoFiles	(Default: true) When true the diff files will be saved as two files so they can be compared with each other
	 * @return void
	 * @throws Exception If there is a problem saving
	 */
	function save( $dir, $prefix = '', $twoFiles = true )
	{
		$dir = trailingslashit( $dir );
		if ( ! is_dir( $dir ) )
		{
			if ( mkdir( $dir ) ) throw new \Exception("Failed to created folder: '$dir'");
		}

		$filename = $dir . ( $prefix ? "$prefix-" : '' );
		if ( file_put_contents( $filename . "onlyA.txt", $this->renderOnly( $this->onlyA ) ) === false ) throw new \Exception("Error saving file 'onlyA'");
		if ( file_put_contents( $filename . "onlyB.txt", $this->renderOnly( $this->onlyB ) ) === false ) throw new \Exception("Error saving file 'onlyB'");

		if ( $twoFiles )
		{
			if ( file_put_contents( $filename . "diffA.txt", $this->renderDiff('A') ) === false ) throw new \Exception("Error saving file 'diffA'");
			if ( file_put_contents( $filename . "diffB.txt", $this->renderDiff('B') ) === false ) throw new \Exception("Error saving file 'diffB'");
		}
		else
		{
			if ( file_put_contents( $filename . "diff.txt", json_encode( $this->differences, JSON_PRETTY_PRINT ) ) === false ) throw new \Exception("Error saving file 'diff'");
			if ( json_last_error() != JSON_ERROR_NONE ) throw new \Exception( "Error generating JSON for diff: " . \XBRL::json_last_error_msg() );
		}
	}

	/**
	 * Creates a text rendering of the 'only' results
	 * @param string $only
	 * @return string
	 */
	private function &renderOnly( &$only )
	{
		$json = json_encode( $only, JSON_PRETTY_PRINT );
		if ( json_last_error() != JSON_ERROR_NONE ) throw new \Exception( "Error generating JSON for only" );
		return $json;
	}

	/**
	 * Renders the differences
	 * @source string 'A' or 'B' to select the contents of the diff information
	 * @return string
	 */
	private function &renderDiff( $source )
	{
		$json = '';

		switch( $source )
		{
			case 'A':
				break;

			case 'B':
				break;
		}

		$change = function( &$array, $source ) use( &$change )
		{
			if ( count( $array ) == 2 && isset( $array['A'] ) && isset( $array['B'] ) )
			{
				return $array[ $source ];
			}

			$result = array();
			foreach( $array as $key => $element )
			{
				$result[ $key ] = $change( $element, $source );
			}
			return $result;
		};

		$changed = $change( $this->differences, $source );
		return json_encode( $changed, JSON_PRETTY_PRINT );
	}
}