<?php

/**
 * XBRL SEC JSON Package handler
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
 * Implements functions to read a taxonomy package
 */
class XBRL_SEC_JSON_Package extends XBRL_Package
{
	/**
	 * Notes about using this package instance
	 * @var string
	 */
	const notes = <<<EOT
The SEC implementations use the namespace of the schema document to create a path to use in the cache.
The namespace format appears to be <domain>/<period> where <period> is the value of the EDGAR period.
A path is not used in the instance document <schemaRef> so the instance document expects to be in the
same folder as the taxonomy.  It is likely that a mapped URL will need to be used so the correct
schema can be accessed from the cache.\n\n
EOT;

	/**
	 * The EDGAR file number
	 * @var string
	 */
	public $fileNumber;

	/**
	 * The EDGAR period
	 * @var string
	 */
	public $period;

	/**
	 * The EDGAR accession number
	 * @var string
	 */
	public $accessionNumber;

	/**
	 * The EDGAR publication date
	 * @var string
	 */
	public $pubDate;

	/**
	 * The EDGAR acceptance Datetime
	 * @var string
	 */
	public $acceptanceDatetime;

	/**
	 * The EDGAR compay name
	 * @var string
	 */
	public $companyName;

	/**
	 * The EDGAR filing date
	 * @var string
	 */
	public $filingDate;

	/**
	 * The EDGAR form type
	 * @var string
	 */
	public $formType;

	/**
	 * The EDGAR fiscal year end
	 * @var string
	 */
	public $fiscalYearEnd;

	/**
	 * The EDGAR company address
	 * 	zip
	 *	state
	 *	street1
	 *	phone
	 *	city
	 * @var string
	 */
	public $address;

	/**
	 * The EDGAR package description
	 * @var string
	 */
	public $description;

	/**
	 * The EDGAR assigned SIC
	 * @var string
	 */
	public $assignedSic;

	/**
	 * The EDGAR cik number
	 * @var string
	 */
	public $cikNumber;

	/**
	 * The list of package files
	 * @var string
	 */
	public $files = array();

	/**
	 * Default constructor
	 * @param ZipArchive $zipArchive
	 */
	public function __construct( ZipArchive $zipArchive  )
	{
		parent::__construct( $zipArchive );
	}

	/**
	 * Returns true if the zip file represents an SEC package
	 * {@inheritDoc}
	 * @see XBRL_IPackage::isPackage()
	 */
	public function isPackage()
	{
		// A SEC JSON package has a file called <ciknumber>-manifest.xml
		try
		{
			$this->traverseContents( function( $path, $name, $type )
			{
				if ( $type == PATHINFO_DIRNAME ) return true;
				$extension = pathinfo( $name, PATHINFO_EXTENSION );
				if ( $extension != 'json' ) return  true;
				$this->metaFilename = $name;

				return false;
			} );

			if ( empty( $this->metaFilename ) )
			{
				throw XBRL_TaxonomyPackageException::withError( "tpe:metadataFileNotFound", "The package does not contain a .json file" );
			}

			return $this->isValidMetaFile();
		}
		catch ( Exception $ex )
		{
			$code = $ex instanceof XBRL_TaxonomyPackageException ? $ex->error : $ex->getCode();
			$this->errors[ $code ] = $ex->getMessage();
		}

		return false;

	}

	/**
	 * Check the meta file is valid
	 */
	private function isValidMetaFile()
	{
		if ( $this->metaFile ) return true;

		try
		{
			$content = $this->getFile( $this->metaFilename );
			// SEC seems to use UCS-2 LE
			$json = mb_convert_encoding( $content, 'UTF-8', 'UTF-16' );
			$this->metaFile = json_decode( $json, true );

			foreach ( $this->metaFile as $name => $value )
			{
				if ( ! property_exists( $this, $name ) ) continue;
				if ( $name == 'filingDate' )
				{
					if ( preg_match( "!Date\((?'timestamp'.*?)\)!", $value, $matches ) )
					{
						$value = date( "y-m-d", $matches['timestamp'] );
					}
				}
				$this->$name = $value;
			}

			$schemaFileList = array_filter( $this->files, function( $item ) {
				return XBRL::endsWith( $item, '.xsd' );
			} );

			if ( count( $schemaFileList ) != 1 )
			{
				throw XBRL_TaxonomyPackageException::withError( "tpe:schemaFileNotFound", "The package does not contain a schema (.xsd) file" );
			}

			$this->schemaFile = reset( $schemaFileList );

		}
		catch ( XBRL_TaxonomyPackageException $ex )
		{
			throw $ex;
		}
		catch ( Exception $ex )
		{
			throw XBRL_TaxonomyPackageException::withError( "tpe:invalidMetaDataFile", $ex->getMessage() );
		}

		return true;
	}

	/**
	 * Save the taxonomy from the package to the cache location
	 * @param string $cacheLocation
	 * @return boolean
	 */
	public function saveTaxonomy( $cacheLocation )
	{
		// Initialize the context
		$context = XBRL_Global::getInstance();
		$context->useCache = true;
		$context->cacheLocation = $cacheLocation;
		$context->initializeCache();

		// Find the schema document
		$content = trim( $this->getFile( $this->schemaFile ) );

		$result = $this->processSchemaDocument( $context, $this->schemaFile, $content, false );

		$this->setUrlMap();

		if ( ! $result )
		{
			$this->errors[] = "Unable to process the schema document";
			return false;
		}

		// Look at the entry points and remap them to their location in the zip file
		foreach ( $this->files as $index => $file )
		{
			$extension = pathinfo( $file, PATHINFO_EXTENSION );
			if ( ! in_array( $extension, array( 'xsd', 'xml' ) ) ) continue;

			$content = $this->getFile( $file );
			if ( $file != $this->schemaFile )
			{
				$xml = simplexml_load_string( $content );
				if ( $xml->getName() == 'xbrl' )
				{
					$this->instanceDocument = $file;
				}
			}

			$context->saveCacheFile( "{$this->schemaNamespace}/$file", $content );
		}

		return true;
	}

}