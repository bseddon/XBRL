<?php

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

use function lyquidity\xml\isXml;

/**
 * Implements an abstract base class to be extended by classes that
 * handle a type of package (zip file).  The type may be an XBRL Taxonomy
 * package or an SEC package (with either an XML or JSON manifest).
 */
abstract class XBRL_Package
{
	/**
	 * Factory to create a package class instance
	 * @param unknown $taxonomyPackage
	 * @throws Exception
	 * @return XBRL_Package
	 */
	public static function getPackage( $taxonomyPackage )
	{
		$packageClasses = array( 'XBRL_TaxonomyPackage', 'XBRL_SEC_JSON_Package', 'XBRL_SEC_XML_Package', 'XBRL_SimplePackage' );

		foreach ( $packageClasses as $packageClassName )
		{
			// $package = $packageClass::fromFile( $taxonomyPackage );
			$package = XBRL_Package::fromFile( $taxonomyPackage, $packageClassName );
			if ( ! $package->isPackage() ) continue;

			return $package;
		}

		if ( ! $found )
		{
			$zip = basename( $taxonomyPackage );
			throw new Exception( "The contents of file '$zip' do not match any of the supported taxonomy package formats" );
		}
	}

	/**
	 * Open a package from a file
	 * @param string $filename
	 * @param string $className
	 * @return boolean
	 * @throws Exception
	 */
	public static function fromFile( string $filename, $className )
	{
		$zipArchive = new ZipArchive();

		try
		{
			if ( $zipArchive->open( $filename ) !== true )
			{
				$zipArchive = false;
				throw new Exception("An attempt has been made to open an invalid zip file");
			}
			return self::fromZip( $zipArchive, $className );
		}
		catch ( Exception $ex )
		{
			if ( $zipArchive ) $zipArchive->close();
			throw XBRL_TaxonomyPackageException::withError( "tpe:invalidArchiveFormat", $ex->getMessage() );
		}

		return false;
	}

	/**
	 * Read a taxonpmy package represented by $zipArchive
	 * @param ZipArchive $zipArchive
	 * @param string $className
	 * return XBRL_Package
	 */
	public static function fromZip( ZipArchive $zipArchive, $className )
	{
		$instance = new $className( $zipArchive );
		return $instance;
	}


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
	 * The meta file as a SimpleXMLElement
	 * @var string
	 */
	protected  $metaFile;

	/**
	 * The name of the meta file
	 * @var string
	 */
	protected $metaFilename;

	/**
	 * Name of the instance document
	 * @var string
	 */
	protected $instanceDocument;

	/**
	 * Schema file
	 * @var string
	 */
	protected  $schemaFile;

	/**
	 * Target namespace of the schema
	 * @var string
	 */
	protected $schemaNamespace;

	/**
	 * A list of errors parsing the package
	 * @var array
	 */
	public $errors = array();

	/**
	 * Default constructor
	 * @param ZipArchive $zipArchive
	 */
	public function __construct( ZipArchive $zipArchive  )
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
	 * Clean up
	 */
	function __destruct()
	{
		if ( ! $this->zipArchive ) return;
		$this->zipArchive->close();
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
	 * Traverses the contents folders and files calling $callback for each node
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

	/**
	 * Gets the content element corresponding to $path
	 * @param string $path
	 * @return array
	 */
	public function getElementForPath( string $path )
	{
		$path = trim( $path, '/' );
		$parts = explode( '/', $path );

		$current = &$this->contents;

		foreach ( $parts as $part )
		{
			if ( ! isset( $current[ $part ] ) ) return false;
			$current = &$current[ $part ];
		}

		return $current;
	}

	/**
	 * Get the instance document xml
	 */
	public function getInstanceDocument()
	{
		if ( $this->instanceDocument )
		{
			$xml = $this->getFileAsXML( $this->instanceDocument );
			return $xml;
		}

		$xml = null;
		$this->traverseContents( function( $path, $name, $type ) use( &$xml ) {
			if ( $type == PATHINFO_DIRNAME ) return true;
			$extension = pathinfo( $name, PATHINFO_EXTENSION );
			if ( ! in_array( $extension, array( 'xml', 'xbrl' ) ) ) return true;

			$path = $path ? "$path$name" : $name;
			$xml = $this->getFileAsXML( $path );
			if ( ! $xml instanceof SimpleXMLElement ) return true;

			if ( $xml->getName() != "xbrl" )
			{
				$xml = null;
				return true;
			}

			$this->instanceDocument = $path;
			return false;
		} );

		return $xml;
	}

	/**
	 * Save the taxonomy from the package to the cache location
	 * @param string $cacheLocation
	 * @return boolean
	 */
	public function saveTaxonomy( $cacheLocation )
	{
		return true;
	}

	/**
	 * Retrieves the taret namespace from $content which is expected to be an XM schema document
	 * @param string $schemaName Name of the schema represented by $content
	 * @param bytes $content Expected to be an XML schema
	 * @param bool $throwException True if an exception should be thrown on error or false is returned otherwise
	 * @return bool
	 * @throws \Exception
	 */
	protected function getTargetNamespace( $schemaName, $content, $throwException = true )
	{
		/** @var \SimpleXMLElement $xml */
		$xml = @simplexml_load_string( $content );
		if ( ! $xml )
		{
			if ( ! $throwException ) return false;
			throw new \Exception( __( "The schema file '{$schemaName}' is not a valid XML document", 'xbrl_validate' ) );
		}

		$xsAttributes = $xml->attributes();
		if ( ! isset( $xsAttributes->targetNamespace ) )
		{
			if ( ! $throwException ) return false;
			throw new \Exception( __( 'The schema document does not contain a target namespace', 'xbrl_validate' ) );
		}

		return (string)$xsAttributes->targetNamespace;
	}

	/**
	 * Processes the schema document in a consistent way
	 * @param \XBRL_Global A reference to the global context
	 * @param string $schemaName Name of the schema represented by $content
	 * @param bytes $content Expected to be an XML schema
	 * @param bool $throwException True if an exception should be thrown on error or false is returned otherwise
	 * @return bool
	 * @throws \Exception
	 */
	protected function processSchemaDocument( $context, $schemaName, $content, $throwException = true )
	{
		if ( ! isXml( $content, $throwException ) ) return false;

		$namespace = $this->getTargetNamespace( $schemaName,  $content, $throwException );
		if ( ! $namespace )
		{
			$msg = "Unable to find the taxonomy namespace";
			$this->errors[] = $msg;

			if ( $throwException )
			{
				throw new \Exception( $msg );
			}

			return false;
		}

		$this->schemaNamespace = $namespace;
		$this->schemaFile = "$namespace/$schemaName";

		if ( $context->findCachedFile( $this->schemaFile ) )
		{
			$msg = "The taxonomy already exists in the cache";
			$this->errors[] = $msg;

			if ( $throwException )
			{
				throw new \Exception( $msg );
			}

			return false;
		}

		if ( ! $context->saveCacheFile( $this->schemaFile, $content ) )
		{
			$msg = "Unable to save the schema file ('$schemaName')";
			$this->errors[] = $msg;

			if ( $throwException )
			{
				throw new \Exception( $msg );
			}

			return false;
		}

		return $namespace;
	}

}
