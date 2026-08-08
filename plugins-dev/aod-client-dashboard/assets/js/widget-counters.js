/* AOD Client Dashboard — le décompte animé des nombres de /gestion.
 *
 * 🔁 EXTRAIT DE `section-home.js` LE 2026-08-06 (demande owner : « fais pour les
 * autres pages ce que tu as fait pour l'Accueil »), et l'extraction EST le
 * correctif. Ce code vivait dans le module de l'Accueil et se bridait lui-même à
 * `[data-section="home"]`, avec cette raison écrite : « le composant KPI sert
 * aussi à Stock, qui n'a rien demandé ». La prudence était juste le jour où on
 * anime UNE page ; elle devient une divergence le jour où on les anime toutes —
 * deux écrans rendant le MÊME composant (`AOD_CD_Dashboard::kpis()`), l'un avec
 * ses chiffres qui montent, l'autre sans, et rien pour dire pourquoi.
 *
 * 🔒 POURQUOI UN `widget-` ET PAS LA COQUE NI UNE SECTION. Le remettre dans
 * `section-home.js` en élargissant sa portée aurait fait piloter Stock par le
 * module de l'Accueil — le genre de dépendance qu'aucun cliquet ne voit et que
 * personne ne devine. La coque, elle, garde les primitives PARTAGÉES de quelques
 * dizaines de lignes (`toast`, `confirmModal`) ; son plafond R1 n'est pas un
 * dépôt pour tout ce qui sert à deux endroits. D'où ce troisième rang, ouvert la
 * veille par `widget-datepicker.js` : ce qui appartient à un COMPOSANT partagé,
 * pas à une page.
 *
 * Le corps est VERBATIM — mêmes trois refus, même durée, même sortie. Seule la
 * portée change (le `[data-section="home"]` tombe) et le nom avec elle.
 */
( function ( core ) {
	'use strict';

	/* Décompte animé des nombres (2026-08-05, généralisé le 2026-08-06)
	 *
	 * Les tuiles « à traiter » et les KPI entiers montent de 0 à leur valeur.
	 * C'est du CHROME au sens strict : aucune requête, aucun état, et la page
	 * affiche déjà le bon chiffre avant que ce code ne s'exécute.
	 *
	 * 🚨 TROIS REFUS PLUTÔT QU'UNE APPROXIMATION, parce que le seul défaut
	 * inacceptable ici serait d'afficher au gérant un nombre FAUX :
	 *
	 *   1. UN SEUL NŒUD TEXTE, sinon on passe. C'est ce qui écarte les montants
	 *      sans avoir à les reconnaître : `wc_price()` rend du balisage imbriqué
	 *      (<bdi>, symbole de devise), donc plusieurs nœuds. Et c'est heureux —
	 *      leur notation dépend des réglages Woo, la reconstituer afficherait une
	 *      seconde durant des prix dans un format qui n'est pas celui de la
	 *      boutique.
	 *   2. LE FORMATEUR DOIT REPRODUIRE LA CHAÎNE RENDUE. On lit le séparateur de
	 *      milliers dans le texte du serveur, on reformate la valeur finale avec,
	 *      et on n'anime que si le résultat est identique au caractère près. Un
	 *      « 100.0 % », un chiffre arabe, une espace fine inattendue : on
	 *      s'abstient. Un décompte silencieusement absent est sans conséquence ;
	 *      un décompte qui invente une notation, non.
	 *   3. LA CHAÎNE D'ORIGINE EST REPOSÉE EN SORTIE, telle quelle. L'état final
	 *      est donc toujours, exactement, ce que PHP a écrit.
	 *
	 * 🔒 Portée : `.aod-cd-content`, donc les pages de /gestion et elles seules.
	 * Les deux classes visées ne sont rendues que par des composants du tableau
	 * de bord — inutile de nommer les sections une à une, ce qui rouvrirait la
	 * liste écrite à la main dont cette étape se débarrasse côté CSS.
	 *
	 * `prefers-reduced-motion` sort avant tout le reste : la feuille ne peut rien
	 * contre une valeur écrite en JavaScript, c'est donc ici que ça se joue. */
	function bindCounters() {
		if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) { return; }

		// Reformate un entier avec le séparateur de milliers observé (groupes de 3).
		function fmt( n, sep ) {
			var s = String( n );
			if ( ! sep ) { return s; }
			var out = '';
			for ( var i = 0; i < s.length; i++ ) {
				if ( i > 0 && 0 === ( s.length - i ) % 3 ) { out += sep; }
				out += s.charAt( i );
			}
			return out;
		}

		var SEL = '.aod-cd-content .aod-cd-todo-tile__n,'
			+ '.aod-cd-content .aod-cd-kpi__val';

		document.querySelectorAll( SEL ).forEach( function ( el ) {
			// Refus n°1 — un seul nœud texte, sinon on passe.
			if ( 1 !== el.childNodes.length || 3 !== el.childNodes[ 0 ].nodeType ) { return; }

			var noeud   = el.childNodes[ 0 ];
			var origine = noeud.nodeValue;
			// Ce qui RESTE une fois les chiffres retirés : le séparateur de milliers
			// tel que `number_format_i18n()` l'a posé (virgule, espace fine…), ou rien.
			var restes = origine.replace( /[0-9]/g, '' );
			var sep    = restes.length ? restes.charAt( 0 ) : '';
			var cible  = parseInt( origine.replace( /[^0-9]/g, '' ), 10 );
			if ( ! isFinite( cible ) || cible <= 0 ) { return; } // Rien à compter jusqu'à 0.
			// Refus n°2 — le formateur doit reproduire EXACTEMENT le rendu du serveur.
			// C'est ce contrôle, et lui seul, qui prouve que la chaîne entière est un
			// entier groupé : « 100.0 % » et « 2 500,00 د.ج » n'y survivent pas.
			if ( fmt( cible, sep ) !== origine ) { return; }

			var duree = 900;
			var debut = null;
			function pas( t ) {
				if ( null === debut ) { debut = t; }
				var p = Math.min( 1, ( t - debut ) / duree );
				// Sortie franche, la même intention que --cd-ease-out : le chiffre
				// s'approche vite de sa valeur puis se pose.
				var e = 1 - Math.pow( 1 - p, 3 );
				if ( p < 1 ) {
					noeud.nodeValue = fmt( Math.round( cible * e ), sep );
					window.requestAnimationFrame( pas );
				} else {
					noeud.nodeValue = origine; // Refus n°3 : l'état final est celui de PHP.
				}
			}
			noeud.nodeValue = fmt( 0, sep );
			window.requestAnimationFrame( pas );
		} );
	}

	core.register( 'bindCounters', bindCounters );
}( window.aodCdCore ) );
