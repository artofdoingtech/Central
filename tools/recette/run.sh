#!/usr/bin/env bash
# Recette navigateur du clone :
#   · /gestion — accessibilité AA, clavier, souris, RTL + mobile ;
#   · les 3 pages publiques — nav à 3 entrées, aucun panier/compte, RTL (`public`).
#
# L'audit de contraste est rejoué sur PLUSIEURS ACCENTS. C'est le cœur du sujet :
# l'accent est choisi par le client dans le Personnalisateur, donc un contraste
# validé avec l'accent par défaut (un jaune très clair, très contrasté) ne prouve
# rien. Les accents ci-dessous sont choisis pour être hostiles — bleu de marque
# banal, bordeaux, teal, rouge pur, presque-noir.
#
#   Usage :  bash tools/recette/run.sh            # tout
#            bash tools/recette/run.sh contraste  # une seule recette
#            bash tools/recette/run.sh public     # les 3 pages publiques + le tunnel COD
#            bash tools/recette/run.sh cod        # juste la commande COD de bout en bout
#            bash tools/recette/run.sh bilingue   # catalogue bilingue FR/AR (clôture Phase 7)
#            bash tools/recette/run.sh riche      # description produit riche FR/AR (clôture Phase 8)
#            bash tools/recette/run.sh categories # sélection des catégories sur l'accueil (clôture Phase 9)
#            bash tools/recette/run.sh conversions # conversions serveur Meta/TikTok/Snapchat + tests connexion (clôture Phase 13 ; alias : capi)
#            bash tools/recette/run.sh abandonnees # commandes abandonnées dans /gestion (clôture Phase 11)
#            bash tools/recette/run.sh suivi       # Confirmée → expédiée se déplace vers Suivi (clôture Phase 12)
#            bash tools/recette/run.sh degrade     # /gestion sans le plugin COD : dégradation propre, zéro fatale (Phase 32.1)
#            bash tools/recette/run.sh journal     # le journal écrit-il depuis une requête WEB ? (dette D28)
#            bash tools/recette/run.sh charge      # tenue en charge de l'Accueil : sème 10 000 commandes, mesure, purge (Phase 31.6)
#                                                  #   volume réglable : AOD_CHARGE_N=3000 ; HORS « tout » (plusieurs minutes)
#            bash tools/recette/run.sh mouvement   # transitions, creux d'appui, mouvement réduit (clôture Phase 16)
#            bash tools/recette/run.sh envoi       # confirmer n'expédie plus (clôture Phase 47)
#            bash tools/recette/run.sh confirmateur # /gestion parcouru en CONFIRMATEUR (clôture Phase 22)
#            bash tools/recette/run.sh assignation # assignation des commandes aux confirmateurs (clôture Phase 42)
#
# Prérequis : la stack Docker du clone tourne, et Chromium est installé
#             (`npx playwright install chromium`, ou AOD_CHROMIUM=/chemin/chrome).
set -uo pipefail

HERE="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
ROOT="$( cd "$HERE/../.." && pwd )"
cd "$ROOT" || exit 1

# 🪟 WINDOWS / GIT BASH — ce script mêle DEUX familles de chemins, et une seule
# des deux doit être convertie. MSYS réécrit tout argument ressemblant à un chemin
# POSIX absolu avant que le programme ne le voie :
#   · les `/scripts/recette-seed-*.php` sont des chemins DANS le conteneur → toute
#     conversion les casse (« Seed en échec », portes auditant des pages vides) ;
#   · le `.js` passé à `node` est un chemin de l'HÔTE → sans conversion, Node lit
#     `/c/Users/…` comme `C:\c\Users\…` et ne trouve aucun module.
# D'où : conversion coupée globalement, puis rétablie POUR LE SEUL appel à node,
# via `cygpath -m` (rend `C:/Users/…`, que Git Bash accepte aussi en `cd`).
# 🚨 Un réglage global ne peut pas convenir aux deux : c'est ce qui rendait cette
# recette injouable sur Windows, dans un sens comme dans l'autre.
# Sans effet sur Linux/macOS : la variable y est ignorée, `cygpath` n'existe pas.
export MSYS_NO_PATHCONV=1
if command -v cygpath >/dev/null 2>&1; then
	HERE_NODE="$( cygpath -m "$HERE" )"
else
	HERE_NODE="$HERE"
fi

ACCENTS=( "#1878f0" "#7B1E3A" "#0d9488" "#ff0000" "#111827" )
FAILS=0

wpcli() { docker compose exec -T wpcli wp "$@" >/dev/null 2>&1; }

# Accent d'ORIGINE du clone, capturé AVANT toute modification.
#
# 🚨 Ce bloc répare un défaut destructif : `restore_accent()` faisait un simple
# `theme mod remove`, donc ne « restaurait » rien — il remettait le site à l'accent
# PAR DÉFAUT du thème. Sur un clone où le gérant a choisi sa couleur dans le
# Personnalisateur, la recette la supprimait silencieusement… alors que le README
# demande justement de la rejouer APRÈS avoir réglé l'accent du client. Et le trap
# l'appelle à chaque exécution, même `run.sh cod`.
#
# On distingue donc deux gestes qui étaient confondus :
#   · use_default_accent() — ce dont la porte `contraste` a besoin (tester le défaut) ;
#   · restore_accent()     — rendre au clone SA valeur (ou l'absence de valeur).
# wpcli DOIT être debout AVANT de lire l'accent : sinon la capture échoue — et,
# plus grave, une capture vide serait indistinguable d'un clone qui n'a pas
# d'accent. Cette porte était la SEULE à dépendre de wpcli sans le relancer.
docker compose up -d wpcli >/dev/null 2>&1 || true

# 🚨 SENTINELLE (défaut de recette corrigé le 2026-07-21). Une capture vide est
# AMBIGUË : « le clone n'a pas d'accent » OU « wpcli était tombé, on n'a rien lu ».
# Les confondre est DESTRUCTEUR : `restore_accent()` ferait un `theme mod remove`
# et effacerait la couleur de marque choisie par le gérant (sur ce clone, #00A651).
# On enregistre donc à part si la capture a RÉUSSI — signalée par l'exit 0 de
# `docker compose exec` (non nul si wpcli est injoignable), et non par le contenu.
ORIG_ACCENT=""
ORIG_ACCENT_KNOWN=0
if _accent_json="$( docker compose exec -T wpcli wp theme mod get aod_cd_accent --format=json 2>/dev/null )"; then
	ORIG_ACCENT="$( printf '%s' "$_accent_json" | sed -n 's/.*"value":"\([^"]*\)".*/\1/p' | tr -d '\r\n' )"
	ORIG_ACCENT_KNOWN=1
fi

use_default_accent() { wpcli theme mod remove aod_cd_accent || true; }
restore_accent() {
	# Capture douteuse (wpcli tombé au démarrage) : NE RIEN FAIRE. Retirer l'accent
	# ici effacerait la couleur de marque du gérant — le défaut destructif exact
	# que la sentinelle ci-dessus empêche. Mieux vaut ne pas restaurer que détruire.
	[ "$ORIG_ACCENT_KNOWN" = "1" ] || return 0
	if [ -n "$ORIG_ACCENT" ]; then
		wpcli theme mod set aod_cd_accent "$ORIG_ACCENT" || true
	else
		wpcli theme mod remove aod_cd_accent || true
	fi
}
BILINGUE_SEEDED=0
cleanup_bilingue() {
	if [ "$BILINGUE_SEEDED" = "1" ]; then
		wpcli eval-file /scripts/recette-seed-bilingue.php cleanup || true
	fi
}
PHASE8_SEEDED=0
cleanup_phase8() {
	if [ "$PHASE8_SEEDED" = "1" ]; then
		wpcli eval-file /scripts/recette-seed-phase8.php cleanup || true
	fi
}
PHASE9_SEEDED=0
cleanup_phase9() {
	if [ "$PHASE9_SEEDED" = "1" ]; then
		wpcli eval-file /scripts/recette-seed-phase9.php cleanup || true
	fi
}
PHASE11_SEEDED=0
cleanup_phase11() {
	if [ "$PHASE11_SEEDED" = "1" ]; then
		wpcli eval-file /scripts/recette-seed-phase11.php cleanup || true
	fi
}
PHASE12_SEEDED=0
cleanup_phase12() {
	if [ "$PHASE12_SEEDED" = "1" ]; then
		wpcli eval-file /scripts/recette-seed-phase12.php cleanup || true
	fi
}

PANIER_SEEDED=0
cleanup_panier() {
	# Restaure AUSSI l'option aod_cod_cart capturée par le seed (leçon D26 :
	# une fatale au milieu de la porte ne doit pas laisser le panier du clone
	# dans son état de test).
	if [ "$PANIER_SEEDED" = "1" ]; then
		wpcli eval-file /scripts/recette-seed-panier.php cleanup || true
	fi
}
SOURIS_SEEDED=0
cleanup_souris() {
	if [ "$SOURIS_SEEDED" = "1" ]; then
		wpcli eval-file /scripts/recette-seed-souris.php cleanup || true
	fi
}
COD_SEEDED=0
cleanup_cod() {
	if [ "$COD_SEEDED" = "1" ]; then
		wpcli eval-file /scripts/recette-seed-cod.php cleanup || true
	fi
}
LANDING_SEEDED=0
cleanup_landing() {
	# Témoin « sans page de vente » (D33). Purge même si la porte est interrompue
	# en plein milieu : un produit jetable laissé publié fausserait la Boutique.
	if [ "$LANDING_SEEDED" = "1" ]; then
		wpcli eval-file /scripts/recette-seed-landing.php cleanup || true
	fi
}
PHASE22_SEEDED=0
cleanup_phase22() {
	if [ "$PHASE22_SEEDED" = "1" ]; then
		wpcli eval-file /scripts/recette-seed-phase22.php cleanup || true
	fi
}
VARIANTE_SEEDED=0
cleanup_variante() {
	# Purge produits témoins + commande témoin (leçon D26 : jamais laisser le clone
	# dans son état de test — même si une fatale interrompt la porte en plein milieu).
	if [ "$VARIANTE_SEEDED" = "1" ]; then
		wpcli eval-file /scripts/recette-seed-variante.php cleanup || true
	fi
}
ASSIGNATION_SEEDED=0
cleanup_assignation() {
	# Purge ciblée (comptes confirmateur + commandes marquées), jamais par rôle
	# ni statut (leçon D26/Phase 22) — même si la porte est interrompue en cours.
	if [ "$ASSIGNATION_SEEDED" = "1" ]; then
		wpcli eval-file /scripts/recette-seed-assignation.php cleanup || true
	fi
}
cleanup_transverse() {
	[ "${D29_SEEDED:-0}" = "1" ] || return 0
	docker compose exec -T wpcli wp eval-file /scripts/recette-seed-transverse.php cleanup >/dev/null 2>&1 || true
	D29_SEEDED=0
}

on_exit() { cleanup_transverse; restore_accent; cleanup_bilingue; cleanup_phase8; cleanup_phase9; cleanup_phase11; cleanup_phase12; cleanup_souris; cleanup_cod; cleanup_landing; cleanup_phase22; cleanup_panier; cleanup_variante; cleanup_assignation; }
trap on_exit EXIT

step() { printf '\n\033[1m▸ %s\033[0m\n' "$1"; }

# Convention de sortie des scénarios :
#   0 = passé · 1 (ou autre) = échec · 3 = RETIRÉ (sans objet sur ce clone).
#
# 🚨 Le code 3 existe parce qu'un scénario qui se retire sortait à 0, donc
# comptait comme un SUCCÈS. Deux portes le font pour de bonnes raisons — pas de
# produit avec page de vente sur le clone (elle est optionnelle par produit),
# Turnstile actif donc pas de jeton en test — mais le bilan final annonçait
# « tout passe » alors qu'une porte n'avait rien joué. Un clone où la landing
# serait cassée au point de n'être détectée nulle part rendait donc le même vert
# qu'un clone sain. On les compte désormais à part, et le bilan les nomme.
RETIRES=0
WITHDRAWN_LIST=""
run() {
	# `$HERE_NODE` et non `$HERE` : chemin de l'HÔTE, cf. le bloc Windows en tête.
	node "$HERE_NODE/$1"
	local code=$?
	if [ "$code" -eq 3 ]; then
		RETIRES=$(( RETIRES + 1 ))
		WITHDRAWN_LIST="${WITHDRAWN_LIST}${WITHDRAWN_LIST:+, }$1"
	elif [ "$code" -ne 0 ]; then
		FAILS=$(( FAILS + 1 ))
	fi
}

WHAT="${1:-tout}"

# 🚨 Garde d'argument. Le dispatch ci-dessous est une SUITE de `if` indépendants,
# pas un `case` : un argument que personne ne reconnaît ne déclenchait aucune
# porte, n'affichait aucun avertissement, et tombait dans le bilan final qui
# annonçait « tout passe » — puis sortait à 0. Autrement dit
# `run.sh contrast` (sans le « e ») rendait exactement le même vert que
# `run.sh contraste`, en n'ayant rien vérifié du tout. C'est le mode d'échec de
# la « règle du témoin » appliqué à l'outil lui-même.
#
# La liste doit rester synchronisée avec les `if` du dispatch — c'est le prix de
# ne pas réécrire tout le fichier en `case`.
SCENARIOS="tout contraste noms clavier mouvement souris rtl public landing bilingue riche categories cod conversions capi abandonnees suivi confirmateur charge degrade journal panier variante assignation envoi"
case " $SCENARIOS " in
	*" $WHAT "*) ;;
	*)
		printf '\033[31m✗ Scénario inconnu : « %s »\033[0m\n' "$WHAT" >&2
		printf 'Scénarios valides :\n' >&2
		printf '  %s\n' $SCENARIOS >&2
		exit 2
		;;
esac

if [ ! -d "$HERE/node_modules" ]; then
	step "Installation de playwright-core"
	( cd "$HERE" && npm install --silent ) || exit 2
fi

# ─── Fixtures TRANSVERSES (solde de la dette D29) ────────────────────────────
#
# 🚨 POURQUOI. Les portes qui balaient les 12 sections tournaient sur le clone TEL
# QUEL. Mesuré le 2026-07-28 : **6 sections sur 12 rendent un état vide** sur un
# clone de développement (`home`, `orders`, `abandoned`, `products`, `stock`,
# `tracking`) — 0 tableau, 0 ligne. Elles auditaient donc des pages quasi nues et
# rendaient le MÊME vert qu'un audit exhaustif. C'est ainsi que `rtl` — qui mesure
# pourtant le débordement à 390 px — est restée verte pendant que `/gestion/orders`
# débordait de 193 px (commit `1920025`).
#
# Le seed est marqué et purgé en sortie (trap), comme tous les autres : une porte
# ne doit JAMAIS emprunter les données du clone.
TRANSVERSES=" tout contraste noms clavier souris rtl mouvement "
D29_SEEDED=0
case "$TRANSVERSES" in
	*" $WHAT "*)
		step "Fixtures transverses — sans elles, 6 des 12 sections n'ont rien à auditer (D29)"
		docker compose up -d wpcli >/dev/null 2>&1 || true
		if docker compose exec -T wpcli wp eval-file /scripts/recette-seed-transverse.php create 2>&1 | grep -q '^D29=1'; then
			D29_SEEDED=1
			export AOD_D29_SEEDED=1
			printf '  \033[32m✅\033[0m Les 12 sections portent du contenu\n'
		else
			printf '  \033[31m❌ Seed transverse en échec — les portes auditeraient des pages vides\033[0m\n'
			FAILS=$(( FAILS + 1 ))
		fi
		;;
esac

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "contraste" ]; then
	# Comme les 9 autres portes : wpcli a pu tomber (OOM). C'était la SEULE porte à
	# en dépendre sans le relancer — donc celle où un `theme mod set` échouait le
	# plus silencieusement, faisant auditer 6 fois le MÊME accent sous 6 en-têtes.
	docker compose up -d wpcli >/dev/null 2>&1 || true

	# 🚨 TÉMOIN D'ACCENT (défaut de recette corrigé). On passe à la porte JS
	# l'accent ATTENDU : elle lit `--cd-accent` sur la page et rougit s'il ne
	# correspond pas — c'est-à-dire si le `theme mod set/remove` ci-dessous a
	# échoué en silence. Sans ce témoin, « 6 accents balayés » pouvait n'en
	# éprouver qu'un. `#FFE21E` = AOD_CD_Customize::DEFAULT_ACCENT (accent retiré).
	step "Contraste AA — accent par défaut"
	use_default_accent
	export AOD_EXPECT_ACCENT="#FFE21E"; run a11y-contraste.js; unset AOD_EXPECT_ACCENT
	for a in "${ACCENTS[@]}"; do
		step "Contraste AA — accent $a"
		wpcli theme mod set aod_cd_accent "$a"
		export AOD_EXPECT_ACCENT="$a"; run a11y-contraste.js; unset AOD_EXPECT_ACCENT
	done
	restore_accent
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "noms" ]; then
	step "Noms accessibles"
	run a11y-noms.js
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "clavier" ]; then
	step "Clavier"
	run a11y-clavier.js
fi

# Le mouvement — porte de la Phase 16. Aucune autre porte ne le regarde : une
# transition tuée par erreur ne casse aucune assertion, elle rend juste
# l'interface sèche. Vérifie aussi que `prefers-reduced-motion` est RÉELLEMENT
# respecté (une surcharge mal placée dans la cascade est invisible sinon).
# Depuis l'étape 18.1, la porte couvre AUSSI le plugin public (3 feuilles + le
# <style> inline du switcher) et porte un cliquet statique sur tout plugins-dev/.
# Elle a donc besoin des mêmes entrées que les scénarios publics : un produit à
# options ET à offres (pour atteindre les états), et l'ID d'une page de vente si
# le clone en a une. Absence de page de vente = feuille auditée sur l'accueil,
# jamais un retrait : `exit 3` est global au process et emporterait /gestion.
if [ "$WHAT" = "tout" ] || [ "$WHAT" = "public" ] || [ "$WHAT" = "mouvement" ]; then
	step "Mouvement (transitions, appui, mouvement réduit, public)"
	MOUV_LANDING_PID="$( docker compose exec -T wpcli wp post list --post_type=product --post_status=publish \
		--meta_key=_aod_landing_enabled --meta_value=1 --field=ID --posts_per_page=1 2>/dev/null | tr -d '[:space:]' )"
	SEED_OUT="$( docker compose exec -T wpcli wp eval-file /scripts/recette-seed-cod.php create 2>&1 )"
	if printf '%s\n' "$SEED_OUT" | grep -q '^COD=1'; then
		COD_SEEDED=1
		AOD_COD_PID="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^CODPID=//p' | tr -d '\r' )"
		export AOD_COD_PID
		AOD_LANDING_PID="$MOUV_LANDING_PID" run mouvement.js
		cleanup_cod
		COD_SEEDED=0
		unset AOD_COD_PID
	else
		printf '  ❌ Seed du produit jetable (mouvement) en échec :\n%s\n' "$SEED_OUT"
		FAILS=$(( FAILS + 1 ))
	fi
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "souris" ]; then
	step "Souris (non-régression)"
	docker compose up -d wpcli >/dev/null 2>&1 || true
	# Commande JETABLE dédiée : ce scénario applique un vrai changement de
	# statut par AJAX, donc il ne doit jamais toucher une commande du gérant.
	# 🔁 47.2 — la raison D'ORIGINE de cette précaution a disparu : « Confirmée »
	# faisait partir un COLIS RÉEL chez le transporteur (auto_send), ce qui n'est
	# plus le cas depuis que l'envoi est un geste. Elle reste néanmoins juste :
	# un changement de statut écrit du stock et de la traçabilité.
	# Cf. recette-seed-souris.php.
	SEED_OUT="$( docker compose exec -T wpcli wp eval-file /scripts/recette-seed-souris.php create 2>&1 )"
	if printf '%s\n' "$SEED_OUT" | grep -q '^SOURIS=1'; then
		SOURIS_SEEDED=1
		AOD_SOURIS_OID="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^SOURISID=//p' | tr -d '\r' )"
		export AOD_SOURIS_OID
		run a11y-souris.js
		cleanup_souris
		SOURIS_SEEDED=0
		unset AOD_SOURIS_OID
	else
		printf '  ❌ Seed de la commande jetable (souris) en échec :\n%s\n' "$SEED_OUT"
		FAILS=$(( FAILS + 1 ))
	fi
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "rtl" ]; then
	step "RTL + mobile 390 px"
	run rtl-mobile.js
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "public" ]; then
	step "Les 3 pages publiques (Accueil · Boutique · Contact)"
	run public-3-pages.js
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "public" ] || [ "$WHAT" = "landing" ]; then
	step "Page de vente (landing) par produit"
	# Découverte : un produit avec landing PUBLIÉE, et un produit ordinaire pour la
	# non-régression. Aucune landing sur ce clone → le scénario se retire tout seul
	# (elle est optionnelle par produit ; son absence n'est pas un défaut).
	#
	# 🚨 `--post_status=publish` est INDISPENSABLE, et son absence a été vérifiée :
	# `wp post list` sans ce filtre renvoie AUSSI les brouillons — et même en tête,
	# puisque le tri est par date décroissante. Le scénario visite les produits en
	# VISITEUR NON CONNECTÉ : un brouillon lui répond 404, et la porte rougissait
	# donc sur un clone parfaitement sain dont le gérant avait un produit en cours
	# de rédaction. Un faux échec, qui apprend à ignorer la recette.
	#
	# 🚨 LE TÉMOIN « SANS LANDING » S'EXCLUAIT MAL — corrigé le 2026-07-30. Cette
	# sélection ne retirait du vivier que le SEUL produit retenu comme landing
	# (`grep -vx "$LANDING_PID"`), pas TOUS ceux qui en ont une. Elle supposait donc
	# qu'un clone n'allume au plus qu'une page de vente — hypothèse jamais écrite, et
	# fausse dès le catalogue de démonstration, qui en allume deux. Mesuré : 2 produits
	# publiés, 2 landings actives, `PLAIN_PID` désignait un produit AVEC landing, et
	# les 3 contrôles de non-régression rougissaient sur du code parfaitement sain.
	# C'est la famille de défaut de D31 : une porte qui suppose la forme des données.
	#
	# 🚨 ET L'EXCLUSION CORRECTE NE SUFFISAIT PAS — dette `D33`, soldée le 2026-08-01.
	# Exclure tout le vivier était juste, mais laissait le témoin DÉPENDRE de ce que
	# le clone contient : sur un clone semé avec le catalogue de démonstration, les
	# DEUX produits publiés ont une page de vente, le vivier est vide, `PLAIN_PID`
	# ressortait vide et les 4 contrôles de non-régression se retiraient. Le retrait
	# était annoncé — lisible, donc, mais toujours absent.
	# On SÈME désormais le témoin au lieu de le chercher, comme `run.sh cod` sème le
	# produit qu'il achète : il est déterministe, délibérément nu (ni options, ni
	# offres, ni landing), et purgé en sortie même si la porte casse en cours.
	LANDING_IDS="$( docker compose exec -T wpcli wp post list --post_type=product --post_status=publish \
		--meta_key=_aod_landing_enabled --meta_value=1 --field=ID 2>/dev/null | tr -d '\r' )"
	LANDING_PID="$( printf '%s\n' "$LANDING_IDS" | head -1 | tr -d '[:space:]' )"
	PLAIN_SEED_OUT="$( docker compose exec -T wpcli wp eval-file /scripts/recette-seed-landing.php create 2>&1 )"
	if printf '%s\n' "$PLAIN_SEED_OUT" | grep -q '^LANDING=1'; then
		LANDING_SEEDED=1
		PLAIN_PID="$( printf '%s\n' "$PLAIN_SEED_OUT" | sed -n 's/^PLAINPID=//p' | tr -d '\r' )"
		AOD_LANDING_PID="$LANDING_PID" AOD_PLAIN_PID="$PLAIN_PID" run landing-produit.js
		cleanup_landing
		LANDING_SEEDED=0
	else
		printf '  ❌ Seed du témoin sans page de vente (landing) en échec :\n%s\n' "$PLAIN_SEED_OUT"
		FAILS=$(( FAILS + 1 ))
	fi
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "public" ] || [ "$WHAT" = "bilingue" ]; then
	step "Catalogue bilingue FR/AR (clôture Phase 7)"
	# Le service wpcli peut être tombé (OOM) : un exec échouerait en cryptique.
	docker compose up -d wpcli >/dev/null 2>&1 || true
	# Seed des fixtures (catégorie + produits traduit/témoin/landing), purgées par
	# le trap EXIT quoi qu'il arrive. Un seed en échec = recette en échec, pas un
	# scénario qui « se retire » : le bilinguisme du catalogue n'est pas optionnel.
	SEED_OUT="$( docker compose exec -T wpcli wp eval-file /scripts/recette-seed-bilingue.php create 2>&1 )"
	BIL_CAT="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^CAT=//p' )"
	BIL_TRAD="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^TRAD=//p' )"
	BIL_TEMOIN="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^TEMOIN=//p' )"
	BIL_LAND="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^LAND=//p' )"
	if [ -z "$BIL_CAT" ] || [ -z "$BIL_TRAD" ] || [ -z "$BIL_TEMOIN" ] || [ -z "$BIL_LAND" ]; then
		printf '  ❌ Seed des fixtures bilingues en échec :\n%s\n' "$SEED_OUT"
		FAILS=$(( FAILS + 1 ))
	else
		BILINGUE_SEEDED=1
		AOD_BIL_CAT="$BIL_CAT" AOD_BIL_TRAD="$BIL_TRAD" AOD_BIL_TEMOIN="$BIL_TEMOIN" AOD_BIL_LAND="$BIL_LAND" run catalogue-bilingue.js
		cleanup_bilingue
		BILINGUE_SEEDED=0
	fi
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "public" ] || [ "$WHAT" = "riche" ]; then
	step "Description produit riche FR/AR (clôture Phase 8)"
	# Le service wpcli peut être tombé (OOM) : un exec échouerait en cryptique.
	docker compose up -d wpcli >/dev/null 2>&1 || true
	# Seed des fixtures (produit riche + témoin plat + pièces jointes image/vidéo),
	# purgées par le trap EXIT quoi qu'il arrive. Un seed en échec = recette en
	# échec : la description riche n'est pas optionnelle.
	SEED_OUT="$( docker compose exec -T wpcli wp eval-file /scripts/recette-seed-phase8.php create 2>&1 )"
	P8_RICH="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^RICH=//p' )"
	P8_PLAIN="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^PLAIN=//p' )"
	P8_IMG="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^IMG=//p' )"
	P8_VID="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^VID=//p' )"
	if [ -z "$P8_RICH" ] || [ -z "$P8_PLAIN" ] || [ -z "$P8_IMG" ] || [ -z "$P8_VID" ]; then
		printf '  ❌ Seed des fixtures Phase 8 en échec :\n%s\n' "$SEED_OUT"
		FAILS=$(( FAILS + 1 ))
	else
		PHASE8_SEEDED=1
		AOD_P8_RICH="$P8_RICH" AOD_P8_PLAIN="$P8_PLAIN" AOD_P8_IMG="$P8_IMG" AOD_P8_VID="$P8_VID" run description-riche.js
		cleanup_phase8
		PHASE8_SEEDED=0
	fi
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "public" ] || [ "$WHAT" = "categories" ]; then
	step "Catégories sur l'accueil : sélection « à la une » + ordre (clôture Phase 9)"
	docker compose up -d wpcli >/dev/null 2>&1 || true
	# Seed des fixtures (catégories cochées/ordonnées, une avec nom AR, un témoin non
	# vide non coché), purgées par le trap EXIT quoi qu'il arrive. Un seed en échec =
	# recette en échec : la sélection accueil n'est pas optionnelle.
	SEED_OUT="$( docker compose exec -T wpcli wp eval-file /scripts/recette-seed-phase9.php create 2>&1 )"
	P9_Z="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^SELZ=//p' )"
	if [ -z "$P9_Z" ]; then
		printf '  ❌ Seed des fixtures Phase 9 en échec :\n%s\n' "$SEED_OUT"
		FAILS=$(( FAILS + 1 ))
	else
		PHASE9_SEEDED=1
		run categories-accueil.js
		cleanup_phase9
		PHASE9_SEEDED=0
	fi
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "public" ] || [ "$WHAT" = "cod" ]; then
	step "Commande COD de bout en bout (le tunnel de vente)"
	docker compose up -d wpcli >/dev/null 2>&1 || true
	# Passe une VRAIE commande en visiteur anonyme et va la relire en base, puis
	# éprouve les couches anti-bot (honeypot, time-trap, verrou 24 h par téléphone).
	# Le produit acheté est SEMÉ (avec une option obligatoire) : emprunter le
	# dernier produit publié du clone rendait la porte non déterministe, et sur un
	# produit à options le refus venait du JS — les couches anti-bot paraissaient
	# vertes sans jamais être atteintes. Cf. scripts/recette-seed-cod.php.
	SEED_OUT="$( docker compose exec -T wpcli wp eval-file /scripts/recette-seed-cod.php create 2>&1 )"
	if printf '%s\n' "$SEED_OUT" | grep -q '^COD=1'; then
		COD_SEEDED=1
		AOD_COD_PID="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^CODPID=//p' | tr -d '\r' )"
		AOD_COD_OPT="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^CODOPT=//p' | tr -d '\r' )"
		export AOD_COD_PID AOD_COD_OPT
		run commande-cod.js
		cleanup_cod
		COD_SEEDED=0
		unset AOD_COD_PID AOD_COD_OPT
	else
		printf '  ❌ Seed du produit jetable (cod) en échec :\n%s\n' "$SEED_OUT"
		FAILS=$(( FAILS + 1 ))
	fi
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "degrade" ]; then
	step "/gestion sans le plugin COD — dégradation propre (Phase 32.1)"
	# 🔒 `--skip-plugins` et NON `wp plugin deactivate` : ce processus WP-CLI
	# démarre sans le plugin, l'installation n'est PAS touchée. Désactiver le
	# formulaire COD sur le clone d'un vrai client, c'est couper la boutique — et
	# une porte qui meurt entre la désactivation et la réactivation la laisse
	# coupée. L'isolation par processus rend l'accident impossible.
	# Rapide (aucun semis) : elle a sa place dans « tout ».
	docker compose up -d wpcli >/dev/null 2>&1 || true
	if docker compose exec -T wpcli wp --skip-plugins=aod-cod-form eval-file /scripts/recette-degrade.php; then
		:
	else
		FAILS=$(( FAILS + 1 ))
	fi
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "journal" ]; then
	step "Le journal écrit-il depuis une requête WEB ? (dette D28)"
	# 🚨 CE CONTRÔLE NE PEUT PAS ÊTRE UN TEST UNITAIRE. `tests/run.sh` tourne DANS
	# le processus WP-CLI — celui-là même qui écrivait correctement pendant que
	# toute requête web échouait en silence. La distinction CLI / web EST le
	# défaut : l'éprouver exige une vraie requête HTTP, donc une porte de recette.
	# Elle ne crée aucune donnée : elle arme le plafond anti-flood puis envoie UNE
	# requête, refusée en 429 — donc aucun prospect créé. Rapide : elle a sa place
	# dans « tout ».
	docker compose up -d wpcli >/dev/null 2>&1 || true
	if docker compose exec -T wpcli wp eval-file /scripts/recette-journal.php; then
		:
	else
		FAILS=$(( FAILS + 1 ))
	fi
fi

if [ "$WHAT" = "charge" ]; then
	step "Tenue en charge de l'Accueil /gestion (Phase 31.6)"
	# 🚨 VOLONTAIREMENT ABSENTE DE « tout ». Cette porte SÈME des milliers de
	# commandes et met plusieurs minutes : la glisser dans la passe complète
	# ferait d'une recette de routine une opération lourde, et on finirait par ne
	# plus lancer « tout ». On la joue quand on touche aux agrégats de l'Accueil.
	#
	# Volume réglable : AOD_CHARGE_N=3000 bash tools/recette/run.sh charge
	# Elle travaille sur des données À ELLE (marque `_aod_charge_temoin`), les
	# purge, et VÉRIFIE que le nombre de commandes réelles est inchangé.
	docker compose up -d wpcli >/dev/null 2>&1 || true
	if docker compose exec -T -e AOD_CHARGE_N="${AOD_CHARGE_N:-10000}" wpcli wp eval-file /scripts/recette-charge.php; then
		:
	else
		FAILS=$(( FAILS + 1 ))
	fi
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "conversions" ] || [ "$WHAT" = "capi" ]; then
	step "Conversions serveur Meta / TikTok / Snapchat + tests connexion (clôture Phase 13)"
	# Recette SERVEUR (pas de navigateur) : les conversions sont des envois serveur-à-
	# serveur (Meta CAPI, TikTok Events API, Snapchat Conversions API) prouvés en
	# interceptant la requête HTTP sortante (aucun appel réel). Couvre aussi les boutons
	# « Tester la connexion » (13.3) et la présence de la dédup côté navigateur. Le
	# script est auto-suffisant (crée/supprime sa commande, restaure l'option pixels).
	# La porte « capi » reste un alias historique de « conversions ».
	docker compose up -d wpcli >/dev/null 2>&1 || true
	if docker compose exec -T wpcli wp eval-file /scripts/recette-capi.php; then
		:
	else
		FAILS=$(( FAILS + 1 ))
	fi
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "abandonnees" ]; then
	step "Commandes abandonnées dans /gestion (clôture Phase 11)"
	docker compose up -d wpcli >/dev/null 2>&1 || true
	# Seed des prospects (abandonné / en cours / converti), purgés par le trap EXIT.
	SEED_OUT="$( docker compose exec -T wpcli wp eval-file /scripts/recette-seed-phase11.php create 2>&1 )"
	if printf '%s\n' "$SEED_OUT" | grep -q '^P11=1'; then
		PHASE11_SEEDED=1
		# 43.3 — IDs NUMÉRIQUES des 2 confirmateurs témoins (le <select> du filtre
		# et le widget d'assignation portent l'ID, pas le login) + les identifiants
		# du premier, pour le témoin d'identité confirmateur.
		AOD_P11_UID_A="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P11_UID_A=//p' | tr -d '\r' )"
		AOD_P11_UID_B="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P11_UID_B=//p' | tr -d '\r' )"
		AOD_P11_LOGIN_A="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P11_LOGIN_A=//p' | tr -d '\r' )"
		AOD_P11_PASS_A="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P11_PASS_A=//p' | tr -d '\r' )"
		export AOD_P11_UID_A AOD_P11_UID_B AOD_P11_LOGIN_A AOD_P11_PASS_A
		run abandonnees.js
		unset AOD_P11_UID_A AOD_P11_UID_B AOD_P11_LOGIN_A AOD_P11_PASS_A
		cleanup_phase11
		PHASE11_SEEDED=0
	else
		printf '  ❌ Seed des fixtures Phase 11 en échec :\n%s\n' "$SEED_OUT"
		FAILS=$(( FAILS + 1 ))
	fi
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "suivi" ]; then
	step "Confirmée → expédiée se déplace vers Suivi (clôture Phase 12)"
	docker compose up -d wpcli >/dev/null 2>&1 || true
	# Seed d'une commande expédiée + d'une non expédiée, purgées par le trap EXIT.
	SEED_OUT="$( docker compose exec -T wpcli wp eval-file /scripts/recette-seed-phase12.php create 2>&1 )"
	if printf '%s\n' "$SEED_OUT" | grep -q '^P12=1'; then
		PHASE12_SEEDED=1
		run commandes-suivi.js
		cleanup_phase12
		PHASE12_SEEDED=0
	else
		printf '  ❌ Seed des fixtures Phase 12 en échec :\n%s\n' "$SEED_OUT"
		FAILS=$(( FAILS + 1 ))
	fi
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "panier" ]; then
	step "Panier COD togglable — les deux faces du toggle (clôture Phase 35)"
	docker compose up -d wpcli >/dev/null 2>&1 || true
	# Seed de 3 produits témoins (Gamma porte des OPTIONS — cf. le seed) + capture
	# de l'état RÉEL de aod_cod_cart, restauré par le trap EXIT quoi qu'il arrive
	# (leçon D26).
	SEED_OUT="$( docker compose exec -T wpcli wp eval-file /scripts/recette-seed-panier.php create 2>&1 )"
	if printf '%s\n' "$SEED_OUT" | grep -q '^P35=1'; then
		PANIER_SEEDED=1
		AOD_P35_URL1="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^URL1=//p' | tr -d '\r' )"
		AOD_P35_URL2="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^URL2=//p' | tr -d '\r' )"
		AOD_P35_URL3="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^URL3=//p' | tr -d '\r' )"
		AOD_P35_IMGPROD="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^IMGPROD=//p' | tr -d '\r' )"
		AOD_P35_IMGVAR="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^IMGVAR=//p' | tr -d '\r' )"
		export AOD_P35_URL1 AOD_P35_URL2 AOD_P35_URL3 AOD_P35_IMGPROD AOD_P35_IMGVAR
		run panier-cod.js
		cleanup_panier
		PANIER_SEEDED=0
		unset AOD_P35_URL1 AOD_P35_URL2 AOD_P35_URL3 AOD_P35_IMGPROD AOD_P35_IMGVAR
	else
		printf '  ❌ Seed des fixtures panier (phase 35) en échec :\n%s\n' "$SEED_OUT"
		FAILS=$(( FAILS + 1 ))
	fi
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "variante" ]; then
	step "Stock par variante — rupture, somme, décrément/ré-incrément (clôture Phase 40)"
	docker compose up -d wpcli >/dev/null 2>&1 || true
	# Seed : 2 produits témoins (ALPHA à stock géré, REPLI sans) + une commande témoin.
	# La porte confirme/annule la commande via le seed (comme run.sh), et lit la somme.
	SEED_OUT="$( docker compose exec -T wpcli wp eval-file /scripts/recette-seed-variante.php create 2>&1 )"
	if printf '%s\n' "$SEED_OUT" | grep -q '^P40=1'; then
		VARIANTE_SEEDED=1
		AOD_P40_ALPHA_URL="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^ALPHA_URL=//p' | tr -d '\r' )"
		AOD_P40_ALPHA_PID="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^ALPHA_PID=//p' | tr -d '\r' )"
		AOD_P40_REPLI_PID="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^REPLI_PID=//p' | tr -d '\r' )"
		export AOD_P40_ALPHA_URL AOD_P40_ALPHA_PID AOD_P40_REPLI_PID
		run variante.js
		cleanup_variante
		VARIANTE_SEEDED=0
		unset AOD_P40_ALPHA_URL AOD_P40_ALPHA_PID AOD_P40_REPLI_PID
	else
		printf '  ❌ Seed des fixtures variante (phase 40) en échec :\n%s\n' "$SEED_OUT"
		FAILS=$(( FAILS + 1 ))
	fi
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "assignation" ]; then
	step "Assignation des commandes aux confirmateurs — sélecteur, filtre, lot, périmètre (clôture Phase 42)"
	docker compose up -d wpcli >/dev/null 2>&1 || true
	# Seed : 2 confirmateurs (mots de passe engendrés) + 5 commandes marquées
	# (assignée A, assignée B, non assignée, 2 pour le lot). Patron
	# recette-seed-variante.php (Phase 40) + recette-seed-phase22.php (login
	# confirmateur, Phase 22).
	SEED_OUT="$( docker compose exec -T wpcli wp eval-file /scripts/recette-seed-assignation.php create 2>&1 )"
	if printf '%s\n' "$SEED_OUT" | grep -q '^P42=1'; then
		ASSIGNATION_SEEDED=1
		AOD_P42_LOGIN="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P42LOGIN=//p' | tr -d '\r' )"
		AOD_P42_PASS="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P42PASS=//p' | tr -d '\r' )"
		AOD_P42_UID_A="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P42_UID_A=//p' | tr -d '\r' )"
		AOD_P42_LOGIN2="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P42LOGIN2=//p' | tr -d '\r' )"
		AOD_P42_PASS2="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P42PASS2=//p' | tr -d '\r' )"
		AOD_P42_UID_B="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P42_UID_B=//p' | tr -d '\r' )"
		AOD_P42_OID_ASSIGNED_A="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P42_OID_ASSIGNED_A=//p' | tr -d '\r' )"
		AOD_P42_OID_ASSIGNED_B="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P42_OID_ASSIGNED_B=//p' | tr -d '\r' )"
		AOD_P42_OID_UNASSIGNED="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P42_OID_UNASSIGNED=//p' | tr -d '\r' )"
		AOD_P42_OID_BULK1="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P42_OID_BULK1=//p' | tr -d '\r' )"
		AOD_P42_OID_BULK2="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P42_OID_BULK2=//p' | tr -d '\r' )"
		export AOD_P42_LOGIN AOD_P42_PASS AOD_P42_UID_A AOD_P42_LOGIN2 AOD_P42_PASS2 AOD_P42_UID_B AOD_P42_OID_ASSIGNED_A AOD_P42_OID_ASSIGNED_B AOD_P42_OID_UNASSIGNED AOD_P42_OID_BULK1 AOD_P42_OID_BULK2
		run assignation.js
		cleanup_assignation
		ASSIGNATION_SEEDED=0
		unset AOD_P42_LOGIN AOD_P42_PASS AOD_P42_UID_A AOD_P42_LOGIN2 AOD_P42_PASS2 AOD_P42_UID_B AOD_P42_OID_ASSIGNED_A AOD_P42_OID_ASSIGNED_B AOD_P42_OID_UNASSIGNED AOD_P42_OID_BULK1 AOD_P42_OID_BULK2
	else
		printf '  ❌ Seed des fixtures assignation (phase 42) en échec :\n%s\n' "$SEED_OUT"
		FAILS=$(( FAILS + 1 ))
	fi
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "envoi" ]; then
	step "L'envoi transporteur est un GESTE, pas une conséquence (clôture Phase 47)"
	# 🔴 AUCUN ENVOI RÉEL : le clone porte un compte transporteur VÉRITABLEMENT
	# configuré. La porte confirme une commande JETABLE, prouve qu'aucun colis
	# n'est créé — ni méta, ni requête vers un domaine de livreur — puis ouvre la
	# fenêtre d'envoi et l'ANNULE. L'unicité de l'appel reste prouvée par
	# tests/unit/test-verrou-envoi.php, qui injecte un transporteur bouchon.
	run envoi-geste.js
fi

if [ "$WHAT" = "tout" ] || [ "$WHAT" = "confirmateur" ]; then
	step "/gestion parcouru en CONFIRMATEUR (clôture Phase 22, dette D13)"
	docker compose up -d wpcli >/dev/null 2>&1 || true
	# LA SEULE PORTE QUI NE SE CONNECTE PAS EN GÉRANT. Sème un confirmateur portant
	# 4 des 7 caps — partiel à dessein : un compte tout coché ne prouverait que les
	# droits accordés, jamais que les droits refusés bloquent.
	#
	# ⚠️ Le mot de passe est engendré par le seed et transite par l'environnement
	# (jamais .env, jamais le dépôt). En cas d'ÉCHEC du seed, le printf ci-dessous
	# l'imprimerait en clair : c'est assumé (compte jetable, dev local, purgé en
	# sortie), pas une surprise.
	SEED_OUT="$( docker compose exec -T wpcli wp eval-file /scripts/recette-seed-phase22.php create 2>&1 )"
	if printf '%s\n' "$SEED_OUT" | grep -q '^P22=1'; then
		PHASE22_SEEDED=1
		# Deux comptes COMPLÉMENTAIRES (A et B) : ensemble, ils exercent les 7 caps
		# dans les deux sens. `^P22LOGIN=` ne capture pas `P22LOGIN2=` (le `=` est
		# ancré juste après la clé) — vérifié, et non supposé.
		AOD_P22_LOGIN="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P22LOGIN=//p' | tr -d '\r' )"
		AOD_P22_PASS="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P22PASS=//p' | tr -d '\r' )"
		AOD_P22_LOGIN2="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P22LOGIN2=//p' | tr -d '\r' )"
		AOD_P22_PASS2="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P22PASS2=//p' | tr -d '\r' )"
		# 47.5 — nombre de confirmations ATTRIBUÉES À B. Sans ce semis, la porte
		# serait creuse : au repos, B n'a rien confirmé (0) et la boutique en
		# compte 1 — un « B voit 0 » passerait sur un rendu cassé comme sur un
		# rendu correct.
		AOD_P22_CONFB="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P22CONFB=//p' | tr -d '\r' )"
		AOD_P22_ASGB="$( printf '%s\n' "$SEED_OUT" | sed -n 's/^P22ASGB=//p' | tr -d '\r' )"
		export AOD_P22_LOGIN AOD_P22_PASS AOD_P22_LOGIN2 AOD_P22_PASS2 AOD_P22_CONFB AOD_P22_ASGB
		run confirmateur.js
		cleanup_phase22
		PHASE22_SEEDED=0
		unset AOD_P22_LOGIN AOD_P22_PASS AOD_P22_LOGIN2 AOD_P22_PASS2 AOD_P22_CONFB AOD_P22_ASGB
	else
		printf '  ❌ Seed du confirmateur témoin (phase 22) en échec :\n%s\n' "$SEED_OUT"
		FAILS=$(( FAILS + 1 ))
	fi
fi

if [ "$RETIRES" -gt 0 ]; then
	printf '\n\033[33mⓘ RECETTE : %s scénario(s) RETIRÉ(S) — sans objet sur ce clone : %s\033[0m\n' \
		"$RETIRES" "$WITHDRAWN_LIST"
	printf '   (retiré ≠ passé : ces portes n’ont rien vérifié.)\n'
fi
if [ "$FAILS" -eq 0 ]; then
	if [ "$RETIRES" -gt 0 ]; then
		printf '\n\033[32m✅ RECETTE : tout ce qui a été joué passe\033[0m (%s porte(s) retirée(s)).\n' "$RETIRES"
	else
		printf '\n\033[32m✅ RECETTE : tout passe.\033[0m\n'
	fi
else
	printf '\n\033[31m❌ RECETTE : %s script(s) en échec.\033[0m\n' "$FAILS"
fi
exit "$FAILS"
