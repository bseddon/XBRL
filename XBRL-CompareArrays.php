<?php
/**
 * CompareArrays
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
 * The class can be used with arrays or JSON strings that can be converted 
 * to arrays or file names of files that contain JSON strings.
 * 
 * JSON strings will be converted to associative arrays. Objects within 
 * arrays will not be compared.  This constraint could be changed but I
 * have not real world examples to work with.
 * 
 * Takes about 4 seconds to process pairs of 10MB JSON strings on a 2.3GHz 
 * dual core processor.  Appears to scale linearly with the size of the 
 * input arrays and has been tested on real world examples of upto 125MB.
 */

/**
 * Class to find similarities and differences between two arrays
 * Only arrays or primitives will be considered.  Objects will not be compared.
 */
class XBRL_CompareArrays
{
	public $differences = array();
	public $onlyA = array();
	public $onlyB = array();

	/**
	 * Static function to create an instance that compares two arrays
	 * @return XBRL_CompareArrays
	 */
	public static function createDiff()
	{
		return new XBRL_CompareArrays();
	}

	/**
	 * An example of comparing JSON files.  This will generate 4 files:
	 * onlyA.json, onlyB.json, diffA.json, diffB.json
	 * @return void
	 */
	public static function example()
	{
		try
		{
			$directory = 'c:/LyquidityWeb/XBRLQuery/';
			// $a = "{$directory}xbrl-validate-files/temp/copy/us-gaap-entryPoint-all-wotmp-2018-01-31 - Copy.json";
			$a = "{$directory}xbrl-validate-files/temp/copy/us-gaap-entryPoint-all-wotmp-2018-01-31.json";
			$b = "{$directory}xbrl-validate-files/temp/copy/us-gaap-entryPoint-all-wotmp-2018-01-31.json";
			$diff = self::createDiff()
				->diffJSONFiles( $a, $b )
				->save( "{$directory}xbrl-validate-files/temp/copy/", '', true );
		}
		catch( Exception $ex )
		{
			echo $ex->getMessage() . "\n";
		}		
	}

	/**
	 * Used as a glue to create a path
	 *
	 * @var string
	 */
	private static $separator = '/|';

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
		$jsonA = '';
		unset( $jsonA );
		if ( ! ( $b = json_decode( $jsonB, true ) ) ) throw new Exception( \XBRL::json_last_error_msg() );
		$jsonB = '';
		unset( $jsonB );

		return $this->diff( $a, $b );
	}

	/**
	 * Performs the diff. Fills info arrays.
	 * @return XBRL_CompareArrays
	 */
	function diff( &$currentA, &$currentB, $path = '', $depth = 0 )
	{
		if ( ! is_array( $currentA ) ) throw new \Exception('The first parameter is not an array');
		if ( ! is_array( $currentB ) ) throw new \Exception('The second parameter is not an array');

		$diffA = array_diff_key( $currentA, $currentB );
		$diffB = array_diff_key( $currentB, $currentA );

		if ( $diffA ) $this->onlyA[ $path ] = array_keys( $diffA );
		if ( $diffB ) $this->onlyB[ $path ] = array_keys( $diffB );

		$intersect = array_intersect_key( $currentA, $currentB );
		$currentDifference =& $this->differences;
		if ( $path )
		{
			$parts = explode( self::$separator, $path );
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
				$newPath = $path ? $path . self::$separator . $key : $key;
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
		if ( file_put_contents( $filename . "onlyA.json", $this->renderOnly( $this->onlyA ) ) === false ) throw new \Exception("Error saving file 'onlyA'");
		if ( file_put_contents( $filename . "onlyB.json", $this->renderOnly( $this->onlyB ) ) === false ) throw new \Exception("Error saving file 'onlyB'");

		if ( $twoFiles )
		{
			if ( file_put_contents( $filename . "diffA.json", $this->renderDiff('A') ) === false ) throw new \Exception("Error saving file 'diffA'");
			if ( file_put_contents( $filename . "diffB.json", $this->renderDiff('B') ) === false ) throw new \Exception("Error saving file 'diffB'");
		}
		else
		{
			if ( file_put_contents( $filename . "diff.json", self::json_encode( $this->differences ) ) === false ) throw new \Exception("Error saving file 'diff'");
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
		$json = self::json_encode( $only );
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
		// Use the same variable so the memory for the constucted array can be released
		$changed = self::json_encode( $changed );
		return $change;
	}

	/**
	 * Create a JSON encoded string.  Removes the special 'glue' used to create paths
	 *
	 * @param [type] $array
	 * @return void
	 */
	private static function json_encode( &$array )
	{
		return str_replace( self::$separator, '/', json_encode( $array, JSON_PRETTY_PRINT ) );
	}
}