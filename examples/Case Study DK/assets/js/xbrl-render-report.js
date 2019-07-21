	jQuery(document).ready( function ( $ ) 
	{
		var changeWidth = function( change ) 
		{
			// var re = /((\d+)px)+/mg;
			var re = /((\d+(.\d+)?)px)+/mg;
			var css = jQuery('div#primary .report-table').css('grid-template-columns');
			var matches = css.match(re);
			if ( matches == null ) return false;
			var last = matches.slice(-1).pop().replace('px', '');
			if ( isNaN(0+last) ) return false;
			var reReplace = new RegExp('(' + last + 'px)+', 'g');
			css = css.replace( reReplace, (last*1+change)+'px' );
			jQuery('div#primary .report-table').css('grid-template-columns', css);			
		};

		var processCheckbox = function(checkbox)
		{
			var selector = 'div#primary .' + $(checkbox).data('class');
			if ( checkbox.checked )
			{
				$(selector).removeClass('hide-section');
			}
			else
			{
				$(selector).addClass('hide-section');
			}
			
		};

		var checkConstraint = function( checkboxes )
		{
			var y = ['structure-table','facts-section','business-rules-section'];
			if ( checkboxes.filter( (i,c) => $(c).prop('checked') ).map( (i,c) => $(c).data('class') ).filter( (i,x) => y.includes( x ) ).length )
			{
				// Remove .constrained clsss
				$('div#primary .model-structure').removeClass('constrained');
			}
			else
			{
				// Add constrained from model-structure
				$('div#primary .model-structure').addClass('constrained');				
			}
			
		};

		window.initializeCheckboxes = function()
		{
			// Find all the checkboxes
			var checkboxes = jQuery('div#primary div.report-selection input');
			checkboxes.each( function(checkbox)
			{
				processCheckbox(this);
			} );

			checkConstraint( checkboxes );

			checkboxes.on('click', function(e)
			{
				processCheckbox(this);
				checkConstraint( checkboxes );				
			} );
		};

		window.initializeChangeColunWidth = function()
		{
			$('div#primary .report-table-controls > .control-wider').on( 'click', function( e )
			{
				changeWidth( 10 );
			} );

			$('div#primary .report-table-controls > .control-narrower').on( 'click', function( e )
			{
				changeWidth( -10 );
			} );
		};
		
		window.initializeLinks = function( e )
		{
			$('div#primary .report-table-links > .factset-link').on( 'click', function( e )
			{
				var name = $(this).data('name');
				var offset = $('div#primary div[name=' + name + ']' ).get(0).offsetTop;
				var page = $('#render-dialog');
				if ( page.length == 0 ) page = $('html');
				page.get(0).scrollTop = offset;
				return false;
			} );
			
		};

		window.initializeChangeColunWidth();
		window.initializeCheckboxes();
		window.initializeLinks();
	} );