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
	 * Notes about using this package instance
	 * @var string
	 */
	const notes = <<<EOT
Each package implementation should include a 'notes' constant to record any useful information
relating to the use of the implementation.\n\n
EOT;

	/**
	 * Factory to create a package class instance
	 * @param string $taxonomyPackage
	 * @param array $additionalPackageClasses (optional) A list of other packaging classes that could be valid
	 * @throws Exception
	 * @return XBRL_Package
	 */
	public static function getPackage( $taxonomyPackage, $additionalPackageClasses = array() )
	{
		if ( ! is_array( $additionalPackageClasses ) )
		{
			$additionalPackageClasses = array();
		}

		$packageClassesFile = __DIR__ . '/TaxonomyPackageTypes.json';
		$packageClasses = null;
		if ( file_exists( __DIR__ . '/TaxonomyPackageTypes.json' ) )
		{
			// echo file_get_contents( __DIR__ . '/TaxonomyPackageTypes.json' );
			$json = json_decode( file_get_contents( __DIR__ . '/TaxonomyPackageTypes.json' ), true );
			if ( $json && isset( $json['classNames'] ) )
			{
				$packageClasses = $json['classNames'];
			}
			else
			{
				echo "Unable to load the package classes JSON file" . XBRL::json_last_error_msg() , "\n";
			}
		}

		if ( ! $packageClasses )
		{
			$packageClasses = array( 'XBRL_TaxonomyPackage', 'XBRL_SEC_JSON_Package', 'XBRL_SEC_XML_Package', 'XBRL_SimplePackage' );
		}

		// Any additional package classes should be evaluated first.
		// Additional classes should make sure there is validation so they are not used when they are really not suitable
		$packageClasses = $additionalPackageClasses + $packageClasses;
		// The 'isPackage' function may add schema files to the mapUrl
		// If the package is found to be not valid then the added
		// functions will hold the instance open and, so, keep a lock
		// on the respective zip file
		global $mapUrl;
		foreach ( $packageClasses as $packageClassName )
		{
			// Make a note of the current top of the mapUrl chain
			$previousMapUrl = $mapUrl;

			/** @var XBRL_Package $package */
			$package = XBRL_Package::fromFile( $taxonomyPackage, $packageClassName );
			$package->is = $package->isPackage();
			if ( ! $package->is )
			{
				// Release the added functions so the instance will be release, the destructor called and the zip file closed.
				$mapUrl = $previousMapUrl;
				continue;
			}

			// Load the XBRL class
			$className = $package->getXBRLClassname();
			class_exists( $className, true );
			return $package;
		}

		$zip = basename( $taxonomyPackage );
		throw new Exception( "The contents of file '$zip' does not match any of the supported taxonomy package formats" );
	}

	/**
	 * Open a package from a file
	 * @param string $filename
	 * @param string $className
	 * return XBRL_Package
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
	 * A reference to the archive hosting the package
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
	public $instanceDocument;

	/**
	 * Schema file
	 * @var string
	 */
	public  $schemaFile;

	/**
	 * Target namespace of the schema
	 * @var string
	 */
	public $schemaNamespace;

	/**
	 * A list of errors parsing the package
	 * @var array
	 */
	public $errors = array();

	static $unique = 1;

	private $index = 0;

	private $is = null;

	/**
	 * Default constructor
	 * @param ZipArchive $zipArchive
	 */
	public function __construct( ZipArchive $zipArchive  )
	{
		$this->index = XBRL_Package::$unique;
		XBRL_Package::$unique++;

		// error_log(sprintf("% 3d construct %s % -30s %s", $this->index, $this->is ? 'y' : ( is_null($this->is) ? '-' : 'n' ), basename($zipArchive->filename), get_class( $this ) ) );
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
					// Don't let this array increment automatically
					$current[ count( $current ) ] = $part;
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
		// error_log(sprintf("% 3d destruct  %s % -30s %s", $this->index, $this->is ? 'y' : ( is_null($this->is) ? '-' : 'n' ), basename($this->zipArchive->filename), get_class( $this ) ) );
		if ( ! $this->zipArchive->close() )
		{
			echo "Failed to close the package zip file\n";
		}
		$this->zipArchive = null;
	}

	/**
	 * Compile a taxonmy
	 * @param string $output_basename Name of the compiled taxonomy to create
	 * @param string $compiledPath (optional) Path to the compiled taxonomies folder
	 * @param string $schemaFile
	 * @return bool
	 * @throws Exception
	 */
	public function compile( $output_basename = null, $compiledPath = null,  $schemaFile = null  )
	{
		$schemaNamespace = $this->schemaNamespace;

		if ( $schemaFile )
		{
			$schemaNamespace = $this->getNamespaceForSchema( $schemaFile );
		}
		else
		{
			$schemaFile = $this->schemaFile;
		}

		if ( $this->isExtensionTaxonomy( $schemaFile ) )
		{
			return XBRL::compileExtensionXSD(
				$schemaFile,
				$this->getXBRLClassname(),
				$schemaNamespace,
				$output_basename,
				$compiledPath
			);
		}
		else
		{
			return XBRL::compile(
				$schemaFile,
				$schemaNamespace,
				$compiledPath . ( is_null( $output_basename ) ? $this->getSchemaFileBasename() : $output_basename )
			);
		}
	}

	/**
	 * An implementation will return true if the package can be processed
	 * by its implementation.
	 */
	public function isPackage() {}

	/**
	 * Returns true if the taxonomy in the package is compiled
	 * @param string $compiledDir Path to the compiled taxonomies folder
	 * @param string $basename Specifies an explicit basename.  Otherwise the basename of the schema name is used.
	 * @return bool
	 */
	public function isCompiled( $compiledDir, $basename = null  )
	{
		if ( is_null( $basename ) )
		{
			$basename = $this->getSchemaFileBasename();
		}

		return XBRL::isCompiled( $compiledDir, $basename );
	}

	/**
	 * Cache value for the flag
	 * @var bool
	 */
	private $isExtensionTaxonomy = null;

	/**
	 * Returns true if the package contains an extension taxonomy
	 * @param string $schemaFile
	 * @return bool
	 * @final
	 */
	public function isExtensionTaxonomy( $schemaFile = null )
	{
		if ( is_null( $this->isExtensionTaxonomy ) )
		{
			$this->isExtensionTaxonomy = $this->getIsExtensionTaxonomy( $schemaFile );
		}
		return $this->isExtensionTaxonomy;
	}

	/**
	 * Workout which file is the schema file
	 * @return void
	 * @throws "tpe:schemaFileNotFound"
	 */
	protected function determineSchemaFile()
	{
		throw new Exception("The function 'determineSchemaFile' MUST be implemented by a concrete instance of XBRL_Package");
	}

	/**
	 * Can be implemented by concrete classes to return true if the taxonomy is an extension taxonomy
	 * This default implementation looks at the XBRL class name advertised by the class to determine
	 * if the schema file contains one of the entry points of the XBRL class.
	 * @param string $schemaFile
	 * @return bool
	 * @abstract
	 */
	protected function getIsExtensionTaxonomy( $schemaFile = null )
	{
		$this->determineSchemaFile();

		if ( ! $schemaFile ) $schemaFile = $this->schemaFile;

		// If the schema in the package imports one of the schemas with an entry point namespace then an extension compilation should be used
		$xml = $this->getFileAsXML( $this->getActualUri( $schemaFile ) );
		$xml->registerXPathNamespace( SCHEMA_PREFIX, SCHEMA_NAMESPACE );
		foreach ( $xml->xpath("/xs:schema/xs:import") as $tag => /** @var SimpleXMLElement $element */ $element )
		{
			$attributes = $element->attributes();
			if ( ! isset( $attributes['namespace'] ) ) continue;
			// echo "{$attributes['namespace']}\n";
			// $nameOfXBRLClass = $this->getXBRLClassname();
			if ( ( $className = $nameOfXBRLClass::class_from_namespace( (string)$attributes['namespace'] ) ) == "XBRL" ) continue;

			return true;
		}

		return false;
	}

	/**
	 * Returns the name of the class implementing the relevant XBRL instance
	 * @return string
	 * @throws Exception
	 */
	public function getXBRLClassname()
	{
		return "XBRL"; // The default
	}

	/**
	 * Return the contents of a file given a path
	 * @param string $path
	 * @return string
	 * @throws Exception if the requested file does not exist
	 */
	public function getFile( $path )
	{
		if ( empty( $path ) ) return false;
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
	 * Returns an array of schema file names defined as entry points
	 */
	public function getSchemaEntryPoints()
	{
		return array();
	}

	/**
	 * Return the details for an entry point identified by index or document name
	 * @param int|string $entryPointId
	 * @return array
	 */
	public function getDetailForEntryPoint( $entryPointId )
	{
		return array();
	}

	/**
	 * Traverses the contents folders and files calling $callback for each node
	 * @param Funtion $callback Three arguents will be passed to the the callback:
	 * 		1) The path preceding the Name
	 * 		2) The name
	 * 		3) PATHINFO_BASENAME if the name is a file or PATHINFO_DIRNAME
	 */
	public function traverseContents( $callback )
	{
		if ( ! $callback ) return;

		$this->traverse( $callback, $this->contents );
	}

	private function traverse( $callback, $nodes, $path = "" )
	{
		if ( is_string( $nodes ) )
		{
			return $callback( $path, $nodes, PATHINFO_BASENAME );
		}

		foreach ( $nodes as $name => $children )
		{
			if ( ! is_array( $children ) ) // It's a file
			{
				if ( ! $this->traverse( $callback, $children, $path ) ) return false;
				continue;
			}

			if ( ! $callback( $path, $name, PATHINFO_DIRNAME ) ) return false;

			if ( ! $this->traverse( $callback, $children, "$path$name/" ) )
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns a localized version of the schema file path
	 * @param string $uri
	 * @return string
	 */
	protected function getActualUri( $uri )
	{
		return $uri;
	}

	/**
	 * Returns an array containing the root folder for the actual uri and the url
	 * @param string $actualUri
	 * @param string $uri
	 * @return string[]
	 */
	public function getCommonRootFolder( $actualUri, $uri )
	{
		$uriParts = array_reverse( explode( "/", $uri ) );
		$actualUriParts = array_reverse( explode( "/", $actualUri ) );

		$count = min( array( count( $uriParts ), count( $actualUriParts ) ) );

		for( $i = 0; $i < $count; $i++ )
		{
			if ( $uriParts[ $i ] != $actualUriParts[ $i ] ) break;
		}

		if ( $uriParts[ $i ] === '' && isset( $uriParts[ $i + 1 ] ) && preg_match_all( "/https?:/", $uriParts[ $i + 1 ] ) )
		{
			$i--;
		}

		return array(
			'actual' => implode( "/", array_reverse( array_slice( $actualUriParts, $i ) ) ),
			'uri'    => implode( "/", array_reverse( array_slice( $uriParts, $i ) ) )
		);
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

		if ( empty( $path ) )
		{
			return $current;
		}

		foreach ( $parts as $part )
		{
			if ( isset( $current[ $part ] ) )
			{
				$current = &$current[ $part ];
				continue;
			}

			return false;

			return in_array( $part, $current )
				? $current
				: false;
		}

		return $current;
	}

	/**
	 * Retruns the instance document contents as named memory stream file
	 * @return boolean|string
	 */
	public function getInstanceDocumentAsMemoryFile()
	{
		// If this returns null there is no instance document
		$xbrl = $this->getInstanceDocument( false );
		if ( ! $xbrl ) return false;

		$instanceFilename = "mem://{$this->instanceDocument}";

		if ( ! class_exists("MemoryStream", true ) )
		{
			/**
			 * Load the dictionary class
			 */
			$utiltiesPath = isset( $_ENV['UTILITY_LIBRARY_PATH'] )
				? $_ENV['UTILITY_LIBRARY_PATH']
				: ( defined( 'UTILITY_LIBRARY_PATH' ) ? UTILITY_LIBRARY_PATH : __DIR__ . "/../utilities" );
			require_once "$utiltiesPath/MemoryStream.php";
		}

		$f = fopen( $instanceFilename, "w+" );
		fwrite( $f, $xbrl );
		fclose( $f );

		return $instanceFilename;
	}

	/**
	 * Get the instance document xml
	 * @param bool $asSimpleXML
	 * @return SimpleXMLElement|string
	 */
	public function getInstanceDocument( $asSimpleXML = true )
	{
		if ( ! $this->schemaFile || ! $this->schemaNamespace )
		{
			echo "The saveTaxonomy function must be called before using this function";
			return false;
		}

		if ( $this->instanceDocument )
		{
			$xml = $asSimpleXML
				? $this->getFileAsXML( $this->instanceDocument )
				: $this->getFile( $this->instanceDocument );
			return $xml;
		}

		$xml = null;
		$this->traverseContents( function( $path, $name, $type ) use( &$xml )
		{
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

		if ( ! $xml instanceof SimpleXMLElement ) return null;

		return $asSimpleXML
			? $xml
			: $this->getFile( $this->instanceDocument );
	}

	/**
	 * Save the taxonomy from the package to the cache location
	 * @param string $cacheLocation
	 * @return boolean
	 */
	public function saveTaxonomy( $cacheLocation )
	{
		return false;
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
	 * Return the namepace for the document which is identified by $uri
	 * @param string $uri
	 * @return boolean|string
	 */
	public function getNamespaceForSchema( $uri )
	{
		$actualUri = $this->getActualUri( $uri );
		$content = $this->getFile( $actualUri );
		if ( ! $content ) return false;

		return $this->getTargetNamespace( $uri, $content );
	}

	/**
	 * Processes the schema document in a consistent way
	 * @param \XBRL_Global $context A reference to the global context
	 * @param bytes $content Expected to be an XML schema
	 * @param bool $throwException True if an exception should be thrown on error or false is returned otherwise
	 * @return bool
	 * @throws \Exception
	 */
	protected function processSchemaDocument( $context, $content, $throwException = true )
	{
		if ( ! isXml( $content, $throwException ) ) return false;

		if ( is_null( $this->schemaNamespace ) )
		{
			$this->schemaNamespace = $this->getTargetNamespace( $this->schemaFile,  $content, $throwException );
			if ( ! $this->schemaNamespace )
			{
				$msg = "Unable to find the taxonomy namespace";
				$this->errors[] = $msg;

				if ( $throwException )
				{
					throw new \Exception( $msg );
				}

				return false;
			}
		}

		$part = parse_url( $this->schemaFile, PHP_URL_SCHEME );
		$prefix = empty( $part ) ? $this->schemaNamespace . "/" : "";
		$schemaFile = "$prefix{$this->schemaFile}";

		if ( $context->findCachedFile( "$schemaFile" ) )
		{
			$msg = "The taxonomy already exists in the cache";
			$this->errors[] = $msg;

			if ( $throwException )
			{
				throw new \Exception( $msg );
			}

			return false;
		}

		if ( ! $context->saveCacheFile( "$schemaFile", $content ) )
		{
			$msg = "Unable to save the schema file ('$schemaFile')";
			$this->errors[] = $msg;

			if ( $throwException )
			{
				throw new \Exception( $msg );
			}

			return false;
		}

		return $this->schemaNamespace;
	}

	/**
	 * Implements a Url map that allows a simple xsd name to map to a path that can be found in the cache
	 * @param string $schemaNamespace
	 * @param string $schemaFile
	 * @throws Exception
	 */
	protected function setUrlMap( $schemaNamespace = null, $schemaFile = null )
	{
		if ( $schemaNamespace && ! $schemaFile || $schemaFile && ! $schemaNamespace )
		{
			throw  new Exception('setUrlMap: If a schema file or schema namespace is provided to the setUrlMapo function then both MUST be provided.');
		}

		if ( ! $schemaFile )
		{
			$schemaFile = $this->schemaFile;
		}
		if ( ! $schemaNamespace )
		{
			$schemaNamespace = $this->schemaNamespace;
		}

		if ( ! $schemaNamespace ) return;

		global $mapUrl;  // This is a function assigned below.  Effectively a change of url maps is created.
		$previousMap = $mapUrl;

		$mapUrl = function( $url ) use( &$previousMap, $schemaFile )
		{
			if ( $url == basename( $schemaFile ) )
			{
				$url = $schemaFile;
			}
			else if ( $previousMap )
			{
				$url = $previousMap( $url );
			}

			return $url;
		};


	}

	/**
	 * Returns the schema file base name without the extension
	 * @param string $replacementExtension
	 * @return string
	 */
	public function getSchemaFileBasename( $replacementExtension = "", $schemaFile = null )
	{
		if ( ! $schemaFile ) $schemaFile = $this->schemaFile;

		return basename( $schemaFile, '.xsd' ) . $replacementExtension;
	}

	/**
	 * Load the taxonomy associated with this package
	 * @param string $compiledPath (optional)
	 * @param string $schemaFile (optional: default uses $this->schemaFile)
	 * @return boolean|XBRL
	 */
	public function loadTaxonomy( $compiledPath = null, $schemaFile = null )
	{
		$schemaNamespace = $this->schemaNamespace;

		if ( $schemaFile )
		{
			$schemaNamespace = $this->getNamespaceForSchema( $schemaFile );
		}
		else
		{
			$schemaFile = $this->schemaFile;
		}

		$basename = $this->getSchemaFileBasename( null, $schemaFile );

		if ( $this->isCompiled(
				$compiledPath,
				$basename
			)
		)
		{
			return XBRL::load_taxonomy(
				"$compiledPath/$basename.json",
				false
			);
		}

		return XBRL::withTaxonomy( $schemaFile );
	}
}
