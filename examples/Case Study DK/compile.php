<?php

/**
 * Example to compile taxonomies of the the Danish Business Authority (Erhvervsstyrelsen)
 * Uses compilation to improve performance.
 *  _					   _	 _ _ _
 * | |   _   _  __ _ _   _(_) __| (_) |_ _   _
 * | |  | | | |/ _` | | | | |/ _` | | __| | | |
 * | |__| |_| | (_| | |_| | | (_| | | |_| |_| |
 * |_____\__, |\__, |\__,_|_|\__,_|_|\__|\__, |
 *	     |___/	  |_|					 |___/
 *
 * @author Bill Seddon
 * @version 0.9
 * @Copyright (C) 2018 Lyquidity Solutions Limited
 * @License: GPL 3.0
 *
 * Taxonomies are available here:
 * https://erhvervsstyrelsen.dk/tidligere-versioner
 *
 * The taxonomies can be viewed here using Yeti:
 * https://yeti2.corefiling.com/yeti/resources/yeti-gwt/Yeti.jsp
 *
 * Handy terms for the english retricted reader:
 *
 * Erhvervsstyrelsen: Business Authority
 *
 * Taxonomy terms:
 * --------------
 * kontoform fordelt på kort - og langfristet:	Account form broken down by short and long term
 * Resultatopgørelse (artsopdelt):				Profit and loss account (divided by class)
 * Resultatopgørelse (funktionsopdelt):			Income Statement (Functional)
 *
 * Report terms:
 * ------------
 * Årsrapport:									Annual Report
 * Nøgletal:									Key figures
 * Omsaetning:									Revenue
 * Bruttofortjeneste:							Gross Profit
 * Resultat før skat:							Profit before tax
 * Årets resultat:								Net profit
 * Egenkapital:									Equity
 * Balance:										Total Assets/Liabilities
 */

namespace lyquidity\dfb;

/** ------------------------------------------------------------
 * Change some environment parameters
 * ------------------------------------------------------------ */
// Set the maximum execution time to three minutes
set_time_limit(180);
// Make sure there is olenty of memory
ini_set( 'memory_limit', '512M' );
// Allow deep nesting which may be required for the XPath 2.0 processor when XDebug is used
ini_set('xdebug.max_nesting_level', 512);

/* ------------------------------------------------------------
 *  In lieu of implementing this as a class, use globals for
 *  the location variables so they can be accessed anywhere
 *  within the code.  There is nothing magical about the
 *  folders chosen here.  They need to be somewhere that can
 *  be written.
 * ------------------------------------------------------------ */
global $cacheLocation, $compiledLocation, $taxonomieslocation;

// This is a local location used to store the expanded taxonomy files because they come in a taxonomy package (zip) file.
$cacheLocation = __DIR__ . "/cache"; // !!! Change this

// This is a local location where the compiled version of the taxonomy will be stored.
$compiledLocation = __DIR__ . "/compiled"; // !!! Change this

// This is the location of the DK taxonomy packages to use.  It could be a non-local location such as a web site.
$taxonomiesLocation = __DIR__ . "/taxonomies"; // !!! Change this

/* ------------------------------------------------------------
 *  Taxonomies
 * ------------------------------------------------------------ */

// The current set of taxonomies to process
$taxonomies = array(
	'xbrl20130401_20140515_1004.zip',
	'xbrl20140701_20140820_1339.zip',
	'xbrl20151001_20151209_0903_0.zip',
	'XBRL20161001_20161123_1408.zip',
	'hent_aarl_taksonomien_zip_xbrl20171001_20171208_0926_1.zip'
);

/* ------------------------------------------------------------
 * Make the processor code accessible
 * ------------------------------------------------------------ */
require_once __DIR__ . '/vendor/autoload.php';  // !!! Change this
require_once __DIR__ . '/Observer.php';
require_once __DIR__ . '/SimplePackage.php';

// Make it a validating processor
\XBRL::setValidationState();

// Use the debug log for more ready display of issues to the console
\XBRL_Log::getInstance()->debugLog();

// An observer is used to capture any validation information generated while processing taxonomies
$observer = new Observer();
\XBRL_Log::getInstance()->attach( $observer );

try
{
	foreach ( $taxonomies as $taxonomyPackageFile )
	{
		echo "Processing taxonomy '$taxonomyPackageFile'";
		// Pass the name of the custom package as an alternative format to try
		$package = \XBRL_Package::getPackage( "$taxonomiesLocation/$taxonomyPackageFile", array( '\lyquidity\dfb\SimplePackage' ) );
		if ( ! $package )
		{
			echo "The package '$taxonomyPackageFile' is not valid\n";
			continue;
		}

		if ( processTaxonomyPackage( $observer, "$taxonomiesLocation/$taxonomyPackageFile", $package ) )
		{
			continue;
		}

		echo "There are validation warnings processing the taxonomy\n";
		echo print_r( $observer->getIssues() );
	}
}
catch( \Exception $ex )
{
	echo $ex->getMessage();
	return;
}

return;

/**
 * Returns true if the response should read 'complete' false otherwise
 * @param observer $observer
 * @param string $file
 * @param $package \XBRL_Package
 * @throws \Exception
 * @return boolean
 */
function processTaxonomyPackage( $observer, $file, $package )
{
	error_log("processTaxonomyPackage");

	global $cacheLocation, $compiledLocation;

	// Initialize the cache
	\XBRL_Global::reset();
	\XBRL_Types::reset();
	$context = \XBRL_Global::getInstance();
	if ( ! $context->useCache )
	{
		$context->useCache = true;
		$context->cacheLocation = $cacheLocation;
		$context->initializeCache();
	}

	$observer->addItem( "action", 'saving package contents' );
	if ( ! $package->saveTaxonomy( $cacheLocation ) && ! $package->schemaFile )
	{
		$observer->addItem( "errors",$package->errors );
		return false;
	}

	$observer->addItem( "action", 'taxonomy saved' );

	$year = substr( $package->getFirstFolderName(), 4 );
	$entryAllBasename = "entryAll";

	if ( ( $compiledTaxonomy = \XBRL::isCompiled( $compiledLocation, "$entryAllBasename$year" ) ) == false )
	{
		$observer->addItem( "action", 'compiling' );

		if ( ! file_exists( $compiledLocation ) )
		{
			mkdir( $compiledLocation );
		}

		// ALways compile the 'all' entry point. This makes sure the compiled taxonomy is a superset of all the other entry points
		if ( ! \XBRL::compile( dirname( $package->schemaFile ) . "/$entryAllBasename.xsd", "http://xbrl.dcca.dk/$entryAllBasename", $compiledLocation . "/$entryAllBasename$year" ) )
		{
			$observer->addItem( "action", "taxonomy $year failed to compile" );
			return false;
		}
		if ( \XBRL_Log::getInstance()->hasConformanceIssueWarning() )
		{
			return false;
		}
		$compiledTaxonomy = \XBRL::isCompiled( $compiledLocation, $package->getSchemaFileBasename() );
		$observer->addItem( "action", 'taxonomy compiled' );
	}
	else
	{
		$observer->addItem( "action", 'taxonomy already compiled' );
	}

	return true;
}
