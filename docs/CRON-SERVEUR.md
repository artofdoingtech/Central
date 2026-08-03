# Cron serveur — indispensable en production

> **À faire à chaque mise en ligne.** Sans cron serveur :
> - le **suivi des colis ne se met pas à jour** de façon fiable — les statuts
>   transporteur (livré, annulé, tentative échouée) peuvent rester bloqués pendant
>   des heures, et les statistiques de livraison de `/gestion` avec eux ;
> - 🆕 depuis la **Phase 14.1**, les **conversions serveur ne partent pas** —
>   Meta CAPI, TikTok Events API et Snapchat Conversions API sont désormais
>   **différées** hors du chemin d'achat (l'acheteur ne doit pas attendre jusqu'à
>   24 s après « Commander »). Un clone sans cron mesure donc ses achats
>   **uniquement** par les pixels navigateur : tout acheteur dont le navigateur
>   bloque les pixels devient invisible, et le gérant pilote ses pubs sur des
>   chiffres tronqués.
>
> **L'enjeu s'est donc élargi : le cron ne conditionne plus seulement la logistique,
> il conditionne la mesure publicitaire.** Les conversions restent **rattrapables**
> (une conversion en file n'est jamais comptée comme envoyée), donc installer le
> cron plus tard récupère l'arriéré — mais tant qu'il manque, rien ne part.

---

## 1. Pourquoi c'est nécessaire

WordPress n'a **pas de vrai planificateur**. Son « WP-Cron » ne se réveille pas
tout seul : il est déclenché **par les visites du site**. Pas de visiteur = pas de
tâche exécutée.

Sur une grosse boutique, ça passe inaperçu. Sur une boutique qui démarre — 20
visites par jour, aucune la nuit — ça veut dire :

- un colis livré à 9 h reste affiché « en transit » jusqu'à ce que quelqu'un charge
  une page ;
- les taux **Livrés / Échec / Annulés** de l'onglet Livraison sont calculés sur des
  données périmées ;
- et le gérant conclut, à juste titre, que « le suivi ne marche pas ».

La solution : demander à **l'hébergeur** d'appeler WordPress à intervalle régulier,
et couper le déclenchement par les visites.

### Ce qui dépend du cron dans ce template

| Tâche | Fréquence | Conséquence si le cron ne tourne pas |
|---|---|---|
| `aod_ship_tracking_sync` — synchro du suivi colis chez le transporteur | toutes les 2 h | Statuts de colis figés · stats de livraison fausses · aucune note de commande « Suivi : Livré » |
| `aod_capi_dispatch` — envoi serveur des conversions (Meta / TikTok / Snapchat) | **une tâche par commande**, planifiée à l'instant de l'achat | **Aucune conversion serveur n'est transmise.** Seuls les pixels navigateur mesurent → sous-comptage invisible chez les régies, ROAS faussé. **Rattrapable** : la conversion reste en file et part dès que le cron fonctionne |
| Tâches du cœur WordPress / WooCommerce (nettoyage, e-mails différés, publications planifiées…) | variable | Effets divers, généralement silencieux |

> ⏱️ **Retard assumé sur les conversions.** Avec la tâche cron recommandée
> ci-dessous (toutes les 15 min), un achat est transmis aux régies dans les 15 min.
> C'est **sans conséquence sur l'attribution** : Meta recommande moins d'une heure
> et tolère jusqu'à 7 jours, TikTok et Snapchat sont au moins aussi permissifs. Le
> gérant qui compare ses chiffres en temps réel doit simplement le savoir — d'où
> cette ligne.

---

## 2. Étape 1 — couper le WP-Cron déclenché par les visites

Dans le **`wp-config.php`** du site en production, **au-dessus** de la ligne
`/* That's all, stop editing! */` :

```php
define( 'DISABLE_WP_CRON', true );
```

> ⚠️ **Ne fais cette étape que si tu enchaînes tout de suite avec l'étape 2.**
> Entre les deux, plus aucune tâche planifiée ne s'exécute.

Sur Hostinger : **hPanel → Fichiers → Gestionnaire de fichiers**, ouvrir
`public_html/wp-config.php`.

---

## 3. Étape 2 — créer la tâche cron chez Hostinger

**hPanel → Sites web → Tableau de bord**, puis chercher **Cron Jobs** (Tâches cron)
dans la barre latérale.

### Méthode recommandée — type « PHP »

C'est la plus simple et la plus rapide : PHP est appelé **directement**, sans
passer par le réseau.

| Champ | Valeur |
|---|---|
| **Type** | `PHP` |
| **Commande à exécuter** | `/home/uXXXXXXXX/domains/VOTRE-DOMAINE.tld/public_html/wp-cron.php` |
| **Planification** | toutes les **15 minutes** (ou le préréglage « deux fois par heure ») |

Le chemin exact se lit dans le **Gestionnaire de fichiers** : clique sur
`wp-cron.php`, à la racine de `public_html`, et recopie le chemin affiché.
Le `uXXXXXXXX` est ton identifiant Hostinger — il ne s'invente pas.

### Méthode de secours — type « Custom »

Si le type PHP ne fonctionne pas (chemin refusé, PHP CLI indisponible), appelle le
fichier par HTTP :

| Champ | Valeur |
|---|---|
| **Type** | `Custom` |
| **Commande à exécuter** | `wget -q -O - "https://VOTRE-DOMAINE.tld/wp-cron.php?doing_wp_cron" >/dev/null 2>&1` |
| **Planification** | toutes les **15 minutes** |

> 🪤 **Le piège.** Le type **PHP** d'hPanel **refuse les caractères spéciaux** comme
> `>/dev/null 2>&1`. C'est documenté par Hostinger. Si tu as besoin de ces
> redirections, il **faut** passer par le type **Custom** — sinon la tâche est
> créée mais ne s'exécute jamais, sans le moindre message d'erreur.

### Sur la fréquence

**15 minutes suffisent largement.** Notre synchro de suivi est planifiée toutes les
2 h : le cron serveur ne fait que *réveiller* WordPress pour qu'il voie qu'une
tâche est due. Un réveil tous les quarts d'heure garantit un retard maximal de
15 minutes.

Ne descends **pas** à « toutes les minutes » : chaque passage démarre WordPress en
entier. C'est de la charge serveur pour rien, et sur un hébergement mutualisé ça
peut te faire taper dans les limites de ressources (Hostinger cite explicitement le
dépassement CPU/mémoire comme cause d'échec des tâches cron).

> 🕐 **Fuseau horaire :** les tâches cron d'hPanel sont planifiées en **UTC+0**, pas
> en heure d'Alger. Sans importance pour un intervalle (« toutes les 15 min »), mais
> à garder en tête si tu programmes un jour une tâche à heure fixe.

---

## 4. Étape 3 — vérifier que ça tourne vraiment

Ne considère pas la chose faite tant que tu n'as pas vu **une** de ces trois preuves.

**a) La plus simple, sans outil.** Va dans `/gestion` → **Suivi commande**. La
colonne de droite indique « mis à jour il y a X ». Note la valeur, **ne touche plus
au site pendant 2 h 30**, puis reviens : la valeur doit avoir **rajeuni**. Si elle
affiche « il y a 3 heures », le cron ne tourne pas.

**b) Par la sortie de la tâche.** hPanel conserve la sortie des tâches cron
(*Liste des tâches cron* → voir la sortie). Une exécution réussie de `wp-cron.php`
ne produit **aucune sortie** : c'est normal. Une erreur PHP, elle, apparaîtra ici —
c'est le premier endroit où regarder.

**c) Par WP-CLI** (si ton offre donne un accès SSH) :

```bash
wp cron event list
```

La ligne `aod_ship_tracking_sync` doit afficher un `next_run` **dans le futur**.
Si elle affiche une date **passée**, c'est que personne ne l'exécute.

---

## 5. Sur un autre hébergeur

Le principe ne change pas : `DISABLE_WP_CRON` + une tâche planifiée.

**cPanel** (LWS, o2switch, PlanetHoster…) — *Tâches Cron* → « Commande » :

```
/usr/local/bin/php /home/UTILISATEUR/public_html/wp-cron.php >/dev/null 2>&1
```

**Accès SSH** (`crontab -e`) — la version la plus propre, car elle n'exécute que ce
qui est réellement dû :

```
*/15 * * * * cd /chemin/vers/le/site && /usr/local/bin/wp cron event run --due-now >/dev/null 2>&1
```

---

## 6. Ça ne marche toujours pas

| Symptôme | Cause la plus probable |
|---|---|
| La tâche est créée mais rien ne se passe | Type **PHP** avec des caractères spéciaux dans la commande → passer en **Custom**. |
| « No such file or directory » | Chemin faux. Le relire dans le Gestionnaire de fichiers — **ne pas le deviner**. |
| Le suivi reste figé alors que le cron tourne | Les identifiants du transporteur ne sont pas saisis dans `/gestion` → Livraison → Transporteurs. Vérifier avec `wp eval-file /scripts/recette-api-transporteur.php <id>`. **Depuis la 32.4, chaque passage laisse une trace** : ouvrir le journal WooCommerce (source `aod-cod`, canal `[tracking]`) — on y lit combien de colis ont été mis à jour, combien sont en erreur, et si le passage a été interrompu au budget de temps. Un journal muet sur ce canal = le cron ne tourne pas vraiment. |
| Les statuts ne changent jamais pour certains colis | Normal : un colis **livré / retourné / annulé** est terminal, il n'est plus réinterrogé. |
| Tout est lent, les tâches échouent par intermittence | Intervalle trop agressif (toutes les minutes) → repasser à 15 min. |

---

## 7. À retenir

1. `define( 'DISABLE_WP_CRON', true );` dans `wp-config.php`.
2. Une tâche cron chez l'hébergeur, **toutes les 15 minutes**, qui appelle
   `wp-cron.php`.
3. **Vérifier** — la colonne « mis à jour il y a X » de `/gestion` → Suivi commande
   doit rajeunir toute seule.

Sans ces trois points, le suivi automatique des colis **ne fonctionne pas en
production**, quelle que soit la qualité du code.

---

*Sources : [Hostinger — How to Set Up a Cron Job](https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/) ·
[Hostinger — Troubleshooting Cron Jobs](https://www.hostinger.com/support/1583514-troubleshooting-cron-jobs-at-hostinger/) ·
[Hostinger — WordPress Cron Job](https://www.hostinger.com/tutorials/how-to-setup-and-manage-a-wordpress-cron-job).
Les intervalles minimaux et les limites par offre ne sont pas documentés
publiquement par Hostinger : si « toutes les 15 min » est refusé, prendre le
préréglage le plus proche.*
