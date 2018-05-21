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
 * @version 0.9
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

use XBRL\Formulas\Resources\Formulas\Formula;
use lyquidity\xml\QName;


 /**
  * Implements the filter class for the Identity filter
  * http://www.xbrl.org/specification/entityfilters/rec-2009-06-22/entityfilters-rec-2009-06-22.html#sec-entity-identifier-filter
  * CAN BE OPTIMIZED
  * Once optimized the addition of variables to the XPath context FactVariable can be removed
  * (see ModelFormulaObject.py ModelMatchFilter class)
  */
class MatchFilter extends Filter
{
	/**
	 * The filter test to apply
	 * @var string $variable
	 */
	public $variable = null;

	/**
	 * A list of any locally defined namespaces
	 * @var null|array $localNamespaces
	 */
	public $localNamespaces = null;

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

		// Get local namespaces but not any default
		$localNamespaces = array_filter( $node->getDocNamespaces( true, false ), function( $namespace, $prefix ) {
			return ! empty( $prefix );
		}, ARRAY_FILTER_USE_BOTH );

		if ( count( $localNamespaces ) )
		{
			$this->localNamespaces = $localNamespaces;
			$result['localNamespaces'] = $this->localNamespaces;
		}

		$attributes = $node->attributes();

		if ( ! property_exists( $attributes, 'variable' ) )
		{
			if ( \XBRL::isValidating() )
			{
				$log->formula_validation( "Match filter", "No 'variable' attribute in the $localName element", array(
					'roleuri' => $roleUri,
					'label' => $label,
					'localname' => $localName,
				) );
			}
		}
		else
		{
			$namespaces = $node->getDocNamespaces(true);

			/**
			 * @var QName $qName
			 */
			// If there is no prefix it should not be resolved to a default namespace
			$variable = trim( $attributes->variable );
			$qName = strpos( $variable, ":" )
				? qname( $variable, $namespaces )
				: new QName( "", null, $variable );
			$this->variable = array(
				'name' => is_null( $qName ) ? $source : $qName->localName,
				'originalPrefix' => is_null( $qName ) ? null : $qName->prefix,
				'namespace' => is_null( $qName ) ? null : $qName->namespaceURI,
			);

			$result['variable'] = $this->variable;

		}

		// No need to call parent::storeFilter() here because descendents will.

		return $result;
	}

	/**
	 * Converts a node to an XPath query
	 *
	 * @param Formula $variableSet
	 * @param FactVariableBinding $factVariableBinding
	 * @return string
	 */
	public function toQuery( $variableSet, $factVariableBinding )
	{
		$variable = $this->variable['originalPrefix']
			? "{$this->variable['originalPrefix']}:{$this->variable['name']}"
			: $this->variable['name'];
		$aspects = implode("','", $this->getAspectsCovered( $variableSet, $factVariableBinding ) );
		$class = get_class($this);
		return "(lyquidity:aspectMatch(\$lyquidity:factVariable,\$lyquidity:variableSet,.,\$$variable,('$aspects'),'$class'))";
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
		return parent::validate( $variableSet, $nsMgr );
	}

	/**
	 * Return any parameter references in the select statement (if there is one)
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		return parent::getVariableRefs();
	}

}
