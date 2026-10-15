# Outils vendeur — à garder chez toi, jamais chez le client

Ce dossier n'est **jamais** copié dans les `.zip` livrés (voir `build.sh`
à la racine du paquet : il ne zippe que `plugins/`).

## `generer-licence.php` — émettre une clé

```bash
php generer-licence.php "Nom du client" [domaine] [jours]
```

- `domaine` — optionnel. Laisse vide (ou mets `*`) pour une clé qui marche
  sur n'importe quel domaine (pratique pour un test, une démo, ou un client
  qui va migrer de domaine). Renseigne le domaine réel du client
  (`boutique-client.dz`) pour lier la clé à SON site : elle refusera de
  s'activer ailleurs.
- `jours` — optionnel, 365 par défaut (1 an). Passe `730` pour 2 ans, etc.

Exemple :

```bash
php generer-licence.php "Boutique Yasmine" boutique-yasmine.dz
```

Le script imprime la clé (`AODG1.…`) à coller par le client dans
**wp-admin → Licence AOD Gestion → Activer une clé**.

## `verifier-licence.php` — contrôler une clé (support)

```bash
php verifier-licence.php "AODG1.xxxx.yyyy"
```

Décode la clé, vérifie sa signature, affiche client/domaine/dates et si elle
est encore valide — sans avoir besoin d'installer WordPress. Utile quand un
client dit « ma licence ne s'active pas » : colle sa clé ici pour voir
exactement pourquoi (signature invalide = clé mal copiée ou pas émise par
toi ; domaine différent ; expirée).

## Le secret de signature

Les deux scripts ci-dessus, ET le plugin `aod-gestion-license`
(`plugins/aod-gestion-license/includes/class-aodg-license.php::SECRET`),
partagent la **même** constante `SECRET`. C'est elle qui fait qu'une clé que
tu émets ici est reconnue comme valide par le plugin installé chez le
client.

⚠️ **Si tu la changes, TOUTES les clés déjà émises (chez tous les clients
déjà livrés) cessent de fonctionner.** Ne la change que si tu es sûr de
vouloir invalider le parc existant (fuite du secret, par exemple) — et si tu
le fais, mets-la à jour aux DEUX endroits (ici et dans le plugin) avant de
regénérer des clés.

Pour en générer un nouveau si besoin un jour :

```bash
php -r "echo bin2hex(random_bytes(32));"
```

## Limite assumée

La vérification est 100% locale (pas de serveur de licence à héberger,
cohérent avec le reste du projet qui ne dépend jamais d'internet sortant
pour fonctionner — cf. `TEMPLATE.md` §3). Le secret vit dans le code PHP du
plugin livré au client : quelqu'un avec un accès FTP/SSH au site et des
compétences PHP pourrait théoriquement forger une clé. C'est un compromis
volontaire (licence « molle »), pas un DRM inviolable.
