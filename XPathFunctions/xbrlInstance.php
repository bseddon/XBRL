<?php

/**
 * XPath 2.0 for PHP
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

namespace XBRL\functions;

use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\XPath2Exception;

/**
 * This is not yet supported.  It has WGWD status.
 *
 * Returns a Xbrl Instance <xbrli:xbrl> element representing the specified Xbrl Instance.
 *
 * This function allows formula expressions to have access to multiple XBRL instances.  It facilitates comparisons
 * between the formula linkbase's usual target instance and one or more additional XBRL instance.
 *
 * The instance that is loaded by this function has its nodes strongly typed according to its own Post Schema Validation
 * Infoset.  Any of this instance's taxonomies which have same namespace as any prior-loaded XBRL instance's taxonomies
 * may share the prior loaded taxonomies xsd's at the discretion of the implementors.
 *
 * Regardless of whether this instance shares schema files with other loaded instances, its linkbases may be non
 * intersecting with those of other instances and the DTS of this instance must be kept separate from the DTS
 * of other instances (even if other instances DTSes have some same schema or linkbase files).  So a Q3 instance and Q2
 * instance may have same target namespaces but different linkbases.  The navigate-relationships and concept-labels
 * functions are able to access relationships and resources appropriate to the loaded instance by the xbrli:xbrl
 * object returned by this function, and if linkbases differ they remain distinct and accessible after loading.
 *
 * Repetitions of this function continue to return the identical result and XPath-identical nodes, it is expected that
 * an efficient implementation will cache function results so that subsequent calls return the first-obtained result.
 * Using the same wording as XPath2 uses for its fn:doc(), two calls on the xfi:xbrl-instance function return the same
 * XBRL instance document node if the same URI Reference (after resolution to an absolute URI Reference) is supplied to
 * both calls. Thus, the following XPath 2 expression (if it does not raise an error) will always be true:
 *
 * 	xfi:xbrl-instance("foo.xml") is xfi:xbrl-instance("foo.xml")
 *
 * The nodes of the instance loaded by this function may be utilized in XPath2 expressions and as function call arguments
 * within XPath2 expressions, and may be bound to generalVariables (but not factVariables). An XPath term referencing a
 * node of this instance will only know about the DTS of this instance, e.g., navigation among nodes of this instance is
 * confined to nodes of this instance.  Functions that are node-aware such as node-name or xfi:concept-balance will use
 * the node's parent XML document information or equivalent implementation information to know which of multiple DTSes in
 * memory the function argument pertains to.
 *
 * Functions that are linkbase aware (such as xfi:navigate-relationships) lack an automatic way of determining which of
 * multiple loaded instance DTSes they pertain to and thus have an (optional) parameter of the instance node when it is
 * desired to specify a DTS of an XBRL instance loaded by this function that is different than that of the target XBRL
 * instance.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return element(xbrli:xbrl)	Returns the xbrli:xbrl element of the href'ed instance, unless it can't be loaded, in
 * 								which case an exception is raised.  The returned xbrli:xbrl element and all descendant
 * 								nodes are PSVI typed according to the DTS of this loaded instance (not the DTS of the
 * 								formula linkbase target instance).
 *
 * 								XPath 2 expressions with therms of this output element and its descendant nodes can
 * 								intermix with terms of target instance nodes and nodes from any other xbrl-instance
 * 								function loaded instances.
 *
 * 								The DTS of this instance, including concept elements and their descendant nodes, arc
 * 								relationships and their arc attributes, and linkbases with resources (such as labels)
 * 								may be shared with other loaded instances if of the same target namespace identifier,
 * 								or may be separate, at the discretion of the implementer.  Any access to such nodes will
 * 								be PSVI typed (such as a weight attribute, of a calculation arc, accessed by the
 * 								navigate-relationships function.
 *
 * @throws xfie:unableToLoadXbrlInstance This error MUST be thrown if the specified instance can't be loaded.
 *
 * This function has one real argument:
 *
 * href	xs:anyURI	The href that specifies the instance document to be loaded.
 * 					If href is relative, then it is relative to the base URI of the target instance document, not relative
 * 					to the formula linkbase.  This allows servers to keep their formula linkbases isolated from storage of
 * 					server processes and their instance documents.
 */
function xbrlInstance( $context, $provider, $args )
{
	try
	{
		if ( $args[0] instanceof XPath2NodeIterator )
		{
			if ( ! $args[0]->getCount() || ! $args[0]->MoveNext() )
			{
				throw new \InvalidArgumentException();
			}

			$args[0] = $args[0]->getCurrent();
		}

		return $args[0]->arcType;
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
