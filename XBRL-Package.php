<?php

use lyquidity\XPath2\NameBinder;

/**
 * XBRL Package interface
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
 * Implements an abstract base class to be extended by classes that
 * handle a type of package (zip file).  The type may be an XBRL Taxonomy
 * package or an SEC package (with either an XML or JSON manifest).
 */
abstract class XBRL_Package
{
	/**
	 * @var ZipArchive $zipArchive
	 */
	private $zipArchive;

	/**
	 * When initialized this variable will contain an array representation of the zip file directory structure
	 * @var array $contents
	 */
	protected $contents;

	/**
	 * Default constructor
	 * @param ZipArchive $zipArchive
	 */
	public function __construct(ZipArchive $zipArchive  )
	{
		$this->zipArchive = $zipArchive;

		$this->contents = array();

		// Read the files and folders
		for ( $index = 0; $index < $this->zipArchive->numFiles; $index++ )
		{
			$name = $this->zipArchive->getNameIndex( $index );
			$parts = explode( "/", $name );

			$current = &$this->contents;

			foreach ( $parts as $i => $part )
			{
				if ( empty( $part ) ) continue;

				if ( $i == count( $parts ) - 1 ) // Leaf
				{
					$current[] = $part;
					continue;
				}

				if ( ! isset( $current[ $part ] ) ) // New directory
				{
					$current[ $part ] = array();
				}

				$current = &$current[ $part ];
			}

		}

	}

	/**
	 * An implementation will return true if the package can be processed
	 * by its implementation.
	 */
	public function isPackage() {}

	/**
	 * Return the contents of a file given a path
	 * @param string $path
	 * @return string
	 * @throws Exception if the requested file does not exist
	 */
	public function getFile( $path )
	{
		return $this->zipArchive->getFromName( $path );
	}

	/**
	 * Return the contents of a file given a path
	 * @param string $path
	 * @return SimpleXMLElement
	 * @throws Exception if the requested file does not exist
	 */
	public function getFileAsXML( $path )
	{
		return simplexml_load_string( $this->getFile( $path ) );
	}

	/**
	 * Returns the name of the root folder
	 * @return mixed
	 */
	public function getFirstFolderName()
	{
		return key( $this->contents );
	}

	/**
	 * Traverses the contents folders and files calling $callbackf for each node
	 * @param Funtion $callback Three arguents will be passed to the the callback:
	 * 		The path preceding the Name
	 * 		The name
	 * 		PATHINFO_BASENAME is the name is a file or PATHINFO_DIRNAME
	 */
	public function traverseContents( $callback )
	{
		if ( ! $callback ) return;

		$traverse = function( $nodes, $path = "" ) use ( &$traverse, &$callback )
		{
			if ( is_string( $nodes ) )
			{
				return $callback( $path, $nodes, PATHINFO_BASENAME );
			}

			foreach ( $nodes as $name => $children )
			{
				if ( is_numeric( $name ) ) // It's a file
				{
					if ( ! $traverse( $children, $path ) ) return false;
					continue;
				}

				if ( ! $callback( $path, $name, PATHINFO_DIRNAME ) ) return false;

				if ( ! $traverse( $children, "$path$name/" ) )
				{
					return false;
				}
			}

			return true;
		};

		$traverse( $this->contents );
	}
}
