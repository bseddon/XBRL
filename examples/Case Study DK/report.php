<?php

/**
 * Example to read instance documents for the Danish Business Authority (Erhvervsstyrelsen)
 * Uses compiled taxonomies to improve performance
 *  _					   _	 _ _ _
 * | |   _   _  __ _ _   _(_) __| (_) |_ _   _
 * | |  | | | |/ _` | | | | |/ _` | | __| | | |
 * | |__| |_| | (_| | |_| | | (_| | | |_| |_| |
 * |_____\__, |\__, |\__,_|_|\__,_|_|\__|\__, |
 *	     |___/	  |_|					 |___/
 *
 * @author Bill Seddon
 * @version 1.0
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
 * Overført resultat							Carried forward
 * Selskabskapital								Share capital
 * I alt										Total
 */

namespace lyquidity\dfb;

/* ------------------------------------------------------------
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
global $cacheLocation, $compiledLocation, $instancesLocation;

// This is a local location used to store the expanded taxonomy files because they come in a taxonomy package (zip) file.
$cacheLocation = __DIR__ . "/cache"; // !!! Change this

// This is a local location where the compiled version of the taxonomy will be stored.
$compiledLocation = __DIR__ . "/compiled"; // !!! Change this

// The location of the instances to be reported.  It could be a non-local location such as a web site.
$instancesLocation = __DIR__ . "/instances"; // !!! Change this

/* ------------------------------------------------------------
 *  Taxonomies and instances
 * ------------------------------------------------------------ */

// The set of instance documents to report
// There needs to be some mechanism to generate sets of company files.
$instances = array( // !!! Change this
	'and co 2014.xml',
	'and co 2015.xml',
	'and co 2016.xml',
	'and co 2017.xml'
);

/* ------------------------------------------------------------
 * Make the processor code accessible
 * ------------------------------------------------------------ */
require_once __DIR__ . '/vendor/autoload.php'; // !!! Change this
require_once __DIR__ . '/Observer.php';

// Make it a validating processor
\XBRL::setValidationState();

// Use the debug log for more ready display of issues to the console
\XBRL_Log::getInstance()->debugLog();

// An observer is used to capture any validation information generated while processing taxonomies
$observer = new Observer();
\XBRL_Log::getInstance()->attach( $observer );

$concepts = array(
	'Omsaetning' => 'fsa.xsd#fsa_Revenue',
	'Bruttofortjeneste' => 'fsa.xsd#fsa_GrossProfitLoss',
	'Resultat før skat' => 'fsa.xsd#fsa_ProfitLossFromOrdinaryActivitiesBeforeTax',
	'Årets resultat' => 'fsa.xsd#fsa_ProfitLoss',
	'Egenkapital' => 'fsa.xsd#fsa_Equity',
	'Balance' => 'fsa.xsd#fsa_Assets'
);

// Data in the form [concept][year][value]
$data = array();

try
{
	if ( ! file_exists( "$compiledLocation" ) )
	{
		$observer->addItem( "error", "The compiled folder location does not exist" );
		return;
	}

	foreach ( $instances as $instanceFilename )
	{
		$observer->addItem( "action", "processing instance '$instanceFilename'" );

		\XBRL_Global::reset();
		\XBRL_Types::reset();

		// Initialize the cache
		$context = \XBRL_Global::getInstance();
		if ( ! $context->useCache )
		{
			$context->useCache = true;
			$context->cacheLocation = $cacheLocation;
			$context->initializeCache();
		}

		if ( ! file_exists( "$instancesLocation/$instanceFilename" ) )
		{
			$observer->addItem( "error", "Unable to locate the instance document '$instanceFilename'" );
			continue;
		}

		// Look to see if there is an existing cached file from a previous report
		$instanceBasename = basename( $instanceFilename, '.xml' );
		if ( file_exists( "$compiledLocation/$instanceBasename.json" ) && file_exists( "$compiledLocation/$instanceBasename.meta" ) )
		{
			// Load the JSON file which contains the name of the taxonomy to use
			$json = file_get_contents( "$compiledLocation/$instanceBasename.meta" );
			$meta = json_decode( $json, true );
			$instance = \XBRL_Instance::FromInstanceCache( $compiledLocation, "{$meta['instance']}.json", $meta['namespace'], "$compiledLocation/{$meta['taxonomy']}" );
		}
		else
		{
			$schemaHRef = getInstanceTaxonomyHRef( "$instancesLocation/$instanceFilename" );

			// Get the version year from the href
			$parts = explode( '/', $schemaHRef );
			if ( count( $parts ) != 6 )
			{
				$observer->addItem( "error", "instance document taxonomy namespace has an invalid structure '$schemaHRef'" );
				continue;
			}

			// Use the version year to choose the correct compiled taxonomy
			$compiledTaxonomyFilename = "$compiledLocation/entryAll{$parts[4]}.json";
			if ( ! file_exists( $compiledTaxonomyFilename ) )
			{
				$observer->addItem( "error", "instance document taxonomy namespace is not a supported version '$parts[4]'" );
				continue;
			}

			// Pass $compiledTaxonmyFilename which will reference the compiled taxonomy
			$instance = \XBRL_Instance::FromInstanceDocument( "$instancesLocation/$instanceFilename", $compiledTaxonomyFilename );
			$instance->toInstanceCache( $compiledLocation, $instanceBasename );
			$metadata = array(
				'taxonomy' => basename( $compiledTaxonomyFilename ),
				'namespace' => $instance->getInstanceTaxonomy()->getNamespace(),
				'instance' => $instanceBasename
			);

			$json = json_encode( $metadata );
			file_put_contents( "$compiledLocation/$instanceBasename.meta", $json );
		}

		// Get the year of the ending balance - there's only one ending balance so there should only be one fact
		$facts = $instance->getElement('ReportingPeriodEndDate');
		$year = substr( reset( $facts )['value'], 0, 4 );

		// Find all the contexts specific to the year so facts can be filtered
		$endingBalanceContexts = $instance->getContexts()->ContextsForYear( $year, true )->getContexts();

		// Get the facts for the current year
		$currentYearElements = $instance->getElements()->ElementsByContexts( array_keys( $endingBalanceContexts ) );

		// Access the list of primary items
		$primaryItems = $instance->getInstanceTaxonomy()->getDefinitionPrimaryItems();

		// Build the data array
		foreach ( $concepts as $concept => $conceptId )
		{
			// Handy reference to the concept taxonomy
			$conceptTaxonomy = $instance->getInstanceTaxonomy()->getTaxonomyForXSD( $conceptId );
			$conceptElement = $conceptTaxonomy->getElementById( $conceptId );

			// This is how to access the concept description
			$descriptionEN = $conceptTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $conceptId );
			$descriptionDA = $conceptTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $conceptId, null, 'da' );

			// If the conceot has not been used yet, add namespace, name and id
			if ( ! isset( $data[ $concept ] ) )
			{
				$data[ $concept ] = array(
					'name' => $conceptElement['name'],
					'namespace' => $conceptTaxonomy->getNamespace(),
					'id' => $conceptId
				);
			}
			// Add a default value to show the cell has been processed even if there is no data
			$data[ $concept ]['values'][ $year ] = array( 'value' => null, 'unit' => null );

			// This will be non-null if the dimensional relationshipset (DRS) for a primary
			// item has been evaluated.  There can be many facts for each concept and some
			// and some or or all of the will be for a primary item.  If so, the facts for
			// a concept that is a primary item will share the same set of hypercubes.
			$drsHypercubes = null;

			$facts = $currentYearElements->ElementsByName( $conceptElement['name'] )->getElements();
			if ( $facts )
			{
				if ( ! isset( $facts[ $conceptElement['name'] ] ) ) continue;
				foreach ( $facts[ $conceptElement['name'] ] as $guid => $fact )
				{
					if ( isset( $primaryItems[ $fact['label'] ] ) )
					{
						// Only do this once
						if ( is_null( $drsHypercubes ) )
						{
							$drsHypercubes = $conceptTaxonomy->getPrimaryItemDRS( $primaryItems[ $fact['label'] ] );
						}

						if ( ! count( $drsHypercubes ) )
						{
							continue; // Primary items have hypercubes so something is wrong!
						}

						// Check to see if any of the hypercubes are dimensionally valid
						// Only one needs to be valid for the pi to be valid
						if ( ! $instance->isDRSValidForFact( $fact, $drsHypercubes ) ) continue;
					}

					$qname = qname( $instance->getUnit($fact['unitRef'] ), $instance->getInstanceNamespaces() );

					$data[ $concept ]['values'][ $year ] = array(
						'value' => $fact['value'],
						'unit' => $qname->localName,
						'en' => $descriptionEN,
						'da' => $descriptionDA
					);

				}
			}
		}

	}

}
catch( \Exception $ex )
{
	echo $ex->getMessage();
	return;
}

print_r( $data );

return;

/**
 * Return the 'href' value of schemaRef tag
 * @param string $filename
 * @return string
 */
function getInstanceTaxonomyHRef( $filename )
{
	$dom = new \DOMDocument();
	$dom->load( $filename );
	$domXPath = new \DOMXPath( $dom );
	$domXPath->registerNamespace( 'xbrli', \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] );
	$domXPath->registerNamespace( 'link', \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] );
	$nodes = $domXPath->query("/xbrli:xbrl/link:schemaRef");
	/** @var $domElement \DOMElement */
	$domElement = $nodes[0];
	return $domElement->getAttribute('xlink:href');

}

