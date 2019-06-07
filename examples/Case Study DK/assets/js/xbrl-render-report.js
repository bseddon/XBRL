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

		window.initializeCheckboxes = function()
		{
			// Find all the checkboxes
			var checkboxes = jQuery('div#primary div.report-selection input');
			checkboxes.each( function(checkbox)
			{
				processCheckbox(this);
			} );

			checkboxes.on('click', function(e)
			{
				processCheckbox(this);
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
		
		window.initializeChangeColunWidth();
		window.initializeCheckboxes();
	} );