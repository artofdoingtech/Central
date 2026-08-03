# Durcissement sécurité — `.htaccess` de référence

> Volet **sécurité générale** (complément de `RUNBOOK-ANTI-BOT-COD.md`, qui traite
> le spam du formulaire COD). Issu de l'audit sécurité d'un site en prod, **nettoyé
> de tout ce qui est spécifique à un client** (blocklist d'IPs, cache LiteSpeed).
> À déployer sur `public_html/.htaccess` de chaque nouveau site.

## Pourquoi

Renforce WordPress au niveau serveur, **avant même que PHP ne s'exécute** :
protège les fichiers sensibles, coupe les vecteurs d'attaque inutiles (XML-RPC,
exécution PHP dans `/uploads`), et ajoute des en-têtes de sécurité HTTP. Aucune de
ces règles ne gêne un site WooCommerce COD normal.

## `.htaccess` durci (générique)

> ⚠️ Colle ces blocs **avant** `# BEGIN WordPress`. Ne touche pas au bloc
> `# BEGIN/END WordPress` géré par WordPress lui-même.
> Pour bloquer des **IPs de bots** précises (attaque en cours), voir
> `RUNBOOK-ANTI-BOT-COD.md` → Volet 4. Pour la parade multi-IP, → Volet 5 (Turnstile).

```apache
# ============================================================================
#  .htaccess DURCI — durcissement WordPress générique (template)
# ============================================================================

# 1) Protège les fichiers sensibles (config, htaccess, logs, infos de version)
<FilesMatch "^(wp-config\.php|\.htaccess|\.htaccess\.bk|\.user\.ini|readme\.html|license\.txt)$">
  Require all denied
</FilesMatch>

# 2) Interdit le téléchargement des sauvegardes / dumps laissés par erreur
<FilesMatch "\.(bak|old|orig|sql|zip|gz|tar|log|swp|save)$">
  Require all denied
</FilesMatch>

# 3) Désactive XML-RPC (vecteur brute-force / DDoS, inutile ici)
<Files xmlrpc.php>
  Require all denied
</Files>

# 4) Empêche l'exécution de PHP dans /uploads (parade n°1 contre les web-shells)
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^wp-content/uploads/.*\.(php|phtml|php3|php4|php5|php7|phar|pht)$ - [F,L,NC]
</IfModule>

# 5) Désactive le listing des répertoires
Options -Indexes

# 6) En-têtes de sécurité HTTP
<IfModule mod_headers.c>
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
</IfModule>

# ---------------------------------------------------------------------------
# BEGIN WordPress  ← ne rien modifier sous cette ligne (géré par WordPress)
# ...
# END WordPress
```

## Housekeeping sécurité à la livraison de chaque site

- [ ] `.htaccess` durci déposé dans `public_html/`
- [ ] Aucun dossier de sauvegarde public laissé (`*.bak` / `-OLD` / dumps `.sql`/`.zip`)
- [ ] Mots de passe **admin + base de données** changés, **salts** WordPress régénérés
      (<https://api.wordpress.org/secret-key/1.1/salt/>)
- [ ] Un seul compte administrateur légitime
- [ ] Anti-spam COD en place (voir `RUNBOOK-ANTI-BOT-COD.md`) + Turnstile activé si besoin
