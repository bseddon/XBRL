<?php

/**
 * XBRL Formulas
 *  _					   _	 _ _ _
 * | |   _   _  __ _ _   _(_) __| (_) |_ _   _
 * | |  | | | |/ _` | | | | |/ _` | | __| | | |
 * | |__| |_| | (_| | |_| | | (_| | | |_| |_| |
 * |_____\__, |\__, |\__,_|_|\__,_|_|\__|\__, |
 *	     |___/	  |_|					 |___/
 *
 * @author Bill Seddon
 * @version 0.1.1
 * @Copyright ( C ) 2017 Lyquidity Solutions Limited
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * ( at your option ) any later version.
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

namespace XBRL\Formulas\Resources\Filters;

 use lyquidity\XPath2\XPath2Expression;
use lyquidity\XPath2\XPath2Exception;

 /**
  * Implements the filter class for the SpecificScheme filter
  * http://www.xbrl.org/specification/entityfilters/rec-2009-06-22/entityfilters-rec-2009-06-22.html#example-specific-entity-scheme-filters
  * CAN BE OPTIMIZED
  * (see ModelFormulaObject.py ModelEntitySpecificIdentifier class)
  */
class SpecificScheme extends Filter
{
	/**
	 * The filter test to apply
	 * @var string $scheme
	 */
	public $scheme = null;

	/**
	 * A compiled version of $scheme
	 * @var XPath2Expression $schemaXPath2Expression
	 */
	public $schemaXPath2Expression = null;

  	/**
 	 * Processes a node to extract formula or variable resource information
 	 * @param string $localName The name of the resource element being processed
 	 * @param \XBRL $taxonomy The taxonomy referencing the linkbase being processed
 	 * @param string $roleUri
 	 * @param string $linkbaseHref
 	 * @param string $label
 	 * @param \SimpleXMLElement $node A \SimpleXMLElement reference to the node to be processed
 	 * @param \DOMNode $domNode A \DOMNode reference to the node to be processed
	 * @param \XBRL_Log $log $log
 	 */
	public function process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log )
	{
		$result = parent::process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log );

		$attributes = $node->attributes();

		if ( ! property_exists( $attributes, 'scheme' ) && \XBRL::isValidating() )
		{
			$log->formula_validation( "Entity filter", "No 'scheme' attribute in the SpecificScheme element", array(
				'roleuri' => $roleUri,
				'label' => $label,
				'localname' => $localName,
			) );
		}
		else
		{
			$this->scheme = trim( $attributes->scheme );
		}

		$result['scheme'] = $this->scheme;

		$result = parent::storeFilter( $result, $localName );

		return $result;
	}

	/**
	 * Converts a node to an XPath query
	 *
	 * @param VariableSet $variableSet
	 * @param FactVariableBinding $factVariableBinding
	 * @return string
	 */
	public function toQuery( $variableSet, $factVariableBinding )
	{
		return "(xfi:fact-identifier-scheme(.) eq {$this->scheme})";
	}

	/**
	 * Filter an array of facts using the filters XPath query.
	 * @param XPath2NodeIterator $facts
	 * @return XPath2NodeIterator Returns the filtered list
	 */
	public function Filterx( $facts )
	{
	}

	/**
	 * Returns the set of aspects covered by this instance
	 *
	 * @var VariableSet $variableSet
	 * @return an array of aspect identifiers
	 */
	public function getAspectsCovered( $variableSet, $factVariableBinding )
	{
		return array( ASPECT_ENTITY_IDENTIFIER );
	}

	/**
	 * Check the select and as
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::validate()
	 * @param VariableSet $variableSet
	 * @param XmlNamespaceManager $nsMgr
	 */
	public function validate( $variableSet, $nsMgr )
	{
			try
		{
			if ( $this->scheme )
			{
				if ( ! $this->schemaXPath2Expression )
				{
					$this->schemaXPath2Expression = XPath2Expression::Compile( $this->scheme, $nsMgr );
				}
			}
		}
		catch ( \Exception $ex )
		{
			\XBRL_Log::getInstance()->formula_validation( "Specific scheme filter", "Failed to compile scheme expression",
				array(
					'schema expression' => $this->scheme,
					'error' => $ex instanceof XPath2Exception ? $ex->ErrorCode : get_class( $ex ),
					'reason' => $ex->getMessage()
				)
			);
			return false;
		}

		return parent::validate( $variableSet, $nsMgr );
	}

	/**
	 * Return any parameter references in the select statement (if there is one)
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		if ( $this->schemaXPath2Expression )
		{
			return $this->schemaXPath2Expression->getParameterQNames();
		}
		return parent::getVariableRefs();
	}

}
