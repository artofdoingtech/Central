/* AOD Client Dashboard — section « Accueil (graphes) » (extraction 33.2).
   IIFE à ALIAS LOCAUX : les corps de fonctions restent VERBATIM — ils
   consomment les helpers de la coque (dashboard.js) sous leur nom d'origine. */
( function ( core ) {
	'use strict';


	/* Graphe à courbe : infobulle + repère vertical au survol (stats) */
	function bindCharts() {
		document.querySelectorAll( '.aod-cd-linechart' ).forEach( function ( box ) {
			var pts;
			try { pts = JSON.parse( box.dataset.points || '[]' ); } catch ( e ) { return; }
			if ( ! pts.length ) { return; }

			var vw    = parseFloat( box.dataset.vw ) || 820;
			var vh    = parseFloat( box.dataset.vh ) || 215;
			var guide = box.querySelector( '.aod-cd-guide' );
			var tip   = box.querySelector( '.aod-cd-tip' );
			var dots  = box.querySelectorAll( '.aod-cd-dot' );

			// Géométrie réellement rendue du SVG. Avec `preserveAspectRatio` +
			// `max-height` en CSS, le tracé peut être plus étroit que le conteneur
			// (marges vides à gauche/droite) : il faut donc l'échelle ajustée et le
			// décalage du letterboxing, sinon le survol vise le mauvais point.
			function geom() {
				var r  = box.getBoundingClientRect();
				var sc = Math.min( r.width / vw, r.height / vh );
				if ( ! sc || ! isFinite( sc ) ) { sc = r.width / vw; }
				return { rect: r, sc: sc, ox: ( r.width - vw * sc ) / 2, oy: ( r.height - vh * sc ) / 2 };
			}

			function show( i, g ) {
				g = g || geom();
				var p = pts[ i ];
				if ( guide ) {
					guide.setAttribute( 'x1', p.x );
					guide.setAttribute( 'x2', p.x );
					guide.style.opacity = '1';
				}
				dots.forEach( function ( d, j ) {
					d.setAttribute( 'r', j === i ? '4.2' : '2.6' );
				} );
				if ( tip ) {
					tip.innerHTML = '<b>' + p.amount + '</b>' + p.label;
					tip.classList.add( 'show' );
					// Maintient l'infobulle dans le cadre du graphe (clamp horizontal),
					// et la bascule sous le point quand l'espace au-dessus manque.
					var px    = g.ox + p.x * g.sc;
					var py    = g.oy + p.y * g.sc;
					var halfW = tip.offsetWidth / 2;
					var pad   = 6;
					var minX  = halfW + pad;
					var maxX  = box.clientWidth - halfW - pad;
					if ( maxX < minX ) { maxX = minX; }
					px = Math.max( minX, Math.min( maxX, px ) );
					tip.classList.toggle( 'is-below', ( py - tip.offsetHeight - 14 ) < 0 );
					tip.style.left = px + 'px';
					tip.style.top  = py + 'px';
				}
			}

			function hide() {
				if ( guide ) { guide.style.opacity = '0'; }
				if ( tip ) { tip.classList.remove( 'show' ); }
				dots.forEach( function ( d ) { d.setAttribute( 'r', '2.6' ); } );
			}

			function pick( clientX ) {
				var g  = geom();
				var mx = ( clientX - g.rect.left - g.ox ) / g.sc; // coord. viewBox
				var best = 0, bd = Infinity;
				for ( var i = 0; i < pts.length; i++ ) {
					var d = Math.abs( pts[ i ].x - mx );
					if ( d < bd ) { bd = d; best = i; }
				}
				show( best, g );
			}

			box.addEventListener( 'mousemove', function ( e ) { pick( e.clientX ); } );
			box.addEventListener( 'mouseleave', hide );
			box.addEventListener( 'touchstart', function ( e ) {
				if ( e.touches[ 0 ] ) { pick( e.touches[ 0 ].clientX ); }
			}, { passive: true } );
			box.addEventListener( 'touchmove', function ( e ) {
				if ( e.touches[ 0 ] ) { pick( e.touches[ 0 ].clientX ); }
			}, { passive: true } );
		} );
	}

	core.register( 'bindCharts', bindCharts );
}( window.aodCdCore ) );
