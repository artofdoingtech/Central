<?php
/**
 * « Le prix est bien écrit sur la ligne » — et le total l'ignorait quand même.
 *
 * LE DÉFAUT D'ORIGINE (trouvé le 2026-08-15 sur le bac à sable, en ouvrant une
 * commande au hasard). `$order->get_item( $id )` vaut `get_item( $id, true )` :
 * WooCommerce rend un objet NEUF, relu en base, DÉTACHÉ de la commande. Poser un
 * prix dessus puis appeler `$item->save()` écrit correctement la ligne — mais
 * `$order->calculate_totals()` additionne `$order->get_items()`, c'est-à-dire les
 * instances MISES EN CACHE par `add_product()`, restées au prix catalogue. Le
 * total ne voit donc jamais ce qu'on vient de décider.
 *
 * 🚨 CE N'EST PAS UN DÉFAUT D'AFFICHAGE, C'EST DE L'ARGENT. En COD, le montant
 * encaissé par le livreur EST `get_total()`. Mesuré sur trois commandes :
 *   · 2 sacs à 5 250 (prix de lot) → total calculé sur 2 × 5 400 : le client
 *     paie 300 د.ج de TROP à sa porte, sur un prix qu'on lui avait promis ;
 *   · un casque à +500 de supplément de variante → 500 de MOINS encaissés, à la
 *     charge de la boutique ;
 *   · une commande sans lot ni supplément → juste, et c'est ce qui rendait le
 *     défaut invisible : il ne se manifeste QUE là où le prix s'écarte du tarif
 *     de base, donc jamais sur un clone neuf ni sur une recette au prix nu.
 *
 * ⚠️ CE QUE CE TEST GARDE, ET CE QU'IL NE PROUVE PAS. Il est STATIQUE : il exige
 * que tout appel à `get_item()` des deux plugins NOMME son second argument, pour
 * qu'on ne puisse plus hériter du défaut par omission. Il ne calcule aucun total
 * et ne remplace pas une recette — la preuve chiffrée est en navigateur. Le
 * cliquet ne vaut ici que par sa portée : il regarde les DEUX plugins, pas le
 * fichier où le défaut a été trouvé. Les quatre appels fautifs vivaient dans
 * trois fichiers différents (création COD publique, éditeur d'articles de
 * `/gestion`, jeu de démonstration) ; n'en corriger qu'un aurait laissé les
 * autres écrire des totaux faux dans la même base.
 *
 * ⚠️ TROU CONNU, ÉCRIT PLUTÔT QUE TU : `scripts/` N'EST PAS BALAYÉ. Le dossier
 * n'est pas un plugin, il n'a pas de constante de chemin, et il est monté
 * ailleurs dans le conteneur. Or c'est précisément là que vit
 * `dev-seed-commandes.php`, le troisième porteur du défaut — corrigé le même
 * jour, mais NON gardé. Un futur script de semis peut donc réintroduire des
 * totaux faux sans faire rougir quoi que ce soit. Le dire ici vaut mieux que
 * laisser croire à une couverture qui n'existe pas.
 *
 * 🔎 POURQUOI EXIGER LE DRAPEAU PLUTÔT QU'INTERDIRE `true`. Les deux valeurs sont
 * légitimes — relire en base a du sens quand on ne veut PAS toucher au total.
 * Ce qui n'en a pas, c'est de laisser le défaut par DÉFAUT. Écrire `false` ou
 * `true` oblige à trancher une fois, à l'endroit où on a le contexte pour le
 * faire ; l'omission, elle, tranche toute seule et dans le mauvais sens.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Ligne de commande — l’instance modifiée est celle que le total additionne' );

/*
 * Racine = les plugins TELS QUE MONTÉS (`AOD_CD_PATH` / `AOD_COD_PATH`), jamais
 * un chemin relatif à ce fichier : la suite tourne dans le conteneur, où
 * `tests/` et `plugins-dev/` ne sont pas voisins. Même patron que
 * `test-nonce-contract.php`.
 */
$aod_gi_cod     = defined( 'AOD_COD_PATH' ) ? AOD_COD_PATH : dirname( AOD_CD_PATH ) . '/aod-cod-form/';
$aod_gi_fautifs = array();
$aod_gi_vus     = 0;

foreach ( array( AOD_CD_PATH, $aod_gi_cod ) as $aod_gi_racine ) {
	$aod_gi_iter = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $aod_gi_racine, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $aod_gi_iter as $aod_gi_f ) {
		if ( 'php' !== strtolower( $aod_gi_f->getExtension() ) ) {
			continue;
		}
		$aod_gi_lignes = file( $aod_gi_f->getPathname(), FILE_IGNORE_NEW_LINES );
		foreach ( $aod_gi_lignes as $aod_gi_n => $aod_gi_ligne ) {
			// `get_items(` ne peut pas matcher : la parenthèse suit le `m` sans « s ».
			if ( ! preg_match( '/->get_item\s*\(/', $aod_gi_ligne ) ) {
				continue;
			}
			++$aod_gi_vus;
			// Un seul argument = le défaut par omission. On ne cherche pas à
			// analyser l'expression : la présence d'une virgule de premier niveau
			// suffit, et un appel étalé sur plusieurs lignes serait de toute façon
			// à relire à la main.
			if ( ! preg_match( '/->get_item\s*\([^)]*,[^)]*\)/', $aod_gi_ligne ) ) {
				$aod_gi_fautifs[] = basename( $aod_gi_f->getPathname() ) . ':' . ( $aod_gi_n + 1 );
			}
		}
	}
}

AOD_Test::ok(
	empty( $aod_gi_fautifs ),
	'aucun `get_item()` des deux plugins ne laisse WooCommerce rendre une copie détachée',
	empty( $aod_gi_fautifs )
		? ''
		: 'appels sans second argument : ' . implode( ', ', $aod_gi_fautifs )
			. ' — écris `false` (l’instance en mémoire, celle que `calculate_totals()` lira) ou `true` (une copie, en sachant que le total l’ignorera)'
);

/*
 * ANCRAGE INVERSE — sans lui, le contrôle ci-dessus serait vert sur un dépôt où
 * plus personne n'appelle `get_item()`, et personne ne s'en apercevrait. La
 * leçon est celle de la Phase 47 : une assertion peut être verte par accident.
 */
AOD_Test::ok(
	$aod_gi_vus > 0,
	'le contrôle a bien vu des appels à inspecter (il ne peut pas être vert par vide)',
	'0 appel `get_item()` trouvé : soit les racines de plugins sont fausses, soit le motif ne mord plus'
);
