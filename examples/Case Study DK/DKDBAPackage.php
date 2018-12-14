<?php

namespace lyquidity\dfb;

/**
 * This is a DBA specific package handler for the years before 2016.
 * Since 2016 the packages have followed the XBRL package specification.
 */
class DKDBAPackage extends \XBRL_SimplePackage
{
	/**
	 * Notes about using this package instance
	 * @var string
	 */
	const notes = <<<EOT
This is a simple package implementation for Danish Business Authority taxonomies before 2016.  Since 2016
taxonomy packages have followed the XBRL taxonomy specification.  This package is hardcoded to use a prefix
of: http://archprod.service.eogs.dk/taxonomy\n\n
EOT;

	const prefix = 'http://archprod.service.eogs.dk/taxonomy';

	/**
	 * Returns an array of schema file names defined as entry points
	 */
	public function getSchemaEntryPoints()
	{
		$root = $this->contents[ $this->getFirstFolderName() ];
		$yearName = key( $root );
		$year = $root[ $yearName ];

		$entryPoints = array_filter( $year , function( $item ) { return ! is_array( $item ) && strpos( $item, 'entry' ) === 0; } );
		$entryPoints = array_map( function( $item ) use( $yearName ) { return join( '/', array( $this::prefix, $yearName, $item ) ); }, $entryPoints );

		return $entryPoints;
	}

	/**
	 * Return the url for the 'all' entry point
	 */
	public function getAllEntryPoint()
	{
		$entryPoints = $this->getSchemaEntryPoints();
		$alls = array_filter( $entryPoints, function( $entryPoint ) { return strpos( $entryPoint, "entryAll" ) !== false; } );
		return $alls ? reset( $alls ) : false;
	}

	/**
	 * Overridden to make sure this class is not use unless the package DOES NOT have a META-INF folder
	 * {@inheritDoc}
	 * @see XBRL_SimplePackage::isPackage()
	 */
	public function isPackage()
	{
		if ( isset( $this->contents[ $this->getFirstFolderName() ][ \XBRL_TaxonomyPackage::metaFolderName ] ) ) return false;

		$found = false;

		// Look for the full_ifrs folder
		$this->traverseContents( function( $path, $name, $type ) use( &$found )
		{
			if ( $type != PATHINFO_DIRNAME ) return true;
			$found = $name == 'full_ifrs';
			return ! $found;
		} );

		if ( $found ) return false;

		return parent::isPackage();
	}

	protected function getActualUri( $uri )
	{
		return $this->getFirstFolderName() . str_replace( self::prefix, '', $uri );
	}

	/**
	 * Workout which file is the schema file
	 * @return void
	 * @throws "tpe:schemaFileNotFound"
	 */
	protected function determineSchemaFile()
	{
		if ( $this->schemaFile && $this->schemaNamespace ) return;

		$schemaFilesList = $this->getSchemaEntryPoints();

		if ( count( $schemaFilesList ) == 0 )
		{
			throw XBRL_TaxonomyPackageException::withError( "tpe:schemaFileNotFound", "The package does not contain any entry schema (.xsd) files" );
		}

		foreach ( $schemaFilesList as $schemaFile )
		{
			$actualUri = $this->getActualUri( $schemaFile );
			$content = $this->getFile( $actualUri );
			if ( $content )
			{
				$this->schemaNamespace = $this->getTargetNamespace( $schemaFile, $content );
				$this->schemaFile = $schemaFile;

				$this->setUrlMap();
			}
		}
	}

	/**
	 * Save the taxonomy from the package to the cache location
	 * @param string $cacheLocation
	 * @return boolean
	 */
	public function saveTaxonomy( $cacheLocation )
	{
		$getCommonRootFolder = function( $actualUri, $uri )
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
				// 'actual' => implode( "/", array_reverse( array_slice( $actualUriParts, $i -1 ) ) ),
				// 'uri'    => implode( "/", array_reverse( array_slice( $uriParts, $i -1 ) ) )
				'actual' => implode( "/", array_reverse( array_slice( $actualUriParts, $i ) ) ),
				'uri'    => implode( "/", array_reverse( array_slice( $uriParts, $i ) ) )
			);
		};

		// Initialize the context
		$context = \XBRL_Global::getInstance();
		$context->useCache = true;
		$context->cacheLocation = $cacheLocation;
		$context->initializeCache();

		$this->determineSchemaFile();

		if ( $context->findCachedFile( $this->schemaFile ) )
		{
			$this->errors[] = "The schema file '{$this->schemaFile}' already exists in the cache.";
			return false;
		}

		// Look at the entry points and remap them to their location in the zip file
		foreach ( $this->getSchemaEntryPoints() as $index => $uri )
		{
			// Is there a rewrite?
			$actualUri = $this->getActualUri( $uri );

			// Should call XBRL_Package::getCommonRootFolder()
			$commonRootFolder = $getCommonRootFolder( $actualUri, $uri );

			// Handle the relative case
			// $folder = $this->getElementForPath( dirname( $actualUri ) );
			$folder = $this->getElementForPath( $commonRootFolder['actual'] );

			// Create a copy of all files and folder in the cache location
			$copy = function( $folder, $path, $uri ) use( &$copy, &$context )
			{
				foreach ( $folder as $index => $item )
				{
					// if $index is numeric then $item is a file
					// if ( is_numeric( $index ) && $index < 2000) // The index might be 2015 or 20161001
					if ( ! is_array( $item ) )
					{
						$content = $this->getFile( "$path/$item" );
						if ( ! $content ) continue;
						if ( $context->findCachedFile( "$uri/$item" ) ) continue;
						$context->saveCacheFile( "$uri/$item", $content );
					}
					else
					{
						if ( $index == "META-INF" ) continue;

						// Handle any directories
						$newUri = "$uri/$index";
						$newPath = "$path/$index";
						$copy( $item, $newPath, $newUri );
					}
				}
			};

			// $copy( $folder, dirname( $actualUri ),  dirname( $uri ) );
			$copy( $folder, $commonRootFolder['actual'],  $commonRootFolder['uri'] );
		}

		return true;
	}

}
